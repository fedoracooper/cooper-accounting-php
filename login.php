<?php
	$current_page = 'login';
	require('include.php');

	$error = '';
	$login_user = '';
	$login_password = '';

	if (isset ($_POST['login_user']))
	{
		// attempted login
		$result = Login::Authenticate($_POST['login_user'], $_POST['login_password']);
		if ($result === true)
		{
			// Successful Login !
			// Create a new session ID to prevent "session fixation" attacks
			session_regenerate_id(true);
			
			// Redirect to main page
			header ("Location: index.php");
			exit();
		}
		else
		{
			// bad login
			$error = $result;
		}

	}	// end login processing
	elseif (isset ($_GET['logout']))
	{
		// Log out this user
		session_unset();		// Delete session variables
		session_destroy();		// Delete persistent session data
	}

	if (isset ($_SESSION['login_id']))
	{
		// debugging
		//$error = $_SESSION['login_id']. ': '. $_SESSION['display_name'];
	}
?>

	<script language="javascript" type="text/javascript">
		function user_focus()
		{
			document.forms[0].login_user.focus();
		}

	</script>
</head>

<body onload="user_focus()">
<h3><?= $title ?></h3>
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
