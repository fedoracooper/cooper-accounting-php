<?php
	$current_page = 'account_details';
	require('include.php');
	
	
	if (!isset ($_SESSION['login_id']))
	{
		// redirect to login if they have not logged in
		header ("Location: login.php");
	}
	
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
		}
		
		return $endDate;
	}

	$error = '';
	$message = '';
	
	// set default vars
	// Set budget date to first day of month
	$startDate = getStartDate();
	$endDate = getEndDate();
	
	// default account comes from DB
	$account_id = Account::Get_top_asset_account_id($_SESSION['login_id']);
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
	if ($showBalance) {
		$balanceHeader = '<th>Balance</th>';
	}
	
	$activeOnlyChecked = $activeOnly ? 'checked="checked"' : '';

	$startDateText = $startDate->format('m/d/Y');
	$endDateText = $endDate->format('m/d/Y');

	// build account dropdowns: include inactive, and only show top 2 tiers
	$account_list = Account::Get_account_list($_SESSION['login_id'],
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

	</script>
</head>


<body onload="bodyLoad()">
<?= $navbar ?>

<h3>Account Details</h3>

<span class="error"><?= $error ?></span>
<span class="message"><?= $message ?></span>

<form action="account_details.php" method="post">
<table>
	<tr>
		<td>Start Date: </td>
		<td><input type="text" maxlength="10" name="start_date" value="<?= $startDateText ?>" /></td>
		<td>End Date: </td>
		<td><input type="text" maxlength="10" name="end_date" value="<?= $endDateText ?>" /></td>
	</tr>

	<tr>
		<td>Top Account: </td>
		<td colspan="2"><?= $account_dropdown ?></td>
		<td>&nbsp;&nbsp;Active: <input type="checkbox" name="activeOnly" value="1" <?= $activeOnlyChecked ?> /></td>
		<td>&nbsp;&nbsp;<input type="submit" value="Update" name="update_date"></td>
	</tr>
</table>
</form>


<table class="budget-list" cellspacing="0" cellpadding="0">
	<tr>
		<th>Account</th>
		<?= $balanceHeader ?>
		<th>Transactions</th>
		<th>Monthly Budget</th>
		<th>Surplus</th>
		<th>Budget %</th>
	</tr>

<?php
	// First loop through data: calculate totals
	$balanceTotal = 0.0;
	$budgetTotal = 0.0;
	$transactionTotal = 0.0;
	$surplusTotal = 0.0;


	// Second loop: display data
	foreach ($account_list as $account_id => $account_data)
	{
		$accountName = $account_data[0];
		$balance = $account_data[1];
		$budget = $account_data[2];
		$transactions = $account_data[3];
		$surplus = null;
		$budgetPercent = null;
		if (is_numeric($budget)) {
			$surplus = $budget - $transactions;
			if ($budget != 0.0) {
				$budgetPercent = $transactions / $budget * 100.0;
			}
		}
		
		$balanceTotal += $balance;
		$budgetTotal += $budget;
		$transactionTotal += $transactions;
		$surplusTotal += $surplus;
		
		echo "	<tr> \n".
			"		<td>$accountName</td> \n";
		if ($showBalance) {
			echo "		<td style='text-align: right;'>". format_currency($balance) . "</td> \n";
		}
		echo "		<td style='text-align: right;'>". format_currency($transactions) . "</td> \n".
			"		<td style='text-align: right;'>". format_currency($budget) . "</td> \n".
			"		<td style='text-align: right;'>". format_currency($surplus) . "</td> \n".
			"		<td style='text-align: right;'>". format_percent($budgetPercent, 0) . "</td> \n".
			"	</tr> \n";
	}	// End budget loop

	$balanceTotalString = format_currency($balanceTotal);
	$budgetTotalString = format_currency($budgetTotal);
	$transactionTotalString = format_currency($transactionTotal);
	$surplusTotalString = format_currency($surplusTotal);
	
	echo "	<tr> \n".
		"		<td style='border-top: 1px solid black; border-bottom: 1px solid black;' ".
		" colspan=\"6\">&nbsp;</td> \n".
		"	</tr> \n\n".
		"	<tr> \n".
		"		<td>Total</td> \n";
	if ($showBalance) {
		echo "		<td style='text-align: right; font-weight: bold;'>$balanceTotalString</td> \n";
	}
	echo "		<td style='text-align: right; font-weight: bold;'>$budgetTotalString</td> \n".
		"		<td style='text-align: right; font-weight: bold;'>$transactionTotalString</td> \n".
		"		<td style='text-align: right; font-weight: bold;'>$surplusTotalString</td> \n".
		"	</tr> \n\n" ;

?>
		
</table>

</body>
</html>
