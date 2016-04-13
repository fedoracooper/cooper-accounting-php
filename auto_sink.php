<?php
/*
  Page for automatically creating "sinking" transactions at the
  end of the month, which means moving money out of the parent
  savings account (Checking, etc.) and into up to 4 sinking accounts,
  or vice versa.

  This is driven off the same two queries used by account_details.php,
  and the "To Save" calculated amount.
*/

	$current_page = 'account_details';
	require('include.php');
	
	$MAX_LEDGER_ENTRIES_PER_TX = 10;
	
	if (!isset ($_SESSION['login_id']))
	{
		// redirect to login if they have not logged in
		header ("Location: login.php");
	}
	$login_id = $_SESSION['login_id'];
	
	$error = '';
	$message = '';
	
	function createSinkTransaction($ledgerEntries, $loginId, $txDate) {
		$txDateString = $txDate->format(SQL_DATE);
	  	$sinkTransaction = new Transaction();
		$sinkTransaction->Init_transaction(
			$loginId,
			"EOM auto-sink",
			$txDateString,
			$txDateString,
			'', // Vendor
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


	// Get POST params
	$account_id = $_POST['account_id'];
	$startDate = new DateTime($_POST['start_date']);
	$startDateText = $startDate->format(SQL_DATE);
	$endDate = new DateTime($_POST['end_date']);
	$endDateText = $endDate->format(SQL_DATE);
	$minDate = $startDate;
	$activeOnly = 1;
	$account_list = array();
	$doAutoSink = null;
	if (isset($_POST['doAutoSink'])) {
  	$doAutoSink = ($_POST['doAutoSink'] == '1');
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

	// Loop through data and aggregate into transactions of MAX_LEDGER_ENTRIES_PER_TX entries or less
	$sinkParentMap = array();
	$transactions = array();
  
	$savingsCount = 0;
	foreach ($account_list as $expense_account_id => $accountSavings)
	{
		if ($accountSavings->savingsId > 0) {
			// check for savings record for this account
			$savingsData = null;
			if (isset($savings_list[$expense_account_id])) {
				$savingsData = $savings_list[$expense_account_id];

	  			// This is an expense account with a sinking / savings
	  			// account associated.
	  			// Set savingsBalance *before* setSaved, for calculation.
				$accountSavings->savingsBalance = $savingsData[5];
				$accountSavings->setSaved($savingsData[0], true);
				$accountSavings->savingsName = $savingsData[1];
				$accountSavings->savingsParentId = $savingsData[3];
				$savingsParentId = $savingsData[3];
				// Debit flags (-1 or +1)
				$savingsDebit = $savingsData[6];
				$parentDebit = $savingsData[7];
				$accountSavings->parentName = $savingsData[4];
				
				if (abs($accountSavings->getToSave()) < 1.0) {
				  // Don't show accounts with 0 to save, or very small amounts
				  continue;
				}
				
	  			$savingsCount++;
				$sinkTransaction = null;
				// Check for transaction for this parent account
				if (isset($sinkParentMap[$savingsParentId])) {
					$sinkTransaction = $sinkParentMap[$savingsParentId];
				} else {
					// Add dummy zero amount for parent account
					$sinkLedgerEntries = array();
					$ledger = new LedgerEntry();
					$ledger->accountId = $savingsParentId;
					$ledger->debit = $parentDebit;
					$ledger->setAmount(0.0);
					$sinkLedgerEntries[0] = $ledger;

					$sinkTransaction = createSinkTransaction($sinkLedgerEntries, $login_id, $endDate);
					$sinkParentMap[$savingsParentId] = $sinkTransaction;
				}
				
				// Add ledger entry:  0 = Ledger ID, 1 = Account ID, 2 = Amount
				$ledger = new LedgerEntry();
				$ledger->accountId = $accountSavings->savingsId;
				$ledger->debit = $savingsDebit;
				$ledger->setAmount($accountSavings->getToSave());
				$sinkTransaction->get_ledgerL_list()[] = $ledger;
				$sinkTransaction->get_account_savings()[] = $accountSavings;
				
				if (count($sinkTransaction->get_ledgerL_list()) >= $MAX_LEDGER_ENTRIES_PER_TX) {
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

	$nullCount = 0;
	// Add transactions from the Parent Map (< $MAX_LEDGER_ENTRIES_PER_TX ledger entries)
	foreach ($sinkParentMap as $parentAccountId => $sinkTransaction) {
		if ($sinkTransaction == null) {
			$nullCount++;
		} else {
			$transactions[] = $sinkTransaction;
		}
	}

	if ($nullCount > 0) {
		$message .= "Found $nullCount null transactions.";
	}
  
	$message = "Found $savingsCount savings account(s) for sinking across ".
	    count($transactions) . ' transaction(s).';

  
	$count = 0;
	// Final loop:  set Sinking Total and Validate.  Insert if needed.
	foreach ($transactions as $transaction) {
		$transaction->Calculate_sinking_total();
		$error = $transaction->Validate();
		if ($error != '') {
			break;
		}

		if ($doAutoSink == '1') {
			$error = $transaction->Save_repeat_transactions();
			if ($error != '') {
				break;
			}
			$count++;
		}
	}
  
	if ($count > 0) {
		$message = "Successfully inserted $count transaction(s).";
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

<p>Savings Accounts for Sinking</p>
<table class="budget-list">
  <th>Savings Account</th>
  <th>Expense Account</th>
  <th>Budget</th>
  <th>Transactions</th>
  <th>Saved</th>
  <th>Savings Balance</th>
  <th>To Save</th>


<?php
  foreach ($transactions as $transaction) {
      $accountSavingsList = $transaction->get_account_savings();
      // Header row:  Parent account
      echo '  <tr><td style="font-weight: bold;">Parent: '. $accountSavingsList[0]->parentName . "</td>\n".
      "   <td></td>\n".
      "   <td></td>\n".
      "   <td></td>\n".
      "   <td></td>\n".
      "   <td></td>\n".
      "   <td class='numeric' style='font-weight: bold;'>".
          format_currency($transaction->get_ledgerL_list()[0]->getAmount()) . "</td>\n".
      " </tr>\n";
      
      foreach ($accountSavingsList as $accountSavings) {
        echo "  <tr><td>". $accountSavings->savingsName . "</td>".
        "   <td>$accountSavings->accountName</td>".
        '   <td class="numeric">'. format_currency($accountSavings->budget). "</td>\n".
        '   <td class="numeric">'. format_currency($accountSavings->transactions). "</td>\n".
        '   <td class="numeric">'. format_currency($accountSavings->getSaved()) . "</td>\n".
        '   <td class="numeric">'. format_currency($accountSavings->savingsBalance) . "</td>\n".
        '   <td class="numeric">'. format_currency($accountSavings->getToSave()) . "</td>\n".
        " </tr>\n";
      }
  }

  $disableSink = '';
  if ($doAutoSink == '1') {
    // Already sinked; disable the button
    $disableSink = "disabled";
  }
?>
</table>

<div class="bottom">
  <form action="account_details.php" method="POST" id="backForm">
    <input type='hidden' name='account_id' value='<?= $account_id ?>' />
    <input type='hidden' name='start_date' value='<?= $startDateText ?>' />
    <input type='hidden' name='end_date' value='<?= $endDateText ?>' />
    <input type='hidden' name='activeOnly' value='1' />
    <input type='submit' name='goBack' value='Back to Account Details' />
  </form>
  
  <span style='line-height: 20px;' id='button-spacer'><br/></span>
  <form action="auto_sink.php" method="POST" id="autoSink">
    <input type='hidden' name='account_id' value='<?= $account_id ?>' />
    <input type='hidden' name='start_date' value='<?= $startDateText ?>' />
    <input type='hidden' name='end_date' value='<?= $endDateText ?>' />
    <input type='hidden' name='doAutoSink' value='1' />
    <input type='submit' name='doAutoSinkButton' value='Execute Auto Sink' <?= $disableSink ?>/>
  </form>

<?php require('footer.php'); ?>

</body>
</html>
