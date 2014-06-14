<?php
	$current_page = 'account_details';
	require('include.php');
	
	
	if (!isset ($_SESSION['login_id']))
	{
		// redirect to login if they have not logged in
		header ("Location: login.php");
	}
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
		}
	}
	
	// Get full account details from DB
	$account = new Account();
	$account->Load_account($account_id);
	$showBalance = ($account->get_equation_side() == 'L');
	$balanceHeader = '';
	$savingsHeader = '';
	if ($showBalance) {
		$balanceHeader = '<th onclick="sortBalance();">Balance</th>';
	} else {
		$savingsHeader = "<th onclick='sortSaved();'>Saved</th> \n".
		"		<th onclick='sortToSave();'>To Save</th>";
	}
	
	$activeOnlyChecked = $activeOnly ? 'checked="checked"' : '';

	$startDateText = $startDate->format('m/d/Y');
	$endDateText = $endDate->format('m/d/Y');

	// build account dropdowns: include inactive, and only show top 2 tiers
	$account_list = Account::Get_account_list($login_id,
		'', -1, false, false, true, true);
	$account_dropdown = Build_dropdown ($account_list, 'account_id',
		$account_id);
	
	$minDate = $startDate;
	if ($showBalance) {
		// when showing balance, we need to go back to the beginning.
		$minDate = new DateTime('0000-00-00');
	}

	// Build main data list
	$account_list = array();	// pass by reference
	if ($error == '') {
		$error = Account::Get_account_details($account_id, $startDate,
				$endDate, $minDate, $activeOnly, $account_list);
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
?>

<html>
<head>
	<title>Account Details</title>
	<link href="style.css" rel="stylesheet" type="text/css">
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

<h3>Account Details</h3>

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


<table class="budget-list" cellspacing="0" cellpadding="0">
	<tr>
		<th onclick="sortAccount();">Account</th>
		<th onclick="sortBudget();">Budget</th>
		<th onclick="sortTransactions();">Transactions</th>
		<?= $balanceHeader ?>
		<?= $savingsHeader ?>
		<th onclick="sortUnspent();">Unspent</th>
		<th onclick="sortBudgetPercent()">Budget %</th>
	</tr>

<?php
	$balanceTotal = 0.0;
	$budgetTotal = 0.0;
	$transactionTotal = 0.0;
	$unspentTotal = 0.0;
	$savedTotal = 0.0;
	$toSaveTotal = 0.0;

	$isSorted = false;

	// First loop:  calculate values and prepare the sort
	foreach ($account_list as $account_id => &$account_data)
	{
		$accountName = $account_data[0];
		$balance = $account_data[1];
		$budget = $account_data[2];
		$transactions = $account_data[3];
		$savingsId = $account_data[4];
		$accountDescr = $account_data[5];

		$saved = 0.0;
		$toSave = 0.0;
		$budgetPercent = 0.0;
		$unspent = 0.0;
		$savingsName = '';

		if ($savingsId > 0) {
			// check for savings record for this account
			$savingsData = null;
			if (isset($savings_list[$account_id])) {
				$savingsData = $savings_list[$account_id];
			}
			// This is an expense account with a sinking / savings
			// account associated.
			if ($savingsData != null) {
				$saved = $savingsData[0];
				$savingsName = 'Account ' . $savingsData[1];
			}
			// unspent will be negative when over budget
			$unspent = $budget - $transactions - $saved;
			$toSave = max(0.0, $unspent); // To Save is never negative
			// if toSave is > 0, then subtrace from unspent
			$unspent -= $toSave;
		} else {
			// no savings
			$unspent = $budget - $transactions;
			if ($budget != 0.0) {
				$budgetPercent = $transactions / $budget * 100.0;
			}
		}

		// Add the calculated values to the data array
		$account_data[6] = $saved;
		$account_data[7] = $toSave;
		$account_data[8] = $budgetPercent;
		$account_data[9] = $unspent;
		$account_data[10] = $savingsName;
		
		$sortKey = null;
		switch ($sortOrder) {
			case 'account':
				$sortKey = null;  // default sort
				break;
			case 'budget':
				$sortKey = $budget;
				break;
			case 'balance':
				$sortKey = $balance;
				break;
			case 'transactions':
				$sortKey = $transactions;
				break;
			case 'saved':
				$sortKey = $saved;
				break;
			case 'toSave':
				$sortKey = $toSave;
				break;
			case 'unspent':
				$sortKey = $unspent;
				break;
			case 'budgetPercent':
				$sortKey = $budgetPercent;
				break;
		}

		if (!is_null($sortKey)) {
			// use compound key to avoid duplicates
			$sortedList[$sortKey . $accountName] = $account_data;
			$isSorted = true;
		}
		
		$balanceTotal += $balance;
		$budgetTotal += $budget;
		$transactionTotal += $transactions;
		$unspentTotal += $unspent;
		$savedTotal += $saved;
		$toSaveTotal += $toSave;

	} // End record loop

	// PHP quirk:  need to unset object reference after foreach,
	// since we are passing the variable by reference
	unset($account_data);


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


	// Second loop:  display results
	foreach ($sortedList as $sortedKey => $account_data)
	{
		$accountName = $account_data[0];
		$balance = $account_data[1];
		$budget = $account_data[2];
		$transactions = $account_data[3];
		$savingsId = $account_data[4];
		$accountDescr = $account_data[5];
		$saved = $account_data[6];
		$toSave = $account_data[7];
		$budgetPercent = $account_data[8];
		$unspent = $account_data[9];
		$savingsName = $account_data[10];

		
		echo "	<tr> \n".
			"		<td title='$accountDescr'>$accountName</td> \n".
			"		<td style='text-align: right;'>". format_currency($budget) . "</td> \n".
			"		<td style='text-align: right;'>". format_currency($transactions) . "</td> \n";
		if ($showBalance) {
			echo "		<td style='text-align: right;'>". format_currency($balance) . "</td> \n";
		}
		if (!$showBalance) {
			// RHS / expenses
			echo "		<td title='$savingsName' style='text-align: right;'>". format_currency($saved) . "</td> \n".
			"		<td style='text-align: right;'>". format_currency($toSave) . "</td> \n";
		}
		echo	"		<td style='text-align: right;'>". format_currency($unspent) . "</td> \n".
			"		<td style='text-align: right;'>". format_percent($budgetPercent, 0) . "</td> \n".
			"	</tr> \n";
	}	// End budget loop

	$balanceTotalString = format_currency($balanceTotal);
	$budgetTotalString = format_currency($budgetTotal);
	$transactionTotalString = format_currency($transactionTotal);
	$unspentTotalString = format_currency($unspentTotal);
	$savedTotalString = format_currency($savedTotal);
	$toSaveTotalString = format_currency($toSaveTotal);
	
	echo "	<tr> \n".
		"		<td style='border-top: 1px solid black; border-bottom: 1px solid black;' ".
		" colspan=\"7\">&nbsp;</td> \n".
		"	</tr> \n\n".
		"	<tr> \n".
		"		<td>Total</td> \n".
		"		<td style='text-align: right; font-weight: bold;'>$budgetTotalString</td> \n".
		"		<td style='text-align: right; font-weight: bold;'>$transactionTotalString</td> \n";
	if ($showBalance) {
		echo "		<td style='text-align: right; font-weight: bold;'>$balanceTotalString</td> \n";
	}
	if (!$showBalance) {
		echo "		<td style='text-align: right; font-weight: bold;'>$savedTotalString</td> \n".
		"		<td style='text-align: right; font-weight: bold;'>$toSaveTotalString</td> \n";
	}
	echo "		<td style='text-align: right; font-weight: bold;'>$unspentTotalString</td> \n".
		"	</tr> \n\n" ;

?>
		
</table>

</body>
</html>
