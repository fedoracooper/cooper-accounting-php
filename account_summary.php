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
		<th>Period</th>
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
	foreach ($summary_list as $summary_data)
	{
		$period_txt = '';
		$period_month = $summary_data[0];
		$period_year = $summary_data[1];
		$account1_sum = $summary_data[2];
		$account2_sum = $summary_data[3];
		$account_total = $account1_sum + ($account2_sum * $net_multiplier);
		$account1_ytd = $summary_data[4];
		$account2_ytd = $summary_data[5];
		$ytd_total = $account1_ytd - $account2_ytd;

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

		if ($period_month == 12 || $i == 0)
		{
			if ($i == 0)
				$descr_txt = "$period_year YTD";
			else
				$descr_txt = $period_year;

			$hr_html = "	<tr> \n".
				"		<td style=\"border-top: 1px solid black; border-bottom: 1px solid black;\"".
				" colspan=\"7\">$descr_txt</td> \n".
				"	</tr> \n\n";
		}

		echo $hr_html;

		echo "	<tr> \n".
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
	}

?>

</table>

</body>
</html>