<?
	$current_page = 'index';
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
	$dateArr['mday'] = 1;	// first day of current month
	$dateArr2 = getdate();
	$dateArr2['mon'] ++;
	$dateArr2['mday'] = 0;	// last day of previous month

	$search_text = '';
	$limit = 0;
	$total_period = 'month';
	$trans = new Transaction();
	$transL_ledgers = array();
	$transR_ledgers = array();
	$editClick = 0;
	
	if (isset ($_POST['sel_account_id']))
	{
		// The form has already been submitted; get filter vars
		$sel_account_id	= $_POST['sel_account_id'];
		$dateArr	= getdate (strtotime ($_POST['start_date']));
		$dateArr2	= getdate (strtotime ($_POST['end_date']));
		$limit			= $_POST['limit'];
		$total_period	= $_POST['total_period'];
		$search_text	= $_POST['search_text'];

		if (isset ($_POST['prev_month']))
		{
			// go to last whole month
			$dateArr['mon'] --;
			$dateArr['mday'] = 1;
			$dateArr2['mday'] = 0;
		}
		elseif (isset ($_POST['next_month']))
		{
			// next whole month
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

	if (isset ($_POST['edit']))
	{
		// Loading a transaction & ledger entries from database.
		$trans->Load_transaction($_POST['edit']);
		$editClick = 1;		// used to set a form var for javascript
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
			$_POST['trans_status'],
			$_POST['trans_id'],
			$_POST['repeat_count'],
			'',		//account display
			NULL,	//ledger amt
			-1,		//ledger ID
			-1,		//audit ID
			0.0,	//audit balance
			$ledgerL_list,
			$ledgerR_list
		);

		if ($error == '')
		{
			if ($mode == 'save')
				$error = $trans->Save_repeat_transactions();
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
		$start_date, $end_date, $limit, $search_text, $total_period, $error);
	// Strip slashes from search_text variable
	$search_text = stripslashes( $search_text );
	$sel_account = new Account ();
	$sel_account->Load_account ($sel_account_id);
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

		function clickEdit()
		{
			document.forms[0].editClick = 1;
		}

		function bodyLoad()
		{
			if (document.forms[0].editClick.value == "1")
			{
				document.forms[0].trans_date.focus();
				document.forms[0].trans_date.select();
			}
		}

		function auditAccount( ledger_id, account_balance )
		{
			// Handle a click of an account total; popup a new window
			window.open( 'audit.php?ledger_id=' + ledger_id
				+ '&account_total=' + account_balance, 'audit',
				'toolbar=no,height=250,width=630');
		}

		function editAudit( audit_id )
		{
			// Handle a click of an account total; popup a new window
			window.open( 'audit.php?audit_id=' + audit_id, 'audit',
				'toolbar=no,height=250,width=630');
		}

	</script>
</head>


<body onload="bodyLoad()">
<?= $navbar ?>

<table style="margin-top: 5px;">
	<tr>
		<td><h3>Account Ledger: <?= $sel_account->get_account_name() ?></h3></td>
		<td style="padding-left: 30px;"><?= $sel_account->get_account_descr() ?></td>
	</tr>
</table>

<?
	if ($error != '')
		echo	"<p class=\"error\">$error</p> \n";
?>

<form method="post" action="index.php">
<input type="hidden" name="editClick" value="<?= $editClick ?>">
<table>

	<tr>
		<td><?= $acct_dropdown ?></td>
		<td>From: </td>
		<td><input type="text" size="10" maxlength="10" name="start_date" value="<?= $start_date ?>"></td>
		<td>To: </td>
		<td><input type="text" size="10" maxlength="10" name="end_date" value="<?= $end_date ?>"></td>
		<td>Search: </td>
		<td><input type="text" size="10" maxlength="20" name="search_text"
			value="<?= $search_text ?>"></td>
		<td style="padding-left: 10px;"><input type="submit" name="filter" value="Filter transactions"></td>
	</tr>
	<tr>
		<td>Rev. period: <?= $period_dropdown ?></td>
		<td>Limit: </td>
		<td><input type="text" size="3" maxlength="3" name="limit" value="<?= $limit ?>"></td>
		<td></td>
		<td colspan="2"><input type="submit" value="<" name="prev_month"> &nbsp;
		<input type="submit" value=">" name="next_month"></td>
	</tr>
<!--
	<tr>
		<td colspan="6"><hr></td>
	</tr>	-->
</table>

<table class="trans-table" cellpadding="0" cellspacing="0" style="">
	<tr>
		<th>Edit</th>
		<th>Date</th>
		<th>Description</th>
		<th>Vendor</th>
		<th>Account</th>
		<th>Other</th>
		<th style="text-align: right;">Amount</th>
		<th style="text-align: right; padding-right: 5px; border-right: 1px solid black;">Total</th>
		<th style="text-align: center;">Per.</th>
	</tr>
<!--
	<tr>
		<td colspan="8"><hr></td>
	</tr>
-->

<?
	$last_trans_id = -1;
	$next_trans = NULL;
	$tr_style = '';

	// Loop through each transaction in the list
	foreach ($trans_list as $key=> $trans_item)
	{
		$new_row = false;
		$td_style = ' style="border-right: 1px solid black;"';
		$new_text = '<td></td>';
		$hr_text = '';

		if ($key -1 >= 0)	// index keys are in reverse order
			// in range: get next account
			$next_trans = $trans_list[$key - 1];
		else
			$next_trans = NULL;		// last row

		if ($trans_item->get_trans_id() != $last_trans_id)
		{
			//new transaction
			$new_row = true;
		}

		
		if ($new_row)
		{
			// change the row style
			if ($trans_item->get_trans_status() == 0)
			{
				// unpaid ledger item: show the row in red
				$tr_style = ' style="background-color: #FF8888;"';
			}
			elseif ($tr_style == '') { // even rows get a different color
				$tr_style = ' style="background-color: #F7CB9F;"';	//#FBAF79"';
			}
			else{
				$tr_style = '';
			}
		}

		if ($next_trans === NULL)
			// Add a day to the current end date (25 hrs for Daylight Savings)
			$time2 = (strtotime ($end_date) + 60*60*25);
		else
			$time2 = strtotime ($next_trans->get_accounting_date(false));

		if ($last_trans_id != -1 || true)	// always true now
		{
			$time1 = strtotime ($trans_item->get_accounting_date(false));
			
			if (date ('m/Y', $time1) != date ('m/Y', $time2))
			{
				// month or year change: add a break
				//$td_style = '';	//' style="border: 1px solid black"';
				$new_text = '<td style="padding-left: 10px;">'.
					substr (date ('F', $time1), 0, 3). '</td>';
			}
			if (date ('Y', $time1) != date ('Y', $time2))
			{
				// New year
				$hr_text = "	<tr style=\"\">\n".
					"		<td colspan=\"9\"><hr></td>\n".
					"	</tr>\n\n";
				$new_text = '<td style="padding-left: 10px; '.
					'padding-right:10px; font-weight: bold; '.
					'border-top: 1px solid black; border-bottom: 1px solid black;">'.
					date ('Y', $time1). '</td>';
				$td_style = ' style="font-weight: bold; border-right: 1px solid black;"';
			}
			elseif ($next_trans !== NULL)
			{
				// no EOY break; insert break after current date
				$trans_time = strtotime ($trans_item->get_accounting_date_sql());
				$next_time = strtotime ($next_trans->get_accounting_date_sql());
				if ($next_time > time() && $trans_time < time())
				{
					$hr_text = "	<tr>\n".
					'		<td style="border-right: 1px solid black; border-bottom: 1px solid black; border-top: 1px solid black; text-align: center;" '.
						"colspan=\"8\">&nbsp;</td>\n".
					'		<td style="border-bottom: 1px solid black; padding-left: 5px;">Tod.</td>'.
					"	</tr>\n\n";
				}
			}
		}

		// if miles, gallons, or check # is recorded, display it
		$other = '';
		$miles = $trans_item->get_gas_miles(false);	 //get numeric form
		$gall = $trans_item->get_gas_gallons();
		if ($trans_item->get_check_number() != '') {
			$other = 'chk #'. $trans_item->get_check_number();
		}
		elseif ($miles != '' && $gall != '') {
			$mpg = round ((float)$miles / (float)$gall, 1);
			$other = sprintf ("%0.1f", $mpg) . ' mpg';
		}
		elseif ($miles != '') {
			$other = $trans_item->get_gas_miles(true). ' mi';
		}
		elseif ($gall != '') {
			$other = $gall. ' gal';
		}

		echo "	<tr$tr_style>\n";
		if ($new_row) {
			echo '		<td style="width: 40px;"><input type="submit" style="height: 18px; '.
				'font-size: 8pt;" onClick="clickEdit()" name="edit" value="'.
				$trans_item->get_trans_id(). "\"></td> \n".
			"		<td style=\"width: 60px;\">".
				$trans_item->get_accounting_date(false, true). "</td>\n".
			"		<td>". $trans_item->get_trans_descr(). "</td>\n".
			"		<td>". $trans_item->get_trans_vendor(). "</td>\n";
		}
		else {
			echo "		<td></td> \n".
				"		<td></td> \n".
				"		<td></td> \n".
				"		<td></td> \n";
		}

		// Onclick handler will open an Audit screen; need to pass
		// in the ledger ID and account total.  Note that currently only
		// LHS accounts may be audited, as these totals are always accurate,
		// not based on period.
		$onclick = '';
		$totalStyle = '';
		$auditAnchor = '';
		$closeAnchor = '';
		$auditTitle = '';
		if ($sel_account->get_equation_side() == 'L')
		{
			$onclick = "auditAccount( ". $trans_item->get_ledger_id().
				", ". $trans_item->get_ledger_total( true ) . ");";
			$auditTitle = "Audit this account balance...";
		}
		if ($trans_item->get_audit_balance() > 0.0)
		{
			// We have an audited record here.
			$onclick = "editAudit( ". $trans_item->get_audit_id() . ");";

			$diff = $trans_item->get_audit_balance()
				- $trans_item->get_ledger_total( true );
			$totalStyle = "font-weight: bold;";
			if (abs( $diff ) > .001)
			{
				// Audit failed
				$totalStyle .= " color: red;";
				$auditTitle = "Account balance audit failed. ".
					"Expected $this->get_ledger_total() ";
			}
			else
			{
				$auditTitle = "This account balance has been audited ".
					"and is accurate.";
			}
		}
		if ($onclick)
		{
			// Need to use an anchor for auditing
			$auditAnchor = "<a href='#' title='$auditTitle' onclick='$onclick' ".
				"style='$totalStyle'>";
			$closeAnchor = "</a>";
		}

		echo "		<td>". $trans_item->get_account_display(). "</td>\n".
			"		<td>$other</td> \n".
			"		<td class=\"currency\">". $trans_item->get_ledger_amount(). "</td>\n".
			"		<td$td_style class=\"currency\">$auditAnchor".
			$trans_item->get_ledger_total(). "$closeAnchor</td>\n".
			"		$new_text\n".
			"	</tr>\n\n";

		echo $hr_text;

		$last_trans_id = $trans_item->get_trans_id();
	}

	// Build Transaction list dropdown
	$status_list = array (1=> 'Fulfilled', 0=> 'To-do');
	$status_dropdown = Build_dropdown ($status_list, 'trans_status',
		$trans->get_trans_status());
?>
<!--
	<tr>
		<td colspan="8"><hr></td>
	</tr>	-->

</table>

<table class="transaction">
	<tr>
		<td colspan="2" style="font-weight: bold;">
			<a name="edit_trans"><?
			if ($trans->get_trans_id() < 0)
				echo "New Transaction";
			else
				echo "Edit Transaction (". $trans->get_trans_id(). ")";
			?></a></td>
		<td colspan="1"><?= $status_dropdown ?></td>
		<td colspan="2">Repeat months:</td>
		<td colspan="2"><input type="text" size="2" maxlength="2" name="repeat_count"
			value="<?= $trans->get_repeat_count() ?>"></td>
		<td class="info" colspan="3"><?
			if ($trans->get_trans_id() >= 0)
				echo "last modified " . $trans->get_updated_time() ?></td>
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
		<td><input type="text" size="7" maxlength="7" name="gas_miles"
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
	$show_inactive = 0;
	// only show inactive accounts when editing
	if ($editClick == 1)
		$show_inactive = 1;
	// Transaction dropdowns (account_id,account_debit as the key)
	$acctL_list = Account::Get_account_list ($_SESSION['login_id'], 'L',
		-1, false, true, $show_inactive);
	$acctL_list = array ('-1' => '--Select--') + $acctL_list;

	$acctR_list = Account::Get_account_list ($_SESSION['login_id'], 'R',
		-1, false, true, $show_inactive);
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
		echo '<td style="padding-right: 25px;"><input type="submit" name="delete" '.
			"onClick=\"return confirmDelete()\" value=\"Delete transaction\"></td>\n".
			"<td><input type=\"submit\" value=\"Cancel\" name=\"cancel\"></td>\n";
	}
?>
	</tr>

</table>




</form>
</body>
</html>
