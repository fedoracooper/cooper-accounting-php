<?
	require('include.php');
	if (!isset ($_SESSION['login_id']))
	{
		// redirect to login if they have not logged in
		header ("Location: login.php");
	}

	$error = '';
	// set default vars
	$start_date = date ('m/1/Y');	//first of current month
	$dateArr = getdate ();
	// add 1 month & subtract one day
	$endTime = mktime (0, 0, 0, $dateArr['mon'] + 1, 0, $dateArr['year']);
	$end_date = date ('m/d/Y', $endTime);
	$account_id = $_SESSION['default_summary2'];
	

	if (isset ($_POST['calc']))
	{
		// Verify

		$start_date	= $_POST['start_date'];
		$end_date	= $_POST['end_date'];
		$account_id	= $_POST['account_id'];
	}

	// build account dropdowns: include inactive, and only show top 2 tiers
	$account_list = Account::Get_account_list ($_SESSION['login_id'],
		'', -1, false, false, true, true);
	$account_dropdown = Build_dropdown ($account_list, 'account_id',
		$account_id);

	// Build main data list
	$account_list = array();
	$error = Account::Get_account_breakdown ($start_date, $end_date,
		$account_id, $account_list);
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
<table>
	<tr>
		<td><h3>Account Breakdown</h3>
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
		<td style="padding-left: 13px;"><a href="account_summary.php">Account Summary</a></td>
	</tr>
</table>

<span class="error"><?= $error ?></span>

<form action="account_breakdown.php" method="post">
<table>
	<tr>
		<td>From: </td>
		<td><input type="text" name="start_date" value="<?= $start_date ?>"></td>
		<td>To: </td> 
		<td>&nbsp;&nbsp;<input type="text" name="end_date" value="<?= $end_date ?>"></td>
		<td>&nbsp;&nbsp;<input type="submit" value="Update" name="calc"></td>	<!-- Month direction arrows -->
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
		<th>Total Amount</th>
		<th>Total Percent</th>
	</tr>

<?
	// First loop through data: calculate totals
	$grand_total = 0.0;
	foreach ($account_list as $account_data)
	{
		// account_list (account_name, account_total)
		$grand_total += $account_data[1];
	}
	$grand_str = number_format ($grand_total, 2);


	// Second loop: display data
	foreach ($account_list as $account_data)
	{
		// account_list (account_name, account_total)
		$total_amt = $account_data[1];
		$total_str = number_format ($total_amt, 2);
		$total_pct = $total_amt / $grand_total * 100.0;
		$pct_str = number_format ($total_pct, 1);
		echo "	<tr> \n".
			"		<td>$account_data[0]</td> \n".
			"		<td style='text-align: right;'>\$$total_str</td> \n".
			"		<td style='text-align: right;'>$pct_str%</td> \n".
			"	</tr> \n\n" ;
	}

	echo "	<tr> \n".
		"		<td style='border-top: 1px solid black; border-bottom: 1px solid black;' ".
		" colspan=\"3\">&nbsp;</td> \n".
		"	</tr> \n\n".
		"	<tr> \n".
		"		<td>Period total</td> \n".
		"		<td style='text-align: right; font-weight: bold;'>\$$grand_str</td> \n".
		"	</tr> \n\n" ;

?>


</table>

</body>
</html>