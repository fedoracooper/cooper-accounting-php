<?php
/*
	Accounts management page
	Created 9/27/2004 by Cooper Blake
	This allows admins to add, update & delete accounts
*/
	$current_page = 'accounts';
	require('include.php');
	if (!isset ($_SESSION['login_id']))
	{
		// redirect to login if they have not logged in
		header ("Location: login.php");
	}
	elseif ($_SESSION['login_admin'] < 1)
	{
		// no admin permission; naughty!
		header ("Location: login.php");
	}

	$error = '';

	// Default values
	$account = new Account ();
	$top_account_id		= -1;
	$sec_account_id		= -1;	// secondary
	$ter_account_id		= -1;	// tertiary
	
	// Form submit
	if (isset ($_POST['top_account_id']))
	{
		// load common values
		$top_account_id		= $_POST['top_account_id'];
		$sec_account_id		= $_POST['sec_account_id'];
		$ter_account_id		= $_POST['ter_account_id'];
	}

	$mode = '';
	if (isset ($_POST['save']))
		$mode = 'save';
	elseif (isset ($_POST['delete']))
		$mode = 'delete';
	if ($mode != '')	// save or delete
	{	
		// Save current account
		// If this is a new account, find the last selected dropdown;
		// otherwise do not update account parent.
		// Find the last selected dropdown
		if ($_POST['account_id'] == -1)
		{
			// New account
			if ($sec_account_id != -1)
				$account_parent_id = $sec_account_id;
			elseif ($top_account_id != -1)
				$account_parent_id = $top_account_id;
			else	// new top-level account (no parent)
				$account_parent_id = -1;
		}
		else
			// Editing an existing account; re-use the parent id
			$account_parent_id = $_POST['account_parent_id'];
		$active = 0;
		if (isset ($_POST['active']) && $_POST['active'] == '1')
		{
			// active flag is set
			$active = 1;
		}
		$is_savings = 0;
		if (isset ($_POST['is_savings'])) {
			$is_savings = 1;
		}
		$is_paycheck_sink = 0;
		if (isset ($_POST['is_paycheck_sink'])) {
			$is_paycheck_sink = 1;
		}

		$error = $account->Init_account (
			$account_parent_id,
			$_POST['account_name'],
			$_POST['account_descr'],
			$_POST['account_debit'],
			$_POST['equation_side'],
			$_POST['monthly_budget'],
			$_POST['savings_account_id'],
			$is_savings,
			$is_paycheck_sink,
			$_POST['account_id'],
			$active
		);

		if ($error == '')
		{
			// Validation successful
			if ($mode == 'save') {
				$error = $account->Save_account();
			}
			else {
				$error = $account->Delete_account();
			}
			// reset to new account
			$account = new Account ();
		}
	}
	elseif (isset ($_POST['edit']))
	{
		// Load an existing account
		if ($ter_account_id != -1)
			$error = $account->Load_account ($ter_account_id);
		elseif ($sec_account_id != -1)
			$error = $account->Load_account ($sec_account_id);
		elseif ($top_account_id != -1)
			$error = $account->Load_account ($top_account_id);
		else
			$error = 'Please select an account to edit';
	}



	// Build the account list dropdown
	$top_list = Account::Get_account_list ($_SESSION['login_id'], '',
		NULL);
	$top_list = array ('-1' => '--Select account--') + $top_list;
	$top_dropdown = Build_dropdown ($top_list, 'top_account_id',
		$top_account_id, 'select_account()');

	$sec_list = Account::Get_account_list ($_SESSION['login_id'], '',
		$top_account_id, true);
	$sec_list = array ('-1' => '--Select account--') + $sec_list;
	$sec_dropdown = Build_dropdown ($sec_list, 'sec_account_id',
		$sec_account_id, 'select_account()');

	$ter_list = Account::Get_account_list ($_SESSION['login_id'], '',
		$sec_account_id, true);
	$ter_list = array ('-1' => '--Select account--') + $ter_list;
	$ter_dropdown = Build_dropdown ($ter_list, 'ter_account_id',
		$ter_account_id);

	// Build account_debit dropdown
	$debit_list = array ('1' => 'Debit (assets, expenses)',
		'-1' => 'Credit (liabilities, income)');
	$debit_dropdown = Build_dropdown ($debit_list, 'account_debit',
		$account->get_account_debit());

	// Build Equation side dropdown
	$side_list = array ('L' => 'LHS (assets)', 'R' => 'RHS (revenue)');
	$side_dropdown = Build_dropdown ($side_list, 'equation_side',
		$account->get_equation_side());

	// Savings account dropdown
	$savings_list = array();
	$error = Account::Get_savings_accounts($_SESSION['login_id'], $savings_list);
	$savings_list = array('-1' => '--Select--') + $savings_list;
	$savings_dropdown = Build_dropdown ($savings_list, 'savings_account_id',
		$account->get_savings_account_id());
	
	// Active checkbox
	if ($account->get_active() == 1) {
		$active_txt = ' CHECKED';
	}
	else {
		$active_txt = '';
	}
	if ($account->get_is_paycheck_sink() == 1) {
		$is_paycheck_sink_txt = ' CHECKED';
	}
	else {
		$is_paycheck_sink_txt = '';
	}
		

	if ($account->get_is_savings() == 1) {
		$is_savings_txt = ' CHECKED';
	} else {
		$is_savings_txt = '';
	}
?>

	<script language="javascript" type="text/javascript">
	
		function select_account()
		{
			window.document.forms[0].submit();
		}

		function confirmDelete()
		{
			return confirm ('Are you sure you want to delete this account?');
		}

	</script>
</head>
<body>
<?= $navbar ?>

<form id="search_form" method="post" action="accounts.php">
<input type="hidden" name="account_id" value="<?= $account->get_account_id() ?>" />
<input type="hidden" name="account_parent_id" value="<?= $account->get_account_parent_id() ?>"/>
<h3><?= $title ?></h3>
<p class="error"><?= $error ?></p>
<p>Please select your account</p>

<table class="trans-table">
	<tr>
		<th>Top Account</th>
		<th>Secondary Account</th>
		<th>Tertiary Account</th>
	</tr>

	<tr>
		<td><?= $top_dropdown ?></td>
		<td><?= $sec_dropdown ?></td>
		<td><?= $ter_dropdown ?></td>
		<td><input type="submit" name="edit" value="Edit"></td>
	</tr>

	<tr>
		<td colspan="4"><hr></td>
	</tr>
</table>
</form>


<form id="data_form" method="post" action="accounts.php" >
<input type="hidden" name="account_id" value="<?= $account->get_account_id() ?>"/>
<input type="hidden" name="account_parent_id" value="<?= $account->get_account_parent_id() ?>" />
<input type="hidden" name="top_account_id" value="<?= $top_account_id ?>" />
<input type="hidden" name="sec_account_id" value="<?= $sec_account_id ?>" />
<input type="hidden" name="ter_account_id" value="<?= $ter_account_id ?>" />


<table>
	<tr>
		<td colspan="2"><?php
			if ($account->get_account_id() == -1)
				echo "New";
			else
				echo "Edit";
			?> Account</td>
	</tr>

	<tr>
		<td style="width: 50px;">&nbsp;</td>
		<td>Account name:</td>
		<td><input type="text" size="25" maxlength="25" name="account_name"
			value="<?= $account->get_account_name() ?>"></td>
	</tr>

	<tr>
		<td></td>
		<td>Account description:</td>
		<td colspan="2"><input type="text" size="50" maxlength="50" name="account_descr"
			value="<?= $account->get_account_descr() ?>"></td>
	</tr>

	<tr>
		<td></td>
		<td>Normal balance:</td>
		<td><?= $debit_dropdown ?></td>
	</tr>

	<tr>
		<td></td>
		<td>Equation side:</td>
		<td><?= $side_dropdown ?></td>
	</tr>

	<tr>
		<td></td>
		<td>Monthly budget:</td>
		<td><input type="text" size="50" maxlength="9" name="monthly_budget"
			value="<?= $account->get_budget_default() ?>"></td>
	</tr>

	<tr>
		<td></td>
		<td title="Typically a sub-account of a Checking account">Is Savings Account:</td>
		<td><input type="checkbox" name="is_savings" value="1"<?= $is_savings_txt ?>></td>
	</tr>
	
	<tr>
		<td></td>
		<td title="Savings account used to smooth out paycheck periods that are not monthly, such as bi-weekly">Is Paycheck Sinking Account:</td>
		<td><input type="checkbox" name="is_paycheck_sink" value="1"<?= $is_paycheck_sink_txt ?>></td>
	</tr>

	<tr>
		<td></td>
		<td title="Sinking account linked to this expense account">Linked Savings Account:</td>
		<td><?= $savings_dropdown ?></td>
	</tr>

	<tr>
		<td></td>
		<td>Active:</td>
		<td><input type="checkbox" name="active" value="1"<?= $active_txt ?>></td>
	</tr>

	<tr>
		<td></td>
		<td colspan="3"><hr></td>
	</tr>

	<tr>
		<td></td>
		<td></td>
		<td><input type="submit" name="save" value="Save account"></td>
<?php
	if ($account->get_account_id() > -1)
	{
		// Editing an account: delete button
		echo "		<td><input type=\"submit\" name=\"delete\" ".
			"onClick=\"return confirmDelete()\" value=\"Delete account\"></td> \n";
	}
?>
	</tr>
</table>

</form>
</body>
</html>
