<?php

// Account Savings Class

class AccountSavings {
	public $accountName;	// Account Name (could be Expense or Asset)
	public $balance;		// Account Balance (for assets)
	public $budget;			// Monthly budget amount (expenses)
	public $budgetId;
	public $budgetComment;
	public $defaultBudget = 0.0;
	public $transactions;	// Total transactions this month for this account
	public $savingsId;		// Savings Account ID
	public $expenseAccountId;	// Expense Account ID (expenses)
	public $accountDescr;	// Account description
	public $accountActive;	// Account Active flag
	private $saved = 0.0;	// Amount Saved in this account this month; use setter method
	private $toSave = 0.0;
	private $budgetPercent = 0.0;
	private $unspent = 0.0;
	public $savingsName = '';	// Savings Account Name
	public $savingsParentId = -1;	// Savings Parent Account ID (like Checking)
	public $savingsBalance = 0.0;	// Savings Account Balance
	public $parentName = '';		// Parent Account Name
	public $savingsDebit;			// Savings Account Debit flag (-1 or +1)
	public $savingsParentDebit;		// Savings Parent Account Debit flag
	
	// After setting the Saved amount for the account, we calculate unspent and toSave.
	public function setSaved($saved, $setToSave) {
	  $this->saved = $saved;
	
		// unspent will be negative when over budget
		$unspent = $this->budget - $this->transactions - $this->saved;
		if ($setToSave) {
			$this->toSave = $this->calculateToSave($unspent);
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
	
	/* Get Available Amount for the given expense account for this month;
	   add Budget, Savings Balance - Expenses - Saved.  If savings balance is
	   negative, meaning a Debit account, we treat it as zero.
	   This represents the amount which could be spent additionally in the
	   given month without breaking the budget.
	 */ 
	public function getAvailable() {
		return $this->budget
			+ max(0.0, $this->savingsBalance)
			- $this->transactions
			- $this->getSaved();
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
