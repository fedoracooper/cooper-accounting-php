<?
	/*	Account Audit Class
		Created 7/2/2006
		Purpose:  Capture a bit of auditing information
	*/

// ACCOUNT AUDIT class
//------------------------------------------------------------------------------
// Member variables are stored with slashes added
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
			"INNER JOIN LedgerEntries le ON le.account_id = a.account_id \n".
			"INNER JOIN Transactions t ON t.trans_id = le.trans_id \n".
			"WHERE le.ledger_id = $ledger_id ";
		db_connect();
		$rs = mysql_query( $sql );
		$error = db_error( $rs, $sql );
		if ($error == '')
		{
			$row = mysql_fetch_array( $rs, MYSQL_ASSOC );
			$this->m_account_name	= $row[ 'account_name' ];
			$this->m_audit_time		= strtotime( $row[ 'accounting_date'] );
		}
		else
		{
			$this->m_account_name = $error;
		}

		mysql_close();
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

		return date( 'm/d/Y', $this->m_audit_time );
	}
	public function get_audit_date_sql()
	{
		if ($this->m_audit_time > 0)
		{
			return date( 'Y-m-d', $this->m_audit_time );
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
		return stripslashes( $this->m_audit_comment );
	}
	public function get_account_name()
	{
		return stripslashes( $this->m_account_name );
	}
	public function get_updated_time()
	{
		if (is_null( $this->m_updated_time ))
		{
			return '';
		}

		return date( 'M j, Y g:i a', $m_updated_time );
	}

	
	// Arguments should have had addslashes called already
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

		// VALIDATE
		$min_date = $this->Get_trans_accounting_date();
		$min_time = strtotime( $min_date );

		if ($this->m_audit_time == -1)
		{
			$error = 'Audit date format is invalid';
			$this->m_audit_str = $audit_date;
		}
		elseif ($min_time > $this->m_audit_time)
		{
			// Make sure the audit date is >= accounting date of the transaction
			$error = "The audit date must be no less than the transaction ".
				"accounting date, '$min_date'";
		}

		return $error;
	}

	// Retrieve the accounting date for the associated transaction
	private function Get_trans_accounting_date()
	{
		$sql = "SELECT accounting_date ".
			"FROM Transactions t \n".
			"INNER JOIN LedgerEntries le ON ".
			"	t.trans_id = le.trans_id \n".
			"WHERE le.ledger_id = $this->m_ledger_id ";

		db_connect();
		$rs = mysql_query( $sql );
		$error = db_error( $rs, $sql );
		if ($error != '')
		{
			mysql_close();
			return $error;
		}

		$row = mysql_fetch_array( $rs, MYSQL_ASSOC );
		$date = $row[ 'accounting_date' ];
		mysql_close();

		return $date;
	}

	public function Load_account_audit( $audit_id )
	{
		$sql = "SELECT aa.*, a.account_name from AccountAudits aa \n".
			"INNER JOIN LedgerEntries le ON le.ledger_id = aa.ledger_id \n".
			"INNER JOIN Accounts a ON a.account_id = le.account_id \n".
			"WHERE aa.audit_id = $audit_id ";

		db_connect();
		$rs = mysql_query( $sql );
		$error = db_error( $rs, $sql );
		if ($error == '')
		{
			$row = mysql_fetch_array( $rs, MYSQL_ASSOC );
			$this->m_audit_id			= $audit_id;
			$this->m_ledger_id			= $row[ 'ledger_id' ];
			$this->m_audit_time			= strtotime( $row[ 'audit_date' ] );
			$this->m_account_balance	= $row[ 'account_balance' ];
			$this->m_audit_comment		= addslashes(
				$row[ 'audit_comment' ] );
			$this->m_account_name		= addslashes( $row[ 'account_name' ] );
			$this->m_updated_time		= strtotime( $row[ 'updated_time'] );
		}
		mysql_close();

		return $error;
	}

	public function Save_account_audit()
	{
		$sql = '';

		if ($this->m_audit_id > -1)
		{
			// Update an existing record (date & comment)
			$sql = "UPDATE AccountAudits \n".
				"SET audit_date = '". $this->get_audit_date_sql() . "', ".
				"  audit_comment = '$this->m_audit_comment' \n".
				"WHERE audit_id = $this->m_audit_id ";
		}
		else
		{
			/*	Insert a new record; we are using table WRITE locks in the
				database to make sure that the account balance is accurate
				when the insert occurs.  As long as transaction updates &
				inserts always check for audit records, we should be consistent.
			*/
			$sqlLock =
				"SET AUTOCOMMIT = 0; \n".
				"LOCK AccountAudits WRITE; \n";

			$sql = 
				"INSERT INTO AccountAudits \n".
				"( ledger_id, audit_date, account_balance, audit_comment ) \n".
				"VALUES( $this->m_ledger_id, '" .
				$this->get_audit_date_sql() . "', ".
				$this->get_account_balance( true ) . ", ".
				"'$this->m_audit_comment' ) ";
		}

		db_connect();
		$rs = mysql_query( $sql );
		$error = db_error( $rs, $sql );

		if ($error == '' && $this->m_audit_id < 0)
		{
			// Update the primary key
			$this->m_audit_id = get_auto_increment();
		}

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

		$sql = "DELETE FROM AccountAudits ".
			"WHERE audit_id = $this->m_audit_id ";
		db_connect();
		$rs = mysql_query( $sql );
		$error = db_error( $rs, $sql );
		mysql_close();

		if ($error == '')
		{
			// Success; set audit_id to -1
			$this->m_audit_id = -1;
		}

		return $error;
	}

} // End Account Audit class
?>
