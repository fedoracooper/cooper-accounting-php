<?
	require('include.php');
	if (!isset ($_SESSION['login_id']))
	{
		// redirect to login if they have not logged in
		header ("Location: login.php");
	}

	$error = '';

	// set default vars
	$dateArr = getdate();
	// start: first of last year
	// end: today
	$start_time = mktime (0, 0, 0, 1, 1, $dateArr['year'] - 1);
	$start_date = date ('m/d/Y', $start_time);
	$end_date = date ('m/d/Y');
	$account1_id = 15;
	$account2_id = 14;

	if (isset ($_POST['calc']))
	{
		$date_verify = parse_date ($_POST['start_date']);
		if ($date_verify == -1)
			$error = 'Bad start date';
		else
		{
			$date_verify = parse_date ($_POST['end_date']);
			if ($date_verify == -1)
				$error = 'Bad end date';
		}

		// refresh data
		if ($error == '')
		{	// don't update dates unless valid
			$start_date		= $_POST['start_date'];
			$end_date		= $_POST['end_date'];
		}
		$account1_id	= $_POST['account1_id'];
		$account2_id	= $_POST['account2_id'];
	}

	// build account dropdowns
	$account_list = Account::Get_account_list ($_SESSION['login_id']);
	$account1_dropdown = Build_dropdown ($account_list, 'account1_id',
		$account1_id);
	$account2_dropdown = Build_dropdown ($account_list, 'account2_id',
		$account2_id);

	// Get summary data
	$summary_list = array();
	$error = Account::Get_summary_list (
		$account1_id,
		$account2_id,
		$start_date,
		$end_date,
		$summary_list
	);

?>

<html>
<head>
	<title>Account Summary</title>
	<link href="style.css" rel="stylesheet" type="text/css">
	<script language="javascript" type="text/javascript">

		function bodyLoad()
		{
			document.forms[0].start_date.focus();
		}

	</script>
</head>


<body onload="bodyLoad()">
<table class="summary-form">
	<tr>
		<td><h3>Account Summary</h3>
		<td style="padding-left: 300px;">
			<a href="login.php?logout=1">Logout <?= $_SESSION['display_name'] ?></a></td>
	</tr>

	<tr>
		<td></td>
		<td style="padding-left: 300px;">
			<a href="index.php">Account Ledger</a></td>
	</tr>

	<?
		if ($_SESSION['login_admin'] == 1)
		{ ?>
	<tr>
		<td></td>
		<td style="padding-left: 300px;">
			<a href="accounts.php">Manage Accounts</a></td>
	</tr>
	<?	}	//end accounts link	 ?>

</table>

<p class="error"><?= $error ?></p>

<form action="account_summary.php" method="post">
<table>
	<tr>
		<td>From: </td>
		<td><input type="text" name="start_date" value="<?= $start_date ?>"></td>
		<td>To: </td> 
		<td><input type="text" name="end_date" value="<?= $end_date ?>"></td>
	</tr>

	<tr>
		<td colspan="2"><?= $account1_dropdown ?> - </td>
		<td colspan="2"><?= $account2_dropdown ?></td>
		<td> = Net &nbsp;&nbsp;<input type="submit" name="calc" value="Update"></td>
	</tr>

</table>
</form>

<?
	// left border for right-most column
	$td_style = 'style="border-left: 1px solid black;"';
?>

<table class="summary-list" cellspacing="0" cellpadding="0">
	<tr>
		<th>Period</th>
		<th>Account 1 - </th>
		<th>Account 2 = </th>
		<th <?= $td_style ?>>Net</th>
	</tr>

<?
	// Loop through summary list
	// (YYYY-MM) => (month, year, account1_sum, account2_sum)
	foreach ($summary_list as $summary_data)
	{
		$period_txt = '';
		$period_month = $summary_data[0];
		$period_year = $summary_data[1];
		$account1_sum = $summary_data[2];
		$account2_sum = $summary_data[3];
		$account_total = $account1_sum - $account2_sum;

		$end_date_arr = getdate (strtotime ($end_date));
		if ($period_month == 13)
		{
			// yearly summary
			if ($period_year == date('Y'))
				// this year
				$period_txt = "YTD ($period_year)";
			else
				$period_txt = $period_year;
		}
		elseif ($period_month == $end_date_arr['mon']
			&& $period_year == $end_date_arr['year'])
		{
			// current end month
			$period_txt = 'MTD';
		}
		else
		{
			// Get regular name of the month
			$period_txt = date ('F', mktime (0, 0, 0, $period_month));
		}

		$number_span = '';
		$account1_str = format_currency ($account1_sum);
		$account2_str = format_currency ($account2_sum);
		$account_total_str = format_currency ($account_total);

		// Output HTML
		$hr_html = '';
		if ($period_month == 13)
		{
			$hr_html = "	<tr> \n".
				"		<td colspan=\"3\"><hr></td> \n".
				"		<td $td_style><hr></td> \n".
				"	</tr> \n\n";
		}

		echo $hr_html;

		echo "	<tr> \n".
			"		<td>$period_txt</td> \n".
			"		<td class=\"currency\">$account1_str</td> \n".
			"		<td class=\"currency\">$account2_str</td> \n".
			"		<td class=\"currency\" $td_style>$account_total_str</td> \n".
			"	</tr> \n\n";

		echo $hr_html;
	}

?>

</table>

</body>
</html>