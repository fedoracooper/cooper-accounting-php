<?
	ob_start();
	require('include.php');

	$error = '';
	$login_user = '';
	$login_password = '';

	if (isset ($_POST['login_user']))
	{
		// attempted login
		$login_user = $_POST['login_user'];
		$login_password = $_POST['login_password'];
		$sql = "SELECT login_id, default_account_id, login_admin, ".
			"  display_name, default_summary1, default_summary2, ".
			"  car_account_id \n".
			"FROM Logins \n".
			"WHERE login_user = '$login_user' ".
			"  AND login_password = MD5('$login_password') ";
		db_connect();
		$rs = mysql_query ($sql);
		if (!$rs)
		{
			$error = "Unable to query the database for login: ".
				mysql_error();
		}
		else
		{
			if ($row = mysql_fetch_array ($rs))
			{
				// successful login
				$_SESSION['login_id']			= $row['login_id'];
				$_SESSION['login_user']			= $_POST['login_user'];
				$_SESSION['default_account_id']	= $row['default_account_id'];
				$_SESSION['default_summary1']	= $row['default_summary1'];
				$_SESSION['default_summary2']	= $row['default_summary2'];
				$_SESSION['car_account_id']		= $row['car_account_id'];
				$_SESSION['login_admin']		= $row['login_admin'];
				$_SESSION['display_name']		= $row['display_name'];

				header ("Location: index.php");
			}
			else
			{
				// bad login
				$error = "Incorrect username & password";
			}
		}
		mysql_close();
	}	// end login processing
	elseif (isset ($_GET['logout']))
	{
		// Log out this user
		session_destroy();
	}

	if (isset ($_SESSION['login_id']))
	{
		// debugging
		//$error = $_SESSION['login_id']. ': '. $_SESSION['display_name'];
	}
?>

<html>
<head>
	<title>Accounting Login</title>
	<link href="style.css" rel="stylesheet" type="text/css">
	<script language="javascript" type="text/javascript">
		function user_focus()
		{
			document.forms[0].login_user.focus();
		}

	</script>
</head>

<body onload="user_focus()">
<h3>Accounting Login</h3>
<p class="error"><?= $error ?></p>
<p>Please log in below.</p>

<form method="post" action="login.php">
<table class="indented-block">
	
	<tr>
		<td>Username:</td>
		<td><input type="text" size="25" maxlength="25" name="login_user" 
			value="<?= $login_user ?>"></td>
	</tr>
	<tr>
		<td>Password:</td>
		<td><input type="password" size="25" maxlength="25" name="login_password"
			value="<?= $login_password ?>"></td>
	</tr>

	<tr>
		<td colspan="2"><hr></td>
	</tr>
	<tr>
		<td></td>
		<td><input type="submit" name="submit" value="Log in"></td>
	</tr>

</form>
</body>
</html>
