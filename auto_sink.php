<?php
/*
  Page for automatically creating "sinking" transactions at the
  end of the month, which means moving money out of the parent
  savings account (Checking, etc.) and into up to 4 sinking accounts,
  or vice versa.

  This is driven off the same two queries used by account_details.php,
  and the "To Save" calculated amount.
*/

	require('include.php');
	
	
	if (!isset ($_SESSION['login_id']))
	{
		// redirect to login if they have not logged in
		header ("Location: login.php");
	}
	$login_id = $_SESSION['login_id'];
	
	$error = '';
	$message = '';
	
	function createSinkTransaction($ledgerEntries, $loginId, $txDate) {
	  $txDateString = $txDate->format('m/d/Y');
	  $sinkTransaction = new Transaction();
    $sinkTransaction->Init_transaction(
			$loginId,
			"EOM auto-sink",
			$txDateString,
			$txDateString,
			NULL, // Vendor
			NULL, // Comment
			NULL, // Check #
			NULL, // Miles
			NULL, // Gallons,
			1, // Transaction status (1= fulfilled)
			0, // Prior Month flag
			0, // Exclude Budget flag
			-1, // transaction ID
			1,  // repeat count
			'',		//account display
			NULL,	//ledger amt
			-1,		//ledger ID
			-1,		//audit ID
			0.0,	//audit balance
			$ledgerEntries,  // LHS
			array() // RHS
    );

    return $sinkTransaction;
	}

  function insertSinkTransaction($ledgerEntries) {
	  // calculate sink total
	  $sinkTotal = 0.0;
	  foreach ($ledgerEntries as $ledgerEntry) {
	    $sinkTotal += $ledgerEntry[2];  // Amount
	  }
	  
	  // Update first ledger entry (the parent account)
	  $parentLedger = $ledgerEntries[0];
	  $parentLedger[2] = ($sinkTotal * -1.0);
	  
	  // Build full, final transaction and validate
	  
    
    if ($error == '') {
  	  // Now save it!
  	  $error = $transaction->Save_transaction();
    }
    
    return $error;
	}
	
	// Get GET params
	$account_id = $_GET['account_id'];
	$startDate = new DateTime($_GET['start_date']);
	$endDate = new DateTime($_GET['end_date']);
	$minDate = $startDate;
	$activeOnly = 1;
	$account_list = array();
	$doAutoSink = null;
	if (isset($_GET['doAutoSink'])) {
  	$doAutoSink = ($_GET['doAutoSink'] == '1');
	}
	
	// Perform SQL queries
	
	// Make sure we are only querying a LHS account
	$account = new Account();
	$account->Load_account($account_id);
	// This only works with RHS accounts (Expenses, Income)
  if ($account->get_equation_side() == 'L') {
    $error = "Account ". $account->get_account_name() . " is not an Expense account";
  }
	
	if ($error == '') {
		$error = Account::Get_account_details($account_id, $startDate,
			$endDate, $minDate, $activeOnly, $account_list);
	}

	$savings_list = array();
	if ($error == '') {
		$error = Account::Get_expense_savings($login_id, $startDate,
			$endDate, $savings_list);
	}

  // Loop through data and aggregate into transactions of 5 entries or less
  $sinkParentMap = array();
  $transactions = array();
  
  $savingsCount = 0;
  $fullSavingsCount = 0;
	foreach ($account_list as $account_id => $accountSavings)
	{
		if ($accountSavings->savingsId > 0) {
			// check for savings record for this account
			$savingsCount++;
			$savingsData = null;
			if (isset($savings_list[$account_id])) {
			  $fullSavingsCount++;
				$savingsData = $savings_list[$account_id];

  			// This is an expense account with a sinking / savings
  			// account associated.
				$accountSavings->setSaved($savingsData[0], true);
				$accountSavings->savingsName = $savingsData[1];
				$accountSavings->savingsParentId = $savingsData[3];
				$savingsParentId = $savingsData[3];
				
				if ($accountSavings->getToSave() < 0.0001) {
				  // Nothing to save, so skip it
				  continue;
				}
				$sinkTransaction = null;
	      // Check for transaction for this parent account
	      if (isset($sinkParentMap[$savingsParentId])) {
          $sinkTransaction = $sinkParentMap[$savingsParentId];
	      } else {
          // Add dummy zero amount for parent account
          $sinkLedgerEntries = array();
          $sinkLedgerEntries[0] = array(-1, $savingsParentId, 0.0);
          
          $sinkTransaction = createSinkTransaction($sinkLedgerEntries, $login_id, $endDate);
          $sinkParentMap[$savingsParentId] = $sinkTransaction;
        }
        
        // Add ledger entry:  0 = Ledger ID, 1 = Account ID, 2 = Amount
        $sinkTransaction->get_ledgerL_list()[] = array(-1,
          $accountSavings->savingsId, $accountSavings->getToSave());
        $sinkTransaction->get_account_savings()[] = $accountSavings;
        
        if (count($sinkTransaction->get_ledgerL_list()) >= 5) {
          // Add completed transaction
          $transactions[] = $sinkTransaction;
          
          // Clear map entry after processing
          $sinkParentMap[$savingsParentId] = null;
        }
				
			} else {
			  // Savings account, but no savings this period
			  $accountSavings->setSaved(0.0, true);
			}
			
		} else {
			// no savings
			$accountSavings->setSaved(0.0, false);
		}

  } // End record loop
  
  $message = "Found $savingsCount savings accounts and $fullSavingsCount full savings accounts.";
  $nullCount = 0;
  // Add transactions from the Parent Map (< 5 ledger entries)
  foreach ($sinkParentMap as $parentAccountId => $sinkTransaction) {
    if ($sinkTransaction == null) {
      $nullCount++;
    } else {
      $transactions[] = $sinkTransaction;
    }
  }
  
  $message .= "Found $nullCount null transactions.";
  
  if ($doAutoSink == '1') {
    $count = 0;
    foreach ($transactions as $transaction) {
      $error = insertSinkTransaction($transaction);
      if ($error != '') {
        break;
      }
      $count++;
    }
    
    $message = "Successfully inserted $count transactions.";
  }
  
?>


<html>
<head>
	<title>End of Month: Auto Sinking</title>
	<link href="style.css" rel="stylesheet" type="text/css">
	<script language="javascript" type="text/javascript">

  </script>
</head>

<body >
<?= $navbar ?>

<h3>End of Month: Auto Sinking</h3>

<span class="error"><?= $error ?></span>
<span class="message"><?= $message ?></span>

<form action="auto_sink.php" method="GET" id="autoSink">

</form>

<p>Savings Accounts for Sinking</p>
<table class="budget-list">
  <th>Savings Account</th>
  <th>Expense Account</th>
  <th>Budget</th>
  <th>Transactions</th>
  <th>Saved</th>
  <th>To Save</th>
  <th>Unspent</th>



<?php
  foreach ($transactions as $transaction) {
      $accountSavingsList = $transaction->get_account_savings();
      // Header row:  Parent account
      echo "  <tr><td>Account ID ". $transaction->get_ledgerL_list()[0][1] . "</td>".
      "   <td>Parent Savings</td>".
      "   <td></td>".
      "   <td></td>".
      "   <td></td>".
      "   <td></td>".
      "   <td></td>".
      " </tr>";
      
      foreach ($accountSavingsList as $accountSavings) {
        echo "  <tr><td>". $accountSavings->savingsName . "</td>".
        "   <td>$accountSavings->accountName</td>".
        "   <td>$accountSavings->budget</td>".
        "   <td>$accountSavings->transactions</td>".
        "   <td>" . $accountSavings->getSaved() . "</td>".
        "   <td>" . $accountSavings->getToSave() . "</td>".
        "   <td>" . $accountSavings->getUnspent() . "</td>".
        " </tr>";
      }
  }

?>

</table>
</body>
</html>
