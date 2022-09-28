<?php
	$current_page = 'export';
	$buildHtmlHeaders = true;

	$lineBreak = "\r\n";
	
	$account_id = -1;
	$error = '';

	if (isset($_POST['account_id'])) {
		$error = exportAccounts();
		// if we get here, then export failed
		$buildHtmlHeaders = true;
	}

	// Normal load
	require('include.php');


	function exportAccounts() {
		global $lineBreak;
		$buildHtmlHeaders = false;
		require('include.php');
		$topAccountId = $_POST['account_id'];
		$startDate = $_POST['startDate'];
		$prefixPayee = $_POST['prefixPayee'];
		$collapseSubAccounts = $_POST['collapseSubAccounts'] ?? 0;
		$accountIds = NULL;
		$fileName = NULL;

		if ($topAccountId > 0) {
			$account = new Account();
			$error = $account->Load_account($topAccountId);
			if ($error != '') {
				return $error;
			}
			if ($account->get_login_id() != $login_id) {
				return 'Error:  account does not belong to your login ID';
			}

			$fileName = $account->get_account_name();

			$accountIds = Account::Get_child_accounts($topAccountId);
			// Add main account to front of list
			array_unshift($accountIds, $topAccountId);

		} elseif ($topAccountId <= 0) {
			// Get list of all LHS accounts
			$account_list = Account::Get_account_list($login_id, 
				'L',	// LHS:  Asset / Liability accounts only
				-1,	// Parent ID value (root accounts only)
				false,	// force to 1 level only 
				false,	// show debit flag
				true	// show inactive
			);

			// account list has all our account IDs
			$accountIds = array_keys($account_list);
			$fileName = 'All Accounts';
		}
		
		header('Content-Type: text/qif; charset=utf-8');
		header("Content-Disposition: attachment; filename=\"$fileName.qif\"");

		// Loop through account & all subaccounts
		$accountIdsExported = array();
		foreach ($accountIds as $accountId) {
			$lastAudit = AccountAudit::Get_latest_audit($accountId);
			$error = '';  // pass by ref
			$ps = Transaction::Get_transactions_export($accountId, $startDate, $error);
			if ($error != '') {
				echo "Error querying database: $error";
				exit();
			}
			// Generate the output file
			// loop through results
			buildTransactions($accountId, $ps, $lastAudit, $accountIdsExported,
				$prefixPayee, $collapseSubAccounts);
			echo $lineBreak . $lineBreak;

			$accountIdsExported[] = $accountId;
			//echo "Account ids exported: ";
			//var_dump($accountIdsExported);
			//echo $lineBreak;

		}

		// All done!  Don't output footer HTML
		exit();
	}


	class Split {
		public $account;
		public $accountId;
		public $memo;
		public $amount;
		public $parentAccountId;

		public function getSplitRecord() {
			global $lineBreak;
			$record = "S$this->account". $lineBreak;
			if (!empty($memo)) {
				$record .= "E$this->memo". $lineBreak;
			}
			$record .= "$$this->amount". $lineBreak;

			return $record;
		}

		// Simplified:  just output account name with L line, and close the record.
		public function getCategoryRecord() {
			global $lineBreak;
			return "L$this->account". $lineBreak;
		}
	}

	/*
	   Build string of full account name:  parentparent:parent:account
	   Also, enclose in square brackets for Asset & Liability accounts,
	   as this indicates in QIF format that it's an account & not an
	   income or expense "category."

	   isMainAccount means this is the primary account of the export,
	   so it would never have square brackets.
	*/
	function buildAccountName($accountName, $accountParent1, $accountParent2, 
	$side, $isMainAccount) {
		$name = '';
		if (!empty($accountParent2)) {
			$name .= "$accountParent2:";
		}
		if (!empty($accountParent1)) {
			$name .= "$accountParent1:";
		}
		$name .= $accountName;
		
		if ($side == 'L' && !$isMainAccount) {
			// Asset or Liability
			return "[$name]";
		} else {
			return $name;
		}
	}
	
	
	function combineDescrComment($descr, $comment) {
		if (empty($descr)) {
			// comment only
			return $comment;
		} else {
			if (empty($comment)) {
				// Description only
				return $descr;
			} else {
				// Both values are present, some combine w/ semi-colon delimiter
				return "$descr; $comment";
			}
		}
	}
	
	function getMemoText($descrComment, $shouldPrefixMemo, $splits) {

		if (isset( $shouldPrefixMemo ) && count( $splits ) > 0 ) {
			return 'M[' . $splits[0]->account . '] ' . $descrComment ?? '';
		} else {
			return "M" . $descrComment ?? '';
		}
	}
	
	function getPayeeText($vendor, $prefixPayee, $splits, $amount) {
	
		$maxDiff = 0.0;
		if (isset( $prefixPayee ) && count( $splits ) > 0 ) {
			// Find the best split to use
			$splitAccount = 'None';
			foreach ($splits as $split) {
				if (abs($amount - $split->amount) > $maxDiff) {
					$maxDiff = abs($amount - $split->amount);
					$splitAccount = $split->account;
				}
			}
				
			return '[' . $splitAccount . ']' . ($vendor == '' ? '' : " $vendor");
		} else {
			return $vendor;
		}
	}
	
	/*
		Combine all sub-accounts of the main account.  Sub-account ledger entries
		are treated as entries against the main account.  We total them all up to
		get a net total.  Return updated $splits array.
	*/
	function collapseSplitsIfNeeded($collapseSubAccounts, $splits, $mainAccountId) {
		if ($collapseSubAccounts == 0) {
			return $splits;
		}
		
		$mainTotal = 0.0;
		$newSplits = array();
		$mainSplit = new Split();
		$mainSplit->amount = 0.0;
		$mainSplit->accountId = $mainAccountId;
		$mainSplit->account = 'Collapsed Account';

		foreach ($splits as $split) {
			if ($split->parentAccountId == $mainAccountId) {
				// Child account to collapse
				$mainSplit->amount += $split->amount;
			} else {
				// Unrelated account, so include it
				$newSplits[] = $split;
			}
		}
		
		if ($mainSplit->amount <> 0.0) {
			// Non-zero total
			$newSplits[] = $mainSplit;
		}
		
		return $newSplits;
	}
	
	/**
		Check for splits which have the main account under export,
		and combine the amount with the main transaction.
	*/
	function collapseSplitAmounts($splits, $mainAmount, $mainAccountId) {
		$newAmount = $mainAmount;
		foreach ($splits as $split) {
			if ($split->accountId == $mainAccountId) {
				$newAmount += $split->amount;
			}
		}
		return $newAmount;
	}
	
	/*
		After main transaction has been generated, we now need to
		strip out splits which duplicate the main account, as their
		amount has already been added to the main transaction.
		This is for a scenario where the same account occurs more than
		once in a transaction.
		
		Returns the updated splits array
	*/
	function removeCollapsedSplits($splits, $mainAccountId) {
		$newSplits = array();
		foreach ($splits as $split) {
			if ($split->accountId != $mainAccountId) {
				// different account
				$newSplits[] = $split;
			}
		}
		return $newSplits;
	}
	
	/*
	   Loop through records until we have a complete transaction.
	   Then output the QIF text record and continue processing.
	   mainAccountId is the primary account being exported,
	   and so its ledger entries must come first.

	   accountIdsExported is a list of account Ids already processed;
	   we will skip any transactions with these accounts to avoid
	   duplicate transactions, which will keep export file smaller.
	 */
	function buildTransactions($mainAccountId, $ps, $lastAudit, $accountIdsExported,
	$prefixPayee, $collapseSubAccounts) {
		$splits = array();
		$record = '';
		global $lineBreak;
		$lastTxId = -1;
		$buildHeader = true;
		$buildRecordHeader = true;

		do {
			$row = $ps->fetch(PDO::FETCH_ASSOC);
			$accountId = -1;
			$txId = -1;
			if ($row) {
				$accountId = $row['account_id'];
				$txId = $row['trans_id'];
			}

			$isMainAccount = ($accountId == $mainAccountId);
			if ($lastTxId == -1) {
				// First tx
				$lastTxId = $txId;
			}
			if ($txId != $lastTxId) {
				$splits = removeCollapsedSplits($splits, $mainAccountId);
				// Finish previous record (assume header already in record)
				$skipTx = false;
				$numSplits = count($splits);
				if ($numSplits == 0) {
					// no splits (probably 0 amount tx)
					$record .= 'LNo Split'. $lineBreak;
				}
				elseif ($numSplits == 1) {
					$record .= $splits[0]->getCategoryRecord();
				} else {
					foreach ($splits as $split) {
						// More than one split, so use QIF split records
						$record .= $split->getSplitRecord();
					}
				}

				// Check for splits on accounts already exported
				foreach ($splits as $split) {
					if (in_array($split->accountId, $accountIdsExported)) {
						$skipTx = true;
					}
				}
				$record .= "^". $lineBreak;  // End of Record indicator
 
				if (!$skipTx) {
					// not skipping; no duplicate account ID
					// Output!
					echo $record;
				}
				// reset fields
				$record = '';
				$splits = array();
				$lastTxId = $txId;
				$buildRecordHeader = true;
			}

			if (!$row) {
				// no more db records, and we wrote the last tx
				break;
			}

			$accountName = buildAccountName(
				$row['account_name'],
				$row['parent_account'],
				$row['parent_parent_account'],
				$row['equation_side'],
				$isMainAccount && $buildRecordHeader);

			// Only print record header once per tx
			if ($isMainAccount && $buildRecordHeader) {
				// build main record
				if ($buildHeader) {
					// Debit accounts = Bank & Credit accounts are Credit Card
					$accountType = $row['account_debit'] > 0 ? 'Bank' : 'CCard';
					$accountDescr = $row['account_descr'];
					$record .= '!Account'. $lineBreak.
						"N$accountName". $lineBreak.
						"T$accountType". $lineBreak.
						"D$accountDescr". $lineBreak.
						"^". $lineBreak.
						"!Type:$accountType". $lineBreak;
					// Reset flag so we don't build file header again
					$buildHeader = false;

					// Write it now, in case we skip the tx
					echo $record;
					$record = '';
				}
				
				$splits = collapseSplitsIfNeeded($collapseSubAccounts, $splits, $mainAccountId);				
				$amount = collapseSplitAmounts($splits, $row['amount'], $mainAccountId);
				$descr = trim($row['trans_descr']);
				$comment = trim($row['trans_comment']);
				$descrComment = combineDescrComment($descr, $comment);
				$memo = getMemoText($descrComment, NULL, $splits);
				$payee = getPayeeText(trim($row['trans_vendor']), $prefixPayee, $splits, $amount);
				$accountingSqlDate = $row['accounting_date'];
				$txDate = convert_date($accountingSqlDate, 2);
				$cleared = '';
				if ($lastAudit != NULL) {
					$auditSqlDate = $lastAudit->get_audit_date_sql();
					if ($auditSqlDate >= $accountingSqlDate) {
						$cleared = "CR". $lineBreak;
					}
				}

				// In our application, description is the primary and
				// vendor is secondary; let's combine vendor and description;
				// the Memo field is not often used in GnuCash.
				$record .= "D$txDate". $lineBreak.  // Date
					"T$amount". $lineBreak.     // Amount
					$memo . $lineBreak.        	// Memo
					$cleared .                  // Cleared status
					"P$payee". $lineBreak;      // Payee

				$buildRecordHeader = false;  // don't print more than once

			} else {
				// secondary / split part of record
				$split = new Split();
				$split->account = $accountName;
				$split->accountId = $accountId;
				$split->memo = $row['memo'];
				$split->amount = $row['amount'];
				$split->parentAccountId = $row['parent_account_id'];
				$splits[] = $split;
			}
		} while (true);
	}

	$account_list = Account::Get_account_list($login_id, 
		'L',	// LHS:  Asset / Liability accounts only
		-1,	// Parent ID value (don't show root accounts)
		false,	// don't force to one level
		false,	// show debit flag
		true	// show inactive
	);

	$account_list = array('-1' => '--All Accounts--') + $account_list;
	
	$account_dropdown = Build_dropdown ($account_list, 'account_id',
		$account_id);
		
	$errorHtml = '';
	if ($error != '') {
		$errorHtml = "<div class='error'> $error </div>";
	}
?>

</head>


<body>
<?= $navbar ?>

<h3><?= $title ?></h3>

<?= $errorHtml ?>

<form method="POST">
	<table>
		<tr> <td>
			<label for="export-account">Account to Export</label></td>
		<td> <?= $account_dropdown ?> </td>
		</tr>

		<tr> <td>
			<label> Start Date </label></td>
		<td>
			<input type="date" name="startDate" /> </td>
		</tr>
		<tr>
		<td><label title="Needed for software that doesn't handle account / category import directly."> 
		Prefix Payee with Account Name </label> </td>
		<td> <input type="checkbox" name="prefixPayee" value="1" /> </td>
		</tr>
		<tr>
		<td><label title="Useful for virtual sub-accounts."> 
		Collapse Sub Accounts </label> </td>
		<td> <input type="checkbox" name="collapseSubAccounts" value="1" /> </td>
		</tr>
		<tr> <td></td/>
			<td>
			<input type="submit" value="Generate QIF File" /></td>
		</tr>
	</div>
</form>


<?php require('footer.php'); ?>

</body>
</html>

