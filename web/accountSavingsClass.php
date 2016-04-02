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
	public $savingsBalance = 0.0;
	public $parentName = '';
	
	// After setting the Saved amount for the account, we calculate unspent and toSave.
	public function setSaved($saved, $setToSave) {
	  $this->saved = $saved;
	
		// unspent will be negative when over budget
		$unspent = $this->budget - $this->transactions - $this->saved;
		if ($setToSave) {
			if (abs($this->saved) > 0.001) {
				// When already savings or drawing from savings, toSave is always 0
				$this->toSave = 0.0;
			} else {
			  $this->toSave = $this->calculateToSave($unspent);
			}
			// if toSave is > 0, then subtract from unspent
			$unspent -= $this->toSave;
		}
  	
		$this->unspent = $unspent;
		
		// Calculate budget %
		if ($this->budget != 0.0) {
			$this->budgetPercent = $this->transactions / $this->budget * 100.0;
		}

	}
	
	private function calculateToSave($unspent) {
	  if ($unspent >= 0.0) {
	    return $unspent;
	  } else {
	    // Don't exceed savings balance when drawing
	    return max(($this->savingsBalance * -1.0), $unspent);
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
