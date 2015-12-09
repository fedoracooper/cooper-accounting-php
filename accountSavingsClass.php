<?php

// Account Savings Class

class AccountSavings {
  public $accountName;
  public $balance;
	public $budget;
	public $transactions;
	public $savingsId;
	public $accountDescr;
  private $saved = 0.0;
	private $toSave = 0.0;
	private $budgetPercent = 0.0;
	private $unspent = 0.0;
	public $savingsName = '';
	public $savingsParentId = -1;
	
	// After setting the Saved amount for the account, we calculate unspent and toSave.
	public function setSaved($saved, $setToSave) {
	  $this->saved = $saved;
	
		// unspent will be negative when over budget
  	$unspent = $this->budget - $this->transactions - $this->saved;
  	if ($setToSave) {
  		$this->toSave = max(0.0, $unspent); // To Save is never negative
  		if ($this->saved < 0.0) {
  			// When already drawing from savings, toSave is always 0
  			$this->toSave = 0.0;
  		}
  		// if toSave is > 0, then subtrace from unspent
  		$unspent -= $this->toSave;
  	}
  	
    $this->unspent = $unspent;
    
    // Calculate budget %
  	if ($this->budget != 0.0) {
  		$this->budgetPercent = $this->transactions / $this->budget * 100.0;
  	}

	}

  public function getSaved() {
    return $this->saved;
  }
  
  public function getToSave() {
    return $this->toSave;
  }
  
  public function getUnspent() {
    return $this->unspent;
  }
  
  public function getBudgetPercent() {
    return $this->budgetPercent;
  }
}  // End AccountSavings


//
class AccountSavingsTransaction {
  
  
}

?>