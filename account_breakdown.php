<?php
	$current_page = 'account_breakdown';
	require('include.php');
	if (!isset ($_SESSION['login_id']))
	{
		// redirect to login if they have not logged in
		header ("Location: login.php");
	}

	$error = '';
	// set default vars
	//$start_date = date ('m/1/Y');	//first of current month
	$dateArr = getdate ();
	$dateArr['mday'] = 1;
	$dateArr2 = getdate();
	$dateArr2['mon'] ++;
	$dateArr2['mday'] = 0;

	$account_id = $_SESSION['default_summary2'];
	
	if (isset ($_POST['start_date']))
	{
		$dateArr	= getdate (strtotime ($_POST['start_date']));
		$dateArr2	= getdate (strtotime ($_POST['end_date']));
		$account_id	= $_POST['account_id'];

		if (isset ($_POST['prev_month']))
		{
			// go back one month
			$dateArr['mon'] --;
			$dateArr['mday'] = 1;
			$dateArr2['mday'] = 0;
		}
		elseif (isset ($_POST['next_month']))
		{
			// forward one month
			$dateArr['mon'] ++;
			$dateArr['mday'] = 1;
			$dateArr2['mon'] += 2;
			$dateArr2['mday'] = 0;
		}
	}
	// convert date arrays into date strings
	$start_time = mktime (0, 0, 0,
		$dateArr['mon'], $dateArr['mday'], $dateArr['year']);
	$end_time = mktime (0, 0, 0,
		$dateArr2['mon'], $dateArr2['mday'], $dateArr2['year']);
		
	if ($dateArr['mon'] == 0) {
		// We adjusted into last year
		$dateArr['year'] = ($dateArr['year'] - 1);
	}
	$ytd_start_time = mktime (0, 0, 0, 1, 1, $dateArr['year']);
	$end_month = $dateArr2['mon'];
	if ($dateArr2['mday'] == 0)
	{
		// When using left/right arrows, the month will be ahead by 1
		// because we are using day 0 of the following month.
		$end_month --;
		if ($end_month == 0) {
			// rollover to December
			$end_month = 12;
		}
	}

	$start_date	= date ('n/j/Y', $start_time);
	$budget_date = date('Y-m-d', $start_time);
	$end_date	= date ('n/j/Y', $end_time);
	$ytd_start_date = date('n/j/Y', $ytd_start_time);

	// build account dropdowns: include inactive, and only show top 2 tiers
	$account_list = Account::Get_account_list ($_SESSION['login_id'],
		'', -1, false, false, true, true);
	$account_dropdown = Build_dropdown ($account_list, 'account_id',
		$account_id);

	// Build main data list
	$account_list = array();
	$error = Account::Get_account_breakdown ($start_date, $end_date,
		$account_id, $account_list);

	$budget_list = array();
	if ($error == '')
	{
		// No error in first query; query the budgets
		$error = Account::Get_monthly_budget_list($account_id, $budget_date, $budget_list);
	}

	// Get year-to-date totals
	$ytd_account_list = array();
	if ($error == '')
	{
		$error = Account::Get_account_breakdown($ytd_start_date, $end_date,
			$account_id, $ytd_account_list);
	}
?>

	<script language="javascript" type="text/javascript">

		function bodyLoad()
		{
			document.forms[0].start_date.focus();
		}

	</script>
</head>


<body onload="bodyLoad()">
<?= $navbar ?>

<h3><?= $title ?></h3>

<span class="error"><?= $error ?></span>

<form action="account_breakdown.php" method="post">
<table>
	<tr>
		<td>From: </td>
		<td><input type="text" name="start_date" value="<?= $start_date ?>"></td>
		<td>To: </td> 
		<td>&nbsp;&nbsp;<input type="text" name="end_date" value="<?= $end_date ?>"></td>
		<td>&nbsp;&nbsp;<input type="submit" value="Update" name="update"></td>	<!-- Month direction arrows -->
	</tr>

	<tr>
		<td>Top Account: </td>
		<td colspan="2"><?= $account_dropdown ?></td>
		<td colspan="1">&nbsp;&nbsp;
			<input type="submit" value="&lt;" name="prev_month"> &nbsp;
			<input type="submit" value="&gt;" name="next_month"></td>
	</tr>
</table>
</form>

<table class="summary-list" cellspacing="0" cellpadding="0">
	<tr>
		<th>Account</th>
		<th style="text-align: center;">Amount</th>
		<th style="text-align: center;">Total %</th>
		<th style="text-align: center;">Budget</th>
		<th style="text-align: center;">Diff</th>
		<th style="text-align: center;">YTD Amount</th>
		<th style="text-align: center;">YTD Budget</th>
		<th style="text-align: center;">YTD Diff</th>

	</tr>

<?php
	// First loop through data: calculate totals
	$grand_total = 0.0;
	$abs_total = 0.0;
	$budget_total = 0.0;
	$diff_total = 0.0;
	$ytd_total = 0.0;
	$ytd_budget_total = 0.0;
	$ytd_diff_total = 0.0;

	// Need the abs_total to calculate row percentages
	foreach ($account_list as $account_data)
	{
		// account_list (account_name, account_total, account_id)
		$grand_total += $account_data[1];
		$abs_total += abs($account_data[1]);
	}

	// Second loop: display data
//	foreach ($account_list as $account_data)
	foreach ($budget_list as $account_id => $budget_data)
	{
		$monthly_amt = 0.0;
		$ytd_amt = 0.0;
		$budget_total += $budget_data[1];
		// YTD budget = budget * month
		$ytd_budget = $budget_data[1] * $end_month;
		$ytd_budget_total += $ytd_budget;

		if (array_key_exists($account_id, $account_list))
		{
			// account_list (account_name, account_total)
			$account_data = $account_list[$account_id];
			$monthly_amt = $account_data[1];
		}
		if (array_key_exists($account_id, $ytd_account_list))
		{
			$ytd_data = $ytd_account_list[$account_id];
			$ytd_amt = $ytd_data[1];
			$ytd_total += $ytd_amt;
		}
		
		$monthly_diff = $budget_data[1] - $monthly_amt;
		$ytd_diff = $ytd_budget - $ytd_amt;
		$total_pct = $monthly_amt / $abs_total * 100.0;

		$diff_str = '$' . number_format ($monthly_diff, 2);
		if ($monthly_diff < -0.001)
			$diff_str = "<span style='color: red;'>$diff_str</span>";
		$ytd_diff_str = '$' . number_format ($ytd_diff, 2);
		if ($ytd_diff < -0.001)
			$ytd_diff_str = "<span style='color: red;'>$ytd_diff_str</span>";

		$monthly_str = '$' . number_format ($monthly_amt, 2);
		$pct_str = number_format ($total_pct, 1) . '%';
		$budget_str = '$' . number_format( $budget_data[1], 2 );
		$ytd_str = '$' . number_format( $ytd_amt, 2 );
		$ytd_budget_str = '$' . number_format ($ytd_budget, 2);
		echo "	<tr> \n".
			"		<td>$budget_data[0]</td> \n".
			"		<td style='text-align: right;'>$monthly_str</td> \n".
			"		<td style='text-align: right;'>$pct_str</td> \n".
			"		<td style='text-align: right;'>$budget_str</td> \n".
			"		<td style='text-align: right;'>$diff_str</td> \n".
			"		<td style='text-align: right;'>$ytd_str</td> \n".
			"		<td style='text-align: right;'>$ytd_budget_str</td> \n".
			"		<td style='text-align: right;'>$ytd_diff_str</td> \n".
			"	</tr> \n\n" ;
	}	// End budget loop

	$diff_total = $budget_total - $grand_total;
	$ytd_diff_total = $ytd_budget_total - $ytd_total;

	$grand_str = '$'. number_format ($grand_total, 2);
	$budget_total_str = '$'. number_format ($budget_total, 2);
	$diff_total_str = '$'. number_format ($diff_total, 2);

	$ytd_total_str = '$' . number_format ($ytd_total, 2);
	$ytd_budget_total_str = '$'. number_format($ytd_budget_total, 2);
	$ytd_diff_total_str = '$'. number_format($ytd_diff_total, 2);

	echo "	<tr> \n".
		"		<td style='border-top: 1px solid black; border-bottom: 1px solid black;' ".
		" colspan=\"8\">&nbsp;</td> \n".
		"	</tr> \n\n".
		"	<tr> \n".
		"		<td>Period total</td> \n".
		"		<td style='text-align: right; font-weight: bold;'>$grand_str</td> \n".
		"		<td style='text-align: right; font-weight: bold;'>&nbsp;</td> \n".
		"		<td style='text-align: right; font-weight: bold;'>$budget_total_str</td> \n".
		"		<td style='text-align: right; font-weight: bold;'>$diff_total_str</td> \n".
		"		<td style='text-align: right; font-weight: bold;'>$ytd_total_str</td> \n".
		"		<td style='text-align: right; font-weight: bold;'>$ytd_budget_total_str</td> \n".
		"		<td style='text-align: right; font-weight: bold;'>$ytd_diff_total_str</td> \n".
		"	</tr> \n\n" ;

?>


</table>
<?php require('footer.php'); ?>

</body>
</html>
