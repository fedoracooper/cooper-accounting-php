<?
/*	Product Number classes
	Created 12/9/2004 by Cooper Blake
*/

// PRODUCT CLASS
//------------------------------------------------------

class Product
{
	private $m_prod_id			= -1;
	private $m_login_id			= -1;
	private $m_category_id		= -1;
	private $m_prod_name		= '';
	private $m_prod_comment		= '';
	private $m_modified_time	= -1;
	private $m_created_time		= -1;
	private $m_active			= 1;
	private $m_account_list		= array();

	// ACCESSOR METHODS
	public function get_prod_id() {
		return $m_prod_id;
	}
	public function get_login_id() {
		return $m_login_id;
	}
	public function get_category_id() {
		return $m_category_id;
	}
	public function get_prod_name() {
		return $m_prod_name;
	}
	public function get_prod_comment() {
		return $m_prod_comment;
	}
	public function get_modified_time ($bSql = false) {
		if ($bSql) {
			return date ('Y-m-d', $m_modified_time);
		}
		else
			return $m_modified_time;
	}
	public function get_created_time ($bSql = false) {
		if ($bSql) {
			return date ('Y-m-d', $m_created_time);
		}
		else
			return $m_created_time;
	}
	public function get_active() {
		return $m_active;
	}
	public function get_account_list() {
		return $m_account_list;
	}


	// Initialize the product based on user input.
	// The date fields cannot be entered manually.
	public function Init_product (
		$login_id,
		$category_id,
		$prod_name,
		$prod_comment,
		$active,
		$prod_id = -1,
		$account_list = array())
	{
		$this->m_login_id		= $login_id;
		$this->m_category_id	= $category_id;
		$this->m_prod_name		= $prod_name;
		$this->m_prod_comment	= $prod_comment;
		$this->m_active			= $active;
		$this->m_prod_id		= $prod_id;
		$this->m_account_list	= $account_list;

		$error = '';
		if (trim ($m_prod_name) == '') {
			$error = 'Please enter a product name';
		}
		
		return $error;
	}

	
	public function Load_product ($prod_id)
	{
		$sql = "SELECT * FROM Products p".
			"INNER JOIN ProductAccounts pa ON".
			"	pa.prod_id = p.prod_id ".
			"WHERE prod_id = '$prod_id'";
		db_connect();
		$rs = mysql_query ($sql);
		$error = db_error($rs, $sql);
		if ($error == '')
		{
			$count = 0;
			$this->m_account_list = array();
			while ($row = mysql_fetch_array ($rs, MYSQL_ASSOC))
			{
				if ($count == 0)
				{
					// Product data
					$this->m_prod_id		= $prod_id;
					$this->m_login_id		= $row['login_id'];
					$this->m_category_id	= $row['category_id'];
					$this->m_prod_name		= addslashes ($row['prod_name']);
					$this->m_prod_comment	= addslashes ($row['prod_comment']);
					$this->m_modified_time	= strtotime ($row['modified_time']);
					$this->m_created_time	= strtotime ($row['created_time']);
					$this->m_active			= $row['active'];
				}
				// Get any account data to load into list
				if ($row['prod_account_id'] !== NULL)
				{
					// TODO *********************
				}

				$count++;
			}
			else
				$error = 'Unable to get product information';
		}
		mysql_close();

		return $error;
	}


	public function Save_product ()
	{
		$error = '';
		$sql = '';
		if ($this->m_prod_id < 0)
		{
			// INSERT
			
		}
		else
		{
			// UPDATE

		}
	}


	public function Delete_product ()
	{
		$error = '';
		if ($m_prod_id < 0)
			$error = 'No product ID to delete';
		else
		{
			$sql = "DELETE FROM ProductAccounts ".
				"WHERE prod_id = ". $this->m_prod_id;
			db_connect();
			$rs = mysql_query ($sql);
			$error = db_error ($rs, $sql);
			if ($error == '')
			{
				$sql = "DELETE FROM Products WHERE prod_id = ". $this->m_prod_id;			
				$rs = mysql_query ($sql);
				$error = db_error ($rs, $sql);
				if ($error == '' && mysql_affected_rows() < 1)
					$error = 'No rows were deleted';
			}

			mysql_close();
		}

		return $error;
	}

	/*
		Created 12/9/2004
		Purpose:
			Generate a list of Product

	*/
	public function Get_product_list ($login_id, $category_id = -1,
		$active = 1)
	{
		$error = '';
		$sql = 

	}

}


// PRODUCT ACCOUNT CLASS
//------------------------------------------------------
class ProductAccount
{
	private $m_prod_account_id	= -1;
	private $m_prod_id			= -1;
	private $m_user_name		= '';
	private $m_password			= '';
	private $m_email			= '';
	private $m_serial_num		= '';
	private $m_account_comment	= '';

	// ACCESSOR METHODS
	public function get_prod_account_id() {
		return $m_prod_account_id;
	}
	public function get_prod_id() {
		return $m_prod_id;
	}
	public function get_user_name() {
		return $m_user_name;
	}
	public function get_password() {
		return $m_password;
	}
	public function get_email() {
		return $m_email;
	}
	public function get_serial_num() {
		return $m_serial_num;
	}
	public function get_account_comment() {
		return $m_account_comment;
	}


	public function Init_product_account (
		$prod_id,
		$user_name,
		$password,
		$email,
		$serial_num,
		$account_comment,
		$prod_account_id = -1 )
	{
		$this->m_prod_account_id	= $prod_account_id;
		$this->m_prod_id			= $prod_id;
		$this->m_user_name			= $user_name;
		$this->m_password			= $password;
		$this->m_email				= $email;
		$this->m_serial_num			= $serial_num;
		$this->m_account_comment	= $account_comment;

		$error = '';
		if ($user_name == '' && $password == '' && $email == ''
			&& $serial_num == '')
		{
			$error = 'Please enter a user name, password, email, or serial #';
		}

		return $error;
	}


	public function Load_product_account ($prod_account_id)
	{
		$sql = "SELECT * FROM productAccounts ".
			"WHERE prod_account_id = $prod_account_id";
		db_connect();
		$rs = mysql_query ($sql);
		$error = db_error ($rs, $sql);

		if ($error == '' && $row = mysql_fetch_array ($rs, MYSQL_ASSOC))
		{
			$this->m_prod_account_id	= $prod_account_id;
			$this->m_prod_id			= $row['prod_id'];
			$this->m_user_name			= addslashes ($row['user_name']);
			$this->m_password			= addslashes ($row['password']);
			$this->m_email				= addslashes ($row['email']);
			$this->m_serial_num			= addslashes ($row['serial_num']);
			$this->m_account_comment	= addslashes ($row['account_comment']);
		}

		mysql_close();
		return $error;
	}


	public function Save_product_account ()
	{
		$sql = '';
		if ($this->m_prod_account_id > -1)
		{
			// UPDATE
			$sql = "UPDATE ProductAccounts \n".
				"SET prod_id = ". $this->m_prod_id. ", ".
				"user_name = '". $this->m_user_name. "', ".
				"password = '". $this->m_password. "', ".
				"email = '". $this->m_email. "', ".
				"serial_num = '". $this->m_serial_num. "', ".
				"account_comment = '". $this->m_account_comment. "' \n".
				"WHERE prod_account_id = ". $this->m_prod_account_id;
		}
		else
		{
			// INSERT
			$sql = "INSERT INTO ProductAccounts ".
				"(prod_id, user_name, password, email, serial_num, ".
				"account_comment ) \n".
				"VALUES (". $this->m_prod_id. ", ".
				"'$this->m_user_name', ".
				"'$this->m_password', ".
				"'$this->m_email', ".
				"'$this->m_serial_num', ".
				"'$this->m_account_comment' )";
		}

		db_connect();
		$rs = mysql_query ($sql);
		$error = db_error ($rs, $sql);
		if ($error == '' && mysql_affected_rows() < 1)
			$error = 'Unable to save to database.';
		mysql_close();

		return $error;
	}


	public function Delete_product_amount ()
	{
		if ($this->m_prod_account_id < 0)
			return 'No product account ID to delete.';

		$sql = "DELETE FROM ProductAccounts \n".
			"WHERE prod_account_id = ". $this->m_prod_account_id;
		db_connect();
		$rs = mysql_query ($sql);
		$error = db_error ($rs, $sql);

		if ($error == '' && mysql_affected_rows() < 1)
			$error = 'Unable to delete product account.';
		mysql_close();

		return $error;
	}

}