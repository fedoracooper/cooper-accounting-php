<?
/*	Transaction Class
	Created 10/11/2004 (pulled out of include.php)
*/

// TRANSACTION class
//------------------------------------------------------------------------------
// As with accounts, maintain all member variables with slashes added.
class Transaction
{	
	private	$m_trans_id			= -1;	//hidden form var
	private $m_ledger_id		= -1;	//For each transaction sub-item
	private $m_audit_id			= -1;	//If the ledger item was audited
	private	$m_login_id			= -1;

	private	$m_trans_descr		= '';
	private	$m_trans_time		= 0;	//must be initialized
	private $m_trans_str		= '';	//incorrectly entered date
	private	$m_accounting_time	= 0;	//must be initialized
	private $m_accounting_str	= '';	//incorrectly entered date
	private	$m_trans_vendor		= '';
	private	$m_trans_comment	= NULL;
	private	$m_check_number		= NULL;
	private	$m_gas_miles		= NULL;
	private	$m_gas_gallons		= NULL;
	private $m_updated_time		= NULL;
	private $m_trans_status		= 1;	// 0=unpaid (to-do), 1=paid (fulfilled)

	private $m_repeat_count		= 1;	// # of times to store new transaction
	private	$m_account_display	= '';
	private	$m_ledger_amount	= NULL;
	private $m_audit_balance	= NULL;
	private $m_ledger_total		= NULL;
	private $m_ledgerL_list		= array();	//array of account_id=>ledger_amount
	private $m_ledgerR_list		= array();


	function __construct ()
	{
		// Initialize date to today
		$this->m_trans_time = time();	 //date ('n/j/Y');
		$this->m_accounting_time = $this->m_trans_time;
	}

	// ACCESSOR methods
	public function get_trans_id() {
		return $this->m_trans_id;
	}
	public function get_ledger_id() {
		return $this->m_ledger_id;
	}
	public function get_audit_id() {
		return $this->m_audit_id;
	}
	public function get_login_id() {
		return $this->m_login_id;
	}
	public function get_trans_descr() {
		return stripslashes ($this->m_trans_descr);
	}
	public function get_trans_date() {
		$return_date = '';
		if ($this->m_trans_time == -1)
			return $this->m_trans_str;
		else
			return date (DISPLAY_DATE, $this->m_trans_time);
	}
	public function get_trans_date_sql() {
		// mySQL-formatted date
		if ($this->m_trans_time > 0)
			return date (SQL_DATE, $this->m_trans_time);
		else
			return '';
	}
	public function get_accounting_date($blank= true, $short= false) {
		if ($this->m_accounting_time == $this->m_trans_time
			&& $blank && $this->m_accounting_time > -1)
			// same as transaction date; show blank field
			return '';
		elseif ($this->m_accounting_time == -1)
		{
			// incorrect date was entered; return this date
			return $this->m_accounting_str;
		}
		else
		{		
			if ($short)
				// cut off last 2 digits of year
				return date ('m/d/y', $this->m_accounting_time);
			else
				// normal date
				return date (DISPLAY_DATE, $this->m_accounting_time);
		}
	}
	public function get_accounting_date_sql() {
		// mySQL-formatted date
		//return convert_date ($this->m_accounting_date, 1);
		return date (SQL_DATE, $this->m_accounting_time);
	}
	public function get_trans_vendor() {
		return stripslashes ($this->m_trans_vendor);
	}
	public function get_trans_comment() {
		if (is_null ($this->m_trans_comment))
			return '';	// don't stripslashes if the value is NULL
		else
			return stripslashes ($this->m_trans_comment);
	}
	public function get_check_number() {
		if (is_null ($this->m_check_number))
			return '';
		else
			return $this->m_check_number;
	}
	public function get_gas_miles($thousands = true) {
		if (is_null ($this->m_gas_miles))
			return '';
		else
		{
			$raw = $this->m_gas_miles;
			if ($thousands && is_numeric ($raw))
				// display using thousands separator
				return number_format ($raw, 0);
			else
				return $raw;
		}
	}
	public function get_gas_miles_trimmed() {
		$raw = $this->m_gas_miles;
		// if this ends in .0, then trim
		if (strstr($raw, '.0') != false)
			return substr($raw, 0, strlen($raw) - 2);
		else
			return $raw;
	}
	public function get_gas_gallons() {
		if (is_null ($this->m_gas_gallons))
			return '';
		else {
			// trim off extra zeroes; no more than 999.9
			return substr($this->m_gas_gallons, 0, 5);
		}
	}
	public function get_updated_time() {
		if (is_null ($this->m_updated_time))
			return '';
		else
			return date (LONG_DATE, $this->m_updated_time);
	}
	public function get_trans_status() {
		return $this->m_trans_status;
	}
	public function get_repeat_count() {
		return $this->m_repeat_count;
	}
	public function get_account_display() {
		return stripslashes ($this->m_account_display);
	}
	public function get_ledger_amount($plain = false) {
		// Format decimal points
		if (is_null ($this->m_ledger_amount))
			return '';
		elseif ($plain)
			return $this->m_ledger_amount;
		else
		{
			$numStr = number_format ($this->m_ledger_amount, 2);
			// Make sure that it is a significant negative #
			if ($this->m_ledger_amount < -0.001)
			{
				// Negative number
				return '<span style="color: red;">$'.
					$numStr. '</span>';
			}
			else
			{
				return '$'. $numStr;
			}
		}
	}
	public function get_ledger_total($plain = false) {
		if (is_null ($this->m_ledger_total))
			return '';
		elseif ($plain)
			return $this->m_ledger_total;
		else
		{
			$numStr = number_format ($this->m_ledger_total, 2);
			if ($this->m_ledger_total < -0.001)
				return '<span style="color: red;">$'. $numStr. '</span>';
			else
				return '$'. $numStr;
		}
	}
	public function set_ledger_total($total) {
		if (is_numeric ($total))
		{
			if (abs( $total ) < 0.001)
			{
				// Round to zero to avoid unnecessary negative signs
				$total = 0.0;
			}
			$this->m_ledger_total = $total;
		}
	}
	public function get_audit_balance() {
		return $this->m_audit_balance;
	}
	public function get_ledgerL_list() {
		return $this->m_ledgerL_list;
	}
	public function get_ledgerR_list() {
		return $this->m_ledgerR_list;
	}


	// All these arguments are assumed to have slashes added
	// (usually via magic quotes).
	// This is called after hitting the Save button.
	public function Init_transaction (
		$login_id,
		$trans_descr,
		$trans_date,
		$accounting_date,
		$trans_vendor,
		$trans_comment,
		$check_number,
		$gas_miles,
		$gas_gallons,
		$trans_status,
		$trans_id = -1,
		$repeat_count = 1,
		$account_display = '',
		$ledger_amount = NULL,
		$ledger_id = -1,
		$audit_id = -1,
		$audit_balance = 0.0,
		$ledgerL_list = array(),
		$ledgerR_list = array())
	{

		//truncate comment to 1000 chars
		$trans_comment = substr ($trans_comment, 0, 1000);
		$trans_time	= parse_date ($trans_date);
		if (trim ($accounting_date) == '')
		{	// no accounting date; set it to transaction date
			$accounting_date = $trans_date;
		}
		$accounting_time	= parse_date ($accounting_date);
		
		$this->m_login_id			= $login_id;
		$this->m_trans_descr		= $trans_descr;
		$this->m_trans_time			= $trans_time;
		$this->m_accounting_time	= $accounting_time;
		$this->m_trans_vendor		= $trans_vendor;

		$trans_comment = trim ($trans_comment);
		if ($trans_comment == '')
			$this->m_trans_comment = NULL;
		else
			$this->m_trans_comment = $trans_comment;

		if ($check_number == '')
			$this->m_check_number = NULL;
		else
			$this->m_check_number = $check_number;

		if ($gas_miles == '')
			$this->m_gas_miles = NULL;
		else
			// strip thousands separators
			$this->m_gas_miles = str_replace(',', '', $gas_miles);

		if ($gas_gallons == '')
			$this->m_gas_gallons = NULL;
		else
			$this->m_gas_gallons = $gas_gallons;

		$this->m_trans_status		= $trans_status;
		$this->m_trans_id			= $trans_id;
		$this->m_repeat_count		= $repeat_count;
		$this->m_account_display	= $account_display;
		$this->m_ledger_amount		= $ledger_amount;
		$this->m_ledger_id			= $ledger_id;
		$this->m_audit_id			= $audit_id;
		$this->m_audit_balance		= $audit_balance;
		$this->m_ledgerL_list		= $ledgerL_list;
		$this->m_ledgerR_list		= $ledgerR_list;


		// VALIDATE
		$error = '';

		$ledger_total = 0.0;	//total of LHS & RHS; must equal 0
		$ledger_list = array_merge ($ledgerL_list, $ledgerR_list);
		// 0=ledger_id, 1=account_id/account_debit, 2=amount
		foreach ($ledger_list as $ledger_data)
		{
			if ($ledger_data[1] == '-1' && $ledger_data[2] != '') {
				// a number without an account has been specified
				return $error = "You must select an account for your amount";
			}
			if ($ledger_data[0] < 0)
			{
				// new ledger entry; need full verification
				if (!is_numeric ($ledger_data[2]))
				{
					return $error = "You must enter a number for the amount '".
						$ledger_data[2]. "'";
				}
			}
			else
			{
				// Existing ledger entry
				if (trim ($ledger_data[2]) != ''
					&& !is_numeric ($ledger_data[2]))
				{
					// Existing ledger with non-empty, non-numeric amount
					return $error = "You must enter a numeric amount or no amount: '".
						$ledger_data[2]. "'";
				}
			}
			// Total up amounts multiplied by debit (1 or -1)
			$accountArr = explode(',', $ledger_data[1]);
			if (count ($accountArr) == 2) {
				// when deleting ledger entries, there may be no account
				$ledger_total += ($ledger_data[2] * (float)$accountArr[1]);
			}
		}

		if (abs ($ledger_total) > .001)
			$error = "Transaction must total to zero; it currently totals \$".
				round($ledger_total, 3);
		elseif (trim ($trans_descr) == '')
			$error = 'You must enter a description of the transaction';
		elseif ($trans_time == -1) {
			$error = 'Transaction Date is invalid';
			$this->m_trans_str = $trans_date;
			$this->m_accounting_str = $accounting_date;
		}
		elseif ($accounting_time == -1) {
			$error = 'Accounting Date is invalid';
			$this->m_accounting_str = $accounting_date;
		}
		elseif (!is_numeric ($repeat_count)) {
			// Any non-numeric value is changed to 1
			$this->m_repeat_count = 1;
		}
		elseif ($check_number != '' && !is_numeric ($check_number)) {
			$error = 'Check number is not a whole number';
		}
		elseif ($gas_miles != '' && !is_numeric ($this->get_gas_miles(false))) {
			$error = 'Gas mileage is not a number';
		}
		elseif ($gas_gallons != '' && !is_numeric ($gas_gallons)) {
			$error = 'Gallons are not numeric';
		}
		// 12/4/2004 change:  can have just 1 entry with zero value
		elseif (count ($ledger_list) < 1)
			$error = 'You must have at least one ledger entry to save';
		

		return $error;
	}

	// Add slashes to all string fields
	public function Load_transaction ($trans_id)
	{
		$sql = "SELECT * from Transactions ".
			"WHERE trans_id = :trans_id ";

		$pdo = db_connect_pdo();
		if (!is_object($pdo)) {
			// return error msg
			return $pdo;
		}

		$ps = $pdo->prepare($sql);
		$ps->bindParam(':trans_id', $trans_id, PDO::PARAM_INT);
		$success = $ps->execute();
		if (!$success) {
			return get_pdo_error($ps);
		}

		$row = $ps->fetch(PDO::FETCH_ASSOC);
		if ($row === FALSE) {
			return get_pdo_error($ps);
		}

		$this->m_trans_id			= $trans_id;
		$this->m_login_id			= $row['login_id'];
		$this->m_trans_descr		= addslashes ($row['trans_descr']);
		// convert dates from yyyy-mm-dd to mm/dd/yyyy
		$this->m_trans_time			= strtotime ($row['trans_date']);
		$this->m_accounting_time	= strtotime ($row['accounting_date']);
		$this->m_updated_time		= strtotime ($row['updated_time']);
		$this->m_trans_vendor		= addslashes ($row['trans_vendor']);
		if (is_null ($row['trans_comment']))
			$this->m_trans_comment = NULL;
		else
			$this->m_trans_comment	= addslashes ($row['trans_comment']);
		$this->m_check_number		= $row['check_number'];
		$this->m_gas_miles			= $row['gas_miles'];
		$this->m_gas_gallons		= $row['gas_gallons'];
		$this->m_trans_status		= $row['trans_status'];

		// Load individual ledger entries
		$sql = "SELECT le.ledger_id, le.account_id, le.ledger_amount, ".
			" a.equation_side, a.account_debit \n".
			"FROM LedgerEntries le \n".
			"INNER JOIN Accounts a ON ".
			"	a.account_id = le.account_id ".
			"WHERE le.trans_id= :trans_id ";
		$ps = $pdo->prepare($sql);
		$ps->bindParam(':trans_id', $trans_id, PDO::PARAM_INT);
		$success = $ps->execute();
		if (!$success) {
			return get_pdo_error($ps);
		}

		$this->m_ledgerL_list = array();
		$this->m_ledgerR_list = array();
		// loop for each ledger entry
		while ($row = $ps->fetch(PDO::FETCH_ASSOC))
		{
			$accountStr = $row['account_id']. ','. $row['account_debit'];
			$subarr = array ($row['ledger_id'], $accountStr,
				$row['ledger_amount']);
			if ($row['equation_side'] == 'L')		// LHS ledger account
				$this->m_ledgerL_list[] = $subarr;
			else	// RHS ledger account
				$this->m_ledgerR_list[] = $subarr;
		}

		$pdo = null;
	}


	/* Update 2/15/2006:  Externally-accessed function call, which will
		loop through the internal save function multiple times if necessary.
		The repeat function can only be used when inserting a new record:
		it will duplicate the record at a monthly interval.
	*/
	public function Save_repeat_transactions()
	{
		$repeat = $this->m_repeat_count;
		if( $this->m_trans_id > -1 )
		{
			// Updating an existing record; do not repeat.
			$repeat = 1;
		}

		$error = '';
		for( $i = 0; $i < $repeat; $i++ )
		{
			$error = $this->Save_transaction();

			if( $error != '' )
				return $error;

			
			if( $i+1 < $repeat )
			{
				// There is at least another repetition;
				// Increment month by 1 (transaction & accounting dates)
				// and reset the transaction ID to force another INSERT
				$this->m_trans_id = -1;
				$error = add_months ($this->m_trans_time, 1);
				$error = add_months ($this->m_accounting_time, 1);
				if( $error != '' )
					return $error;
			}
		}

		return $error;
	}


	// Slashes should already be added if needed.
	// This does an insert or update, depending on trans_id.
	// Need to convert NULL values into strings, and dates into
	// SQL-formatted dates.
	private function Save_transaction()
	{
		$error = '';

		// Formatting for save
		$trans_comment	= $this->m_trans_comment;
		$check_number	= $this->m_check_number;
		$gas_miles		= $this->m_gas_miles;
		$gas_gallons	= $this->m_gas_gallons;
		
		// no longer need special null handling ?
		/*
		if (is_null ($trans_comment))
			$trans_comment = 'NULL';
		else
			$trans_comment = "'$trans_comment'";	//enclose in quotes for non null
		if (is_null ($check_number))
			$check_number = 'NULL';
		if (is_null ($gas_miles))
			$gas_miles = 'NULL';
		if (is_null ($gas_gallons))
			$gas_gallons = 'NULL';
			*/

		// Query the audit table to check for conflicts
		$error = $this->Check_audits();
		if ($error != '')
		{
			return $error;
		}
		
		$pdo = db_connect_pdo();
		$pdo->beginTransaction();
		
		if (is_string($pdo)) {
			return $pdo;
		}
		$ps = null;

		if ($this->m_trans_id == -1)
		{
			// Either this hasn't been inserted, or we are doing repeat
			// insertions.
			// INSERT
			$sql = "INSERT INTO Transactions \n".
				"(login_id, trans_descr, trans_date, accounting_date, ".
				" trans_vendor, trans_comment, check_number, gas_miles, ".
				" gas_gallons, trans_status) \n".
				"VALUES( :login_id, :descr, :trans_date, " .
				" :accounting_date, :vendor, :comment, :check_num, " .
				" :gas_miles, :gas_gallons, :status ) ";
			$ps = $pdo->prepare($sql);
		}
		else
		{
			// UPDATE
			$sql = "UPDATE Transactions \n".
				"SET login_id = :login_id, ".
				" trans_descr = :descr, ".
				" trans_date = :trans_date, ".
				" accounting_date = :accounting_date, ".
				" trans_vendor = :vendor, ".
				" trans_comment = :comment, ".	// single quotes are included in the var
				" check_number = :check_num, ".
				" gas_miles = :gas_miles, ".
				" gas_gallons = :gas_gallons, ".
				" trans_status = :status \n".
				"WHERE trans_id = :trans_id ";
			$ps = $pdo->prepare($sql);
			// the only additional param is the trans id
			$ps->bindParam(':trans_id', $this->m_trans_id, PDO::PARAM_INT);
		}
		
		// set all generic params
		$ps->bindParam(':login_id', $this->m_login_id, PDO::PARAM_INT);
		$ps->bindParam(':descr', $this->m_trans_descr);
		$transDate = $this->get_trans_date_sql();
                $ps->bindParam(':trans_date', $transDate);
                $accDate = $this->get_accounting_date_sql();
                $ps->bindParam(':accounting_date', $accDate);
		$ps->bindParam(':vendor', $this->m_trans_vendor);
		$ps->bindParam(':comment', $trans_comment);
		$ps->bindParam(':check_num', $check_number);
		$ps->bindParam(':gas_miles', $gas_miles);
		$ps->bindParam(':gas_gallons', $gas_gallons);
		$ps->bindParam(':status', $this->m_trans_status, PDO::PARAM_INT);
		
		$success = $ps->execute();
		$error = get_pdo_error($ps);
		if ($error != '') {
			return $error;
		}
		
		$ledger_inserts = 0;

		if ($this->m_trans_id == -1)
		{
			// get id from the insert
			$this->m_trans_id = get_auto_increment($pdo);
			if ($this->m_trans_id < 1) {
				return 'Invalid auto_increment val: ' . $this->m_trans_id;
			}
		}
		
		// prepare all queries
		$psInsert = $pdo->prepare("INSERT INTO LedgerEntries \n".
			"(trans_id, account_id, ledger_amount) \n".
			"VALUES(:trans_id, :account_id, :ledger_amount)");
		
		$psDelete = $pdo->prepare("DELETE from LedgerEntries \n".
			"WHERE ledger_id = :ledger_id");
		
		$psUpdate = $pdo->prepare("UPDATE LedgerEntries \n".
						"SET account_id= :account_id, ".
						" ledger_amount= :ledger_amount \n".
						"WHERE ledger_id= :ledger_id ");
		$ps = NULL;

		// insert the individual ledger entries
		// Combine the LHS & RHS lists
		$ledger_list = array_merge ($this->m_ledgerL_list,
			$this->m_ledgerR_list);
		foreach ($ledger_list as $ledger_data)
		{
			// 0=ledger_id, 1=account_id/account_debit, 2=amount
			$accountArr = explode(',', $ledger_data[1]);
			if ($ledger_data[0] == -1)
			{
				// no ledger_id; new record.
				$psInsert->bindParam(':trans_id', $this->m_trans_id, PDO::PARAM_INT);
				$psInsert->bindParam(':account_id', $accountArr[0], PDO::PARAM_INT);
				$psInsert->bindParam(':ledger_amount', $ledger_data[2]);
				$ps = $psInsert;
			}
			else
			{
				if (trim ($ledger_data[2] == ''))
				{
					// An existing ledger entry is being deleted
					$psDelete->bindparam(':ledger_id', $ledger_data[0], PDO::PARAM_INT);
					$ps = $psDelete;
				}
				else
				{
					// UPDATE an existing ledger entry
					$psUpdate->bindParam(':account_id', $accountArr[0], PDO::PARAM_INT);
					$psUpdate->bindParam(':ledger_amount', $ledger_data[2]);
					$psUpdate->bindParam(':ledger_id', $ledger_data[0], PDO::PARAM_INT);
					$ps = $psUpdate;
				}
			}
			
			$success = $ps->execute();
			if (!$success) {
				return get_pdo_error($ps);
			}

			$ledger_inserts += $ps->rowCount();
		}
		
		$success = $pdo->commit();
		if (!$success) {
			return get_pdo_error($pdo);
		}

		$pdo = null;
	}


	/*	This internal function is used to verify that we won't violate
		any account audits with a save.
		A return value of empty string indicates we are okay; otherwise
		a string will be returned with a reason for the violation.
		check_original:  whether we are assuming this is the original DB data.
	*/
	private function Check_audits()
	{
		$error = '';

		// Loop through the ledger entries
		$accounts = '';
		$ledger_list = array_merge ($this->m_ledgerL_list,
			$this->m_ledgerR_list);

		foreach ($ledger_list as $ledger_data)
		{
			if ($accounts != '')
			{
				$accounts .= ", ";
			}

			// 0=ledger_id, 1=account_id/account_debit, 2=amount
			$accountArr = explode( ',', $ledger_data[ 1 ] );
			// Store the account ID of every ledger ID in a SQL list
			$accounts .= $accountArr[ 0 ];
		}

		$oldTrans = null;
		$oldDate = null;
		$newDate = $this->get_accounting_date_sql();
		$minDate = $newDate;
		if ($this->m_trans_id > -1)
		{
			// Find out the original record's date
			$oldTrans = new Transaction();
			$oldTrans->Load_transaction( $this->m_trans_id );
			$oldDate = $oldTrans->get_accounting_date_sql();
			$minDate = min($oldDate, $newDate);
		}


		// Look for any audits that touch any accounts being updated, with
		// an accounting date on or before the audit date
		$sql = "SELECT aa.audit_date, a.account_name, le.account_id \n".
			"FROM AccountAudits aa \n".
			"INNER JOIN LedgerEntries le ON le.ledger_id = aa.ledger_id \n".
			"INNER JOIN Accounts a ON a.account_id = le.account_id \n".
			"WHERE le.account_id IN( $accounts ) \n".
			"	AND aa.audit_date >= '$minDate' ".
			"ORDER BY aa.audit_date DESC ";

		$conn = db_connect();
		$rs = mysql_query( $sql, $conn );
		$error = db_error( $rs, $sql );
		if ($error != '')
		{
			mysql_close( $conn );
			return $error;
		}

		// Loop through all potentially conflicting audit records until
		// we find an error or we exhaust the list.
		while ($row = mysql_fetch_array( $rs, MYSQL_ASSOC ))
		{
			$time = strtotime( $row[ 'audit_date' ] );
			$date = date( DISPLAY_DATE, $time );

			if ($this->m_trans_id <= -1)
			{
				// New transaction crosses old audit period.
				$error = "This transaction would affect the account ".
					"'{$row['account_name']}', which has already been audited ".
					"up to $date.";
			} else
			{
				// One of the transaction items has been audited.
				// Load up the original record and check if the account
				// ledger value has changed.
				$accountId = $row[ 'account_id' ];
				$auditDate = $row[ 'audit_date' ];
				$time = strtotime($auditDate);
				$date = date( DISPLAY_DATE, $time );
				$oldTime = strtotime($oldDate);
				if ($oldDate != $newDate)
				{
					// The conflict is due to a date change
					if ($newDate > $auditDate)
					{
						$error = "Cannot change the accounting date from ".
							date(DISPLAY_DATE, $oldTime) . " for this ".
							"transaction, as the account ". $row['account_name'].
							" has been audited up to $date.";
					} else
					{
						$error = "This transaction's accounting date cannot ".
							"change, due to an account audit on date $date and account ".
							$row[ 'account_name' ]. "; please do not change the ".
							"accounting date.";
					}
				}

				$oldValue = $oldTrans->Get_ledger_value( $accountId );
				$newValue = $this->Get_ledger_value( $accountId );
				if ($error == '' && abs( $oldValue - $newValue ) > 0.001)
				{
					// The audited account has changed
					$error = "This transaction violates a past account audit. ".
						"The account '{$row[ 'account_name' ]}' was audited up ".
						"to date $date; please change the transaction accounting ".
						"date.";
				}
			}

			if ($error != '')
			{
				// If we found an audit violation, exit now.
				break;
			}
		}	// End while loop through audits

		mysql_close( $conn );
		return $error;
	}

	/*	Lookup the ledger value in this transaction for the given account ID.
		If no value is found, null will be returned.
	*/
	public function Get_ledger_value( $accountId )
	{
		if ($accountId === null)
		{
			return null;
		}

		$ledgerList = array_merge ($this->m_ledgerL_list,
			$this->m_ledgerR_list);
		foreach ($ledgerList as $ledgerItem)
		{
			// 0=ledger_id, 1=account_id/account_debit, 2=amount
			$accountArr = explode( ',', $ledgerItem[ 1 ] );
			$itemAccountId = $accountArr[ 0 ];
			if ($itemAccountId == $accountId)
			{
				// Return the amount for the matching account ID
				return $ledgerItem[ 2 ];
			}
		}

		// Not found
		return null;
	}


	// Delete the current transaction from the database
	public function Delete_transaction()
	{
		$error = '';
		if ($this->m_trans_id < 0)
			$error = "Unable to delete transaction.";
		else
		{
			// Delete the ledger entries, then the transactions
			$sql = "DELETE FROM LedgerEntries \n".
				"WHERE trans_id = $this->m_trans_id ";
			db_connect();
			$rs = mysql_query ($sql);
			$error = db_error ($rs, $sql);
			if ($error == '')
			{
				// Delete transactions
				$sql = "DELETE FROM Transactions \n".
					"WHERE trans_id = $this->m_trans_id ";
				$rs = mysql_query ($sql);
				$error = db_error ($rs, $sql);
			}
		}

		mysql_close();
		return $error;
	}


	// This function creates a list of Transaction objects from
	// the database.
	// A limit of 0 means no limit; otherwise it counts the number of
	// rows prior to end_date & ignores the start_date
	public static function Get_transaction_list ($account_id, $start_date,
		$end_date, $limit, $search_text, $total_period, &$error)
	{
		$trans_list = array();

		// VALIDATION
		$error = '';
		$start_time	= parse_date ($start_date);
		$end_time	= parse_date ($end_date);
		if ($start_time == -1)
			$error = 'Invalid start date';
		elseif ($end_time == -1)
			$error = 'Invalid end date';
		elseif ($limit < 0)
			$error = 'Limit must be 0 (no limit) or greater';
		if ($error != '')
		{
			//echo $error;
			return $trans_list;
		}


		$sql = "SELECT account_debit, equation_side \n".
			"FROM Accounts WHERE account_id = $account_id";
		$account_debit = '';
		$equation_side = '';
		db_connect();
		$rs = mysql_query ($sql);
		if ($rs && $row = mysql_fetch_row ($rs))
		{
			$account_debit = $row[0];
			$equation_side = $row[1];
		}
		else
			return $trans_list;	//empty list

		// Convert to mysql dates
		$start_date_sql = convert_date ($start_date, 1);
		$end_date_sql = convert_date ($end_date, 1);

		$search_text_sql = '';
		if( $search_text != '' )
		{
			// Search for a search string across several fields.
			$search_text_sql = "  and( t.trans_descr like '%$search_text%' ".
				"   OR t.trans_vendor like '%$search_text%' ".
				"	OR t.trans_comment like '%$search_text%' )\n";

		}

		/*
			The ledger amount has to have the correct sign. If the ledger
			entry is for an account with the same normal balance
			(account debit) as the searched account, then it will be positive;
			otherwise it will be negative.

			The accounts are displayed by trans_date, but the totals
			are calculated based on the accounting_date. This pulls up
			any sub-accounts of the specified account. It is sorted in
			reverse order so that the limit clause can be used for the most
			recent dates.
		*/
		$sql = "SELECT t.trans_id, trans_descr, trans_date, accounting_date, ".
			" trans_vendor, trans_comment, check_number, gas_miles, ".
			" gas_gallons, trans_status, a.account_name, le.ledger_id, ".
			" aa.audit_id, aa.account_balance as audit_balance, ".
			" a2.account_name as account2_name, ".
			" a2.account_id as a2_account_id, a.account_id, ".
			"  (ledger_amount * a.account_debit * $account_debit) as amount \n". 
			"FROM Transactions t \n".
			"inner join LedgerEntries le on ".
			"	le.trans_id = t.trans_id ".
			"inner join Accounts a on ".
			"	le.account_id = a.account_id ".
			"left join Accounts a2 on ".	// join the account's parent, if it exists
			"	a.account_parent_id = a2.account_id \n".
			"left join AccountAudits aa ON ".
			"	aa.ledger_id = le.ledger_id ".
			"	AND a.account_id = $account_id \n".	// audit record must match exact account
			"WHERE (a.account_id = $account_id ".
			"  or a2.account_id = $account_id ".
			"  or a2.account_parent_id = $account_id ) ".
			"  and accounting_date >= '$start_date_sql' ".
			"  and accounting_date <= '$end_date_sql' \n".
			$search_text_sql .
			"ORDER BY accounting_date DESC, t.trans_id DESC, le.ledger_id DESC \n" ;
		if ($limit > 0)
			$sql .= "limit $limit ";

		$rs = mysql_query ($sql);
		$err = db_error ($rs, $sql);
		if ($err != '')
			echo $err;
		else
		{
			// Iterate through all the transactions
			$i = 0;
			while ($row = mysql_fetch_array ($rs, MYSQL_ASSOC))
			{
				$account_display = $row['account_name'];
				if (!is_null ($row['account2_name'])
					&& $row['a2_account_id'] != $account_id
					&& $row['account_id'] != $account_id)
				{
					// there is a parent account which is not
					// the currently selected one; display it.
					$account_display = $row['account2_name']. ':'.
						$account_display;
				}

				$trans = new Transaction();
				$trans->Init_transaction (
					$_SESSION['login_id'],
					$row['trans_descr'],
					convert_date ($row['trans_date'], 2),
					convert_date ($row['accounting_date'], 2),
					$row['trans_vendor'],
					$row['trans_comment'],
					$row['check_number'],
					$row['gas_miles'],
					$row['gas_gallons'],
					$row['trans_status'],
					$row['trans_id'],
					1,		// No repeats by default
					$account_display,
					$row['amount'],
					$row['ledger_id'],
					$row['audit_id'],
					$row['audit_balance']
				);
				
				$trans_list[$i] = $trans;
				$i++;
			}

			// The array order must be reversed (by the array key)
			krsort ($trans_list);

			// Loop through the transactions & calculate the totals
			$running_total = 0.0;
			$i = 0;
			$last_date = NULL;
			foreach ($trans_list as $trans)
			{
				$start_date_total = NULL;
				if ($equation_side == 'R')
				{	// Only do period totals for RHS accounts (revenue & expenses)
					// The min_date for the total depends on each transaction;
					// It is the first of the month or first of the year of the accounting date.
					$start_date_arr = explode ('/', $trans->get_accounting_date(false));
					switch ($total_period)
					{
						case 'month':
							// total starting from 1st of start_date month
							$start_date_total = $start_date_arr[2]. '-'. $start_date_arr[0].
								'-01';
							break;
						case 'year':
							$start_date_total = $start_date_arr[2]. '-01-01';
							break;
						case 'visible':
							// filter by visible dates
							$start_date_total = $start_date_sql;
					}
				}

				// Only query the account total for the first balance
				if ($i == 0)
				{
					$trans->Set_trans_balance ($account_id, $account_debit,
						$start_date_total);
					$running_total = $trans->get_ledger_total(true);
				}
				else
				{
					if ($last_date != $start_date_total)
					{
						// new period of data; reset the running total
						$running_total = $trans->get_ledger_amount(true);
					}
					else
					{
						// add to previous total
						$running_total += $trans->get_ledger_amount(true);
					}
					$trans->set_ledger_total ($running_total);
				}

				$i++;
				$last_date = $start_date_total;
			}
		}

		mysql_close();
		return $trans_list;
	}

	// Queries the total account balance based on the accounting date
	// and transaction id (when transactions occur on the same date, 
	// the lower transaction id is displayed first).
	// The dates refer to accounting dates, assuming we are comparing them
	// to transaction dates.; min_date can be null, in which
	// case it is ignored and everything up to trans_date will be totalled.
	// Some transactions have multiple ledger entries for the same account;
	// this is why we need to be able to total items with the same date
	// AND trans_id, but for lesser ledger_id's.  All these things
	// require that the list orders by date, trans_id, then ledger_id.
	// Assumes an open DB connection.
	private function Set_trans_balance ($account_id, $account_debit,
		$min_date)
	{
		$sql = "SELECT sum(ledger_amount * a.account_debit * $account_debit)".
			" as balance \n".
			"FROM LedgerEntries le \n".
			"INNER JOIN Transactions t on ".
			"	le.trans_id = t.trans_id ".
			"INNER JOIN Accounts a on ".
			"	le.account_id = a.account_id ".
			"LEFT JOIN Accounts a2 on ".
			"	a.account_parent_id = a2.account_id \n".
			"WHERE (a.account_id = $account_id OR ".
			"  a2.account_id = $account_id OR ".
			"  a2.account_parent_id = $account_id) ".
			"  AND (t.accounting_date < '{$this->get_accounting_date_sql()}' ".
			"		OR (t.accounting_date = '{$this->get_accounting_date_sql()}' ".
			"			AND (t.trans_id < $this->m_trans_id ".
			"				OR (t.trans_id = $this->m_trans_id ".
			"					AND le.ledger_id < {$this->get_ledger_id()} ) ) ) )";
		if (!is_null ($min_date))
		{
			// doing a period total, so add a minimum accounting date
			$sql.= "\n	AND t.accounting_date >= '$min_date' ";
		}
		// Time the query
		$time = microtime(true);
		$rs = mysql_query ($sql);
		$err = db_error ($rs, $sql);
		if ($err != '')
			echo $err;
		else
		{
			// Successful query
			$row = mysql_fetch_row ($rs);
			$elapsed = round( ( microtime(true) - $time) * 1000, 0 );
			//echo "Select time: $elapsed". "ms";
			if ($row)
				$this->m_ledger_total = $row[0] + $this->get_ledger_amount(true);
			else
				$this->m_ledger_total = 0.0;	// no rows found
		}
	}


}	//End Transaction class

?>
