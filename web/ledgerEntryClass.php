<?php

class LedgerEntry {
	public $ledgerId = -1;
	public $accountId = -1;
	
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
