<?php

class LedgerEntry {
	public $ledgerId = -1;
	public $accountId = -1;

	public $memo = '';
	
	// Debit flag; -1 or +1
	public $debit = 0;
	public $debitAmount;
	public $creditAmount;
	public $toDelete = false;
	
	// Get concatenation of accountId and debit flag:  "45,-1"
	public function getAccountIdDebitString() {
		return $this->accountId . ','. $this->debit;
	}
	
	/* Get the amount as stored in the database.
	   This can be positive or negative, as we don't store
	   Debits and Credits separately.
	   
	   Returns error message on error.
	 */
	public function getAmount() {
		
		if ($this->debit > 0) {
			// Debit acct
			return (is_numeric($this->debitAmount))
				? $this->debitAmount
				: $this->creditAmount * -1.0;
				
		} elseif ($this->debit < 0) {
			// Credit acct
			return (is_numeric($this->creditAmount))
				? $this->creditAmount
				: $this->debitAmount * -1.0;
				
		} else {
			return 'Error:  debit flag not set';
		}
	}
	
	// Return debit amount, or zero if not set.
	public function getDebitAmountNumeric() {
		return (is_numeric($this->debitAmount)) ? $this->debitAmount : 0.0;
	}
	
	// Return credit amount, or zero if not set.
	public function getCreditAmountNumeric() {
		return (is_numeric($this->creditAmount)) ? $this->creditAmount : 0.0;
	}
	
	/* Set from single database amount field.  We will populate the appropriate
	   field, debitAmount or creditAmount, based on the account ID.
	   
	   Account ID must already be set.
	 */
	public function setAmount($amount) {
		// Default fields to empty string
		$this->debitAmount = '';
		$this->creditAmount = '';
		
		if ($this->debit > 0) {
			if ($amount >= 0.0) {
				// Debit account / 0+
				$this->debitAmount = $amount;
			} else {
				// negative debit
				$this->creditAmount = $amount * -1.0;
			}
		} elseif ($this->debit < 0) {
			if ($amount >= 0.0) {
				// Credit acct / 0+
				$this->creditAmount = $amount;
			} else {
				// Negative credit
				$this->debitAmount = $amount * -1.0;
			}
		}
	}
	
	/*
	  Validate the amount fields for saving to database.
	  Returns an error message on error, or empty string on success.
	 */
	public function validate() {
		$debit = $this->debitAmount;
		$credit = $this->creditAmount;
		
		if ($this->toDelete) {
			// skip most validation for deletion
			if ($this->ledgerId <= 0) {
				return 'No ledger ID provided for ledger entry deletion';
			} else {
				return '';
			}
		}
		
		if (is_numeric($debit) && is_numeric($credit)) {
			if ($debit != 0.0 && $credit != 0.0) {
				return "Cannot specify Debit Amount and Credit Amount for the same ledger entry";
			}
		}
		if (!is_numeric($debit) && !is_numeric($credit)) {
			return "Please enter either a Debit Amount or Credit Amount for each ledger entry";
		}
		
		if ($this->accountId <= 0) {
			return "Please select an Account for each Ledger Entry";
		}
		if ($this->debit == 0) {
			// Shouldn't happen when accountId is set
			return "Error:  credit flag not initialized";
		}
		
		if ((is_numeric($debit) && $debit < 0.0) ||
			(is_numeric($credit) && $credit < 0.0)) {
				return "Amounts cannot be negative.  Use either Debit or Credit column";
		}
		
		return '';
	}
	


	// Get html-escaped memo value
	public function getMemo() {
		return htmlspecialchars($this->memo);
	}
	
	// Take a string in this form:  "accountId,debit"
	// In some cases, it will just be accountId.
	public function setAccountData($accountData) {
		$accountArr = explode(',', $accountData);
		if (count ($accountArr) == 2) {
			$this->accountId = $accountArr[0];
			$this->debit = $accountArr[1];
		} else {
			// Deletion?  No debit flag
			$this->accountId = $accountData;
		}
	}

}

?>
