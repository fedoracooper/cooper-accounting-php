<?php
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
	private $m_ledger_memo		= '';
	private $m_audit_balance	= NULL;
	private $m_ledger_total		= NULL;
	private $m_ledgerL_list		= array();	//array of account_id=>ledger_amount
	private $m_ledgerR_list		= array();
	private $m_prior_month		= 0;
	private $m_exclude_budget	= '0';
	private $m_closing_tx		= '0';
	private $m_account_savings = array();


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
		return htmlspecialchars($this->m_trans_descr);
	}
	public function get_trans_date() {
		$return_date = '';
		if ($this->m_trans_time == -1)
			return $this->m_trans_str;
		else
			return date (SQL_DATE, $this->m_trans_time);
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
				return date (SQL_DATE, $this->m_accounting_time);
		}
	}
	public function get_accounting_date_sql() {
		// mySQL-formatted date
		//return convert_date ($this->m_accounting_date, 1);
		return date (SQL_DATE, $this->m_accounting_time);
	}
	public function get_budget_date_sql() {
		if ($this->m_prior_month == 1) {
			// Prior Month is checked; get last day of prior
			// month (day 0 of the accounting month).
			$dateArr = getdate($this->m_accounting_time);
			$budgetTime = mktime(0, 0, 0, $dateArr['mon'], 0, $dateArr['year']);
			return date(SQL_DATE, $budgetTime);
		} else {
			// otherwise, just use accountint date
			return $this->get_accounting_date_sql();
		}
	}
	public function get_exclude_budget() {
		return $this->m_exclude_budget;
	}
	public function get_closing_tx() {
		return $this->m_closing_tx;
	}
	public function set_closing_tx($value) {
		$this->m_closing_tx = $value;
	}
	public function get_prior_month() {
		return $this->m_prior_month;
	}
	public function get_trans_vendor() {
		return htmlspecialchars($this->m_trans_vendor);
	}
	public function get_trans_comment() {
		if (is_null ($this->m_trans_comment))
			return '';	// don't process if the value is NULL
		else
			return htmlspecialchars($this->m_trans_comment);
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
	public function get_ledger_memo() {
		return $this->m_ledger_memo;
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
	public function &get_ledgerL_list() {
		return $this->m_ledgerL_list;
	}
	public function get_ledgerR_list() {
		return $this->m_ledgerR_list;
	}

	public function get_ledger_list() {
		return array_merge($this->m_ledgerL_list, $this->m_ledgerR_list);
	}

	public function set_account_savings($account_savings) {
	  $this->m_account_savings = $account_savings;
	}
	public function &get_account_savings() {
	  return $this->m_account_savings;
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
		$prior_month,
		$exclude_budget,
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
		$trans_time	= parse_sql_date ($trans_date);
		if (trim ($accounting_date) == '')
		{	// no accounting date; set it to transaction date
			$accounting_date = $trans_date;
		}
		$accounting_time	= parse_sql_date ($accounting_date);

		
		$this->m_login_id			= $login_id;
		$this->m_trans_descr		= $trans_descr;
		$this->m_trans_time			= $trans_time;
		$this->m_accounting_time	= $accounting_time;
		$this->m_trans_vendor		= $trans_vendor;
		$this->m_prior_month		= $prior_month;

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
		$this->m_exclude_budget		= $exclude_budget;
		$this->m_trans_id			= $trans_id;
		$this->m_repeat_count		= $repeat_count;
		$this->m_account_display	= $account_display;
		$this->m_ledger_amount		= $ledger_amount;
		$this->m_ledger_id			= $ledger_id;
		$this->m_audit_id			= $audit_id;
		$this->m_audit_balance		= $audit_balance;
		$this->m_ledgerL_list		= $ledgerL_list;
		$this->m_ledgerR_list		= $ledgerR_list;
		
		
		if ($trans_time == -1) {
			$error = 'Transaction Date is invalid';
			$this->m_trans_str = $trans_date;
			$this->m_accounting_str = $accounting_date;
		}
		elseif ($accounting_time == -1) {
			$error = 'Accounting Date is invalid';
			$this->m_accounting_str = $accounting_date;
		}
  }


	public function Validate() {
		$error = '';

		$debit_total = 0.0;	// Debits & Credits must match
		$credit_total = 0.0;
		$ledger_list = $this->get_ledger_list();

		foreach ($ledger_list as $ledgerEntry)
		{
			$error = $ledgerEntry->validate();
			if ($error != '') {
				return $error;
			}
			
			if ($ledgerEntry->accountId > 0 && $ledgerEntry->debit == 0) {
				// Account ID without Account Debit flag; invalid!
				return $error = "Account ID ". $ledgerEntry->accountId .
					" specified without Debit flag.";
			}
				
			// Total up amounts
			$debit_total += $ledgerEntry->getDebitAmountNumeric();
			$credit_total += $ledgerEntry->getCreditAmountNumeric();
		}

		$amountDiff = $debit_total - $credit_total;
		if (abs($amountDiff) > .001)
			$error = "Debits and Credits must be equal; current difference: \$".
				round($amountDiff, 3);
		elseif (trim ($this->m_trans_descr) == '')
			$error = 'You must enter a description of the transaction';
		elseif (!is_numeric ($this->m_repeat_count)) {
			// Any non-numeric value is changed to 1
			$this->m_repeat_count = 1;
		}
		elseif ($this->m_check_number != '' && !is_numeric ($this->m_check_number)) {
			$error = 'Check number is not a whole number';
		}
		elseif ($this->m_gas_miles != '' && !is_numeric ($this->get_gas_miles(false))) {
			$error = 'Gas mileage is not a number';
		}
		elseif ($this->m_gas_gallons != '' && !is_numeric ($this->m_gas_gallons)) {
			$error = 'Gallons are not numeric';
		}
		// 12/4/2004 change:  can have just 1 entry with zero value
		elseif (count ($ledger_list) < 1)
			$error = 'You must have at least one ledger entry to save';
		elseif ($this->m_trans_time == -1) {
			$error = 'Transaction Date is invalid';
		}
		elseif ($this->m_accounting_time == -1) {
			$error = 'Accounting Date is invalid';
		}
		

		return $error;
	}
	
	
	// When auto sinking, calculate the ledger entry amount of the
	// parent account, which will be the first ledger entry.
	public function Calculate_sinking_total() {
	  $total = 0.0;
	  foreach ($this->m_ledgerL_list as $ledgerEntry) {
	    $total -= $ledgerEntry->getAmount();
	  }
	  
	  // Set total in the first entry
	  $this->m_ledgerL_list[0]->setAmount($total);
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
		$this->m_trans_descr		= $row['trans_descr'];
		// convert dates from yyyy-mm-dd to mm/dd/yyyy
		$this->m_trans_time			= strtotime ($row['trans_date']);
		$this->m_accounting_time	= strtotime ($row['accounting_date']);
		$this->m_updated_time		= strtotime ($row['updated_time']);
		$this->m_trans_vendor		= $row['trans_vendor'];
		if (is_null ($row['trans_comment']))
			$this->m_trans_comment = NULL;
		else
			$this->m_trans_comment	= $row['trans_comment'];
		$this->m_check_number		= $row['check_number'];
		$this->m_gas_miles			= $row['gas_miles'];
		$this->m_gas_gallons		= $row['gas_gallons'];
		$this->m_trans_status		= $row['trans_status'];
		$this->m_exclude_budget		= $row['exclude_from_budget'];
		$this->m_closing_tx			= $row['closing_transaction'];
		$budget_time = strtotime($row['budget_date']);
		if ($budget_time < $this->m_accounting_time) {
			// Budget date is < Accounting date
			$this->m_prior_month = 1;
		} else {
			$this->m_prior_month = 0;
		}

		// Load individual ledger entries
		$sql = "SELECT le.ledger_id, le.account_id, le.ledger_amount, le.memo, ".
			" a.equation_side, a.account_debit \n".
			"FROM Ledger_Entries le \n".
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
			$ledger = new LedgerEntry();
			$ledger->ledgerId = $row['ledger_id'];
			$ledger->accountId = $row['account_id'];
			$ledger->debit = $row['account_debit'];
			$ledger->setAmount($row['ledger_amount']);
			$ledger->memo = $row['memo'];
			
			if ($row['equation_side'] == 'L')		// LHS ledger account
				$this->m_ledgerL_list[] = $ledger;
			else	// RHS ledger account
				$this->m_ledgerR_list[] = $ledger;
		}

		$pdo = null;
	}


	/* Update 2/15/2006:  Externally-accessed function call, which will
		loop through the internal save function multiple times if necessary.
		The repeat function can only be used when inserting a new record:
		it will duplicate the record at a monthly interval.
	*/
	public function Save_repeat_transactions($checkAudits = true)
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
			$error = $this->Save_transaction($checkAudits);

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
	private function Save_transaction($checkAudits)
	{
		$error = '';

		// Formatting for save
		$trans_comment	= $this->m_trans_comment;
		$check_number	= $this->m_check_number;
		$gas_miles		= $this->m_gas_miles;
		$gas_gallons	= $this->m_gas_gallons;

		// Query the audit table to check for conflicts
		if ($checkAudits) {
			$error = $this->Check_audits();
			if ($error != '')
			{
				return $error;
			}
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
				" gas_gallons, trans_status, budget_date, exclude_from_budget, ".
				" closing_transaction) ".
				"VALUES( :login_id, :descr, :trans_date, " .
				" :accounting_date, :vendor, :comment, :check_num, " .
				" :gas_miles, :gas_gallons, :status, :budget_date, :exclude_budget, ".
				" :closing_tx ) ";
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
				" trans_status = :status, ".
				" budget_date = :budget_date, ".
				" updated_time = current_timestamp, ".
				" exclude_from_budget = :exclude_budget, ".
				" closing_transaction = :closing_tx ".
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
		$budgetDate = $this->get_budget_date_sql();
		$ps->bindParam(':budget_date', $budgetDate);
		$ps->bindParam(':exclude_budget', $this->m_exclude_budget);
		$ps->bindParam(':closing_tx', $this->m_closing_tx);
		
		$success = $ps->execute();
		$error = get_pdo_error($ps);
		if ($error != '') {
			return $error . $ps->debugDumpParams();
		}
		
		$ledger_inserts = 0;

		if ($this->m_trans_id == -1)
		{
			// get id from the insert
			$this->m_trans_id = get_auto_increment($pdo, 'transactions_trans_id_seq');
			if ($this->m_trans_id < 1) {
				return 'Invalid auto_increment val: ' . $this->m_trans_id;
			}
		}
		
		// prepare all queries
		$psInsert = $pdo->prepare("INSERT INTO Ledger_Entries \n".
			"(trans_id, account_id, ledger_amount, memo) \n".
			"VALUES(:trans_id, :account_id, :ledger_amount, :memo)");
		
		$psDelete = $pdo->prepare("DELETE from Ledger_Entries \n".
			"WHERE ledger_id = :ledger_id");
		
		$psUpdate = $pdo->prepare("UPDATE Ledger_Entries \n".
						"SET account_id= :account_id, ".
						" ledger_amount= :ledger_amount, ".
						" memo = :memo \n".
						"WHERE ledger_id= :ledger_id ");
		$ps = NULL;

		// insert the individual ledger entries
		// Combine the LHS & RHS lists
		$ledger_list = $this->get_ledger_list();
		foreach ($ledger_list as $ledger)
		{
			$amount = $ledger->getAmount();
			if ($ledger->ledgerId == -1)
			{
				// no ledger_id; new record.
				$psInsert->bindParam(':trans_id', $this->m_trans_id, PDO::PARAM_INT);
				$psInsert->bindParam(':account_id', $ledger->accountId, PDO::PARAM_INT);
				$psInsert->bindParam(':ledger_amount', $amount);
				$psInsert->bindParam(':memo', $ledger->memo);
				$ps = $psInsert;
			}
			else
			{
				if ($ledger->toDelete)
				{
					// An existing ledger entry is being deleted
					$psDelete->bindparam(':ledger_id', $ledger->ledgerId, PDO::PARAM_INT);
					$ps = $psDelete;
				}
				else
				{
					// UPDATE an existing ledger entry
					$psUpdate->bindParam(':account_id', $ledger->accountId, PDO::PARAM_INT);
					$psUpdate->bindParam(':ledger_amount', $amount);
					$psUpdate->bindParam(':ledger_id', $ledger->ledgerId, PDO::PARAM_INT);
					$psUpdate->bindParam(':memo', $ledger->memo);
					$ps = $psUpdate;
				}
			}
			
			$success = $ps->execute();
			if (!$success) {
				return get_pdo_error($ps) . $ps->debugDumpParams();
			}

			$ledger_inserts += $ps->rowCount();
		}
		
		$success = $pdo->commit();
		if (!$success) {
			return get_pdo_error($pdo);
		}

		$pdo = null;
	}

	/* Get list of account IDs of ledger entries of this transaction.
	*/
	private function Get_ledger_account_ids() {

		$accountIds = array();
	
		foreach ($this->get_ledger_list() as $ledger)
		{
			// Prevent SQL injection by checking data type!
			if (!is_numeric($ledger->accountId)) {
				return "Unable to check audits with non-numeric accountId:"
					. $ledger->accountId;
			}
			$accountIds[] = $ledger->accountId;
		}

		return $accountIds;
	}

	/*
		Load account & subaccount totals into totalMap, based on
		this transaction's ledger entries and the given childAccountMap.
	*/
	private function Get_account_totals(&$totalMap, $childAccountMap) {
		$ledger_list = $this->get_ledger_list();
	
		foreach ($ledger_list as $ledger) {
			$accountId = $ledger->accountId;
			if (array_key_exists($accountId, $totalMap)) {
				// Direct audit:  increment the total
				$totalMap[$accountId] += $ledger->getAmount();
			}
    
			if (array_key_exists($accountId, $childAccountMap)) {
				// Child account of audit:  increment
				$parentId = $childAccountMap[$accountId];
				if (array_key_exists($parentId, $totalMap)) {
					$totalMap[$parentId] += $ledger->getAmount();
				}
			}
		}
	}

	private static function All_net_zero($totalMap) {
		$netZero = true;
		foreach ($totalMap as $accountId => $total) {
			if (abs($total) > 0.01) {
				error_log("Non-balanced account $accountId affects audit");
				$netZero = false;
			} else {
				error_log("Account $accountId affects audited account, but ".
					"net amount is zero, so permitting...");
			}
		}

		return $netZero;
	}

	/* Check for audited account net amounts from old vs. new transaction
	 which is being modified.  Return error msg when change is not allowed.
	 Each totalMap should have the same keys, with a value of 0.0 indicating
	 a net zero amount or no transactions.
	 */
	private static function Validate_total_changes($totalMap, $totalMapOld) {
		foreach ($totalMap as $accountId => $newTotal) {
			
			$oldTotal = $totalMapOld[$accountId];
			// net amount must be the same
			if (abs($newTotal - $oldTotal) > 0.001) {
				return "net amount must be the same as before";
			}
		}
	
		return '';
	}



	/*	This internal function is used to verify that we won't violate
		any account audits with a save.
		A return value of empty string indicates we are okay; otherwise
		a string will be returned with a reason for the violation.
		check_original:  whether we are assuming this is the original DB data.

		forDelete:  true when checking for tx deletion; false otherwise.
	*/
	private function Check_audits($forDelete = false)
	{
		$error = '';

		// Loop through the ledger entries
		$accounts = '';
		$ledger_list = $this->get_ledger_list();
		$accountIds = $this->Get_ledger_account_ids();
		$oldAccountIds = array();
		$allAccountIds = $accountIds;
		if (is_string($accountIds)) {
			// error msg
			return $accountIds;
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

			// Add account Ids to list for SQL search
			$oldAccountIds = $oldTrans->Get_ledger_account_ids();
			$ids = array_merge($accountIds, $oldAccountIds);
			$allAccountIds = array_unique($ids);
		}

		// build SQL list of account Ids
		$accounts = '';
		foreach ($allAccountIds as $accountId) {
			if ($accounts != '') {
				$accounts .= ", ";
			}
			$accounts .= $accountId;
		}

error_log("Account ID sql: $accounts");

		/* Look for any audits that touch any accounts in current or previous tx, with
		 an accounting date on or before the audit date.  Include
		 any child account IDs which are present in the list of ledger entries,
		 as all sub-accounts will affect the parent account balance.
		 */
		$sql = "SELECT MAX(aa.audit_date) as latest_audit_date, a.account_name, le.account_id, " .
			" child.account_id as child_account_id \n".
			"FROM Account_Audits aa \n".
			"INNER JOIN Ledger_Entries le ON le.ledger_id = aa.ledger_id \n".
			"INNER JOIN Accounts a ON a.account_id = le.account_id \n".
			"LEFT JOIN Accounts child ON child.account_parent_id = a.account_id \n".
			"WHERE ( le.account_id IN( $accounts ) OR ".
			"	child.account_id IN( $accounts ) ) \n".
			"	AND aa.audit_date >= :minDate ".
			"GROUP BY a.account_name, le.account_id, child.account_id ".
			"ORDER BY le.account_id, child.account_id ";

		$pdo = db_connect_pdo();
		$ps = $pdo->prepare($sql);
		$ps->bindParam(':minDate', $minDate);
		$success = $ps->execute();
		if (!$success) {
			return get_pdo_error($ps);
		}

		// Map of parent account ID -> total
		$totalMap = array();
		$totalMapOld = array();
		// List of SQL rows for each account with direct audit conflicts
		$parentRows = array();
		// Map of child account ID -> parent account ID
		$childAccountMap = array();

		$lastAccountId = -1;

		// Loop through all potentially conflicting audit records until
		// we find an error or we exhaust the list.
		while ($row = $ps->fetch(PDO::FETCH_ASSOC))
		{
			$accountId = $row[ 'account_id' ];
			$childId = $row[ 'child_account_id' ];
		
			if ($accountId != $lastAccountId) {
				// new audit account
				$totalMap[$accountId] = 0.0;
				$totalMapOld[$accountId] = 0.0;
				$lastAccountId = $accountId;
				$parentRows[] = $row;
			}
			if ($childId != NULL) {
				// Each row should be a new child account ID
				$childAccountMap[$childId] = $accountId;
			}
		}

		// Loop through all ledger entries
		// 1. Audited account?
		// 2. Subaccount of audit?
		$this->Get_account_totals($totalMap, $childAccountMap);
		if ($oldTrans != null) {
			$oldTrans->Get_account_totals($totalMapOld, $childAccountMap);
		}

		// Check for special audit exemption:
		// all ledger entries for audited accounts net to 0
		// due to transfers from / to subaccounts.
		$netZero = Transaction::All_net_zero($totalMap);
		$netZeroOld = Transaction::All_net_zero($totalMap);

		
		// Proceed with audit error message
		foreach ($parentRows as $row) {
			
			$auditDate = $row[ 'latest_audit_date' ];
			$time = strtotime( $auditDate );
			$date = date( DISPLAY_DATE, $time );
			$accountId = $row[ 'account_id' ];
			$childId = $row[ 'child_account_id' ];
			$accountName = $row['account_name'];
	
			if ($forDelete || $this->m_trans_id <= -1)
			{
				// New tx or Delete transaction:  only allow for net zero
				if ($netZero) {
					// special exemption:
					// allow new transactions on audited accounts
					// when subaccount net to zero (moving money to sink accounts)
					return '';
				}
				// New transaction crosses old audit period.
				$error = "This transaction affects the account ".
					"'$accountName', which has already been audited ".
					"up to $date.";
			} else
			{
				// One of the transaction items has been audited.
				// Load up the original record and check if the account
				// ledger value has changed.
				$oldTime = strtotime($oldDate);
				if ($oldDate != $newDate)
				{
					// The conflict is due to a date change
					if ($newDate > $auditDate)
					{
						$error = "Cannot change the accounting date from ".
							date(DISPLAY_DATE, $oldTime) . " for this ".
							"transaction, as the account $accountName ".
							"has been audited up to $date.";
					} else
					{
						$error = "This transaction's accounting date cannot ".
							"change, due to an account audit on date $date and account ".
							$accountName. "; please do not change the ".
							"accounting date.";
					}
				}

				$errorMsg = Transaction::Validate_total_changes(
					$totalMap, $totalMapOld);

				if ($errorMsg != '') {
					// The audited account NET AMOUNT has changed
					$error = "This transaction violates a past account audit: ".
						$errorMsg;
				}
			}

			if ($error != '')
			{
				// If we found an audit violation, exit now.
				break;
			}
		}	// End while loop through audits

		$pdo = null;
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

		$ledgerList = $this->get_ledger_list();
		foreach ($ledgerList as $ledger)
		{
			$itemAccountId = $ledger->accountId;
			if ($itemAccountId == $accountId)
			{
				// Return the amount for the matching account ID
				return $ledger->getAmount();
			}
		}

		// Not found
		return null;
	}


	// Delete the current transaction from the database
	public function Delete_transaction()
	{
		$error = '';
		if ($this->m_trans_id < 0) {
			return "Unable to delete transaction.";
		}

		$error = $this->Check_audits(true);
		if ($error != '') {
			return $error;
		}

		// Delete the ledger entries, then the transactions
		$sql = "DELETE FROM Ledger_Entries \n".
			"WHERE trans_id = :trans_id ";
		$pdo = db_connect_pdo();
		$ps = $pdo->prepare($sql);
		$ps->bindParam(':trans_id', $this->m_trans_id);
		$success = $ps->execute();
		
		if (!$success) {
			return get_pdo_error($ps);
		}
			
		// Delete transactions
		$sql = "DELETE FROM Transactions \n".
			"WHERE trans_id = :trans_id ";
		$ps = $pdo->prepare($sql);
		$ps->bindParam(':trans_id', $this->m_trans_id);
		$success = $ps->execute();
		
		if (!$success) {
			return get_pdo_error($ps);
		}
		
		$pdo = null;

		return $error;
	}


	// This function creates a list of Transaction objects from
	// the database.
	// A limit of 0 means no limit; otherwise it counts the number of
	// rows prior to end_date & ignores the start_date
	public static function Get_transaction_list ($account_id, $start_date,
		$end_date, $limit, $search_text, $total_period, &$error, &$warning)
	{
		$trans_list = array();

		// VALIDATION
		$error = '';
		$start_time	= parse_sql_date ($start_date);
		$end_time	= parse_sql_date ($end_date);
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
			"FROM Accounts WHERE account_id = :account_id";
		$account_debit = '';
		$equation_side = '';
		$pdo = db_connect_pdo();
		$ps = $pdo->prepare($sql);
		$ps->bindParam(':account_id', $account_id);
$t1 = microtime(true);
		$success = $ps->execute();
$t2 = microtime(true);
		
		if (!$success) {
			echo get_pdo_error($ps);
			return;
		}
		
		if ($row = $ps->fetch(PDO::FETCH_ASSOC))
		{
			$account_debit = $row['account_debit'];
			$equation_side = $row['equation_side'];
		}
		else
			return $trans_list;	//empty list

		// Convert to mysql dates
		$start_date_sql = $start_date;
		$end_date_sql = $end_date;

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
			" t.budget_date, t.exclude_from_budget, le.memo as ledger_memo, ".
			" t.closing_transaction, ".
			"  (ledger_amount * a.account_debit * :account_debit) as amount \n".
			"FROM Transactions t \n".
			"inner join Ledger_Entries le on ".
			"	le.trans_id = t.trans_id ".
			"inner join Accounts a on ".
			"	le.account_id = a.account_id ".
			"left join Accounts a2 on ".	// join the account's parent, if it exists
			"	a.account_parent_id = a2.account_id \n".
			"left join Account_Audits aa ON ".
			"	aa.ledger_id = le.ledger_id ".
			"	AND a.account_id = :account_id \n".	// audit record must match exact account
			"WHERE (a.account_id = :account_id ".
			"  or a2.account_id = :account_id ".
			"  or a2.account_parent_id = :account_id ) ".
			"  and accounting_date >= :start_date_sql ".
			"  and accounting_date <= :end_date_sql \n".
			$search_text_sql .
			"ORDER BY accounting_date DESC, t.trans_id DESC, le.ledger_id DESC \n" ;
		if ($limit > 0)
			$sql .= "limit $limit ";

		$ps = $pdo->prepare($sql);
		$ps->bindParam(':account_debit', $account_debit);
		$ps->bindParam(':account_id', $account_id);
		$ps->bindParam(':start_date_sql', $start_date_sql);
		$ps->bindParam(':end_date_sql', $end_date_sql);
$t3 = microtime(true);
		$success = $ps->execute();
$t4 = microtime(true);

global $execTime, $readTime;
$execTime += $t2 - $t1 + $t4 - $t3;
		
		if (!$success) {
			echo get_pdo_error($ps);
			return;
		}

		// Iterate through all the transactions
		$i = 0;
		while ($row = $ps->fetch(PDO::FETCH_ASSOC))
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
			$accountingTime = strtotime($row['accounting_date']);
			$budgetTime = strtotime($row['budget_date']);
			$priorMonth = 0;
			if ($budgetTime < $accountingTime) {
				$priorMonth = 1;
			}

			$trans = new Transaction();
			$trans->Init_transaction (
				$_SESSION['login_id'],
				$row['trans_descr'],
				convert_date ($row['trans_date'], 1),
				convert_date ($row['accounting_date'], 1),
				$row['trans_vendor'],
				$row['trans_comment'],
				$row['check_number'],
				$row['gas_miles'],
				$row['gas_gallons'],
				$row['trans_status'],
				$priorMonth,
				$row['exclude_from_budget'],
				$row['trans_id'],
				1,		// No repeats by default
				$account_display,
				$row['amount'],
				$row['ledger_id'],
				$row['audit_id'],
				$row['audit_balance']
			);
			$trans->m_ledger_memo = $row['ledger_memo'] . '';
			$trans->m_closing_tx = $row['closing_transaction'];
			
			$trans_list[$i] = $trans;
			$i++;
			
			$MAX_ROWS = 1000;
			if ($i > $MAX_ROWS) {
				// Avoid memory errors
				$warning = "Warning: truncated data to $MAX_ROWS rows";
				break;
			}
		}
		
$t5 = microtime(true);
$readTime += $t5 - $t4; 

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
				$start_date_arr = getdate($trans->m_accounting_time);
				switch ($total_period)
				{
					case 'month':
						// total starting from 1st of start_date month
						$start_date_total = $start_date_arr['year']. '-'. $start_date_arr['mon'].
							'-01';
						break;
					case 'year':
						$start_date_total = $start_date_arr['year']. '-01-01';
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

		$pdo = null;
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
		$sql = "SELECT sum(ledger_amount * a.account_debit * :account_debit)".
			" as balance \n".
			"FROM Ledger_Entries le \n".
			"INNER JOIN Transactions t on ".
			"	le.trans_id = t.trans_id ".
			"INNER JOIN Accounts a on ".
			"	le.account_id = a.account_id ".
			"LEFT JOIN Accounts a2 on ".
			"	a.account_parent_id = a2.account_id \n".
			"WHERE (a.account_id = :account_id OR ".
			"  a2.account_id = :account_id OR ".
			"  a2.account_parent_id = :account_id) ".
			"  AND (t.accounting_date < :accounting_date ".
			"		OR (t.accounting_date = :accounting_date ".
			"			AND (t.trans_id < :trans_id ".
			"				OR (t.trans_id = :trans_id ".
			"					AND le.ledger_id < :ledger_id ) ) ) )";
		if (!is_null ($min_date))
		{
			// doing a period total, so add a minimum accounting date
			$sql.= "\n	AND t.accounting_date >= :min_date ";
		}
		// Time the query
		$time = microtime(true);
		$pdo = db_connect_pdo();
		$ps = $pdo->prepare($sql);
		$ps->bindParam(':account_debit', $account_debit);
		$ps->bindParam(':account_id', $account_id);
		if (!is_null($min_date)) {
			$ps->bindParam(':min_date', $min_date);
		}
		$accounting_date_val = $this->get_accounting_date_sql();
		$ps->bindParam(':accounting_date', $accounting_date_val);
		$ps->bindParam(':trans_id', $this->m_trans_id);
		$ledger_id_val = $this->get_ledger_id();
		$ps->bindParam(':ledger_id', $ledger_id_val);
		$success = $ps->execute();
		
		if (!$success) {
			echo get_pdo_error($ps);
			return;
		}
		
		// Successful query
		$row = $ps->fetch(PDO::FETCH_NUM);
		$elapsed = round( ( microtime(true) - $time) * 1000, 0 );
		//echo "Select time: $elapsed". "ms";
		if ($row)
			$this->m_ledger_total = $row[0] + $this->get_ledger_amount(true);
		else
			$this->m_ledger_total = 0.0;	// no rows found
	}


}	//End Transaction class

?>
