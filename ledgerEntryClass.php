<?php

class LedgerEntry {
	public $ledgerId = -1;
	public $accountId = -1;

	public $memo = '';
	
	// Debit flag; -1 or +1
	public $debit = 0;
	public $amount;

	// Standardized amount:  amount * debit flag (-1 or +1)
	public function getDebitAmount() {
		return $this->amount * (float)$this->debit;
	}
	
	// Get concatenation of accountId and debit flag:  "45,-1"
	public function getAccountIdDebitString() {
		return $this->accountId . ','. $this->debit;
	}

	/* Get Debit Amount.  Never negative; returns empty string
	 * for Credit Ledger Entries. */
	public function getDebit() {
		if ($this->debit > 0 && $this->amount > 0.0) {
			// Debit account / positive amount
			return $this->amount;
		} elseif ($this->debit < 0 && $this->amount < 0.0) {
			// Credit account / negative amount = Positive Debit
			return $this->amount * -1.0;
		} else {
			// Zero amount corner-case:  return as Debit.  This is arbitrary,
			// but we can have a zero-dollar ledger entry.
			return 0.0;
		}
	}
	
	/*
		Set the Debit Amount and Credit Amount values from the UI.
		Only one of these values can be non-zero.  Negative values
		are not allowed, only Debit or Credit.  This will set the
		single amount field with the appropriate sign, depending on
		the account type and the field used.
		
		Returns an error string if input is invalid.
	*/
	public function setDebitCredit($debit, $credit) {
		if ($this->debit == 0) {
			return "Error:  credit flag not initialized";
		}
		
		if (is_numeric($debit) && is_numeric($credit)) {
			if ($debit != 0.0 && $credit != 0.0) {
				// Save debit amount and display error
				$this->setDebitCredit($debit, '');
				return "Cannot specify Debit Amount and Credit Amount for the same ledger entry";
			}
		}
		if (is_numeric($debit)) {
			if ($debit < 0.0) {
				return "Amounts cannot be negative.  Use either Debit or Credit column";
			} else {
				// Debit to a Credit account is stored as a negative value
				$invert = ($this->debit > 0) ? 1.0 : -1.0;
				$this->amount = $debit * $invert;
			}
		} elseif (is_numeric($credit)) {
			if ($credit < 0.0) {
				return "Amounts cannot be negative.  Use either Debit or Credit column";
			} else {
				// Credit to a Debit account is stored as a negative value
				$invert = ($this->debit > 0) ? -1.0 : 1.0;
				$this->amount = $credit * $invert;
			}
		} else {
			return "No valid Debit or Credit amount set";
		}
		
		return '';
	}

	/* Get Credit Amount.  Never negative; returns empty string
	 * for Debit Ledger Entries. */
	public function getCredit() {
		if ($this->debit < 0 && $this->amount > 0.0) {
			// Credit account / positive amount
			return $this->amount;
		} elseif ($this->debit > 0 && $this->amount < 0.0) {
			// Debit account / negative amount = Positive Credit
			return $this->amount * -1.0;
		} else {
			return '';
		}
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
