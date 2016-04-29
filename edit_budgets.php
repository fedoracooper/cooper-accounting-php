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
		
		$pdo = db_connect_pdo();
$t1 = microtime(true);
		$pdo->beginTransaction();
$t2 = microtime(true);
		$ps = NULL;
		
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
			$error = $budget->Save($pdo, $ps);
			if ($error != '') {
				break;
			}
			
			// update default budget
			$account = new Account();
			$account->Init_for_budget_update($accountId, $defaultBudget);
			$error = $account->Update_budget_default($pdo, $ps);
			if ($error != '') {
				break;
			}
		}
		
		if ($error == '') {
$t3 = microtime(true);
			$pdo->commit();
$t4 = microtime(true);
$txTime += $t2 - $t1 + $t4 - $t3;
			$message = 'Successfully saved ' . count($accountIds).
			' budget record(s)';
		} else {
			// $pdo->rollBack();
		}
		
		$pdo = NULL;
		$ps = NULL;
	}

	$budgetDateText = $budgetDate->format('m/Y');
	
	// Goto first day of next month, then subtract 1 day
	$endOfMonth = new DateTime();
	$endOfMonth->setDate($budgetDate->format('Y'), $budgetDate->format('m') + 1, 1);
	$endOfMonth->sub(new DateInterval('P1D'));
	

	// build account dropdowns: include inactive, and only show top 2 tiers
	$account_list = Account::Get_account_list ($_SESSION['login_id'],
		'', -1, false, false, true, true);
	$account_dropdown = Build_dropdown ($account_list, 'account_id',
		$account_id);

	// Build main data list
	$budget_list = array();	// pass by reference
	if ($error == '') {
		$error = Account::Get_account_details($account_id,
			$budgetDate, $endOfMonth, $budgetDate, true, $budget_list);
		//$error = Account::Get_account_budgets($budgetDate,
		//	$account_id, $budget_list);
	}
	
	// Get savings balances
	$savings_list = array();  // pass by ref
	if ($error == '') {
		$error = Account::Get_expense_savings($login_id,
			$budgetDate,	// Start date:  First day of month
			$endOfMonth,	// End date:  last day of month
			$savings_list);
	}
	
	// Get income transactions
	$income_list = array();  // pass by ref
	if ($error == '') {
		$error = Account::Get_income($budgetDate, $login_id, $income_list);
	}
	
	// Get total income
	$totalIncome = 0.0;
	foreach ($income_list as $income) {
		$totalIncome += $income->amount;
	}
?>

	<script>

		$(document).ready(function() {
			$(".budgetAmount").change(function() {
				calculateBudgetTotal();
			});

			// Calculate total on first load
			calculateBudgetTotal();
		});

		function calculateBudgetTotal() {
			var total = 0.0;
			$(".budgetAmount").each(function() {
				total += (Number($(this).val()) || 0.0);
			});

			$("#new-total-budget").text(formatCurrency(total));
			
			// Get total Income, stripping $ and thousands separators
			var totalIncome = $("#total-income").text().replace(/[$,]/g, '');
			var unbudgeted = totalIncome - total;
			$("#total-unbudgeted").text( formatCurrency(unbudgeted) );
			
			// Highlight the unbudgeted amount if it is not 0
			if (unbudgeted > 0.001) {
				$("#total-unbudgeted").removeClass('red-shadow');
				$("#total-unbudgeted").addClass('all-green');
			} else if (unbudgeted < -0.001) {
				$("#total-unbudgeted").removeClass('all-green');
				$("#total-unbudgeted").addClass('red-shadow');
			} else {
				$("#total-unbudgeted").removeClass('all-green');
				$("#total-unbudgeted").removeClass('red-shadow');
			}
		}


	</script>
</head>


<body>
<?= $navbar ?>

<h3><?= $title ?></h3>

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
		<th class="numeric">Default Budget</th>
		<th class="numeric"> Savings </th>
		<th style='text-align: center;'> Budget </th>
		<th class="numeric"> Spent </th>
		<th class="numeric"> Unspent </th>
		<th class="numeric"> Available </th>
		<th style="text-align: center;">Budget Comment</th>

	</tr>

<?php
	// First loop through data: calculate totals
	$defaultTotal = 0.0;
	$budgetTotal = 0.0;
	$savingsTotal = 0.0;
	$spentTotal = 0.0;
	$unspentTotal = 0.0;

	// Need the abs_total to calculate row percentages
	foreach ($budget_list as $account_data)
	{
		// account_list (account_name, account_total, account_id)
		$defaultTotal += $account_data->defaultBudget;
		$budgetTotal += $account_data->budget;
	}

	// Second loop: display data; loop through AccountSavings objects
	foreach ($budget_list as $account_id => $budget_data)
	{
		$accountName = htmlspecialchars($budget_data->accountName);
		$defaultBudget = format_amount($budget_data->defaultBudget);
		$budgetAmount = format_amount($budget_data->budget);
		$budgetStyle = '';
		if ($defaultBudget != $budgetAmount) {
			// This month's budget is not the default
			$budgetStyle = 'color: red;"';
		}
		$budgetId = $budget_data->budgetId;
		$accountDescr = htmlspecialchars($budget_data->accountDescr);
		$budgetComment = htmlspecialchars($budget_data->budgetComment);
		$spent = $budget_data->transactions;
		$spentTotal += $spent;
		$spentAmount = format_currency($spent);
		
		$newBudget = $budgetAmount;
		if ($budgetAmount == null) {
			// Apply default to budget when undefined
			$newBudget = $defaultBudget;
		}
		
		// Find associated savings account, if applicable
		$savingsBalance = '';
		$savingsAccountName = '';
		if (array_key_exists($account_id, $savings_list)) {
			$accountSavings = $savings_list[$account_id];
			$savingsAmount = $accountSavings->savingsBalance;
			// Set Savings data into main AccountSavings, which will
			// then have all data needed to calculate Unspent & Available.
			$budget_data->savingsBalance = $accountSavings->savingsBalance;
			$budget_data->setSaved($accountSavings->getSaved(), true);

			$savingsTotal += $savingsAmount;
			$savingsBalance = format_currency($savingsAmount);
			$savingsAccountName = htmlspecialchars($accountSavings->savingsName);
		} else {
			// no savings for this period
			$budget_data->setSaved(0.0, false);
		}

		$unspent = $budget_data->getUnspent();	
		$unspentTotal += $unspent;
		$unspentAmount = format_currency($unspent);
		$available = $budget_data->getAvailable();
		$availableAmount = format_currency($available);

		echo "	<tr> \n".
			"		<input type='hidden' name='accountIds[]' value='$account_id' />".
			"		<input type='hidden' name='budgetIds[]' value='$budgetId' />".
			"		<td title=\"$accountDescr\">$accountName</td> \n".
			"		<td class='numeric'><input type='number' min='0.0' max='999999.99' step='0.01' name='defaultBudgets[]' ".
			" value='$defaultBudget' size='10' /></td> \n".
			"		<td class='numeric' title=\"$savingsAccountName\"> $savingsBalance </td> \n".
			"		<td class='numeric'><input class='budgetAmount' type='number' min='0.0' max='999999.99' step='0.01' name='budgetAmounts[]' ".
			"maxlength='9' value='$newBudget' size='10' /></td> \n".
			"		<td class='numeric'> $spentAmount </td> \n".
			"		<td class='numeric'> $unspentAmount </td> \n".
			"		<td class='numeric'> $availableAmount </td> \n".
			"		<td><input type='text' name='budgetComments[]' ".
			"maxlength='100' class='long-text' value=\"$budgetComment\" /></td> \n".
			"	</tr> \n\n" ;
	}	// End budget loop

	$defaultTotalString = format_currency($defaultTotal);
	$budgetTotalString = format_currency($budgetTotal);
	$savingsTotalString = format_currency($savingsTotal);
	$spentTotalString = format_currency($spentTotal);
	$unspentTotalString = format_currency($unspentTotal);
	
	echo "	<tr> \n".
		"		<td style='border-top: 1px solid black; border-bottom: 1px solid black;' ".
		" colspan='8'>&nbsp;</td> \n".
		"	</tr> \n\n".
		"	<tr> \n".
		"		<td class='total'>Total</td> \n".
		"		<td class='total'>$defaultTotalString</td> \n".
		"		<td class='total'>$savingsTotalString</td> \n".
		"		<td class='total'><span id='new-total-budget'></span> </td> \n".
		"		<td class='total'> $spentTotalString </td> \n".
		"		<td class='total'> $unspentTotalString </td> \n".
		"		<td class='total'> </td> \n";
?>
	<td colspan="1" style="text-align: center;">
		<input style="margin-top: 5px; margin-bottom: 5px;" type="submit" 
		name="saveBudget" value="Save Budgets" />
	</td>
</tr>

<tr>
	<th colspan="2">Income Account</th>
	<th class="numeric">Amount</th>
	<th class="numeric">Total</th>
	<th colspan="3">Transaction Description</th>
</tr>

<?php
	foreach ($income_list as $income) {
		echo "  <tr> <td colspan='2'>". $income->accountName . "</td> \n".
		"<td class='numeric'>". format_currency($income->amount, false) . "</td> \n".
		"<td></td> \n".
		"<td colspan='3'>". $income->transDescr . "</td>\n".
		"</tr> \n";
	}
	
	$totalUnbudgeted = $totalIncome - $budgetTotal;
	echo "<tr><td style='font-weight: bold;'>Total Income</td> <td></td> ".
		"<td class='total' id='total-income'>" . format_currency($totalIncome) . "</td> \n".
		"</tr>";
	echo "<tr><td style='font-weight: bold;'>Unbudgeted Amount</td> <td></td> <td></td> ".
		"<td class='total' id='total-unbudgeted'>" . format_currency($totalUnbudgeted, false) . "</td> \n".
		"</tr>";
?>
<tr>
	<td colspan="7"><?php require('footer.php'); ?></td>
</tr>
		

</table>
</form>


</body>
</html>
