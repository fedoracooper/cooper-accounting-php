<?
/*
	Login class
	Created 11/17/2004 by Cooper Blake

	Purpose:
		The management of login accounts

	Notes:
		As with other classes, the string member variables
		must have quotation marks escaped.  The accessor methods
		will strip these slashes.

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
		return stripslashes ($this->m_login_user);
	}
	public function get_login_password() {
		return stripslashes ($this->m_login_password);
	}
	public function get_default_account_id ($bSql = false)
	{
		if ($bSql && $this->m_default_account_id == -1)
			return 'NULL';

		return $this->m_default_account_id;
	}
	public function get_default_summary1 ($bSql = false) 
	{
		if ($bSql && $this->m_default_summary1 == -1)
			return 'NULL';

		return $this->m_default_summary1;
	}
	public function get_default_summary2 ($bSql = false)
	{
		if ($bSql && $this->m_default_summary2 == -1)
			return 'NULL';

		return $this->m_default_summary2;
	}
	public function get_car_account_id($bSql = false)
	{
		if ($bSql && $this->m_car_account_id == -1)
			return 'NULL';

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
		return stripslashes ($this->m_display_name);
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
			"WHERE login_id = $login_id";
		db_connect();
		$rs = mysql_query ($sql);
		$error = db_error ($rs, $sql);
		if ($error == '')
		{
			$row = mysql_fetch_array ($rs, MYSQL_ASSOC);
			$this->m_login_id			= $row['login_id'];
			$this->m_login_user			= addslashes ($row['login_user']);
			$this->m_login_password		= '';
			$this->m_default_account_id	= $row['default_account_id'];
			$this->m_default_summary1	= $row['default_summary1'];
			$this->m_default_summary2	= $row['default_summary2'];
			$this->m_car_account_id		= $row['car_account_id'];
			$this->m_login_admin		= $row['login_admin'];
			$this->m_display_name		= addslashes ($row['display_name']);
			$this->m_bad_login_count	= $row['bad_login_count'];
			$this->m_locked				= $row['locked'];
			$this->m_active				= $row['active'];
		}
		mysql_close();

		return $error;
	}


	/*
		Purpose:
			Save current login to the database.  This can be an insert or update.
	*/
	public function Save_login ()
	{	
		if ($this->m_login_id > -1)
		{
			// UPDATE existing login
			$sql = "UPDATE Logins \n".
				"SET login_user = '$this->m_login_user', ".
				" default_account_id = {$this->get_default_account_id(true)}, ".
				" default_summary1 = {$this->get_default_summary1(true)}, ".
				" default_summary2 = {$this->get_default_summary2(true)}, ".
				" car_account_id = {$this->get_car_account_id(true)}, ".
				" login_admin = $this->m_login_admin, ".
				" display_name = '$this->m_display_name', ".
				" bad_login_count = $this->m_bad_login_count, ".
				" locked = $this->m_locked, ".
				" active = $this->m_active \n";
			if ($this->m_login_password != '')
			{
				// user is updating the password
				$sql.= ", login_password = MD5('$this->m_login_password')";
			}
			$sql.= "\n WHERE login_id = $this->m_login_id ";	
		}
		else
		{
			// INSERT new login
			$sql = "INSERT INTO Logins \n".
				"(login_user, login_password, default_account_id, ".
				" default_summary1, default_summary2, car_account_id, ".
				" login_admin, display_name, active) \n".
				"VALUES( '$this->m_login_user', MD5('$this->m_login_password'), ".
				" {$this->get_default_account_id(true)}, ".
				" {$this->get_default_summary1(true)}, ".
				" {$this->get_default_summary2(true)}, ".
				" {$this->get_car_account_id(true)}, ".
				" $this->m_login_admin, '$this->m_display_name', ".
				" $this->m_active )";
		}
	//return $sql;

		db_connect();
		$rs = mysql_query ($sql);
		$error = db_error ($rs, $sql);
		$affected = mysql_affected_rows();
		mysql_close();

		$msg = '';
		if ($affected < 0)
			$error = 'Unable to add or update login info.';
		else
		{
			$msg = "Successfully updated $affected row";
			if ($affected > 1)
				$msg .= 's';
		}
		
		return $error;
	}



	public function Delete_login ()
	{
		$error = '';
		$sql = "DELETE FROM Logins \n".
			"WHERE login_id = $this->m_login_id ";

		db_connect();
		$rs = mysql_query ($sql);
		$error = db_error ($rs, $sql);
		$affected = mysql_affected_rows();
		mysql_close();

		$msg = '';
		if ($affected < 0)
			$error = 'Could not find rows to delete.';
		else
		{
			$msg = "Successfully deleted $affected row";
			if ($affected > 1)
				$msg .= 's';
		}

		return $error;
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

		db_connect();
		$rs = mysql_query ($sql);
		$error = db_error ($rs, $sql);
		if ($error == '')
		{
			while ($row = mysql_fetch_array ($rs, MYSQL_ASSOC))
			{
				$login_list[$row['login_id']] = $row['login_user'].
					" ($row[display_name])";
			}
		}
		
		mysql_close();
		return $login_list;
	}


	// Set the bad_login_count for the given user login_user.  Update
	// the locked flag to 0 when $count is 0, or 1 when we exceed
	// the bad login count.
	public static function Set_bad_login_count($login_user, $count)
	{
		$locked = 0;
		if ($count >= self::$MAX_AUTH_FAILURES)
		{
			$locked = 1;
		}
		$sql = "UPDATE Logins SET bad_login_count = $count, locked = $locked ".
			"WHERE login_user = '$login_user'";
		db_connect();
		$result = mysql_query($sql);
		$affected = mysql_affected_rows();
		$error = '';
		if ($result !== true)
		{
			$error = 'Error updating bad login count: ' . mysql_error();
		}
		else if ($affected != 1)
		{
			$error = "Got $affected affected rows instead of 1";
		}

		mysql_close();
		return $error;
	}


	// Get the login id from the give login_user, or NULL
	// if the user isn't found.
	public static function Find_login_id($login_user)
	{
		$result = null;

		$sql = "SELECT login_id FROM Logins WHERE login_user = '$login_user'";
		db_connect();
		$rs = mysql_query($sql);
		if (!$rs)
		{
			$result = "Error querying login id: " . mysql_error();
		}
		else
		{
			if ($row = mysql_fetch_array($rs))
			{
				$result = $row['login_id'];
			}
		}

		mysql_close();
		return $result;
	}


	public static function Authenticate($user, $password)
	{
		$sql = "SELECT login_id, default_account_id, login_admin, ".
			"  display_name, default_summary1, default_summary2, ".
			"  car_account_id, bad_login_count, locked \n".
			"FROM Logins \n".
			"WHERE login_user = '$user' ".
			"  AND login_password = MD5('$password') ";
		$result = false;
		$db = db_connect();
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

					// on success, wipe the bad login count
					self::Set_bad_login_count($user, 0);
				}
			}
			else
			{
				// bad login
				$result = "Incorrect username & password";

				// find the relevant user login_id
				$login_id = self::Find_login_id($user);

				if (is_numeric($login_id) && $login_id > 0)
				{
					// Load user object, get bad count, then increment it.
					$login = new Login();
					$login->Load_login($login_id);
					$bad_count = $login->get_bad_login_count();
					$bad_count++;
					$error = self::Set_bad_login_count($user, $bad_count);

					if ($bad_count >= self::$MAX_AUTH_FAILURES)
					{
						$result = "The account '$user' has been locked!";
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
			}  // End bad login
		}	// End rs handling

		mysql_close($db);

		return $result;
	}


}	// END LOGIN CLASS
