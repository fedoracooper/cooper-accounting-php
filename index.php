<?php
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
	//$dateArr = getdate ();
	//$dateArr['mday'] = 1;	// first day of current month
	//$dateArr2 = getdate();
	//$dateArr2['mon'] ++;
	//$dateArr2['mday'] = 0;	// last day of previous month

	// Get last x days + x days forward
	$days = 5 * 24 * 60 * 60;
	$dateArr = getdate(time() - $days);
	$dateArr2 = getdate(time() + $days);

	$search_text = '';
	$limit = 0;
	$total_period = 'month';
	$trans = new Transaction();
	$transL_ledgers = array();
	$transR_ledgers = array();
	$editClick = 0;
	
	// If the filter form was submitted, update session vars
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
			// Back 30 days
			$dateArr['mday'] -= 30;
			$dateArr2['mday'] -= 30;
		}
		elseif (isset ($_POST['next_month']))
		{
			// Plus 30 days
			$dateArr['mday'] += 30;
			$dateArr2['mday'] += 30;
		}

		// Set session vars
		$_SESSION['sel_account_id'] = $sel_account_id;
		$_SESSION['dateArr'] = $dateArr;
		$_SESSION['dateArr2'] = $dateArr2;
		$_SESSION['limit'] = $limit;
		$_SESSION['total_period'] = $total_period;
		$_SESSION['search_text'] = $search_text;
	}
	// Otherwise, try to retrieve previous selections from session vars
	elseif (isset ($_SESSION['sel_account_id']))
	{
		$sel_account_id = $_SESSION['sel_account_id'];
		$dateArr		= $_SESSION['dateArr'];
		$dateArr2		= $_SESSION['dateArr2'];
		$limit			= $_SESSION['limit'];
		$total_period	= $_SESSION['total_period'];
		$search_text	= $_SESSION['search_text'];
	}

	// convert date arrays into date strings
	$start_time = mktime (0, 0, 0,
		$dateArr['mon'], $dateArr['mday'], $dateArr['year']);
	$end_time = mktime (0, 0, 0,
		$dateArr2['mon'], $dateArr2['mday'], $dateArr2['year']);
	$start_date	= date (SQL_DATE, $start_time);
	$end_date	= date (SQL_DATE, $end_time);

	$excludeBudgetCheck = '';
	$priorMonthCheck = '';
	if (isset ($_POST['edit']))
	{
		// Loading a transaction & ledger entries from database.
		$error = $trans->Load_transaction($_POST['edit']);
		$editClick = 1;		// used to set a form var for javascript
		$excludeBudgetCheck = get_checked($trans->get_exclude_budget());
		$priorMonthCheck = get_checked($trans->get_prior_month());
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
		
	$deleteLedgerIdArray = array();

	// Save or Delete
	if ($mode != '')
	{
		// Build the ledger lists
		$ledger_list = array();
		$numRows = count($_POST['account_id']);
		
		for ($i=0; $i < $numRows; $i++)
		{
			$ledger = new LedgerEntry();
			$ledgerIdRaw = $_POST['ledger_id'][$i];
			// If ledgerId is blank, convert to -1 for downstream processing.
			$ledger->ledgerId = is_numeric($ledgerIdRaw) ? $ledgerIdRaw : -1;
			$ledger->memo = $_POST['ledger_memo'][$i];
			$ledger->setAccountData($_POST['account_id'][$i]);
			$ledger->debitAmount = $_POST['amountDebit'][$i];
			$ledger->creditAmount = $_POST['amountCredit'][$i];
			$ledger_list[] = $ledger;
		}	// End Ledger Entry for loop
		
		if (isset($_POST['delete_ledger_id'])) {
			// Loop through deleted ledger entry rows
			$deleteLedgerIdArray = $_POST['delete_ledger_id'];
			for ($i = 0; $i < count($deleteLedgerIdArray); $i++) {
				// Deletion is detected by a ledger ID with amount set to empty string
				$ledger = new LedgerEntry();
				$ledger->toDelete = true;
				$ledger->ledgerId = $deleteLedgerIdArray[$i];
				$ledger_list[] = $ledger;
			}
		}
		
		//nl2br (print_r ($ledgerL_list));
		//nl2br (print_r ($ledgerR_list));
		$excludeBudget = 0;
		$priorMonth = 0;
		if (isset($_POST['prior_month'])) {
			$priorMonth = 1;
		}
		if (isset($_POST['exclude_budget'])) {
			$excludeBudget = 1;
		}
		
		$trans->Init_transaction (
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
			$priorMonth,
			$excludeBudget,
			$_POST['trans_id'],
			$_POST['repeat_count'],
			'',		//account display
			NULL,	//ledger amt
			-1,		//ledger ID
			-1,		//audit ID
			0.0,	//audit balance
			$ledger_list	// Debits + Credits
		);
		
		if ($error == '') {
			$error = $trans->Validate();
		}

		if ($error == '')
		{
			if ($mode == 'save') {
				$error = $trans->Save_repeat_transactions();
			}
			else {
				$error = $trans->Delete_transaction();
			}
		}

		if ($error == '')
		{
			//successful save; reset for a new transaction
			$trans = new Transaction ();
		}
	}

	$error1 = $error;
	$warning = '';
	// Build the transaction list
	$trans_list = Transaction::Get_transaction_list ($sel_account_id,
		$start_date, $end_date, $limit, $search_text, $total_period, $error, $warning);
	// Strip slashes from search_text variable
	$search_text = stripslashes( $search_text );
	$sel_account = new Account ();
	$sel_account->Load_account ($sel_account_id);
	if ($error1 != '') {
		// an error occurred before calling transaction list
		$error = $error1;
	}
?>


<html>
<head>
	<title>Account Ledger</title>
	<link href="style.css" rel="stylesheet" type="text/css">
	<script src="https://code.jquery.com/jquery-2.2.3.js" ></script>
	<script language="javascript" type="text/javascript">
		
		$(document).ready(function() {
	
			$(".delete-ledger").click(function() {
				if ($(".ledger-row").length <= 2) {
					alert("At least two Ledger Entries are required");
					return;
				}
				// Look for existing ledger_id; save to hidden field if present.
				// button -> td -> tr
				var row = $(this).parent().parent();
				var ledgerId = row.find("input[name='ledger_id[]']").val();
				if ($.isNumeric(ledgerId) && ledgerId > 0) {
					// Found a real ledger id; copy the whole hidden input
					$("#editForm").append("<input type='hidden' name='delete_ledger_id[]' value='"
						+ ledgerId + "' /> ");
				}
				row.remove();
			});
			
			$("#new-ledger").click(function() {
				// Copy first data row and append to the table.
				var newRow = $(".ledger-row").first().clone(true).insertBefore($("#summary-row"));
				
				// Clear existing input values from new row
				newRow.find("input").val("");
				newRow.find("select").prop("selectedIndex", "0");
			});
		
			// Initialize change handler, then invoke now	
			$("select[name='account_id[]']").change(handleAccountSelect).change();
			
			// Bind Copy button to function
			$("#copyButton").click(copyTransaction);
			
			// Debit / Credit amount calculation handlers
			$("input[name='amountDebit[]']").change(calculateTotals);
			$("input[name='amountCredit[]']").change(calculateTotals);
			
			// Calculate on first load
			calculateTotals();
		});
		
		function getNumberOrZero(jQueryField) {
			if ($.isNumeric(jQueryField.val())) {
				return Number(jQueryField.val());
			} else {
				return 0.0;
			}
		}
		
		// Total up the amounts
		function calculateTotals() {
			var debitTotal = 0.0;
			var creditTotal = 0.0;
			$("input[name='amountDebit[]']").each(function() {
				debitTotal += getNumberOrZero($(this));
			});
			$("input[name='amountCredit[]']").each(function() {
				creditTotal += getNumberOrZero($(this));
			});
			
			var amountDiff = debitTotal - creditTotal;
			$("#debitTotal").val("$" + debitTotal.toFixed(2));
			$("#creditTotal").val("$" + creditTotal.toFixed(2));
			$("#totalDiff").val("$" + amountDiff.toFixed(2));
		}


		// Highlight positive side of Ledger Entry in green (Debit or Credit)
		function handleAccountSelect() {
			// Reset box shadows on Debit & Credit
			$(this).parent().parent().find("input[type='number']").css("box-shadow", "none");

			// Value is account_id,debit
			var valueArray = $(this).val().split(",");
			if (valueArray.length != 2) {
				// No valid selection; return w/ no highlighting
				return;
			}

			var debit = valueArray[1];
			var fieldSelector = debit > 0 ? "input[name='amountDebit[]']" 
				: "input[name='amountCredit[]']";
			// select -> td -> tr -> find input on this row
			var field = $(this).parent().parent().find(fieldSelector);
			field.css("box-shadow", "0 0 5px #00FF00");
		}


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
				document.forms[1].trans_date.focus();
				document.forms[1].trans_date.select();
			}
		}

		// Change from Edit to New, leaving ledger entries in tact.
		function copyTransaction()
		{
			// 1. Change Tx header text
			var editTrans = document.getElementById('edit_trans');
			editTrans.innerHTML = 'New Transaction (copy)';

			// 2. Wipe out trans_id hidden field
			$("#trans_id").val("-1");

			// 3. Wipe out ledger ID values
			$("input[name='ledger_id[]']").val("-1");

			// 4. Remove Delete and Cancel buttons
			var deleteButton = document.getElementById('deleteButton');
			deleteButton.parentNode.removeChild(deleteButton);

			var cancelButton = document.getElementById('cancelButton');
			cancelButton.parentNode.removeChild(cancelButton);

			var copyButton = document.getElementById('copyButton');
			copyButton.parentNode.removeChild(copyButton);

			// 5. Clear out dates
			$("#trans_date").val("").focus();
			$("#accounting_date").val("");
		}

		function auditAccount( ledger_id, account_balance )
		{
			// Handle a click of an account total; popup a new window
			window.open( 'audit.php?ledger_id=' + ledger_id
				+ '&account_total=' + account_balance, 'audit',
				'toolbar=no,height=250,width=700');
		}

		function editAudit( audit_id )
		{
			// Handle a click of an account total; popup a new window
			window.open( 'audit.php?audit_id=' + audit_id, 'audit',
				'toolbar=no,height=250,width=700');
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

<?php
	if ($error != '')
		echo "<div class='error'>$error</div> \n";
	if ($warning != '') {
		echo "<div class='message'>$warning</div> \n";
	}
?>

<form method="post" action="index.php" name="searchForm">
<input type="hidden" name="editClick" value="<?= $editClick ?>">
<table>

	<tr>
		<td><?= $acct_dropdown ?></td>
		<td>From: </td>
		<td><input type="date" min="1980-01-01" max="2100-01-01" name="start_date" value="<?= $start_date ?>"></td>
		<td>To: </td>
		<td><input type="date" min="1980-01-01" max="2100-01-01" name="end_date" value="<?= $end_date ?>"></td>
		<td>Search: </td>
		<td><input type="text" size="10" maxlength="20" name="search_text"
			value="<?= $search_text ?>"></td>
		<td style="padding-left: 10px;"><input type="submit" name="filter" value="Filter transactions"></td>
	</tr>
	<tr>
		<td>Rev. period: <?= $period_dropdown ?></td>
		<td>Limit: </td>
		<td><input type="number" min="0" max="999" name="limit" value="<?= $limit ?>"></td>
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

<?php
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
				// End of year total; italicize
				$td_style = ' style="font-style: italic; border-right: 1px solid black;"';
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
			$other = '#'. $trans_item->get_check_number();
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
		if ($trans_item->get_audit_id() > -1)
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
					"Expected $". $trans_item->get_audit_balance();
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
			$auditAnchor = "<a title='$auditTitle' onclick='$onclick' ".
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
</form>


<div id="tx-form">
<form method="post" action="index.php" name="editForm" id="editForm">
	<?php
		// Create ledger entry deletion inputs, for failed deletion submit.
		foreach ($deleteLedgerIdArray as $deleteLedgerId) {
			echo "	<input type='hidden' name='delete_ledger_id[]' "
				. "value='$deleteLedgerId' /> \n";
		}
	?>
	<fieldset> <legend> <a "edit_trans" id="edit_trans"><?php
			if ($trans->get_trans_id() < 0)
				echo "New Transaction";
			else
				echo "Edit Transaction (". $trans->get_trans_id(). ")";
			?></a> </legend>
		<div id="tx1">
			<label class="lhs">Status:</label> <?= $status_dropdown ?>
			<label>Repeat months: </label><input type="number" min="1" max="12" step="1" name="repeat_count"
			value="<?= $trans->get_repeat_count() ?>">

			<div class="info"><?php
				if ($trans->get_trans_id() >= 0)
					echo "last modified " . $trans->get_updated_time() ?></div>
		</div>

		<div id="tx2">
			<label class="lhs">Date: </label> <input type="hidden" id="trans_id" name="trans_id" value="<?=
					$trans->get_trans_id() ?>">
				<input type="date" min="1980-01-01" max="2100-01-01" name="trans_date"
				id="trans_date"
				value="<?= $trans->get_trans_date() ?>">
			<label> Accounting date: </label> <input type="date" min="1980-01-01" max="2100-01-01" name="accounting_date"
				id="accounting_date"
				value="<?= $trans->get_accounting_date() ?>">
		</div>
		
		<div id="tx3">			
			<label class="lhs">Number: </label> <input type="number" min="1" max="9999" name="check_number"
				value="<?= $trans->get_check_number() ?>">
			<label>Miles: </label>  <input type="number" min="0" max="9999" step="0.1" name="gas_miles"
				value="<?= $trans->get_gas_miles_trimmed() ?>">
			<label>Gallons: </label> <input type="number" min="0" max="99" step="0.01" name="gas_gallons"
				value="<?= $trans->get_gas_gallons() ?>">
		</div>

		<div id="tx4">
			<label class="lhs"> Description: </label> <input type="text" size="50" maxlength="50" name="trans_descr"
				value="<?= $trans->get_trans_descr() ?>">
			<label> Vendor: </label> <input type="text" size="50" maxlength="50" name="trans_vendor"
				value="<?= $trans->get_trans_vendor() ?>">
		</div>

		<div id="tx5">
			<label class="lhs"> Comment:</label>
			<textarea name="trans_comment" rows="2" cols="50"
				><?= $trans->get_trans_comment() ?></textarea>
			<label for="prior_month">Budget for prior month: </label> <input type="checkbox" id="prior_month" name="prior_month" value="1" <?= $priorMonthCheck ?>/>
			<label for="exclude_budget">Exclude from budget: </label>
				<input type="checkbox" id="exclude_budget" name="exclude_budget" value="1" <?= $excludeBudgetCheck ?>/> 
		</div>
	</fieldset>

	<fieldset>
		<legend>Ledger Entries </legend>
	<table id="ledger-table"> 
		<tr>
			<th>Memo</th>
			<th>Account</th>
			<th>Debit Amount</th>
			<th>Credit Amount</th>
		</tr>

<?php

	$show_inactive = 0;
	// only show inactive accounts when editing
	if ($editClick == 1) {
		$show_inactive = 1;
	}
	// Transaction dropdowns (account_id,account_debit as the key)
	$accountList = Account::Get_account_list ($_SESSION['login_id'], '',
		-1, false, true, $show_inactive);
	$accountList = array ('-1' => '--Select--') + $accountList;

	// Get all the values from the LHS & RHS LedgerEntry objects
	$ledgers = $trans->get_ledger_list();

	// Figure out number of Ledger Entries needed.
	// Default:  2
	$rowCount = max(2, count($ledgers));

	for ($i = 0; $i < $rowCount; $i++)
	{
		// LHS
		$ledger = GetLedger($ledgers, $i);
		if ($ledger->toDelete) {
			// Skip ledgers being deleted (failed submit)
			continue;
		}
		echo "	<tr class='ledger-row'>\n".
			'		<td><input type="text" class="memo" maxlength="50" name="ledger_memo[]" '.
				"value=\"". $ledger->getMemo() . "\" /> </td> \n";
		echo '		<td><input type="hidden" name="ledger_id[]" value="' . $ledger->ledgerId . "\" /> \n";
		// Build account dropdown
		$acct_drop = Build_dropdown ($accountList, 'account_id[]',
			$ledger->getAccountIdDebitString());
		echo $acct_drop. "</td>\n".
			"		<td><input type='number' class='numeric' min='0.0' max='9999999' ".
			"step='0.01' name='amountDebit[]' value='". $ledger->debitAmount . "' /> </td> \n";
		echo "		<td><input type='number' class='numeric' min='0.0' max='9999999' ".
			"step='0.01' name='amountCredit[]' value='". $ledger->creditAmount . "' /> </td> \n";
		echo "		<td><button type='button' class='delete-ledger'> Remove </button></td> \n";
		echo "	</tr> \n\n";
	}
?>
	<!-- Summary Row -->
	<tr id="summary-row">
		<td>
			<button type="button" id="new-ledger"> Add Ledger Entry </button>			
		</td>
		<td>
			Totals:
		</td>
		<td id="debitTotal" class="numeric"></td>
		<td id="creditTotal" class="numeric"></td>
		<td id="totalDiff" class="numeric"></td>
	</tr>

	</table>
	
	</fieldset>
</div>  <!-- tx-form -->
		
<table style="float: left;">
	<tr class="padded-row">
		<td style="padding-left: 25px;">&nbsp;</td>
		<td><input type="submit" name="save" value="Save transaction"></td>
<?php
	if ($trans->get_trans_id() > -1)
	{
		// currently editing; show delete button
		echo '<td><input type="submit" name="delete" id="deleteButton" '.
			"onClick=\"return confirmDelete()\" value=\"Delete transaction\" /></td>\n".
			"<td><input type=\"submit\" value=\"Cancel\" name=\"cancel\" id=\"cancelButton\" /></td>\n" .
			"<td><input type=\"button\" id=\"copyButton\" value=\"Copy\" /></td>\n";
	}
		echo '<td>';
		echo '</td>';
?>
	</tr>

</table>

<?php require('footer.php'); ?>

</form>

</body>
</html>

