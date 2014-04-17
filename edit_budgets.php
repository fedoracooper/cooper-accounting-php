<?php
	$current_page = 'edit_budgets';
	require('include.php');
	
	function initBudgetDate() {
		
		$budgetDate = new DateTime();
		if (isset ($_POST['budget_date']))
		{
			// Expect date as MM/YYYY
			$dateArr = explode('/', $_POST['budget_date']);
		
			if (count($dateArr) != 2) {
				$error = "Budget date must be in MM/YYYY format";
			} elseif ($dateArr[0] < 1 || $dateArr[0] > 12) {
				$error = "Budget date must be in MM/YYYY format";
			} elseif ($dateArr[1] > 2100) {
				$error = "Budget date year cannot go beyond 2100";
			} else {
				$budgetTime = mktime(0, 0, 0, $dateArr[0], 1, $dateArr[1]);
				$budgetDate->setTimestamp($budgetTime);
			}
		} else {
			// default date:  first day of current month
			$dateArr = getdate();
			$budgetDate->setDate($dateArr['year'], $dateArr['mon'], 1);
		}
		
		return $budgetDate;
	}
	
	if (!isset ($_SESSION['login_id']))
	{
		// redirect to login if they have not logged in
		header ("Location: login.php");
	}

	$error = '';
	$message = '';
	// set default vars
	// Set budget date to first day of month
	$budgetDate = initBudgetDate();
	
	// default account comes from DB
	$account_id = $_SESSION['default_summary2'];
	if (isset ($_POST['account_id'])) {
		$account_id	= $_POST['account_id'];
	}
	
	
	if (isset ($_POST['prev_month']))
	{
		// go back one month
		$budgetDate->sub(new DateInterval('P1M'));
	}
	elseif (isset ($_POST['next_month']))
	{
		// forward one month
		$budgetDate->add(new DateInterval('P1M'));
		
	} elseif (isset($_POST['saveBudget'])) {
		// try saving
		$newBudgets = $_POST['budgetAmounts'];
		$accountIds = $_POST['accountIds'];
		$budgetIds  = $_POST['budgetIds'];
		$defaultBudgets = $_POST['defaultBudgets'];
		$budgetComments = $_POST['budgetComments'];
		
		for ($i = 0; $i < count($accountIds); $i++) {
			$accountId = $accountIds[$i];
			$newBudget = $newBudgets[$i];
			$budgetId = $budgetIds[$i];
			$defaultBudget = $defaultBudgets[$i];
			$comments = $budgetComments[$i];
			if (empty($budgetId)) {
				// not editing, so set to -1
				$budgetId = -1;
			}
			
			// update budget
			$budget = new Budget();
			$budget->Init_budget($accountId, $budgetDate, $newBudget, $comments, $budgetId);
			$error = $budget->Save();
			if ($error != '') {
				break;
			}
			
			// update default budget
			$account = new Account();
			$account->Init_for_budget_update($accountId, $defaultBudget);
			$error = $account->Update_budget_default();
			if ($error != '') {
				break;
			}
		}
		
		if ($error == '') {
			$message = 'Successfully saved ' . count($accountIds).
			' budget record(s)';
		}
	}

	$budgetDateText = $budgetDate->format('m/Y');

	// build account dropdowns: include inactive, and only show top 2 tiers
	$account_list = Account::Get_account_list ($_SESSION['login_id'],
		'', -1, false, false, true, true);
	$account_dropdown = Build_dropdown ($account_list, 'account_id',
		$account_id);

	// Build main data list
	$budget_list = array();	// pass by reference
	if ($error == '') {
		$error = Account::Get_account_budgets($budgetDate,
			$account_id, $budget_list);
	}
	
?>

<html>
<head>
	<title>Budget</title>
	<link href="style.css" rel="stylesheet" type="text/css">
	<script language="javascript" type="text/javascript">

		function bodyLoad()
		{
			document.forms[0].budget_date.focus();
		}

	</script>
</head>


<body onload="bodyLoad()">
<?= $navbar ?>

<h3>Budget</h3>

<span class="error"><?= $error ?></span>
<span class="message"><?= $message ?></span>

<form action="edit_budgets.php" method="post">
<table>
	<tr>
		<td>Budget Month (MM/YYYY): </td>
		<td><input type="text" maxlength="7" name="budget_date" value="<?= $budgetDateText ?>" /></td>
		<td>&nbsp;&nbsp;<input type="submit" value="Update" name="update_date"></td>
	</tr>

	<tr>
		<td>Top Account: </td>
		<td colspan="2"><?= $account_dropdown ?></td>
		
		<!-- Month direction arrows -->
		<td colspan="1">&nbsp;&nbsp;<input type="submit" value="&lt;" name="prev_month" /> &nbsp;
		<input type="submit" value="&gt;" name="next_month" /></td>
	</tr>
</table>
</form>


<form action="edit_budgets.php" method="post">
<!-- Copy upper form params here -->
<input type="hidden" name="budget_date" value="<?= $budgetDateText ?>" />
<input type="hidden" name="account_id" value="<?= $account_id ?>" />


<table class="budget-list" cellspacing="0" cellpadding="0">
	<tr>
		<th>Account</th>
		<th style="text-align: center;">Default Budget</th>
		<th style="text-align: center;">Curent</th>
		<th style="text-align: center;">New Budget</th>
		<th style="text-align: center;">Budget Comment</th>

	</tr>

<?php
	// First loop through data: calculate totals
	$defaultTotal = 0.0;
	$budgetTotal = 0.0;


	// Need the abs_total to calculate row percentages
	foreach ($budget_list as $account_data)
	{
		// account_list (account_name, account_total, account_id)
		$defaultTotal += $account_data[1];
		$budgetTotal += $account_data[2];
	}

	// Second loop: display data
//	foreach ($account_list as $account_data)
	foreach ($budget_list as $account_id => $budget_data)
	{
		$accountName = $budget_data[0];
		$defaultBudget = format_amount($budget_data[1]);
		$budgetAmount = format_amount($budget_data[2]);
		$budgetStyle = '';
		if ($defaultBudget != $budgetAmount) {
			// This month's budget is not the default
			$budgetStyle = 'color: red;"';
		}
		$budgetId = $budget_data[3];
		$accountDescr = $budget_data[4];
		$budgetComment = $budget_data[5];
		$newBudget = $budgetAmount;
		if ($budgetAmount == null) {
			// Apply default to budget when undefined
			$newBudget = $defaultBudget;
		}

		echo "	<tr> \n".
			"		<input type='hidden' name='accountIds[]' value='$account_id' />".
			"		<input type='hidden' name='budgetIds[]' value='$budgetId' />".
			"		<td title='$accountDescr'>$accountName</td> \n".
			"		<td><input type='text' name='defaultBudgets[]' ".
			"maxlength='9' value='$defaultBudget' size='10' /></td> \n".
			"		<td style='text-align: right; $budgetStyle'>$budgetAmount</td> \n".
			"		<td><input type='text' name='budgetAmounts[]' ".
			"maxlength='9' value='$newBudget' size='10' /></td> \n".
			"		<td><input style='text-align: left;' type='text' name='budgetComments[]' ".
			"maxlength='100' size='50' value='$budgetComment' /></td> \n".
			"	</tr> \n\n" ;
	}	// End budget loop

	$defaultTotalString = format_currency($defaultTotal);
	$budgetTotalString = format_currency($budgetTotal);
	
	echo "	<tr> \n".
		"		<td style='border-top: 1px solid black; border-bottom: 1px solid black;' ".
		" colspan=\"5\">&nbsp;</td> \n".
		"	</tr> \n\n".
		"	<tr> \n".
		"		<td>Total</td> \n".
		"		<td style='text-align: right; font-weight: bold;'>$defaultTotalString</td> \n".
		"		<td style='text-align: right; font-weight: bold;'>$budgetTotalString</td> \n".
		"		<td style='text-align: right; font-weight: bold;'></td> \n".
		"	</tr> \n\n" ;

?>
	<tr><td colspan="5" style="text-align: right;">
		<input style="margin-left: 500px; margin-top: 20px;" type="submit" 
		name="saveBudget" value="Save Budgets" />
		</td>
	</tr>
		
</table>
</form>

</body>
</html>
