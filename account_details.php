<?php
	$current_page = 'account_details';
	require('include.php');
	
	$login_id = $_SESSION['login_id'];
	
	function getStartDate() {
		// First, check for post variable
		$startDate = new DateTime();
		if (isset($_POST['start_date'])) {
			try {
				$startDate = new DateTime($_POST['start_date']);
			} catch (Exception $e) {
				return $e->getMessage();
			}
		} else {
			// Default to first day of current month
			$dateArr = getdate();
			$startDate->setDate($dateArr['year'], $dateArr['mon'], 1);
		}
		
		return $startDate;
	}
	
	function getEndDate() {
		$endDate = new DateTime();
		
		if (isset($_POST['end_date'])) {
			try {
				$endDate = new DateTime($_POST['end_date']);
			} catch (Exception $e) {
				return $e->getMessage();
			}
		} else {
			// default to last day of this month
			$dateArr = getdate();
			$endDate->setDate($dateArr['year'], $dateArr['mon']+1, 0);
		}
		
		return $endDate;
	}


	$error = '';
	$message = '';
	
	// set default vars
	// Set budget date to first day of month
	$startDate = getStartDate();
	$endDate = getEndDate();
	$doAutoSink = false;
	
	if (isset ($_POST['prev_month']))
	{
		// go back one month
		$dateArr = getdate($startDate->getTimestamp());
		// first day of last month to last day of last month
		$startDate->setDate($dateArr['year'], $dateArr['mon']-1, 1);
		$endDate->setDate($dateArr['year'], $dateArr['mon'], 0);
// 		$interval = new DateInterval('P1M');
// 		$interval->invert = 1;
// 		$startDate->add($interval);
// 		$endDate->add($interval);
	}
	elseif (isset ($_POST['next_month']))
	{
		// forward one month
		$dateArr = getdate($startDate->getTimestamp());
		// first day of next month to last day of next month
		$startDate->setDate($dateArr['year'], $dateArr['mon']+1, 1);
		$endDate->setDate($dateArr['year'], $dateArr['mon']+2, 0);
// 		$interval = new DateInterval('P1M');
// 		$startDate->add($interval);
// 		$endDate->add($interval);
	}

	// default account comes from DB (top Expense)
	$account_id = Account::Get_top_account_id($login_id, '1', 'R');
	$activeOnly = true;
	
	if (!is_numeric($account_id)) {
		$error = $account_id;
	} else if (isset ($_POST['account_id'])) {
		$account_id	= $_POST['account_id'];
		if (isset ($_POST['activeOnly'])) {
			$activeOnly = true;
		} else {
			// unchecked active only
			$activeOnly = false;
			$sinkParentMap = array();
		}
	}
	
	$searchAccountId = $account_id;
	
	// Get full account details from DB
	$account = new Account();
	$account->Load_account($account_id);
	// Show balance field for Assets, Liabilities and -99 (Checking - Liabilities)
	$showBalance = ($account_id == -99 || $account->get_equation_side() == 'L');
	$balanceHeader = '';
	$savingsHeader = '';
	$availableHeader = '';
	if ($showBalance) {
		$balanceHeader = '<th onclick="sortBalance();">Balance</th>';
	} else {
		$savingsHeader = "<th class='numeric' onclick='sortSaved();'>Saved</th> \n".
		"		<th class='numeric' onclick='sortToSave();'>To Save</th> \n";
		$availableHeader = "  <th class='numeric'> Available </th> \n";
	}
	
	$activeOnlyChecked = $activeOnly ? 'checked="checked"' : '';

	$startDateText = $startDate->format('m/d/Y');
	$endDateText = $endDate->format('m/d/Y');

	// build account dropdowns: include inactive, and only show top 2 tiers
	$account_list = Account::Get_account_list($login_id,
		'', -1, false, false, true, true);

	// Add special Checking - Liabilities option
	$top_item_array = array();
	$top_item_array[-99] = 'Checking - Credit Cards';
	$account_list = $top_item_array + $account_list;

	$account_dropdown = Build_dropdown ($account_list, 'account_id',
		$account_id);

	
	$minDate = $startDate;
	if ($showBalance) {
		// when showing balance, we need to go back to the beginning.
		$minDate = new DateTime('0001-01-01');
	}

	// Build main data list
	$account_list = array();	// pass by reference
	
	if ($error == '') {
		if ($account_id == -99) {
			// special query for checking - liabilities
			$error = Account::Get_checking_and_liabilities($startDate,
				$endDate, $login_id, $account_list);
		} else {
			$error = Account::Get_account_details($account_id, $startDate,
				$endDate, $minDate, $activeOnly, $account_list);
		}
	}

	$savings_list = array();
	if ($error == '') {
		$error = Account::Get_expense_savings($login_id, $startDate,
			$endDate, $savings_list);
	}
	
	$sortOrder = 'account';
	if (isset($_POST['sortOrder'])) {
		$sortOrder = $_POST['sortOrder'];
	}
	$sortDirection = '0';
	if (isset($_POST['sortDirection'])) {
		$sortDirection = $_POST['sortDirection'];
	}
	
	
	
	$balanceTotal = 0.0;
	$budgetTotal = 0.0;
	$transactionTotal = 0.0;
	$unspentTotal = 0.0;
	$savedTotal = 0.0;
	$toSaveTotal = 0.0;

	$isSorted = false;

	// First loop:  calculate values and prepare the sort
	foreach ($account_list as $account_id => $accountSavings)
	{
		if ($accountSavings->savingsId > 0) {
			// check for savings record for this account
			$savingsData = null;
			if (isset($savings_list[$account_id])) {
				$savingsData = $savings_list[$account_id];
			}
			// This is an expense account with a sinking / savings
			// account associated.
			if ($savingsData != null) {
  			// Set savingsBalance *before* setSaved, for calculation.
				$accountSavings->savingsBalance = $savingsData->savingsBalance;
				$accountSavings->setSaved($savingsData->getSaved(), true);
				$accountSavings->savingsName = 'Account ' . $savingsData->savingsName;
				$accountSavings->savingsParentId = $savingsData->savingsParentId;
			} else {
			  // Savings account, but no savings this period
			  $accountSavings->setSaved(0.0, true);
			}
			
		} else {
			// no savings
			$accountSavings->setSaved(0.0, false);
		}
		
		$balanceTotal += $accountSavings->balance;
		$budgetTotal += $accountSavings->budget;
		$transactionTotal += $accountSavings->transactions;
		// Only add Unspent amount for budgets or expenses
		if (!$showBalance || $accountSavings->budget != 0.0) {
			$unspentTotal += $accountSavings->getUnspent();
		}
		$savedTotal += $accountSavings->getSaved();
		$toSaveTotal += $accountSavings->getToSave();


		$sortKey = null;
		switch ($sortOrder) {
			case 'account':
				$sortKey = null;  // default sort
				break;
			case 'budget':
				$sortKey = $accountSavings->budget;
				break;
			case 'balance':
				$sortKey = $accountSavings->balance;
				break;
			case 'transactions':
				$sortKey = $accountSavings->transactions;
				break;
			case 'saved':
				$sortKey = $accountSavings->getSaved();
				break;
			case 'toSave':
				$sortKey = $accountSavings->getToSave();
				break;
			case 'unspent':
				$sortKey = $accountSavings->getUnspent();
				break;
			case 'budgetPercent':
				$sortKey = $accountSavings->getBudgetPercent();
				break;
		}

		if (!is_null($sortKey)) {
			// use compound key to avoid duplicates
			$sortedList[$sortKey . $accountSavings->accountName] = $accountSavings;
			$isSorted = true;
		}
		
	} // End record loop

	// PHP quirk:  need to unset object reference after foreach,
	// since we are passing the variable by reference
	unset($accountSavings);


	if ($isSorted) {
		if ($sortDirection == 0) {
			// sort the array backwards (descending order)
			krsort($sortedList, SORT_NUMERIC);
		} else {
			ksort($sortedList, SORT_NUMERIC);
		}
	} else {
		$sortedList = &$account_list;
	}


?>

	<script language="javascript" type="text/javascript">

	function bodyLoad()
	{
		document.forms[0].start_date.focus();
	}

	function sortByField(sortField) {
		if (document.getElementById('sortOrder').value == sortField) {
			// invert sort order (value will be 0 or 1)
			var newDirection = (Number(document.getElementById('sortDirection').value) + 1) % 2;
			document.getElementById('sortDirection').value = newDirection;
		}
		document.getElementById('sortOrder').value = sortField;
		document.getElementById('searchForm').submit();
	}

	function sortBudget() {
		sortByField('budget');
	}
	function sortAccount() {
		sortByField('account');
	}
	function sortTransactions() {
		sortByField('transactions');
	}
	function sortSaved() {
		sortByField('saved');
	}
	function sortToSave() {
		sortByField('toSave');
	}
	function sortUnspent() {
		sortByField('unspent');
	}
	function sortBudgetPercent() {
		sortByField('budgetPercent');
	}
	function sortBalance() {
		sortByField('balance');
	}

	</script>
</head>


<body onload="bodyLoad()">
<?= $navbar ?>

<h3><?= $title ?></h3>

<span class="error"><?= $error ?></span>
<span class="message"><?= $message ?></span>

<form action="account_details.php" method="post" id="searchForm">
<input type="hidden" id="sortOrder" name="sortOrder" value="<?= $sortOrder ?>" />
<input type="hidden" id="sortDirection" name="sortDirection" value="<?= $sortDirection ?>" />
<table>
	<tr>
		<td>Start Date: </td>
		<td><input type="text" maxlength="10" name="start_date" value="<?= $startDateText ?>" /></td>
		<td>End Date: </td>
		<td><input type="text" maxlength="10" name="end_date" value="<?= $endDateText ?>" /></td>
		<td>&nbsp;&nbsp;Active: <input type="checkbox" name="activeOnly" value="1"
			<?= $activeOnlyChecked ?> /></td>
	</tr>

	<tr>
		<td>Top Account: </td>
		<td colspan="2"><?= $account_dropdown ?></td>
		<td colspan="1">&nbsp;&nbsp;
			<input type="submit" value="&lt;" name="prev_month"> &nbsp;
			<input type="submit" value="&gt;" name="next_month"></td>
		<td>&nbsp;&nbsp;<input type="submit" value="Update" name="update_date"></td>
	</tr>
</table>
</form>


<table class="budget-list">
	<tr>
		<th onclick="sortAccount();">Account</th>
		<th style='text-align: right;' onclick="sortBudget();">Budget</th>
		<th onclick="sortTransactions();">Transactions</th>
		<?= $balanceHeader ?>
		<?= $savingsHeader ?>
		<th onclick="sortUnspent();">Unspent</th>
		<?= $availableHeader ?>	
		<th onclick="sortBudgetPercent()">Budget %</th>
	</tr>

<?php

	// Second loop:  display results
	foreach ($sortedList as $sortedKey => $accountSavings)
	{
	  $unspent = $accountSavings->getUnspent();
	  $budgetPercent = $accountSavings->getBudgetPercent();
	  
		echo "	<tr> \n".
			"		<td title='$accountSavings->accountDescr'>$accountSavings->accountName</td> \n".
			"		<td class='numeric'>". format_currency($accountSavings->budget) . "</td> \n".
			"		<td class='numeric'>". format_currency($accountSavings->transactions) . "</td> \n";
		if ($showBalance) {
			echo "		<td class='numeric'>". format_currency($accountSavings->balance) . "</td> \n";
		}
		
		if (empty($accountSavings->budget)) {
			// suppress Unspent from asset accounts with no budget
			$unspent = '';
			$budgetPercent = '';
		}

		if (!$showBalance) {
			// RHS / expenses
			$balanceTitle = '';
			$available = $unspent;
			if ($accountSavings->savingsParentId > 0) {
			  // Savings account is present for this expense account
			  $balanceTitle = "Savings balance: $accountSavings->savingsBalance";
			  $available = $accountSavings->getAvailable();
			}
			echo "		<td title='$accountSavings->savingsName' style='text-align: right;'>".
			  format_currency($accountSavings->getSaved()) . "</td> \n".
			  "		<td title='$balanceTitle' class='numeric'>".
			  format_currency($accountSavings->getToSave()) . "</td> \n";
		}
		echo "		<td class='numeric'>". format_currency($unspent) . "</td> \n";
		if (!$showBalance) {
			echo "		<td class='numeric'>". format_currency($available). "</td> \n";
		}
		echo "		<td class='numeric'>". format_percent($budgetPercent, 0) . "</td> \n".
			"	</tr> \n";
	}	// End budget loop

	$balanceTotalString = format_currency($balanceTotal);
	$budgetTotalString = format_currency($budgetTotal);
	$transactionTotalString = format_currency($transactionTotal);
	$unspentTotalString = format_currency($unspentTotal);
	$savedTotalString = format_currency($savedTotal);
	$toSaveTotalString = format_currency($toSaveTotal);
	
	if (empty($budgetTotal)) {
		// Suppress summary fields that don't matter
		$budgetTotalString = '';
		$unspentTotalString = '';
	}
	
	echo "	<tr> \n".
		"		<td style='border-top: 1px solid black; border-bottom: 1px solid black;' ".
		" colspan=\"8\">&nbsp;</td> \n".
		"	</tr> \n\n".
		"	<tr> \n".
		"		<td>Total</td> \n".
		"		<td class='total'>$budgetTotalString</td> \n".
		"		<td class='total'>$transactionTotalString</td> \n";
	if ($showBalance) {
		echo "		<td class='total'>$balanceTotalString</td> \n";
	}
	if (!$showBalance) {
		echo "		<td class='total'>$savedTotalString</td> \n".
		"		<td class='total'>$toSaveTotalString</td> \n";
	}
	echo "		<td class='total'>$unspentTotalString</td> \n".
		"	</tr> \n\n" ;
		
?>
</table>

<div class="bottom">
  <form action='auto_sink.php' method='POST'>
    <input type='hidden' name='account_id' value='<?= $searchAccountId ?>' />
    <input type='hidden' name='start_date' value='<?= $startDateText ?>' />
    <input type='hidden' name='end_date' value='<?= $endDateText ?>' />
    <input type='submit' name='goSink' value='End of Month - Auto Sink Accounts' />
<?php require('footer.php'); ?>
  </form>
</div>


</body>
</html>
