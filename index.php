<?
	require('include.php');
	if (!isset ($_SESSION['login_id']))
	{
		// redirect to login if they have not logged in
		header ("Location: login.php");
	}

	$error = '';

	// Default values
	$sel_account_id = $_SESSION['default_account_id'];	// default account selection
	// default date range to the whole current month
	$dateArr = getdate ();
	// add 1 month & subtract one day
	$endTime = mktime (0, 0, 0, $dateArr['mon'] + 1, 0, $dateArr['year']);
	$start_date = "$dateArr[mon]/1/$dateArr[year]";
	$next_month = ($dateArr['mon'] % 12) + 1;
	$end_date = date ('n/j/Y', $endTime);
	$limit = 0;
	$total_period = 'month';
	$trans = new Transaction();
	$transL_ledgers = array();
	$transR_ledgers = array();
	
	if (isset ($_POST['sel_account_id']))
	{
		// The form has already been submitted; get filter vars
		$sel_account_id	= $_POST['sel_account_id'];
		$start_date		= $_POST['start_date'];
		$end_date		= $_POST['end_date'];
		$limit			= $_POST['limit'];
		$total_period	= $_POST['total_period'];
	}
	if (isset ($_POST['edit']))
	{
		// Loading a transaction & ledger entries from database.
		$trans->Load_transaction($_POST['edit']);
	}

	// Build the account list dropdown
	$acct_list = Account::Get_account_list ($_SESSION['login_id']);
	$acct_dropdown = Build_dropdown ($acct_list, 'sel_account_id',
		$sel_account_id);

	// Total period dropdown
	// Note: this does not affect Assets or Liabilities (LHS accounts)
	$period_list = array (
		'month'		=> 'Monthly',		// start @ 1st of month (ignore start_date)
		'year'		=> 'Yearly',		// start @ 1st of year (ignore end_date)
		'visible'	=> 'Visible dates'	// total for the entered period
	);
	$period_dropdown = Build_dropdown ($period_list, 'total_period',
		$total_period);

	$mode = '';
	if (isset ($_POST['save']))
		$mode = 'save';
	elseif (isset ($_POST['delete']))
		$mode = 'delete';

	// Save or Delete
	if ($mode != '')
	{
		// Build the ledger lists
		$ledgerL_list = array();
		$ledgerR_list = array();
		for ($i=0; $i<10; $i++)
		{
			if ($i < 5 &&
				($_POST['amountL'][$i] != '' || $_POST['ledgerL_id'][$i] > -1))
			{
				// User entered an amount or deleted an amount
				//echo "amount: ". $_POST['amountL'][$i];
				$subarr = array (
					$_POST['ledgerL_id'][$i],
					$_POST['accountL_id'][$i],
					$_POST['amountL'][$i]
				);
				$ledgerL_list[] = $subarr;
			}
			if ($_POST['amountR'][$i] != ''
				|| $_POST['ledgerR_id'][$i] > -1)
			{
				$subarr = array (
					$_POST['ledgerR_id'][$i],
					$_POST['accountR_id'][$i],
					$_POST['amountR'][$i]
				);
				$ledgerR_list[] = $subarr;
			}
		}
		//nl2br (print_r ($ledgerL_list));
		//nl2br (print_r ($ledgerR_list));
		$error = $trans->Init_transaction (
			$_SESSION['login_id'],
			$_POST['trans_descr'],
			$_POST['trans_date'],
			$_POST['accounting_date'],
			$_POST['trans_vendor'],
			$_POST['trans_comment'],
			$_POST['check_number'],
			$_POST['gas_miles'],
			$_POST['gas_gallons'],
			$_POST['trans_id'],
			'',		//account display
			NULL,	//ledger amt
			$ledgerL_list,
			$ledgerR_list
		);

		if ($error == '')
		{
			if ($mode == 'save')
				$error = $trans->Save_transaction();
			else
				$error = $trans->Delete_transaction();
		}

		if ($error == '')
		{
			//successful save; reset for a new transaction
			$trans = new Transaction ();
		}
	}

	$error1 = $error;
	// Build the transaction list
	$trans_list = Transaction::Get_transaction_list ($sel_account_id,
		$start_date, $end_date, $limit, $total_period, $error);
	if ($error1 != '')
		// an error occurred before calling transaction list
		$error = $error1;
?>


<html>
<head>
	<title>Account Ledger</title>
	<link href="style.css" rel="stylesheet" type="text/css">
	<script language="javascript" type="text/javascript">

		function confirmDelete()
		{
			return confirm('Are you sure you want to delete the '
				+ 'current transaction?');
		}

	</script>
</head>


<body>
<table>
	<tr>
		<td><h3>Account Ledger</h3>
		<td style="padding-left: 300px;">
			<a href="login.php?logout=1">Logout <?= $_SESSION['display_name'] ?></a></td>
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

<form method="post" action="index.php">
<table>

	<tr>
		<td><?= $acct_dropdown ?></td>
		<td>From: </td>
		<td><input type="text" size="10" maxlength="10" name="start_date" value="<?= $start_date ?>"></td>
		<td>To: </td>
		<td><input type="text" size="10" maxlength="10" name="end_date" value="<?= $end_date ?>"></td>
	</tr>
	<tr>
		<td>Rev. period: <?= $period_dropdown ?></td>
		<td>Limit: </td>
		<td><input type="text" size="3" maxlength="3" name="limit" value="<?= $limit ?>"></td>
		<td></td>
		<td><input type="submit" name="filter" value="Filter transactions"></td>
	</tr>

	<tr>
		<td colspan="5"><hr></td>
	</tr>
</table>

<table class="trans-table">
	<tr>
		<th>Edit</th>
		<th>Date</th>
		<th>Description</th>
		<th>Vendor</th>
		<th>Account</th>
		<th style="text-align: right;">Amount</th>
		<th style="text-align: right; padding-right: 5px;">Total</th>
	</tr>

	<tr>
		<td colspan="7"><hr></td>
	</tr>

<?
	$last_trans_id = -1;
	// Loop through each transaction in the list
	foreach ($trans_list as $trans_item)
	{
		$new_row = false;
		if ($trans_item->get_trans_id() != $last_trans_id)
		{
			//new transaction
			$new_row = true;
		}
		echo "	<tr>\n";
		if ($new_row) {
			echo '		<td><input type="submit" style="height: 18px; font-size: 8pt;" name="edit" value="'.
				$trans_item->get_trans_id(). "\"></td> \n".
			"		<td>". $trans_item->get_trans_date(). "</td>\n".
			'		<td>'. $trans_item->get_trans_descr(). "</td>\n".
			"		<td>". $trans_item->get_trans_vendor(). "</td>\n";
		}
		else {
			echo "		<td></td> \n".
				"		<td></td> \n".
				"		<td></td> \n".
				"		<td></td> \n";
		}
		echo "		<td>". $trans_item->get_account_display(). "</td>\n".
			"		<td class=\"currency\">". $trans_item->get_ledger_amount(). "</td>\n".
			"		<td class=\"currency\">". $trans_item->get_ledger_total(). "</td>\n".
			"	</tr>\n\n";

		$last_trans_id = $trans_item->get_trans_id();
	}
?>
	<tr>
		<td colspan="7"><hr></td>
	</tr>

</table>

<table class="transaction">
	<tr>
		<td colspan="4" style="font-weight: bold;"><?
			if ($trans->get_trans_id() < 0)
				echo "New Transaction";
			else
				echo "Edit Transaction (". $trans->get_trans_id(). ")";
			?></td>
	</tr>

	<tr>
		<td>Date:</td>
		<td><input type="hidden" name="trans_id" value="<?=
				$trans->get_trans_id() ?>">
			<input type="text" size="10" maxlength="10" name="trans_date"
			value="<?= $trans->get_trans_date() ?>"></td>
		<td>Accounting date:</td>
		<td><input type="text" size="10" maxlength="10" name="accounting_date"
			value="<?= $trans->get_accounting_date() ?>"></td>
		<td>Check #:</td>
		<td><input type="text" size="4" maxlength="4" name="check_number"
			value="<?= $trans->get_check_number() ?>"></td>
		<td>Miles:</td>
		<td><input type="text" size="6" maxlength="6" name="gas_miles"
			value="<?= $trans->get_gas_miles() ?>"></td>
		<td>Gallons:</td>
		<td><input type="text" size="5" maxlength="5" name="gas_gallons"
			value="<?= $trans->get_gas_gallons() ?>"></td>
	</tr>

	<tr>
		<td>Description:</td>
		<td colspan="3"><input type="text" size="50" maxlength="50" name="trans_descr"
			value="<?= $trans->get_trans_descr() ?>"></td>
		<td>Vendor:</td>
		<td colspan="5"><input type="text" size="50" maxlength="50" name="trans_vendor"
			value="<?= $trans->get_trans_vendor() ?>"></td>
	</tr>

	<tr>
		<td>Comment:</td>
		<td colspan="9"><textarea name="trans_comment" rows="2" cols="80"
			><?= $trans->get_trans_comment() ?></textarea></td>
	</tr>
</table>

<table style="border: 1px solid black;">
	<tr>
		<th>LHS Accounts</th>
		<th>LHS $</th>
		<th>RHS Accounts</th>
		<th>RHS $</th>
		<th>RHS Accounts</th>
		<th>RHS $</th>
	</tr>

<?
	// Transaction dropdowns (account_id,account_debit as the key)
	$acctL_list = Account::Get_account_list ($_SESSION['login_id'], 'L',
		-1, false, true);
	$acctL_list = array ('-1' => '--Select--') + $acctL_list;

	$acctR_list = Account::Get_account_list ($_SESSION['login_id'], 'R',
		-1, false, true);
	$acctR_list = array ('-1' => '--Select--') + $acctR_list;

	// Get all the values from the LHS & RHS arrays
	// 0=ledger_id, 1=account_id/account_debit, 2=amount
	$j = 0;
	$listL = $trans->get_ledgerL_list();
	$listR = $trans->get_ledgerR_list();

	// create 5 rows of ledger adjustments (1 LHS column, 2 RHS columns)
	for ($i=0; $i<5; $i++)
	{
		// LHS
		$subarr = ArrVal ($listL, $i);
		echo "	<tr>\n".
			'		<td><input type="hidden" name="ledgerL_id[]"'.
				" value=\"$subarr[0]\"> \n";
		// Build account dropdown
		$acct_drop = Build_dropdown ($acctL_list, 'accountL_id[]',
			$subarr[1]);
		echo $acct_drop. "</td>\n".
			"		<td><input type=\"text\" size=\"8\" maxlength=\"10\" ".
			"name=\"amountL[]\" value=\"$subarr[2]\">&nbsp;&nbsp;&nbsp;</td> \n";

		//RHS
		for ($j=0; $j<=1; $j++)
		{
			// Get the subarray at index i & i+5
			$subarr = ArrVal ($listR, $i + (5* $j));
			echo "		<td><input type=\"hidden\" name=\"ledgerR_id[]\"".
					" value=\"$subarr[0]\"> \n";
			// Build account dropdown
			$acct_drop = Build_dropdown ($acctR_list, 'accountR_id[]',
				$subarr[1]);
			echo $acct_drop. "&nbsp;&nbsp;</td>\n".
				"		<td><input type=\"text\" size=\"8\" maxlength=\"10\" ".
				"name=\"amountR[]\" value=\"$subarr[2]\"></td> \n";
		}

		echo "	</tr> \n\n";
	}
?>
</table>
		
<table>
	<tr>
		<td style="width: 50px;">&nbsp;</td>
		<td style="padding-right: 25px;"><input type="submit" name="save" value="Save transaction"></td>
<?
	if ($trans->get_trans_id() > -1)
	{
		// currently editing; show delete button
		echo '<td><input type="submit" name="delete" '.
			'onClick="return confirmDelete()" value="Delete transaction"></td>';
	}
?>
	</tr>

</table>




</form>
</body>
</html>