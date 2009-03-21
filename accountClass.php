<?
/*	Account Class
	Created 10/11/2004 (pulled out of include.php)
*/

// ACCOUNT CLASS
//------------------------------------------------------------------------------
// The Account string fields should all have their quotation marks escaped.
// This way database updates will work seamlessly.  The accessor methods
// remove the slashes for display on forms.

class Account
{
	private	$m_account_id			= -1;
	private	$m_login_id				= -1;

	private	$m_account_parent_id	= NULL;
	private	$m_account_name			= '';
	private	$m_account_descr		= '';
	private	$m_account_debit		= 1;	// 1 = debit, -1 = credit
	private	$m_equation_side		= 'R';	// 'L' or 'R'
	private $m_monthly_budget		= 0.0;
	private $m_active				= 1;


	// ACCESSOR methods: called typically after load from DB
	public function get_account_id() {
		return $this->m_account_id;
	}
	public function get_login_id() {
		return $this->m_login_id;
	}
	public function get_account_parent_id() {
		if (is_null ($this->m_account_parent_id))
			return '-1';
		else
			return $this->m_account_parent_id;
	}
	public function get_account_name() {
		return stripslashes ($this->m_account_name);
	}
	public function get_account_descr() {
		return stripslashes ($this->m_account_descr);
	}
	public function get_account_debit() {
		return $this->m_account_debit;
	}
	public function get_equation_side() {
		return $this->m_equation_side;
	}
	public function get_monthly_budget() {
		return $this->m_monthly_budget;
	}
	public function get_active() {
		return $this->m_active;
	}

	// Initialize all the variables, with account_id being optional.
	// This is called after an account save is posted, so it is assumed
	// that all the string values have had their quotations escaped.
	// Returns an empty string on success, otherwise an error message.
	public function Init_account(
		$account_parent_id,
		$account_name,
		$account_descr,
		$account_debit,
		$equation_side,
		$monthly_budget,
		$account_id = -1,
		$active = 1)
	{
		// VALIDATE
		$error = '';
		if (trim ($account_name) == '')
			$error = 'You must enter an account name';
		elseif (!is_numeric($monthly_budget))
			$error = 'The monthly budget must be numeric';
		elseif (abs($monthly_budget) > 999999.99)
			$error = 'The monthly budget cannot be more than 6 digits, plus decimal';
		
		if ($error == '')
		{
			// validation successful, so populate
			$this->m_login_id			= $_SESSION['login_id'];
			if ($account_parent_id == '-1')
				// NULL will be represented by empty string when submitted in form
				$this->m_account_parent_id = NULL;
			else
				$this->m_account_parent_id	= $account_parent_id;
			$this->m_account_name		= $account_name;
			$this->m_account_descr		= $account_descr;
			$this->m_account_debit		= $account_debit;
			$this->m_equation_side		= $equation_side;
			$this->m_monthly_budget		= $monthly_budget;
			$this->m_account_id			= $account_id;
			$this->m_active				= $active;
		}

		return $error;
	}

	// When loading the account from the database, slashes need to be added
	// in case of SQL update.
	public function Load_account($account_id)
	{
		$sql = "Select * from Accounts where account_id = $account_id";
		$link = db_connect();
		$rs = mysql_query($sql, $link);
		$success = false;	//default

		if ($row = mysql_fetch_array($rs, MYSQL_ASSOC))
		{
			$this->m_account_id			= $account_id;
			$this->m_login_id			= $row['login_id'];
			$this->m_account_parent_id	= $row['account_parent_id'];
			$this->m_account_name		= addslashes ($row['account_name']);
			$this->m_account_descr		= addslashes ($row['account_descr']);
			$this->m_account_debit		= $row['account_debit'];
			$this->m_equation_side		= $row['equation_side'];
			$this->m_monthly_budget		= $row['monthly_budget'];
			$this->m_active				= $row['active'];

			$success = true;
		}

		mysql_close($link);
		return $success;	//indicates that no records were found
	}

	// Slashes have already been added to the member variables, so all
	// the inserts should be okay.
	public function Save_account()
	{
		$error = '';
		// Formatting for database
		$account_parent_id = $this->m_account_parent_id ;
		if ($account_parent_id === NULL)
		{
			$account_parent_id = 'NULL';
		}

		if ($this->m_account_id == -1)
		{
			// New account: perform an insert
			$sql = "INSERT INTO Accounts \n".
				"(login_id, account_parent_id, ".
				"account_name, account_descr, ".
				" account_debit, equation_side, monthly_budget, active) \n".
				"VALUES ($this->m_login_id, $account_parent_id, ".
				" '$this->m_account_name', '$this->m_account_descr', ".
				" $this->m_account_debit, '$this->m_equation_side', ".
				" $this->m_monthly_budget, ".
				" $this->m_active) ";
		}
		else
		{
			// Existing account; perform an update
			$sql = "UPDATE Accounts \n".
				"SET login_id = $this->m_login_id, ".
				"  account_parent_id = $account_parent_id, ".
				"  account_name = '$this->m_account_name', ".
				"  account_descr = '$this->m_account_descr', ".
				"  account_debit = $this->m_account_debit, ".
				"  equation_side = '$this->m_equation_side', ".
				"  monthly_budget = $this->m_monthly_budget, ".
				"  active = $this->m_active \n".
				"WHERE account_id = $this->m_account_id ";
		}

		db_connect();
		$rs = mysql_query($sql);
		$error = db_error ($rs, $sql);
		if ($error == '' && $this->m_account_id == -1)
		{
			// find the id just created in the insert
			$this->m_account_id = get_auto_increment();
		}

		mysql_close();
		return $error;
	}

	// Delete the current account
	public function Delete_account()
	{
		$error = '';
		$sql = "DELETE FROM Accounts \n".
			"WHERE account_id = $this->m_account_id ";
		db_connect();
		$rs = mysql_query ($sql);
		$error = db_error ($rs, $sql);

		mysql_close();
		return $error;
	}


	/*
		This method builds a list of account descriptions for use in building
		a dropdown menu. When called with
		only the login_id, all accounts are loaded; this is used when loading
		the account list for selection on the ledger page.

		The equation side filters for left or right side, but still displays
		the full account path. When filtering by account_parent_id, no parent
		account names are displayed. force_parent forces the query to filter
		by parent_account_id, even if it is -1.

		Params:
			login_id	User's login id; will always filter by this
			equation_side	Can be 'L' or 'R' to only show accounts from 1 side
			account_parent_id	Can specify a list of accounts with this parent
			force_parent
			show_debit		Include the debit value in the list key (account_id, debit)
			show_inactive	Show accounts that have a Active = 0 or 1
			top2_tiers		Do not show third-tier accounts

		All Accounts: parent = -1, force = false
		Top accounts: parent = NULL, force = false
		Sec accounts: parent = -1 to n, force = true	- filter, regardless of parent_id
		Ter accounts: parent = -1 to n, force = true

		The return list is of the form
			account_id => Account_display
		unless show_debit is true, in which case it is
			'account_id,account_debit' => Account_display
		For the full account list, only show active accounts.
		Otherwise show inactive as well, for editing.

		top2_tiers: when true, this will retrieve a list of the top 2 tiers 
		of accounts.

	*/
	public static function Get_account_list ($login_id, $equation_side = '',
		$account_parent_id = -1, $force_parent = false, $show_debit = false,
		$show_inactive = false, $top2_tiers = false)
	{
		$account_list = array();
		if ($login_id < 0)
			return $account_list;	// empty list

		if ($account_parent_id < 0 && !$force_parent)
		{
			// query all layers of accounts
			$sql =
				"SELECT a.account_id, ".
				"  concat( ifnull( concat(a3.account_name, ':'), ''), ".
				"    ifnull( concat( a2.account_name, ':'), ''), ".
				"    a.account_name) as account_display, ".
				"  a.account_debit, a.active \n".
				"FROM Accounts a \n".		// represents any account (top to bottom)
				"LEFT JOIN Accounts a2 on ".
				"  a.account_parent_id = a2.account_id \n".
				"LEFT JOIN Accounts a3 on ".
				"  a2.account_parent_id = a3.account_id \n".
				"WHERE a.login_id = $login_id ";
			if ($equation_side <> '')
			{
				$sql .= "and a.equation_side = '$equation_side' ";
			}
			if (!$show_inactive)
			{
				$sql .= "AND a.active = 1 ";
			}
			if ($top2_tiers)
			{
				// ignore rows at the third tier
				$sql .= "AND a3.account_id IS NULL ";
			}
			$sql .= "\n ORDER BY concat(ifnull( concat( a3.account_name, ':'), ''), ".
				"    ifnull( concat( a2.account_name, ':'), ''), ".
				"    a.account_name) ";
		}
		else
		{
			// query only one level of accounts
			$sql = "SELECT a.account_id, a.account_name as account_display, ".
				"  a.account_debit, a.active \n".
				"FROM Accounts a \n".
				"WHERE login_id = $login_id ";
			if ($account_parent_id > -1 || $force_parent)
			{
				$sql.= "  AND account_parent_id = $account_parent_id ";
			}
			else
			{
				$sql.= "  AND account_parent_id is NULL ";
			}
			$sql.= "\n ORDER BY a.account_name ";
		}

		db_connect();
		$rs = mysql_query($sql);
		$err = db_error ($rs, $sql);
		if ($err != '')
			echo $err;
		else
		{
			// loop through each account id and display name
			while ($row = mysql_fetch_array($rs, MYSQL_ASSOC))
			{
				$active = $row['active'];
				if ($show_debit)
					$key = $row['account_id']. ','. $row['account_debit'];
				else
					$key = $row['account_id'];
				$account_display = $row['account_display'];
				if ($active == 0)
					$account_display.= ' (inactive)';

				$account_list[$key] = $account_display;
			}
		}
		mysql_close();

		return $account_list;
	}

	
	/*	This function creates a list of monthly summaries, in descending 
		chronological order, of two selected accounts. Account2 is subracted
		from account1, with the total being displayed as well.

		The first two items will be Year to Date (YTD) and Month to Date (MTD),
		unless the end date is on the end of a year or month in the past. The
		YTD sums keep a running total for the year.

		The list is built in chronological order so that the YTD sums can be
		calculated; it is then reversed for display

		summary_list
			(YYYY-MM) => (month, year, account1_sum, account2_sum,
				account1_ytd, account2_ytd)

	*/
	public static function Get_summary_list ($account1_id, $account2_id,
		$start_date, $end_date, &$summary_list)
	{
		$summary_list = array();
		$error = '';

		// VALIDATE
		$start_time	= parse_date ($start_date);
		$end_time	= parse_date ($end_date);
		if ($start_time == -1)
			return 'Start date is invalid';
		elseif ($end_time == -1)
			return 'End date is invalid';
		$start_date_sql = date ('Y-m-d', $start_time);
		$end_date_sql = date ('Y-m-d', $end_time);

		// Query the normal balance of the selected accounts
		$sql = "SELECT a.account_id, a.account_debit ".
			"FROM Accounts a \n".
			"WHERE account_id IN ($account1_id, $account2_id) ";
		db_connect();
		$rs = mysql_query ($sql);
		$error = db_error ($rs, $sql);
		if ($error != '')
		{
			mysql_close();
			return $error;
		}
		$account1_debit = 1;
		$account2_debit = 1;
		while ($row = mysql_fetch_array ($rs, MYSQL_ASSOC))
		{
			if ($row['account_id'] == $account1_id)
				$account1_debit = $row['account_debit'];
			elseif ($row['account_id'] == $account2_id)
				$account2_debit = $row['account_debit'];
		}

		// SQL statement: group the summary by month & year (once per account)
		for ($i = 0; $i < 2; $i++)
		{
			$group_sql = "month(t.accounting_date), year(t.accounting_date) ";
			$month_sql = "month(t.accounting_date) as accounting_month, ";
			if ($i == 0 || $i == 2)
			{
				$account_id = $account1_id;
				$account_debit = $account1_debit;
			}
			elseif ($i == 1 || $i == 3)
			{
				$account_id = $account2_id;
				$account_debit = $account2_debit;
			}
			if ($i == 2 || $i == 3)
			{
				// yearly summary
				$group_sql = "year(t.accounting_date) \n";
				// count as month 13, as this sorts after december
				$month_sql = "13 as accounting_month, ";
			}

			$sql = "SELECT sum(ledger_amount * a.account_debit * $account_debit) ".
				"  as account_sum, $month_sql".
				"  year(t.accounting_date) as accounting_year \n".
				"FROM Transactions t \n".
				"INNER JOIN LedgerEntries le on le.trans_id = t.trans_id \n".
				"INNER JOIN Accounts a on a.account_id = le.account_id \n".
				"LEFT JOIN Accounts a2 on a.account_parent_id = a2.account_id \n".
				"WHERE (a.account_id = $account_id OR ".
				"  a2.account_id = $account_id OR ".
				"  a2.account_parent_id = $account_id) ".
				"  and t.accounting_date >= '$start_date_sql' ".
				"  and t.accounting_date <= '$end_date_sql' \n".
				"GROUP BY $group_sql \n".
				"ORDER BY year(accounting_date) ASC, month(accounting_date) ASC ";

			$rs = mysql_query ($sql);
			$error = db_error ($rs, $sql);
			if ($error != '')
			{
				mysql_close();
				return $error;
			}

			$ytd_total = 0;
			$last_key = '';
			$last_year = 0;
			// (YYYY-MM) => (month, year, account1_sum, account2_sum)
			while ($row = mysql_fetch_array ($rs, MYSQL_ASSOC))
			{
				$summary_year = $row['accounting_year'];
				if ($summary_year != $last_year)
				{
					// new year; reset the YTD totals
					$ytd_total = 0;
				}
				$summary_month = $row['accounting_month'];
				$summary_month = str_pad ($summary_month, 2, '0',
					STR_PAD_LEFT);
				$key = $summary_year. '-'. $summary_month;
				$ytd_total += $row['account_sum'];
				if ($i == 0 || $i == 2)
				{
					// account 1 query (always a new list item)
					$summary_list[$key] = array (
						(int)$summary_month,
						$summary_year,
						$row['account_sum'], 0,
						$ytd_total, 0
					);
					if ($last_key != '')
					{
						// for account YTD, when not on first row,
						// grab last month's value as default.
						// This is in case this month has no data;
						// the YTD should still stay the same.
//						$summary_list[$key][3] =
//							$summary_list[$last_key][3];
//						$summary_list[$key][5] =
//							$summary_list[$last_key][5];
					}
				}
				else
				{
					// account2: check for existing list item
					if (array_key_exists ($key, $summary_list))
					{
						$summary_list[$key][3] = $row['account_sum'];
						$summary_list[$key][5] = $ytd_total;
					}
					else
					{
						// no existing data for this month
						$summary_list[$key] = array (
							(int)$summary_month,
							$summary_year,
							0, $row['account_sum'],
							0, $ytd_total
						);
					}

				}

				$last_key = $key;
				$last_year = $summary_year;
			}	// row loop
		}	// account loop

		mysql_close();
		// re-sort by the key in descending order
		ksort ($summary_list);

		return $error;
	}	// End Get_summary_list


	/*	Created 10/28/2004
		This function calculates the total miles, gallons, and MPG
		for each Car expense account that has gas_gallons & gas_miles data.

		returns:
		//	array (account_name, accounting_year, num_records, total_miles, 
		//		total_gallons, total_dollars)
	*/
	public static function Get_gas_totals (&$totals)
	{
		$totals = array();
		$sql = "SELECT min(a.account_name) as account_name, ".
			"  count(*) as cnt, sum(gas_miles) as total_miles, ".
			"  sum(gas_gallons) as total_gallons, ".
			"  sum(ledger_amount) as total_dollars, ".
			"  year(accounting_date) as accounting_year \n".
			"FROM Transactions t \n".
			"INNER JOIN LedgerEntries le ON ".
			"  le.trans_id = t.trans_id \n".
			"INNER JOIN Accounts a ON ".
			"  a.account_id = le.account_id ".
			"  AND a.account_parent_id = $_SESSION[car_account_id] \n".
			"WHERE gas_gallons > 0 AND gas_miles > 0 \n".
			"GROUP BY a.account_id, year(t.accounting_date) \n".
			"ORDER BY year(accounting_date) DESC, a.account_name ";
		db_connect();
		$rs = mysql_query ($sql);
		$error = db_error($rs, $sql);
		if ($error != '')
			return $error;

		while ($row = mysql_fetch_array ($rs, MYSQL_ASSOC))
		{
			$totals[] = array (
				$row['account_name'],
				$row['accounting_year'],
				$row['cnt'],
				$row['total_miles'],
				$row['total_gallons'],
				$row['total_dollars']
			);
		}
		mysql_close();

		return $error;
	}	// End Gas totals


	/*
		Get breakdown of account dollars based on a parent account
		and a date range.
		Params:
			date range, account parent id
		Returns:
			error string:  empty when no error
	*/
	public static function Get_account_breakdown ($start_date, $end_date,
		$account_id, &$account_list)
	{
		$account_list = array();
		$error = '';
		// convert dates into mysql dates
		$start_sql = convert_date ($start_date, 1);
		$end_sql = convert_date ($end_date, 1);
		if ($account_id < 0)
			return 'You must enter a correct account id';
		elseif ($start_sql == '')
			return 'Start date is invalid.';
		elseif ($end_sql == '')
			return 'End date is invalid.';

		$sql = "SELECT sum( ledger_amount ) AS total_amount, ".
			"  ifnull( a2.account_id, a.account_id ) as account_id, ".
			"  min( ifnull( a2.account_name, a.account_name ) ) as name ".
			"FROM LedgerEntries le \n".
			"INNER JOIN Transactions t ON t.trans_id = le.trans_id \n".
			"INNER JOIN Accounts a ON le.account_id = a.account_id \n".
			"LEFT  JOIN Accounts a2 ON a.account_parent_id = a2.account_id ".
			"  AND a2.account_id <> $account_id \n".
			"WHERE ( a.account_parent_id= $account_id ".
			"  OR a2.account_parent_id= $account_id OR a.account_id = $account_id ) \n".
			"  AND t.accounting_date >=  '$start_sql' ".
			"  AND t.accounting_date <=  '$end_sql' \n".
			"GROUP BY IFNULL( a2.account_id, a.account_id ) \n".
			"ORDER BY total_amount DESC " ;

		db_connect();
		$rs = mysql_query ($sql);
		$error = db_error ($rs, $sql);
		if ($error != '')
			return $error;

		// account_list (account_name, account_total, account_id)
		while ($row = mysql_fetch_array($rs, MYSQL_ASSOC))
		{
			$account_list[$row['account_id']] = array ($row['name'], $row['total_amount'],
				$row['account_id']);
		}
		mysql_close();

		return $error;
	}

	public static function Get_monthly_budget_list ($parent_account_id, &$account_list)
	{
		$error = '';
		$sql = "SELECT ifnull(a2.account_id, a.account_id) as account_id, ".
			"  sum( a.monthly_budget ) + ".
			"  min(ifnull(a2.monthly_budget, 0.0)) as monthly_budget \n".
			"FROM Accounts a \n".
			"LEFT JOIN Accounts a2 ON a.account_parent_id = a2.account_id ".
			"  AND a2.account_id <> $parent_account_id \n".
			"WHERE (a.account_parent_id = $parent_account_id ".
			"  OR a2.account_parent_id = $parent_account_id ".
			"  OR a.account_id = $parent_account_id) ".
			"  AND a.active = 1 ".
			"GROUP BY ifnull(a2.account_id, a.account_id) \n".
			"ORDER BY monthly_budget ";
		
		db_connect();
		$rs = mysql_query($sql);
		$error = db_error($rs, $sql);
		if ($error != '')
			return $error;

		// Update map of account_id -> budget
		while ($row = mysql_fetch_array($rs, MYSQL_ASSOC))
		{
			$account_list[ $row['account_id'] ] = $row['monthly_budget'];
		}
		mysql_close();

		return $error;
	}


}	//End Account class

?>
