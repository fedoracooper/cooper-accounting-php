<?
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
	$start_date	= date ('n/j/Y', $start_time);
	$end_date	= date ('n/j/Y', $end_time);


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
		$error = Account::Get_monthly_budget_list($account_id, $budget_list);
	}
?>

<html>
<head>
	<title>Account Breakdown</title>
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

<h3>Account Breakdown</h3>

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
		<td colspan="1">&nbsp;&nbsp;<input type="submit" value="<" name="prev_month"> &nbsp;
		<input type="submit" value=">" name="next_month"></td>
	</tr>
</table>
</form>

<table class="summary-list" cellspacing="0" cellpadding="0">
	<tr>
		<th>Account</th>
		<th style="text-align: right;">Amount</th>
		<th style="text-align: right;">Total %</th>
		<th style="text-align: right;">Budget</th>
		<th style="text-align: right;">Diff</th>
	</tr>

<?
	// First loop through data: calculate totals
	$grand_total = 0.0;
	$abs_total = 0.0;
	$budget_total = 0.0;
	$diff_total = 0.0;

	foreach ($account_list as $account_data)
	{
		// account_list (account_name, account_total, account_id)
		$grand_total += $account_data[1];
		$abs_total += abs($account_data[1]);
		$budget_total += $budget_list[$account_data[2]][1];
	}
	$diff_total = $budget_total - $grand_total;
	$diff_total_str = number_format ($diff_total, 2);

	$grand_str = number_format ($grand_total, 2);
	$budget_total_str = number_format ($budget_total, 2);

	// Second loop: display data
//	foreach ($account_list as $account_data)
	foreach ($budget_list as $account_id => $budget_data)
	{
		$account_data = null;
		$monthly_amt = 0.0;

		if (array_key_exists($account_id, $account_list))
		{
			// account_list (account_name, account_total)
			$account_data = $account_list[$account_id];
			$monthly_amt = $account_data[1];
		}
		
		$monthly_diff = $budget_data[1] - $monthly_amt;
		$diff_str = '$' . number_format ($monthly_diff, 2);
		if ($monthly_diff < -0.001)
			$diff_str = "<span style='color: red;'>$diff_str</span>";
		$monthly_str = number_format ($monthly_amt, 2);
		$total_pct = $monthly_amt / $abs_total * 100.0;
		$pct_str = number_format ($total_pct, 1);
		$budget_str = number_format( $budget_data[1], 2 );
		echo "	<tr> \n".
			"		<td>$budget_data[0]</td> \n".
			"		<td style='text-align: right;'>\$$monthly_str</td> \n".
			"		<td style='text-align: right;'>$pct_str%</td> \n".
			"		<td style='text-align: right;'>\$$budget_str</td> \n".
			"		<td style='text-align: right;'>$diff_str</td> \n".
			"	</tr> \n\n" ;
	}

	echo "	<tr> \n".
		"		<td style='border-top: 1px solid black; border-bottom: 1px solid black;' ".
		" colspan=\"5\">&nbsp;</td> \n".
		"	</tr> \n\n".
		"	<tr> \n".
		"		<td>Period total</td> \n".
		"		<td style='text-align: right; font-weight: bold;'>\$$grand_str</td> \n".
		"		<td style='text-align: right; font-weight: bold;'>&nbsp;</td> \n".
		"		<td style='text-align: right; font-weight: bold;'>\$$budget_total_str</td> \n".
		"		<td style='text-align: right; font-weight: bold;'>\$$diff_total_str</td> \n".
		"	</tr> \n\n" ;

?>


</table>

</body>
</html>
