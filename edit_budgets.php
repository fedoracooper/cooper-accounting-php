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
	$copyDefault = false;
	if (isset ($_POST['copy_default'])) {
		$copyDefault = true;
	}
	
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
		$psInsert = NULL;
		$psUpdate = NULL;
		
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
			$error = $budget->Save($pdo, $psInsert, $psUpdate);
			if ($error != '') {
				break;
			}
			
			// update default budget
			$account = new Account();
			$account->Init_for_budget_update($accountId, $defaultBudget);
			$error = $account->Update_budget_default($pdo);
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
		$psInsert = NULL;
		$psUpdate = NULL;
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
			$budgetDate, $endOfMonth, $budgetDate, $budget_list);
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
			
			$(".default-budget").change(function() {
				// Update Budget highlighting
				var row = $(this).parent().parent();
				var defaultBudget = Number(this.value);
				var budgetField = row.find(".budgetAmount");
				var budget = Number(budgetField.val());
				setBudgetStyle(budgetField, defaultBudget, budget);
				
				// Update Default budget total
				var total = 0.0;
				$(".default-budget").each(function() {
					total += Number( this.value ) || 0.0;
				});
				
				$("#default-budget-total").text( formatCurrency(total) );
			});
			
			$(".budgetAmount").change(function() {
				// input -> td -> tr
				var row = $(this).parent().parent();
				
				// Recalculate Unspent + Available columns
				// Unspent = Budget - SpentOrSaved - To Save
				// ToSave = max(Savings * -1.0, Budget - SpentOrSaved )
				var budget = Number(this.value);
				var defaultBudget = Number(row.find(".default-budget").val());
				setBudgetStyle($(this), defaultBudget, budget);
				
				var spentOrSaved = currencyToNum(row.find(".spent-or-saved").text());
				var saved = Number(row.find(".saved-amount").text());
				var savingsText = row.find(".savings-balance").text().trim();
				var savings = currencyToNum(savingsText);
				var toSave = Math.max(savings * -1.0, budget - spentOrSaved);

				if (savingsText == '' || savings < -0.001) {
					// No savings account, or this is a long term debt
					toSave = 0.0;
				}
				var unspent = budget - spentOrSaved - toSave;
				var unspentCell = row.find(".unspent-amt");
				unspentCell.text(formatCurrency(unspent));
				setUnspentStyle(unspentCell, unspent);
				
				// Available = Budget + max(0.0, Savings) - SpentOrSaved
				var available = budget + Math.max(savings, 0.0) - spentOrSaved;
				var availableCell = row.find(".available-amt");
				availableCell.text(formatCurrency(available));
				setAvailableStyle(availableCell, available);
				
				calculateBudgetTotal();
			});

			// Calculate total on first load
			calculateBudgetTotal();
			
			// Format Budget on first load
			$(".budgetAmount").each(function() {
				var budget = Number(this.value);
				var row = $(this).parent().parent();
				var defaultBudget = Number( row.find(".default-budget").val() );
				setBudgetStyle($(this), defaultBudget, budget);
			});

			// Format negatives on first load
			$(".unspent-amt").each(function() {
				var val = currencyToNum($(this).text());
				setUnspentStyle($(this), val);
			});

			$(".available-amt").each(function() {
				setAvailableStyle($(this), currencyToNum($(this).text()));
			});
		});
		
		function setBudgetStyle(jQueryElement, defaultBudget, budget) {
			var absDiff = Math.abs(budget - defaultBudget);
			// Highlight budget amount in orange if it is different from default budget
			setCssClass(jQueryElement, 'orange-shadow', absDiff > 0.001);
		}
		
		/* Set CSS style on Unspent jQuery element, based on the numeric value of num.
		 */
		function setUnspentStyle(jQueryElement, num) {
			setCssClass(jQueryElement, 'red-shadow', num < -0.001);	// Negatives are bright red
			setCssClass(jQueryElement, 'green-shadow', num > 0.001 && num < 0.999);	// Light green for 0.01 to 0.99
			setCssClass(jQueryElement, 'all-green', num >= 0.999);	// >= $1.00 is bright green
		}
		
		/* Set CSS style on Available jQuery element, based on numeric value num.
		 */
		function setAvailableStyle(jQueryElement, num) {
			setCssClass(jQueryElement, 'negative', num < -0.001);
		}

		function calculateBudgetTotal() {
			var total = 0.0;
			$(".budgetAmount").each(function() {
				total += (Number(this.value) || 0.0);
			});

			// Total Budget
			$("#new-total-budget").text( formatCurrency(total) );
			
			// Get total Income, stripping $ and thousands separators; Total Unbudgeted
			var unbudgeted = currencyToNum($("#total-income").text()) - total;
			setHighlightedTotals( $("#total-unbudgeted"),     unbudgeted );
			setHighlightedTotals( $("#total-unbudgeted-top"), unbudgeted );

			// Unspent totals
			total = 0.0;
			$(".unspent-amt").each(function() {
				total+= currencyToNum($(this).text());
			});
			setHighlightedTotals($("#unspent-total"),     total);
			setHighlightedTotals($("#unspent-total-top"), total);
		}

		/* Set the given amount into a jQuery object, and update styles.
		   Numbers >= 0 will have green highlighting, and negatives will be red.
		   cell = jQuery object to update; usually a table cell.
		   amount = numeric value to set into each cell using .text().
		 */
		function setHighlightedTotals(cell, amount) {
			cell.text( formatCurrency(amount) );
			// Highlight the total amount in green or red
			setCssClass(cell, 'red-shadow', amount < -0.001);
			setCssClass(cell, 'all-green', amount >= -0.001);
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
		<td colspan="2">&nbsp;&nbsp;<input type="submit" value="Update" name="update_date"> </td>
		<td>
			<label for="copy_default" title="Copy Default Budget values into Budget values which are unset">Copy default budgets</label>
			<input type="checkbox" name="copy_default" id="copy_default" />
		</td>
	</tr>

	<tr>
		<td>Top Account: </td>
		<td colspan="2"><?= $account_dropdown ?></td>
		
		<!-- Month direction arrows -->
		<td colspan="1">&nbsp;&nbsp;<input type="submit" value="&lt;" name="prev_month" /> &nbsp;
		<input type="submit" value="&gt;" name="next_month" /></td>
		<td> </td>
	</tr>
</table>
</form>


<form action="edit_budgets.php" method="post">
<!-- Copy upper form params here -->
<input type="hidden" name="budget_date" value="<?= $budgetDateText ?>" />
<input type="hidden" name="account_id" value="<?= $account_id ?>" />


<table class="budget-list" >
	<tr>
		<th>Account</th>
		<th class="numeric">Default Budget</th>
		<th class="numeric"> Savings </th>
		<th style='text-align: center;'> Budget </th>
		<th class="numeric"> Spent + Saved </th>
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
		$spentOrSaved = $budget_data->transactions;
		
		$newBudget = $budgetAmount;
		if ($budgetAmount == null && $copyDefault) {
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
			$spentOrSaved += $budget_data->getSaved();
			$savingsBalance = format_currency($savingsAmount);
			$savingsAccountName = htmlspecialchars($accountSavings->savingsName);
		} else {			
			$budget_data->setSaved(0.0, false);
		}
		
		if ($budget_data->accountActive == 0 
			&& $budget_data->getSaved() == 0.0
			&& $budget_data->transactions == 0.0
			&& $budget_data->savingsBalance == 0.0) {
				
			// No savings, no account activity & account is inactive, so SKIP
			continue;
		}

		$spentTotal += $spentOrSaved;
		$spentAmount = format_currency($spentOrSaved);
		$unspent = $budget_data->getUnspent();	
		$unspentTotal += $unspent;
		// Format negatives in JavaScript code
		$unspentAmount = format_currency($unspent, NULL);
		$available = $budget_data->getAvailable();
		$availableAmount = format_currency($available, NULL);
		$savedAmount = $budget_data->getSaved();

		echo "	<tr> \n".
			"		<td title=\"$accountDescr\"> ".
			"			<input type='hidden' name='accountIds[]' value='$account_id' />".
			"			<input type='hidden' name='budgetIds[]' value='$budgetId' />".
			"			<div style='display: none;' class='saved-amount'>$savedAmount</div> \n".
			"			$accountName </td> \n".
			"		<td class='numeric'><input type='number' class='default-budget' min='0.0' max='999999.99' step='0.01' name='defaultBudgets[]' ".
			" value='$defaultBudget' size='10' /></td> \n".
			"		<td class='numeric savings-balance' title=\"$savingsAccountName\"> $savingsBalance </td> \n".
			"		<td class='numeric'><input class='budgetAmount' type='number' min='0.0' max='999999.99' step='0.01' name='budgetAmounts[]' ".
			" value='$newBudget' size='10' /></td> \n".
			"		<td class='numeric spent-or-saved'> $spentAmount </td> \n".
			"		<td class='numeric unspent-amt'> $unspentAmount </td> \n".
			"		<td class='numeric available-amt'> $availableAmount </td> \n".
			"		<td><input type='text' name='budgetComments[]' ".
			"maxlength='100' class='long-text' value=\"$budgetComment\" /></td> \n".
			"	</tr> \n\n" ;
	}	// End budget loop

	$defaultTotalString = format_currency($defaultTotal);
	$budgetTotalString = format_currency($budgetTotal);
	$savingsTotalString = format_currency($savingsTotal);
	$spentTotalString = format_currency($spentTotal);
	$unspentTotalString = format_currency($unspentTotal, NULL); // format in JS
	
	echo "	<tr> \n".
		"		<td style='border-top: 1px solid black; border-bottom: 1px solid black;' ".
		" colspan='8'>&nbsp;</td> \n".
		"	</tr> \n\n".
		"	<tr> \n".
		"		<td class='total-text'>Total</td> \n".
		"		<td class='total' id='default-budget-total'>$defaultTotalString</td> \n".
		"		<td class='total'>$savingsTotalString</td> \n".
		"		<td class='total' id='new-total-budget'></td> \n".
		"		<td class='total'> $spentTotalString </td> \n".
		"		<td class='total' id='unspent-total' title='Unspent Total; should be 0 by end of month'> $unspentTotalString </td> \n".
		"		<td class='total'> </td> \n";
?>
	<td colspan="1" style="text-align: center;">
		<input style="margin-top: 5px; margin-bottom: 5px;" type="submit" 
		name="saveBudget" value="Save Budgets" />
	</td>
</tr>

<tr class="income-header">
	<th colspan="2">Income Account</th>
	<th class="numeric">Amount</th>
	<th class="numeric">Total</th>
	<th colspan="4">Transaction Description</th>
</tr>

<?php
	foreach ($income_list as $income) {
		echo "  <tr> <td colspan='2'>". $income->accountName . "</td> \n".
		"<td class='numeric'>". format_currency($income->amount) . "</td> \n".
		"<td></td> \n".
		"<td colspan='4'>". $income->transDescr . "</td>\n".
		"</tr> \n";
	}
	
	$totalUnbudgeted = $totalIncome - $budgetTotal;
	$totalUnbudgetedString = format_currency($totalUnbudgeted, NULL);
	echo "<tr><td colspan='2' class='total-text'>Total Income</td> ".
		"<td class='total' id='total-income'>" . format_currency($totalIncome) . "</td> \n".
		"<td colspan='5'> </td> ".
		"</tr> \n";
	echo "<tr><td class='total-text' colspan='3'>Unbudgeted Income</td> ".
		"<td class='total' id='total-unbudgeted' title='Money to be budgeted this month'> $totalUnbudgetedString </td> \n".
		"<td colspan='4'> </td> ".
		"</tr> \n";
?>
<tr>
	<td colspan="8"><?php require('footer.php'); ?></td>
</tr>
		

</table>
</form>


<div id='budget-totals-top'>
	<table class='total-table-top'>
		<tr>
			<td >
				Unbudgeted Income:
			</td>
			<td id='total-unbudgeted-top' class='total'>
				<?= $totalUnbudgetedString ?>
			</td>
		</tr>
		
		<tr>
			<td >
				Unspent Total:
			</td>
			<td id='unspent-total-top' class='total'>
				<?= $unspentTotalString ?>
			</td>
		</tr>
	</table>
</div>

</body>
</html>
