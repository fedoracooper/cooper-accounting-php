<?php
	/*	Account Audit Class
		Created 7/2/2006
		Purpose:  Capture a bit of auditing information
	*/

// ACCOUNT AUDIT class
//------------------------------------------------------------------------------
class AccountAudit
{
	private $m_audit_id			= -1;
	private $m_ledger_id		= -1;
	private $m_audit_time		= 0;
	private $m_audit_str		= '';
	private $m_account_balance	= 0.0;
	private $m_audit_comment	= '';

	private $m_updated_time		= NULL;
	private $m_account_name		= '';	// The ledger's account name


	function __construct()
	{

	}

	public function Load_basic_audit( $ledger_id, $account_balance )
	{
		$this->m_ledger_id			= $ledger_id;
		$this->m_account_balance	= $account_balance;

		// Query the database for the account name
		$sql = "SELECT a.account_name, t.accounting_date \n".
			"FROM Accounts a \n".
			"INNER JOIN Ledger_Entries le ON le.account_id = a.account_id \n".
			"INNER JOIN Transactions t ON t.trans_id = le.trans_id \n".
			"WHERE le.ledger_id = :ledger_id ";
		$pdo = db_connect_pdo();
		$ps = $pdo->prepare($sql);
		$ps->bindParam(':ledger_id', $ledger_id);
		$success = $ps->execute();
		if (!$success) {
			$this->m_account_name = get_pdo_error($ps);
			return;
		}
			
		$row = $ps->fetch(PDO::FETCH_ASSOC);
		$this->m_account_name	= $row[ 'account_name' ];
		$this->m_audit_time		= strtotime( $row[ 'accounting_date'] );

		$pdo = null;
	}

	// ACCESSOR methods
	public function get_audit_id()
	{
		return $this->m_audit_id;
	}
	public function get_ledger_id()
	{
		return $this->m_ledger_id;
	}
	public function get_audit_date()
	{
		if ($this->m_audit_time == -1)
		{
			// Invalid date entered
			return $this->m_audit_str;
		}

		return date( DISPLAY_DATE, $this->m_audit_time );
	}
	public function get_audit_date_sql()
	{
		if ($this->m_audit_time > 0)
		{
			return date( SQL_DATE, $this->m_audit_time );
		}

		return '';
	}
	public function get_account_balance( $plain = false )
	{
		if ($plain)
		{
			return $this->m_account_balance;
		}

		return '$'. number_format( $this->m_account_balance, 2 );
	}
	public function get_audit_comment()
	{
		return $this->m_audit_comment;
	}
	public function get_account_name()
	{
		return $this->m_account_name;
	}
	public function get_updated_time()
	{
		if (is_null( $this->m_updated_time ))
		{
			return '';
		}

		return date( LONG_DATE, $this->m_updated_time );
	}

	
	public function Init_account_audit(
		$ledger_id,
		$audit_date,
		$account_balance,
		$audit_comment,
		$account_name,
		$audit_id = -1 )
	{
		$this->m_ledger_id			= $ledger_id;
		$this->m_audit_time			= parse_date( $audit_date );
		$this->m_account_balance	= $account_balance;
		// Truncate comments to 1000 chars
		$this->m_audit_comment		= substr( $audit_comment, 0, 1000 );
		$this->m_account_name		= $account_name;
		$this->m_audit_id			= $audit_id;

		$error = '';
		if ($this->m_audit_time == -1)
		{
			$error = 'Audit date format is invalid';
			$this->m_audit_str = $audit_date;
		}

		return $error;
	}

	// Retrieve the accounting date for the associated transaction
	// Return the results through function parameters.
	private function Get_trans_date_range( &$trans_id, &$min_date,
		&$max_trans_id, &$max_date )
	{
		/*	First, get the transaction tied to this audit record.
			Second, find the next transaction in this account, by accounting
			date, with transaction ID being the tie breaker.  Note that because
			we will typically get a NULL record in each dataset from the LEFT
			JOIN, we must sort by a non-null value, as NULLs typically appear
			at the top of the list.  Hence the isnull call in the order by.
		*/
		$sql = "SELECT t.accounting_date as min_date, t.trans_id as min_trans_id, ".
			" t2.accounting_date as max_date, t2.trans_id as max_trans_id ".
			"FROM Transactions t \n".
			"INNER JOIN Ledger_Entries le ON ".
			"	t.trans_id = le.trans_id \n".
			"LEFT JOIN Ledger_Entries le2 ON ".
			"	le2.account_id = le.account_id ".
			"	AND le2.ledger_id <> le.ledger_id \n".
			"LEFT JOIN Transactions t2 ON ".
			"	t2.trans_id = le2.trans_id ".
			"	AND( ( t2.accounting_date = t.accounting_date ".
			"			AND t2.trans_id > t.trans_id ) ".
			"		OR( t2.accounting_date > t.accounting_date ) ) \n".
			"WHERE le.ledger_id = :ledger_id ".
			"ORDER BY coalesce( t2.accounting_date, '2999-01-01' ), t2.trans_id \n".
			"LIMIT 1 ";

		$pdo = db_connect_pdo();
		$ps = $pdo->prepare($sql);
		$ps->bindParam(':ledger_id', $this->m_ledger_id);
		$success = $ps->execute();
		if (!$success) {
			return get_pdo_error($ps);
		}

		$row = $ps->fetch(PDO::FETCH_ASSOC);
		$trans_id		= $row[ 'min_trans_id' ];
		$min_date		= $row[ 'min_date' ];
		$max_trans_id	= $row[ 'max_trans_id' ];
		$max_date		= $row[ 'max_date' ];
		
		$pdo = null;
		return '';
	}

	public function Load_account_audit( $audit_id )
	{
		$sql = "SELECT aa.*, a.account_name from Account_Audits aa \n".
			"INNER JOIN Ledger_Entries le ON le.ledger_id = aa.ledger_id \n".
			"INNER JOIN Accounts a ON a.account_id = le.account_id \n".
			"WHERE aa.audit_id = :audit_id ";

		$pdo = db_connect_pdo();
		$ps = $pdo->prepare($sql);
		if ($ps == false) {
			return get_pdo_error($ps);
		}
		$ps->bindParam(':audit_id', $audit_id);
		$success = $ps->execute();
		if (!$success) {
			return get_pdo_error($ps);
		}
		
		$row = $ps->fetch(PDO::FETCH_ASSOC );
		$this->m_audit_id			= $audit_id;
		$this->m_ledger_id			= $row[ 'ledger_id' ];
		$this->m_audit_time			= strtotime( $row[ 'audit_date' ] );
		$this->m_account_balance	= $row[ 'account_balance' ];
		$this->m_audit_comment		= $row[ 'audit_comment' ];
		$this->m_account_name		= $row[ 'account_name' ];
		$this->m_updated_time		= strtotime( $row[ 'updated_time'] );

		$pdo = null;
		
		return '';
	}

	public function Save_account_audit()
	{
		$error = '';

		// VALIDATE
		$error = $this->Get_trans_date_range( $trans_id, $min_date, $max_trans_id,
			$max_date );

		if ($error != '')
		{
			return $error;
		}

		$min_time = strtotime( $min_date );
		$max_time = NULL;
		if ($max_date != NULL)
		{
			$max_time = strtotime( $max_date );
		}

		// Make sure we're the last transaction on this date
		$date = date( DISPLAY_DATE, $min_time ); 
		if ($min_date == $max_date && $trans_id < $max_trans_id)
		{
			$error = "Transaction $trans_id is not the last transaction ".
				"for this account on $date; unable to audit.";
		}
		// Make sure we're between the min & max dates
		elseif ($min_time > $this->m_audit_time)
		{
			// Make sure the audit date is >= accounting date of the transaction
			$error = "The audit date must be no less than the transaction ".
				"accounting date, $date.";
		}
		elseif ($max_time != NULL && $max_time <= $this->m_audit_time)
		{
			$date = date( DISPLAY_DATE, $max_time );
			$error = "The audit date must be less than the next transaction ".
				"date for this account, ID $max_trans_id on $date.";
		}

		if ($error != '')
		{
			return $error;
		}

		// TODO:  make sure the date doesn't exceed map to another transaction
		// for this account

		$pdo = db_connect_pdo();
		$pdo->beginTransaction();
		$ps = NULL;		
		if ($this->m_audit_id > -1)
		{
			// Update an existing record (date & comment)
			$sql = "UPDATE Account_Audits \n".
				"SET audit_date = :audit_date, ".
				"  audit_comment = :audit_comment \n".
				"WHERE audit_id = :audit_id ";
			$ps = $pdo->prepare($sql);
			$auditDate = $this->get_audit_date_sql();
			$ps->bindParam(':audit_date', $auditDate); 
			$ps->bindParam(':audit_comment', $this->m_audit_comment);
			$ps->bindParam(':audit_id', $this->m_audit_id);
		}
		else
		{
			$sql = 
				"INSERT INTO Account_Audits \n".
				"( ledger_id, audit_date, account_balance, audit_comment ) \n".
				"VALUES( :ledger_id, :audit_date, :account_balance, ".
				":audit_comment ) ";
			$ps = $pdo->prepare($sql);
			$ps->bindParam(':ledger_id', $this->m_ledger_id);
			$auditDate = $this->get_audit_date_sql();
			$ps->bindParam(':audit_date', $auditDate);
			$accBalance = $this->get_account_balance( true );
			$ps->bindParam(':account_balance', $accBalance); 
			$ps->bindParam(':audit_comment', $this->m_audit_comment);
		}

		if ($ps == FALSE) {
			// sql problem
			return get_pdo_error($ps);
		}
		
		$success = $ps->execute();
		if (!$success) {
			return get_pdo_error($ps);
		}

		if ($this->m_audit_id < 0)
		{
			// Update the primary key
			$this->m_audit_id = get_auto_increment($pdo, 'account_audits_audit_id_seq');
		}
		
		$pdo->commit();
		$pdo = null;

		// Reload from DB (will get updated_time)
		$error = $this->Load_account_audit( $this->m_audit_id );

		return $error;
	}

	// Delete the current audit record from the database
	public function Delete_account_audit()
	{
		$error = '';
		if ($this->m_audit_id < 0)
		{
			return "Unable to delete audit record; not yet initialized.";
		}

		$sql = "DELETE FROM Account_Audits ".
			"WHERE audit_id = :audit_id ";

		$pdo = db_connect_pdo();
		$ps = $pdo->prepare($sql);
		$ps->bindParam(':audit_id', $this->m_audit_id);
		$success = $ps->execute();
		if (!$success) {
			return get_pdo_error($ps);
		}
		
		$count = $ps->rowCount(); 
		if ($count != 1)
		{
			return 'Error: audit delete affected ' . $count . ' rows';
		}

		// Success; set audit_id to -1
		$this->m_audit_id = -1;
		return '';
	}

} // End Account Audit class
?>
