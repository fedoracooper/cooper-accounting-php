<?php
/*	Account Audit edit screen
	Created 7/2/2006 by Cooper Blake
	Purpose:  Add, update, or delete an account audit record.  An audit record
	will freeze the given account up to the audit date.  It is tied to ledger
	entry that resulted in the correct account balance.

	Modes
		There are two different modes of this page:
		load:  Virgin load with no data except a ledger_id, account name,
			account balance, and audit date.
		save:  Form data with edited comments and date.
*/

$current_page = 'audit';
require( 'include.php' );

if (!isset ($_SESSION['login_id']))
{
	// redirect to login if they have not logged in
	header ("Location: login.php");
}


// Initialize defaults
$error = '';
$audit = new AccountAudit();

if (isset( $_GET[ 'ledger_id' ] ))
{
	// First load of the screen
	$audit->Load_basic_audit( $_GET[ 'ledger_id' ],
		$_GET[ 'account_total' ] );
}
elseif (isset( $_GET[ 'audit_id'] ))
{
	// Load from an audit id
	$audit->Load_account_audit( $_GET[ 'audit_id'] );
}
elseif (isset( $_POST[ 'save' ] ))
{
	// Did a submit; try to load the result
	$error = $audit->Init_account_audit(
		$_POST[ 'ledger_id' ],
		$_POST[ 'audit_date' ],
		$_POST[ 'account_balance' ],
		$_POST[ 'audit_comment' ],
		$_POST[ 'account_name' ],
		$_POST[ 'audit_id' ]
	);

	if ($error == '')
	{
		// Attempt to save the data
		$error = $audit->Save_account_audit();
	}
}
elseif (isset( $_POST[ 'delete' ] ))
{
	// Attempt to delete this record
	$error = $audit->Load_account_audit( $_POST[ 'audit_id' ] );

	if ($error == '')
	{
		$error = $audit->Delete_account_audit();
	}
}

if ($error != '')
{
	// Pad the error string in HTML
	$error = "<tr><td class='error'>$error</td></tr>";
}


//if ($_POST[ '']);

?>

<html>
<head>
	<title>Account Audit</title>
	<link href="style.css" rel="stylesheet" type="text/css">

	<script language="javascript" type="text/javascript">

		function confirmDelete()
		{
			return confirm('Are you sure you want to delete the '
				+ 'current account audit record?');
		}

	</script>
</head>

<body>
<table style="margin-top: 5px;">
	<tr>
		<td><h3>Account Audit <?
		$audit_id = $audit->get_audit_id();
		if ($audit_id < 0)
		{
			echo "(new)";
		}
		else
		{
			echo "($audit_id)";
		}
		echo ": ". $audit->get_account_name() ?></h3></td>
	</tr>

<?= $error ?>
</table>

<form method="post" action="audit.php">
<!-- Hidden values -->
<input type="hidden" name="audit_id" value="<?= $audit->get_audit_id() ?>">
<input type="hidden" name="ledger_id" value="<?= $audit->get_ledger_id() ?>">
<input type="hidden" name="account_balance"
	value="<?= $audit->get_account_balance( true ) ?>">
<input type="hidden" name="account_name"
	value="<?= $audit->get_account_name() ?>">

<table>
	<tr>
		<td style="padding-right: 10px;">Account balance: </td>
		<td><?= $audit->get_account_balance() ?></td>
	</tr>

	<tr>
		<td>Audit date: </td>
		<td><input type="text" name="audit_date" maxlength="10"
			value="<?= $audit->get_audit_date() ?>">
			&nbsp;&nbsp;&nbsp;&nbsp;Updated: <?= $audit->get_updated_time() ?>
			</td>
	</tr>

	<tr>
		<td>Audit comment: </td>
		<td><textarea name="audit_comment" cols="45" rows="4"><?=
			$audit->get_audit_comment() ?></textarea></td>
	</tr>

	<tr>
		<td>&nbsp;</td>
		<td><input type="submit" name="save" value="Save audit record">
		&nbsp;&nbsp;&nbsp;&nbsp;<?
	if ($audit->get_audit_id() > -1)
	{
		// This audit record has been stored in DB; allow delete
		echo '<input type="submit" name="delete" value="Delete audit record"'.
			' onClick="return confirmDelete();">';
	}
	?></td>
	</tr>
</table>

</form>

</body>
</html>
