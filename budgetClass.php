<?php
// Budget class for storing & retrieving monthly
// budget numbers for a given account.

class Budget {
	private $m_budget_id = -1;
	private $m_account_id = -1;
	private $m_budget_month = NULL;
	private $m_budget_amount = 0.0;
	private $m_budget_comment = '';
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
	
	public function get_budget_comment() {
		return $this->m_budget_comment;
	}
	
	public function Init_budget(
			$account_id,
			$budget_month,
			$budget_amount,
			$budget_comment,
			$budget_id = -1,
			$updated_time = NULL) {
		
		$this->m_account_id = $account_id;
		$this->m_budget_month = $budget_month;
		$this->m_budget_amount = $budget_amount;
		$this->m_budget_comment = htmlspecialchars_decode($budget_comment);
		$this->m_budget_id = $budget_id;
		$this->m_updated_time = $updated_time;
	}
	
	/* Insert or update the budget.
	   Takes a PDO connection, which must be initialized, and a Prepared Statement,
	   which should be non-null on the second and later invocations for efficiency.
	 */
	public function Save($pdo, &$psInsert, &$psUpdate) {
		
		$ps = NULL;  // PS will be either Insert or Update
		$nullAmount = ($this->m_budget_amount === '');
		
		if ($this->m_budget_id < 1) {
			if ($nullAmount) {
				// No need to insert a NULL, so skip
				return '';
			}
			
			// INSERT
			$sql = 'INSERT INTO Budget (account_id, budget_month, budget_amount,'
				. ' budget_comment) '
				. 'VALUES (:account_id, :budget_month, :budget_amount, '
				. ' :budget_comment)';
			
			if ($psInsert == NULL) {
				$psInsert = $pdo->prepare($sql);
			}
			$psInsert->bindParam(':account_id', $this->m_account_id);
			$dateString = $this->m_budget_month->format('Y-m-d');
			$psInsert->bindParam(':budget_month', $dateString);
			$ps = $psInsert;
		
		} else {
			// UPDATE or Delete existing row
			if ($nullAmount) {
				// Blank / null amount, so delete it
				$sql = 'DELETE FROM Budget WHERE budget_id = :budget_id';
				// Deleting budget rows is uncommon, so we don't try to cache the PS
				$ps = $pdo->prepare($sql);
			} else {
				$sql = 'UPDATE Budget set budget_amount = :budget_amount, '
					. 'budget_comment = :budget_comment, '
					. 'updated_time = current_timestamp '
					. 'WHERE budget_id = :budget_id';
				
				if ($psUpdate == NULL) {
					$psUpdate = $pdo->prepare($sql);
				}
				
				$ps = $psUpdate;
			}
			$ps->bindParam(':budget_id', $this->m_budget_id);
		}

		if (!$nullAmount) {
			$ps->bindParam(':budget_amount', $this->m_budget_amount);
			$ps->bindParam(':budget_comment', $this->m_budget_comment, PDO::PARAM_STR);
		}
$t2 = microtime(true);
		
		$success = $ps->execute();
$t3 = microtime(true);
global $execTime;
$execTime += $t3 - $t2;
		if (!$success) {
			return get_pdo_error($ps);
		}
		
		return '';
	}
}
?>
