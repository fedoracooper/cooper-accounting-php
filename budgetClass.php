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
	
	/* Build compound SQL statements for Update and Insert, for efficiency
	   with high latency SQL connections. */
	public static function saveBatch($pdo, $batch_list, &$updateCount) {
	
		$updateSql = 'UPDATE Budget SET '
			. 'budget_amount = b.amount, '
			. 'budget_comment = b.comment, '
			. 'updated_time = current_timestamp '
			. 'FROM (VALUES ';
		$insertSql = 'INSERT INTO Budget (account_id, budget_month, budget_amount,'
			. ' budget_comment) VALUES ';
		$updateList = array();
		$insertList = array();
		$updateCount = 0;
		foreach ($batch_list as $batch) {
			// Build VALUES clause for virtual table of values
		
			if ($batch->get_budget_id() < 1) {
				if (count($insertList) > 0) {
					$insertSql .= ', ';
				}
				$insertSql .= '(?, ?, ?, ?) ';
				$insertList[] = $batch;
			} else {
				if (count($updateList) > 0) {
					$updateSql .= ', ';
				}
				$updateSql .= '(?, ?, ?) ';
				$updateList[] = $batch;
			}
		}
		
		if (! empty($updateList)) {
			// Close up the Update SQL
			$updateSql .= ') as b(amount, comment, budget_id) '
				. 'WHERE budget.budget_id = b.budget_id ';
			error_log("Update SQL to prepare: $updateSql ");

			$ps = $pdo->prepare($updateSql);
			$i = 1;
			foreach ($updateList as $batch) {
				$ps->bindValue($i++, $batch->get_budget_amount());
				$ps->bindValue($i++, $batch->get_budget_comment());
				error_log("Binding budget ID " . $batch->get_budget_id()
					. " for column $i. ");
				$result = $ps->bindValue($i++, intval($batch->get_budget_id()), PDO::PARAM_INT);
				if (!$result) {
					error_log ('Problem binding budget ID of ' . $batch->get_budget_id()
						. ' for column ' . ($i-1) . ' ');
				}
			}
			$success = $ps->execute();
			if (!$success) {
				return get_pdo_error($ps);
			}
			$updateCount += $ps->rowCount();
		}
		
		if (! empty($insertList)) {
			$ps = $pdo->prepare($insertSql);
			$i = 1;
			foreach ($insertList as $batch) {
				$ps->bindValue($i++, $batch->get_account_id(), PDO::PARAM_INT);
				$ps->bindValue($i++, $batch->get_budget_month()->format('Y-m-d'));
				$ps->bindValue($i++, $batch->get_budget_amount());
				$ps->bindValue($i++, $batch->get_budget_comment());
			}
			$success = $ps->execute();
			if (!$success) {
				return get_pdo_error($ps);
			}
			$updateCount += $ps->rowCount();
		}
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
