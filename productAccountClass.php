<?
/*	Product Number classes
	Created 12/9/2004 by Cooper Blake

	Purpose:
		The product is the parent element to the product account.
		Each product can have one or more product accounts.

		Some products may have multiple logins or multiple serial numbers,
		in which case they have many product accounts.
*/

// PRODUCT CLASS
//------------------------------------------------------

class Product
{
	private $m_prod_id			= -1;
	private $m_login_id			= -1;
	private $m_category_id		= 1;
	private $m_prod_name		= '';
	private $m_prod_comment		= '';
	private $m_modified_time	= -1;
	private $m_created_time		= -1;
	private $m_active			= 1;
	private $m_account_list		= array();

	// ACCESSOR METHODS
	public function get_prod_id() {
		return $this->m_prod_id;
	}
	public function get_login_id() {
		return $this->m_login_id;
	}
	public function get_category_id() {
		return $this->m_category_id;
	}
	public function get_prod_name() {
		return stripslashes ($this->m_prod_name);
	}
	public function get_prod_comment() {
		return stripslashes ($this->m_prod_comment);
	}
	public function get_modified_time ($bSql = false) {
		if ($bSql) {
			if ($this->m_modified_time < 0)
				return '';

			return date ('Y-m-d', $this->m_modified_time);
		}
		else
			return $this->m_modified_time;
	}
	public function get_created_time ($bSql = false) {
		if ($bSql) {
			if ($this->m_created_time < 0)
				return '';

			return date ('Y-m-d', $this->m_created_time);
		}
		else
			return $this->m_created_time;
	}
	public function get_active($bStr = false) {
		if ($bStr)
		{
			// return an HTML string representation
			if ($this->m_active == 1)
				return ' CHECKED';
			else
				return '';
		}

		return $this->m_active;
	}
	public function get_account_list() {
		return $this->m_account_list;
	}


	/* Initialize the product based on user input.
		The date fields cannot be entered manually.

		When initializing, the form will load a product, and optionallly,
		a product sub-account.  If a sub-account ID is present, it
		must be validated.

		One save button should do two things:
		1. Save the product form info.
			- on error, reload invalid product
			- current account should be managed in the page
		2. Save the account info, if applicable

		If no account is entered (on new), then a NULL should be
		passed in.
	
	*/
	public function Init_product (
		$login_id,
		$category_id,
		$prod_name,
		$prod_comment,
		$modified_time,
		$created_time,
		$active,
		$prod_id = -1,
		$account_list = array())
	{
		$this->m_login_id		= $login_id;
		$this->m_category_id	= $category_id;
		$this->m_prod_name		= $prod_name;
		$this->m_prod_comment	= $prod_comment;
		$this->m_modified_time	= $modified_time;
		$this->m_created_time	= $created_time;
		$this->m_active			= $active;
		$this->m_prod_id		= $prod_id;
		$this->m_account_list	= $account_list;

		if ($prod_id > -1)
		{
			// existing product; prepare to load info from DB
			$current_prod = new Product();
			$current_prod->Load_product ($prod_id);
			if ($modified_time < 0)
				$this->m_modified_time	= $current_prod->get_modified_time();
			if ($created_time < 0)
				$this->m_created_time	= $current_prod->get_created_time();
			if (count($account_list) > 0)
				$this->m_account_list	= $current_prod->get_account_list();
		}

		$error = '';
		if ($category_id < 0)
			$error = 'Please select a category';
		if (trim ($prod_name) == '') {
			$error = 'Please enter a product name';
		}
		
		return $error;
	}

	// Add a product account object to the list
	public function Add_product_account ($productAccount)
	{
		//echo var_dump ($this->m_account_list);
		$this->m_account_list[] = $productAccount;
	}

	
	public function Load_product ($prod_id)
	{
		$sql = "SELECT * FROM Products p ".
			"LEFT JOIN ProductAccounts pa ON".
			"	pa.prod_id = p.prod_id ".
			"WHERE p.prod_id = $prod_id";
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
					$prodAccount = new ProductAccount();
					$prodAccount->Init_product_account (
						$prod_id,
						addslashes ($row['user_name']),
						addslashes ($row['password']),
						addslashes ($row['email']),
						addslashes ($row['serial_num']),
						addslashes ($row['account_comment']),
						$row['prod_account_id']
					);

					$this->m_account_list[] = $prodAccount;
				}

				$count++;
			}
		}

		mysql_close();
		return $error;
	}

	
	/*
		Note:  this saves only the product, and not any accounts.
		It is assumed that product accounts are edited and saved
		separately.
	*/
	public function Save_product ()
	{
		$error = '';
		$sql = '';
		if ($this->m_prod_id < 0)
		{
			// INSERT
			$sql = "INSERT INTO Products ".
				"(login_id, ".
				"category_id, ".
				"prod_name, ".
				"prod_comment, ".
				"active ) \n".
				"VALUES ($this->m_login_id, ".
				"$this->m_category_id, ".
				"'$this->m_prod_name', ".
				"'$this->m_prod_comment', ".
				"$this->m_active ) \n";
		}
		else
		{
			// UPDATE
			$sql = "UPDATE Products \n".
				"SET login_id = $this->m_login_id, ".
				"category_id = $this->m_category_id, ".
				"prod_name = '$this->m_prod_name', ".
				"prod_comment = '$this->m_prod_comment', ".
				"active = $this->m_active \n".
				"WHERE prod_id = $this->m_prod_id ";
		}

		db_connect();
		$rs = mysql_query ($sql);
		$error = db_error ($rs, $sql);

		if ($error == '')
		{
			// further error checking
			$affRows = mysql_affected_rows();
			//if ($affRows != 1)
			//	$error = "Insert / update problem: $affRows rows affected";
			if ($this->m_prod_id < 0)
			{
				// successful insert; get the new prod id
				$this->m_prod_id = mysql_insert_id();
			}
		}

		mysql_close();
		return $error;
	}


	public static function Delete_product ($prod_id)
	{
		$error = '';
		if ($prod_id < 0)
			$error = 'No product ID to delete';
		else
		{
			$sql = "DELETE FROM ProductAccounts ".
				"WHERE prod_id = ". $prod_id;
			db_connect();
			$rs = mysql_query ($sql);
			$error = db_error ($rs, $sql);
			if ($error == '')
			{
				$sql = "DELETE FROM Products WHERE prod_id = ". $prod_id;			
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
			Generate a list of Products and their respective accounts.
			The list can be filtered by a certain category or active
			status, but it must always be filtered by a login_id.

	*/
	public static function Get_product_list ($login_id, &$prod_list,
		$category_id = -1, $active = -1)
	{
		$error = '';
		$prod_list = array();

		$sql = "SELECT p.prod_id, login_id, p.category_id, prod_name, ".
			"prod_comment, modified_time, created_time, p.active, ".
			"user_name, password, email, serial_num, account_comment, ".
			"prod_account_id ".
			"FROM Products p \n".
			"INNER JOIN ProductCategories pc ON ".
			"	pc.category_id = p.category_id ".
			"LEFT JOIN ProductAccounts pa ON ".
			"	pa.prod_id = p.prod_id \n".
			"WHERE login_id = $login_id ";
		if ($category_id > -1)
		{
			// filter category
			$sql.= "AND p.category_id = $category_id ";
		}
		if ($active > -1)
		{
			// filter active status
			$sql.= "AND p.active = $active ";
		}
		$sql .= "\n ORDER BY category_name, prod_name ";
//echo $sql;
		$db = db_connect();
		$rs = mysql_query ($sql);
		$error = db_error ($rs, $sql);
		if ($error != '')
		{
			mysql_close();
			return $error;
		}

		// loop through results and create a list of product objects
		while ($row = mysql_fetch_array ($rs, MYSQL_ASSOC))
		{
			$product = new Product();
			$product->Init_product (
				$row['login_id'],
				$row['category_id'],
				addslashes ($row['prod_name']),
				addslashes ($row['prod_comment']),
				strtotime ($row['modified_time']),
				strtotime ($row['created_time']),
				$row['active'],
				$row['prod_id']
			);

			// if account is present, load it as well
			if ($row['prod_account_id'] !== NULL)
			{
				$prodAccount = new ProductAccount();
				$prodAccount->Init_product_account (
					$row['prod_id'],
					addslashes ($row['user_name']),
					addslashes ($row['password']),
					addslashes ($row['email']),
					addslashes ($row['serial_num']),
					addslashes ($row['account_comment']),
					$row['prod_account_id']
				);
				$product->Add_product_account ($prodAccount);
			}

			$prod_list[] = $product;
		}

		mysql_close($db);
		//return $prod_list;
		return $error;
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
		return $this->m_prod_account_id;
	}
	public function get_prod_id() {
		return $this->m_prod_id;
	}
	public function get_user_name() {
		return stripslashes ($this->m_user_name);
	}
	public function get_password() {
		return stripslashes ($this->m_password);
	}
	public function get_email() {
		return stripslashes ($this->m_email);
	}
	public function get_serial_num() {
		return stripslashes ($this->m_serial_num);
	}
	public function get_account_comment() {
		return stripslashes ($this->m_account_comment);
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


	// Load a product account based on an ID
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

}	// END ProductAccounts class



// PRODUCT CATEGORY CLASS
// --------------------------------------
class ProductCategory
{
	private $m_category_id		= -1;
	private $m_category_name	= '';
	private $m_category_comment	= '';
	private $m_active			= 1;

	// ACCESSOR methods

	public function get_category_id() {
		return $this->m_category_id;
	}
	public function get_category_name() {
		return $this->m_category_name;
	}
	public function get_category_comment() {
		return $this->m_category_comment;
	}
	public function get_active() {
		return $this->m_active;
	}


	public function Init_product_category (
		$category_name,
		$category_comment,
		$active,
		$category_id = -1 )
	{
		// validate
		$error = '';
		if (trim ($category_name) == '')
			$error = 'You must enter a category name';
		
		$this->m_category_name		= $category_name;
		$this->m_category_comment	= $category_comment;
		$this->m_active				= $active;
		$this->m_category_id		= $category_id;

		return $error;
	}

	public function Load_product_category ($category_id)
	{
		$error = '';
		// Modified 1/26/2005:  -1 will load "All" category
		if ($category_id == -1)
		{
			$this->m_category_name = 'All';
			return $error;
		}

		$sql = "SELECT * FROM ProductCategories \n".
			"WHERE category_id = $category_id ";
		db_connect();
		$rs = mysql_query ($sql);
		$error = db_error ($rs, $sql);
		if ($error == '')
		{
			$row = mysql_fetch_array ($rs, MYSQL_ASSOC);
			$this->m_category_id		= $category_id;
			$this->m_category_name		= addslashes ($row['category_name']);
			$this->m_category_comment	= addslashes ($row['category_comment']);
			$this->m_active				= $row['active'];
		}

		mysql_close();
		return $error;
	}

	// Generates a key-> value data list
	public function Get_product_category_list ($active = 1)
	{
		$error = '';
		$category_list = array();
		$sql = "SELECT category_id, category_name FROM ProductCategories \n".
			"WHERE active = $active \n".
			"ORDER BY category_name ";
		db_connect();
		$rs = mysql_query ($sql);
		$error = db_error ($rs, $sql);

		if ($error == '')
		{
			while ($row = mysql_fetch_array ($rs, MYSQL_ASSOC))
			{
				$category_list[$row['category_id']] = $row['category_name'];
			}
		}
		mysql_close();

		return $category_list;
	}

}	// END ProductCategory class