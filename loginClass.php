<?php
/*
	Login class
	Created 11/17/2004 by Cooper Blake

	Purpose:
		The management of login accounts

	Notes:
		The password variable will be blank when login is loaded from
		database.  Only a non-blank password will be updated on save;
		however, a non-blank password is required when the login_id is
		-1 (for new login account).

*/

class Login
{
	private $m_login_id				= -1;
	private $m_login_user			= '';
	private $m_login_password		= '';
	private $m_default_account_id	= -1;
	private $m_default_summary1		= -1;
	private $m_default_summary2		= -1;
	private	$m_car_account_id		= -1;
	private $m_login_admin			= 0;
	private $m_display_name			= '';
	private $m_bad_login_count		= 0;
	private $m_locked				= 0;
	private $m_active				= 1;

	private static $MAX_AUTH_FAILURES = 5;

	// ACCESSOR methods
	public function get_login_id() {
		return $this->m_login_id;
	}
	public function get_login_user() {
		return $this->m_login_user;
	}
	public function get_login_password() {
		return $this->m_login_password;
	}
	public function get_default_account_id ($bSql = false)
	{
		if ($bSql && $this->m_default_account_id == -1)
			return NULL;

		return $this->m_default_account_id;
	}
	public function get_default_summary1 ($bSql = false) 
	{
		if ($bSql && $this->m_default_summary1 == -1)
			return NULL;

		return $this->m_default_summary1;
	}
	public function get_default_summary2 ($bSql = false)
	{
		if ($bSql && $this->m_default_summary2 == -1)
			return NULL;

		return $this->m_default_summary2;
	}
	public function get_car_account_id($bSql = false)
	{
		if ($bSql && $this->m_car_account_id == -1)
			return NULL;

		return $this->m_car_account_id;
	}
	public function get_login_admin ($bText = false)
	{
		if ($bText) 
		{
			switch ($this->m_login_admin)
			{
				case 0:
					return 'Normal user';
				case 1:
					return 'Account manager';
				case 2:
					return 'System manager';
				default:
					return 'Unknown user type';
			}
		}

		return $this->m_login_admin;
	}
	public function get_display_name() {
		return $this->m_display_name;
	}

	public function get_bad_login_count() {
		return $this->m_bad_login_count;
	}

	public function get_locked() {
		return $this->m_locked;
	}

	public function get_active ($bText = false) {
		if ($bText)
		{
			if ($this->m_active == 1)
				return 'Active';
			else
				return 'Inactive';
		}
		return $this->m_active;
	}


	/*
		Purpose:
			Initialize all required member variables; used to load
			logins about to be saved or prepare update to existing login.
		Returns:
			an empty string on success, error message otherwise.
		Preconditions:
			1. All string fields must have quotation marks already escaped.
		Post-conditions:
			1. All member variables are bound to input values.
	*/
	public function Init_login (
		$login_user,
		$login_password,
		$default_account_id,
		$default_summary1,
		$default_summary2,
		$car_account_id,
		$login_admin,
		$display_name,
		$login_id = -1,
		$active = 1 )
	{
		$error = '';

		// VALIDATE
		if ($login_user == '')
			$error = 'You must enter a username';
		/*
		elseif ($default_account_id < 0)
			$error = 'Please select a default ledger account';
		elseif ($default_summary1 < 0)
			$error = 'Please select a first default summary account';
		elseif ($default_summary2 < 0)
			$error = 'Please select a second default summary account';
		*/
		elseif ($display_name == '')
			$error = 'Please enter a display name';

		$this->m_login_user			= $login_user;
		$this->m_login_password		= $login_password;
		$this->m_default_account_id	= $default_account_id;
		$this->m_default_summary1	= $default_summary1;
		$this->m_default_summary2	= $default_summary2;
		$this->m_car_account_id		= $car_account_id;
		$this->m_login_admin		= $login_admin;
		$this->m_display_name		= $display_name;
		$this->m_login_id			= $login_id;
		$this->m_active				= $active;

		return $error;
	}


	/*
		Purpose:
			Load data from database into this class.
		Post:
			Member vars are populated, with string quotations escaped.
	*/
	public function Load_login ($login_id)
	{
		$sql = "SELECT * FROM Logins ".
			"WHERE login_id = :login_id";
		$pdo = db_connect_pdo();
		$ps = $pdo->prepare($sql);
		$ps->bindParam(':login_id', $login_id);
		$success = $ps->execute();
		if (!$success) {
			return get_pdo_error($ps);
		}

		$row = $ps->fetch(PDO::FETCH_ASSOC);
		if ($row == false) {
			return 'Could not find login';
		}
		
		$this->m_login_id			= $row['login_id'];
		$this->m_login_user			= $row['login_user'];
		$this->m_login_password		= '';
		$this->m_default_account_id	= $row['default_account_id'];
		$this->m_default_summary1	= $row['default_summary1'];
		$this->m_default_summary2	= $row['default_summary2'];
		$this->m_car_account_id		= $row['car_account_id'];
		$this->m_login_admin		= $row['login_admin'];
		$this->m_display_name		= $row['display_name'];
		$this->m_bad_login_count	= $row['bad_login_count'];
		$this->m_locked				= $row['locked'];
		$this->m_active				= $row['active'];

		return '';
	}


	/*
		Purpose:
			Save current login to the database.  This can be an insert or update.
	*/
	public function Save_login ()
	{	
		$pdo = db_connect_pdo();
		$ps = null;
		if ($this->m_login_id > -1)
		{
			// UPDATE existing login
			$sql = "UPDATE Logins \n".
				"SET login_user = :login_user, ".
				" default_account_id = :default_account_id, ".
				" default_summary1 = :default_summary1, ".
				" default_summary2 = :default_summary2, ".
				" car_account_id = :car_account_id, ".
				" login_admin = :login_admin, ".
				" display_name = :display_name, ".
				" bad_login_count = :bad_login_count, ".
				" locked = :locked, ".
				" active = :active \n";

			if ($this->m_login_password != '')
			{
				// user is updating the password
				$sql.= ", login_password = MD5(:password) \n";
			}
			$sql.= "WHERE login_id = :login_id ";

			$ps = $pdo->prepare($sql);
			// bind params that are only used by UPDATE
			$ps->bindParam(':bad_login_count', $this->m_bad_login_count);
			$ps->bindParam(':locked', $this->m_locked);
			$ps->bindParam(':login_id', $this->m_login_id);
			if ($this->m_login_password != '') {
				$ps->bindParam(':password', $this->m_login_password);
			}
		}
		else
		{
			// INSERT new login
			$sql = "INSERT INTO Logins \n".
				"(login_user, login_password, default_account_id, ".
				" default_summary1, default_summary2, car_account_id, ".
				" login_admin, display_name, active) \n".
				"VALUES( :login_user, MD5(:password), ".
				" :default_account_id, ".
				" :default_summary1, ".
				" :default_summary2, ".
				" :car_account_id, ".
				" :login_admin, :display_name, ".
				" :active )";
			$ps = $pdo->prepare($sql);
			$ps->bindParam(':password', $this->m_login_password);
		}

		$ps->bindParam(':login_user', $this->m_login_user);
		$ps->bindParam(':default_account_id', $this->get_default_account_id(true));
		$ps->bindParam(':default_summary1', $this->get_default_summary1(true));
		$ps->bindParam(':default_summary2', $this->get_default_summary2(true));
		$ps->bindParam(':car_account_id', $this->get_car_account_id(true));
		$ps->bindParam(':login_admin', $this->m_login_admin);
		$ps->bindParam(':display_name', $this->m_display_name);
		$ps->bindParam(':active', $this->m_active);

		$success = $ps->execute();
		if (!$success) {
			return get_pdo_error($ps);
		}
		$affected = $ps->rowCount();
		$ps = null;
		$pdo = null;

		$msg = '';
		$error = '';
		if ($affected < 0)
			$error = 'Unable to add or update login info.';
		else
		{
			$msg = "Successfully updated $affected row(s)";
			return $msg;
		}
		
		return $error;
	}



	public function Delete_login ()
	{
		$sql = "DELETE FROM Logins \n".
			"WHERE login_id = :login_id ";

		$pdo = db_connect_pdo();
		$ps = $pdo->prepare($sql);
		$ps->bindParam(':login_id',  $this->m_login_id);
		$success = $ps->execute();
		if (!$success) {
			return get_pdo_error($ps);
		}
		
		$affected = $ps->rowCount();
		$ps = null;
		$pdo = null;

		$msg = '';
		if ($affected < 0)
			$msg = 'Could not find rows to delete.';
		else
		{
			$msg = "Successfully deleted $affected row";
			if ($affected > 1)
				$msg .= 's';
		}

		return $msg;
	}


	/*
		Purpose:
			Query database for list of all user logins.  The returned list
			will be used to build a dropdown.
	*/
	public static function Get_login_list()
	{
		$sql = "SELECT login_id, login_user, display_name \n".
			"FROM Logins ";
		$error = '';
		$login_list = array();

		$pdo = db_connect_pdo();
		$ps = $pdo->prepare($sql);
		$success = $ps->execute();
		if (!$success) {
			return get_pdo_error($ps);
		}

		while ($row = $ps->fetch(PDO::FETCH_ASSOC))
		{
			$login_list[$row['login_id']] = $row['login_user'].
				" ($row[display_name])";
		}
		
		return $login_list;
	}


	// Set the bad_login_count for the given user login_user.  Update
	// the locked flag to 0 when $count is 0, or 1 when we exceed
	// the bad login count.
	public static function Set_bad_login_count($pdo, $login_user, $count)
	{
		$locked = 0;
		if ($count >= self::$MAX_AUTH_FAILURES)
		{
			$locked = 1;
		}
		$sql = "UPDATE Logins SET bad_login_count = :count, locked = :locked ".
			"WHERE login_user = :login_user ";
		$ps = $pdo->prepare($sql);
		$ps->bindParam(':count', $count);
		$ps->bindParam(':locked', $locked);
		$ps->bindParam(':login_user', $login_user);
		
		$success = $ps->execute();
		if (!$success) {
			return get_pdo_error($ps);
		}
		
		$affected = $ps->rowCount();
		$error = '';
		if ($affected != 1)
		{
			$error = "Got $affected affected rows instead of 1";
		}

		return $error;
	}

	public static function Insert_login_audit($pdo, $login_user, $success, $locked) {
		$sql = 'INSERT INTO login_audit(login_user, ip_address, login_success, account_locked) '.
			'VALUES(:login_user, :ip_address, :login_success, :account_locked)';
		$ps = $pdo->prepare($sql);

		$successChar = $success ? 'Y' : 'N';
		$lockedChar = $locked ? 'Y' : 'N';
		$ps->bindParam(':login_user', $login_user);
		$ps->bindParam(':ip_address', $_SERVER['REMOTE_ADDR']);
		$ps->bindParam(':login_success', $successChar);
		$ps->bindParam(':account_locked', $lockedChar);

		$result = $ps->execute();
		if (!$success) {
			return get_pdo_error($ps);
		}

		return '';
	}
		

	// Get the login id from the give login_user, or NULL
	// if the user isn't found.
	public static function Find_login_id($pdo, $login_user)
	{
		$result = null;

		$sql = "SELECT login_id FROM Logins WHERE login_user = :login_user ";
		$ps = $pdo->prepare($sql);
		$ps->bindParam(':login_user', $login_user);
		$success = $ps->execute();
		
		if (!$success)
		{
			return get_pdo_error($ps);
		}

		if ($row = $ps->fetch(PDO::FETCH_ASSOC))
		{
			$result = $row['login_id'];
		}
	
		return $result;
	}


	public static function Authenticate($user, $password)
	{
		$sql = "SELECT login_id, default_account_id, login_admin, ".
			"  display_name, default_summary1, default_summary2, ".
			"  car_account_id, bad_login_count, locked \n".
			"FROM Logins \n".
			"WHERE login_user = :user ".
			"  AND login_password = MD5(:password) ";
		$result = false;
		$pdo = db_connect_pdo();
		
		// use transaction because an update will follow the select
		$pdo->beginTransaction();
		
		$ps = $pdo->prepare($sql);
		$ps->bindParam(':user', $user);
		$ps->bindParam(':password', $password);
		$success = $ps->execute();
		if (!$success)
		{
			return get_pdo_error($ps);
		}

		if ($row = $ps->fetch(PDO::FETCH_ASSOC))
		{
			// found login & correct password.  Check for lockout.
			$locked = $row['locked'];
			if ($locked > 0)
			{
				$result = "The account '$user' is locked!";
			}
			else
			{
				$_SESSION['login_id']			= $row['login_id'];
				$_SESSION['login_user']			= $_POST['login_user'];
				$_SESSION['default_account_id']	= $row['default_account_id'];
				$_SESSION['default_summary1']	= $row['default_summary1'];
				$_SESSION['default_summary2']	= $row['default_summary2'];
				$_SESSION['car_account_id']		= $row['car_account_id'];
				$_SESSION['login_admin']		= $row['login_admin'];
				$_SESSION['display_name']		= $row['display_name'];

				$result = true;

				$auditResult = Login::Insert_login_audit($pdo, $user, true, false);
				if ($auditResult != '') {
					$pdo->rollBack();
					return $auditResult;
				}

				// on success, wipe the bad login count
				self::Set_bad_login_count($pdo, $user, 0);
			}
		}
		else
		{
			// bad login
			$result = "Incorrect username & password";

			// find the relevant user login_id
			$login_id = self::Find_login_id($pdo, $user);
			$is_locked = false;

			if (is_numeric($login_id) && $login_id > 0)
			{
				// Load user object, get bad count, then increment it.
				$login = new Login();
				$login->Load_login($login_id);
				$bad_count = $login->get_bad_login_count();
				$bad_count++;
				$error = self::Set_bad_login_count($pdo, $user, $bad_count);

				if ($bad_count >= self::$MAX_AUTH_FAILURES)
				{
					$result = "The account '$user' has been locked!";
					$is_locked = true;
				}

				if ($error != '')
				{
					$result = $error;
				}
			}
			else if (strlen($login_id) > 5)
			{
				$result = "Problem finding login_id: " . $login_id;
			}

			$auditResult = Login::Insert_login_audit($pdo, $user, false, $is_locked);
			if ($auditResult != '') {
				$pdo->rollBack();
				return $auditResult;
			}

		}  // End bad login
		
		$pdo->commit();

		return $result;
	}


}	// END LOGIN CLASS
