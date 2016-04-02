<?php
/*
	Login management page
	Created 11/17/2004 by Cooper Blake
	This allows admins to add, update & delete login accounts
*/
	$current_page = 'logins';
	require('include.php');

	$fixed_login_id = -1;
	$login_id = -1;
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
	elseif ($_SESSION['login_admin'] == 1)
	{
		// limit user to only their own account
		$fixed_login_id = $_SESSION['login_id'];
		$login_id = $fixed_login_id;
	}

	$error = '';

	// Default values
	$login = new Login ();

	$mode = '';
	if (isset ($_POST['save']))
		$mode = 'save';
	elseif (isset ($_POST['delete']))
		$mode = 'delete';
	elseif (isset ($_POST['login_id']))
		$mode = 'select';	//login account has been selected
	// Form submit
	if ($mode == 'save' || $mode == 'delete')
	{
		$active = 0;
		if (isset ($_POST['active']))
			$active = 1;

		$error = $login->Init_login (
			$_POST['login_user'],
			$_POST['login_password'],
			$_POST['default_account_id'],
			$_POST['default_summary1'],
			$_POST['default_summary2'],
			$_POST['car_account_id'],
			$_POST['login_admin'],
			$_POST['display_name'],
			$_POST['login_id'],
			$active );

		$login_id = $login->get_login_id();
		if ($error == '')
		{
			if ($mode == 'save')
			{
				$error = $login->Save_login();
			}
			elseif ($mode == 'delete')
				$error = $login->Delete_login();

			if ($fixed_login_id == -1 && $error == '')
			{
				// reset display on success
				$login = new Login ();
				$login_id = -1;
			}
		}
	}
	elseif ($mode == 'select' && $fixed_login_id == -1)
	{
		$login_id = $_POST['login_id'];
	}

	if ($fixed_login_id > -1 || $mode == 'select' && $login_id > -1)
	{
		// fixed login OR new login selected
		$login->Load_login ($login_id);
	}

	// Build dropdowns
	$login_list = Login::Get_login_list();
	$login_list = array('-1'=> '--Select login--') + $login_list;
	$login_input = Build_dropdown ($login_list, 'login_id',
		$login_id);
	
	$acc_list = Account::Get_account_list ($login_id);
	$acc_list = array('-1'=> '--Select Account--') + $acc_list;
	$defAcc_dropdown = Build_dropdown ($acc_list, 'default_account_id',
		$login->get_default_account_id());
	$summ1_dropdown = Build_dropdown ($acc_list, 'default_summary1',
		$login->get_default_summary1());
	$summ2_dropdown = Build_dropdown ($acc_list, 'default_summary2',
		$login->get_default_summary2());
	$car_dropdown = Build_dropdown ($acc_list, 'car_account_id',
		$login->get_car_account_id());

	$admin_list = array ('0'=>'Normal user',
		'1'=>'Account manager',
		'2'=>'System manager');
	$admin_input = Build_dropdown ($admin_list, 'login_admin',
		$login->get_login_admin());
	$active_input = '<input type="checkbox" name="active" value="1"';
	if ($login->get_active() == 1)
		$active_input .= ' CHECKED';
	$active_input .= '>';


	if ($fixed_login_id > -1)
	{
		// replace dropdown with fixed text
		$login_input = $login->get_login_user(). ' ('.
			$login->get_display_name(). ') <input type="hidden" '.
			'name="login_id" value="'. $fixed_login_id. '">';
		$admin_input = $login->get_login_admin (true).
			'<input type="hidden" name="login_admin" value="'.
				$login->get_login_admin(). '">';
		$active_input = $login->get_active(true).
			' <input type="hidden" name="active" value="'.
			$login->get_active(). '">';
	}

?>


<html>
<head>
	<title>Login Management</title>
	<link href="style.css" rel="stylesheet" type="text/css">
	<script language="javascript" type="text/javascript">
	
		function select_account()
		{
			window.document.forms[0].submit();
		}

		function confirmDelete()
		{
			return confirm ('Are you sure you want to delete this login?');
		}

	</script>
</head>
<body>
<?= $navbar ?>

<form method="post" action="logins.php">
<h3>Login Management</h3>
<p class="error"><?= $error ?></p>
<p>Please select your login</p>

<table>
	<tr>
		<td>Login account:</td>
		<td><?= $login_input ?></td>
		<td><?
			if ($fixed_login_id == -1)
				echo '<input type="submit" name="select" value="Edit">';
			?></td>
	</tr>

	<tr>
		<td colspan="3"><hr></td>
	</tr>

	<tr>
		<td></td>
		<td>Username:</td>
		<td><input type="text" name="login_user" size="25" maxlength="25"
			value="<?= $login->get_login_user() ?>"></td>
	</tr>

	<tr>
		<td></td>
		<td>Display name:</td>
		<td><input type="text" name="display_name" size="25" maxlength="50"
			value="<?= $login->get_display_name() ?>"></td>
	</tr>

	<tr>
		<td></td>
		<td>Password:</td>
		<td><input type="password" name="login_password" size="25" maxlength="25"
			value="<?= $login->get_login_password() ?>"></td>
	</tr>
	<tr>
		<td></td>
		<td>Default ledger account:</td>
		<td><?= $defAcc_dropdown ?></td>
	</tr>

	<tr>
		<td></td>
		<td>Default summary account 1:</td>
		<td><?= $summ1_dropdown ?></td>
	</tr>

	<tr>
		<td></td>
		<td>Default summary account 2:</td>
		<td><?= $summ2_dropdown ?> &nbsp;(used for Account Breakdown)</td>
	</tr>

	<tr>
		<td></td>
		<td>Car expense account:</td>
		<td><?= $car_dropdown ?> &nbsp;(fuel consumption statistics)</td>
	</tr>

	<tr>
		<td></td>
		<td>User type:</td>
		<td><?= $admin_input ?></td>
	</tr>

	<tr>
		<td></td>
		<td>User status:</td>
		<td><?= $active_input ?></td>
	</tr>

	<tr>
		<td></td>
		<td colspan="2"><hr></td>
	</tr>

	<tr>
		<td></td>
		<td><input type="submit" name="save" value="Save"></td>
		<td><?
			if ($fixed_login_id == -1)
			{
				echo '<input type="submit" name="delete" onClick="return confirmDelete()" '.
					'value="Delete">';
			}
			?></td>
	</tr>


</table>

</body>
</html>
