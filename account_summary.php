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
	$end_date = date ('12/31/Y');
	$account1_id = $_SESSION['default_summary1'];
	$account2_id = $_SESSION['default_summary2'];
	$net_multiplier = -1;

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
			$net_multiplier = (int)$_POST['net_multiplier'];
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
	// plus / minus dropdown
	$sum_list = array ('-1' => 'Subtract', '1' => 'Add');
	$sum_dropdown = Build_dropdown ($sum_list, 'net_multiplier',
		$net_multiplier);

	// Get summary data
	$summary_list = array();
	$error = Account::Get_summary_list (
		$account1_id,
		$account2_id,
		$start_date,
		$end_date,
		$summary_list
	);

	$fuel_list = array();
	if ($error == '')
	{
		// Get fuel consumption data
		$error = Account::Get_gas_totals ($fuel_list);
	}
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
		<td><h3>Account Ledger</h3>
		<td style="padding-left: 300px;">
			<a href="login.php?logout=1">Logout <?= $_SESSION['display_name'] ?></a></td>
	<?
		if ($_SESSION['login_admin'] == 1)
		{ ?>
		<td style="padding-left: 13px;">
			<a href="accounts.php">Manage Accounts</a></td>
	<?	}	//end accounts link	 ?>	
	</tr>

	<tr>
		<td></td>
		<td style="padding-left: 300px;">
			<a href="index.php">Account Ledger</a></td>
		<td style="padding-left: 13px;"><a href="account_breakdown.php">Account Breakdown</a></td>
	</tr>

</table>

<span class="error"><?= $error ?></span>

<form action="account_summary.php" method="post">
<table>
	<tr>
		<td>From: </td>
		<td><input type="text" name="start_date" value="<?= $start_date ?>"></td>
		<td>To: </td> 
		<td><input type="text" name="end_date" value="<?= $end_date ?>"></td>
		<td><?= $sum_dropdown ?></td>
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
	$td_style = 'style="border-left: 1px solid black; "';
	$c_style = 'style="border-left: 2px solid black; "';	// center divider
?>

<table class="summary-list" cellspacing="0" cellpadding="0">
	<tr>
		<th colspan="2">Period</th>
		<th>Account 1 </th>
		<th>Account 2 </th>
		<th <?= $td_style ?>>Sum </th>
		<th <?= $c_style ?>>Account 1 YTD </th>
		<th>Account 2 YTD </th>
		<th <?= $td_style ?>>Sum YTD </th>
	</tr>

<?
	// Loop through summary list
	// (YYYY-MM) => (month, year, account1_sum, account2_sum,
	//		account1_ytd, account2_ytd)
	$i = 0;
	$last_row = array();
	$last_ytd1 = 0;
	$last_ytd2 = 0;
	foreach ($summary_list as $key => $summary_data)
	{
		$period_txt = '';
		$period_month = $summary_data[0];
		$period_year = $summary_data[1];
		$account1_sum = $summary_data[2];
		$account2_sum = $summary_data[3];
		$account_total = $account1_sum + ($account2_sum * $net_multiplier);
		$account1_ytd = $summary_data[4];
		$account2_ytd = $summary_data[5];
		$ytd_total = $account1_ytd + ($account2_ytd * $net_multiplier);
		if ($account1_ytd == 0.0 && $i > 0 && $period_month != 1)
		{
			// grab last non-zero YTD val if it's zero;
			// For January, don't grab last month's val.
			// if there's no data for this month, it will be set to zero.
			$account1_ytd = $last_ytd1;
		}
		else
		{
			$last_ytd1 = $account1_ytd;
		}

		if ($account2_ytd == 0.0 && $i > 0 && $period_month != 1)
		{
			// last month's YTD val for account 2
			$account2_ytd = $last_ytd2;
		}
		else
		{
			$last_ytd2 = $account2_ytd;
		}


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

		// Output HTML
		$hr_html = '';

		if ($period_month == 1) //|| $period_month == 12)
		{
			if ($i == 0)
				$descr_txt = "$period_year";
			else
				$descr_txt = $period_year;

			$hr_html = "	<tr> \n".
				"		<td style=\"border-top: 1px solid black; border-bottom: 1px solid black;\"".
				" colspan=\"8\">$descr_txt</td> \n".
				"	</tr> \n\n";
		}

		echo $hr_html;

		echo "	<tr> \n".
			"		<td style=\"width: 20px;\">&nbsp;</td>".
			"		<td>$period_txt</td> \n".
			"		<td class=\"currency\">". format_currency($account1_sum).
				"</td> \n".
			"		<td class=\"currency\">". format_currency($account2_sum).
				"</td> \n".
			"		<td class=\"currency\" $td_style>".
				format_currency($account_total). "</td> \n".
			"		<td class=\"currency\" $c_style>". format_currency($account1_ytd).
				"</td> \n".
			"		<td class=\"currency\">". format_currency($account2_ytd).
				"</td> \n".
			"		<td class=\"currency\" $td_style>".
				format_currency($ytd_total). "</td> \n".
			"	</tr> \n\n";

		//echo $hr_html;

		$i++;
		$last_row = $summary_data;	// copy current row
	}

?>

</table>

<br>

<p>Fuel consumption statistics</p>
<table class="summary-list" cellspacing="0" cellpadding="0">

	<tr>
		<th>Account</th>
		<th>Tanks</th>
		<th>Total miles</th>
		<th>Total galls</th>
		<th>Avg MPG</th>
		<th>Avg $ / gallon</th>
		<th>Avg miles / tank</th>
	</tr>

<?
	//	array (account_name, num_records, total_miles, total_gallons,
	//		total_dollars)
	foreach ($fuel_list as $fuel_data)
	{
		$total_miles = number_format ($fuel_data[2]);
		$total_gallons = number_format ($fuel_data[3], 1);
		$avg_mpg = '0.0';
		$cost_per_gall = '0.00';
		if ($fuel_data[3] != 0.0)
		{
			$avg_mpg = sprintf ("%0.1f", $fuel_data[2] / $fuel_data[3]);
			$cost_per_gall = number_format ($fuel_data[4] / $fuel_data[3], 2);
		}
		$avg_miles = '0.0';
		if ($fuel_data[1] != 0)
		{
			$avg_miles = number_format ($fuel_data[2] / $fuel_data[1], 1);
		}

		echo "	<tr> \n".
			"		<td>$fuel_data[0]</td> \n".
			"		<td style=\"text-align: right;\">$fuel_data[1]</td> \n".
			"		<td style=\"text-align: right;\">$total_miles</td> \n".
			"		<td style=\"text-align: right;\">$total_gallons</td> \n".
			"		<td style=\"text-align: right;\">$avg_mpg</td> \n".
			"		<td style=\"text-align: right;\">\$$cost_per_gall</td> \n".
			"		<td style=\"text-align: right;\">$avg_miles</td> \n".
			"	</tr> \n\n" ;
	}
?>
</table>

</body>
</html>