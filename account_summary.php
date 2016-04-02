<?php
	$current_page = 'account_summary';
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

	// Add or subtract display vars
	$net_sign = ' &#150 ';	// long dash
	$net_txt = 'Diff';
	if ($net_multiplier != -1)
	{
		$net_sign = ' + ';
		$net_txt = 'Sum';
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
<?= $navbar ?>

<h3>Account Summary</h3>

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
		<td colspan="2"><?= $account1_dropdown. $net_sign ?></td>
		<td colspan="2"><?= $account2_dropdown ?></td>
		<td> = Net &nbsp;&nbsp;<input type="submit" name="calc" value="Update"></td>
	</tr>

</table>
</form>

<?php
	// left border for right-most column
	$td_style = 'style="border-left: 1px solid black;"';
	$c_style = 'style="border-left: 2px solid black; "';	// center divider
?>

<table class="summary-list" cellspacing="0" cellpadding="0">
	<tr>
		<th colspan="2">Period</th>
		<th>Account 1 </th>
		<th>Account 2 </th>
		<th <?= $td_style ?>><?php $net_txt ?></th>
		<th style="text-align: center;">%</th>
		<th <?= $c_style ?>>Account 1 YTD </th>
		<th>Account 2 YTD </th>
		<th <?= $td_style ?>>Sum YTD </th>
		<th style="text-align: center;">YTD %</th>
	</tr>

<?php
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
		$account_pct = 0;
		$ytd_pct = 0.0;

		if ($account1_sum != 0)
		{
			if ($net_multiplier < 0)
				$account_pct = $account_total / $account1_sum;
			else
				$account_pct = $account2_sum / $account1_sum;
		}
		$account1_ytd = $summary_data[4];
		$account2_ytd = $summary_data[5];
		
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

		// calcuate YTD total & percent AFTER ytd amounts are settled
		$ytd_total = $account1_ytd + ($account2_ytd * $net_multiplier);
		if ($account1_ytd != 0)
		{
			if ($net_multiplier < 0)
				$ytd_pct = $ytd_total / $account1_ytd;
			else
				$ytd_pct = $account2_ytd / $account1_ytd;
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
		/*
		elseif ($period_month == $end_date_arr['mon']
			&& $period_year == $end_date_arr['year'])
		{
			// current end month
			$period_txt = 'MTD';
		}
		*/
		else
		{
			// Get regular name of first of the month
			// Note:  must specify day 1 or it defaults to current day
			// of month.
			$period_txt = date ('F', mktime (0, 0, 0, $period_month, 1) );
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
				" colspan=\"10\">$descr_txt</td> \n".
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
			"		<td class=\"currency\">".
				number_format ($account_pct*100, 1). "%</td> \n".
			"		<td class=\"currency\" $c_style>". format_currency($account1_ytd).
				"</td> \n".
			"		<td class=\"currency\">". format_currency($account2_ytd).
				"</td> \n".
			"		<td class=\"currency\" $td_style>".
				format_currency($ytd_total). "</td> \n".
			"		<td class=\"currency\">".
				number_format ($ytd_pct*100, 1). "%</td> \n".
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
		<th>Year</th>
		<th>Account</th>
		<th>Tanks</th>
		<th>Total miles</th>
		<th>Total galls</th>
		<th>Total $</th>
		<th>Avg MPG</th>
		<th>Avg $ / gallon</th>
		<th>Avg miles / tank</th>
	</tr>

<?php
		//	array (account_name, accounting_year, num_records, total_miles, 
		//		total_gallons, total_dollars)
	foreach ($fuel_list as $fuel_data)
	{
		$total_miles	= number_format ($fuel_data[3]);
		$total_gallons	= number_format ($fuel_data[4], 1);
		$total_dollars	= number_format ($fuel_data[5], 2);
		$avg_mpg = '0.0';
		$cost_per_gall = '0.00';

		if ($fuel_data[4] != 0.0)
		{
			$avg_mpg = sprintf ("%0.1f", $fuel_data[3] / $fuel_data[4]);
			$cost_per_gall = number_format ($fuel_data[5] / $fuel_data[4], 2);
		}
		$avg_miles = '0.0';
		if ($fuel_data[1] != 0)
		{
			$avg_miles = number_format ($fuel_data[3] / $fuel_data[2], 1);
		}

		echo "	<tr> \n".
			"		<td>$fuel_data[1]</td> \n".
			"		<td>$fuel_data[0]</td> \n".
			"		<td style=\"text-align: right;\">$fuel_data[2]</td> \n".
			"		<td style=\"text-align: right;\">$total_miles</td> \n".
			"		<td style=\"text-align: right;\">$total_gallons</td> \n".
			"		<td style=\"text-align: right;\">\$$total_dollars</td> \n".
			"		<td style=\"text-align: right;\">$avg_mpg</td> \n".
			"		<td style=\"text-align: right;\">\$$cost_per_gall</td> \n".
			"		<td style=\"text-align: right;\">$avg_miles</td> \n".
			"	</tr> \n\n" ;
	}
?>
</table>

</body>
</html>
