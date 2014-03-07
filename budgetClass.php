<?php
// Budget class for storing & retrieving monthly
// budget numbers for a given account.

class Budget {
	private $m_budget_id = -1;
	private $m_account_id = -1;
	private $m_budget_month = NULL;
	private $m_budget_amount = 0.0;
	private $m_updated_time = NULL;

	public function get_budget_id() {
		return $this->m_budget_id;
	}
	
	public function get_account_id() {
		return $this->m_account_id;
	}
	
	public function get_budget_month() {
		return $this->m_budget_month;
	}
	
	public function get_budget_amount() {
		return $this->m_budget_amount;
	}
	
	public function Init_budget(
			$account_id,
			$budget_month,
			$budget_amount,
			$budget_id = -1,
			$updated_time = NULL) {
		
		$this->m_account_id = $account_id;
		$this->m_budget_month = $budget_month;
		$this->m_budget_amount = $budget_amount;
		$this->m_budget_id = $budget_id;
		$this->m_updated_time = $updated_time;
	}
	
	public function Save() {
		
		$pdo = db_connect_pdo();
		if ($this->m_budget_id < 1) {
			// INSERT
			$sql = 'INSERT INTO Budget (account_id, budget_month, budget_amount) '
					. 'VALUES (:account_id, :budget_month, :budget_amount)';
			
			$ps = $pdo->prepare($sql);
			$ps->bindParam(':account_id', $this->m_account_id);
			$dateString = $this->m_budget_month->format('Y-m-d');
			$ps->bindParam(':budget_month', $dateString);
			$ps->bindParam(':budget_amount', $this->m_budget_amount);
				
		} else {
			// UPDATE
			$sql = 'UPDATE Budget set budget_amount = :budget_amount '
					. 'WHERE budget_id = :budget_id';
			
			$ps = $pdo->prepare($sql);
			$ps->bindParam(':budget_id', $this->m_budget_id);
			$ps->bindParam(':budget_amount', $this->m_budget_amount);
		}
		
		$success = $ps->execute();
		if (!$success) {
			return get_pdo_error($ps);
		}
		
		return '';
	}
}
?>
