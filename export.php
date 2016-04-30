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
			buildTransactions($accountId, $ps, $lastAudit, $accountIdsExported);
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
	
	/*
	   Loop through records until we have a complete transaction.
	   Then output the QIF text record and continue processing.
	   mainAccountId is the primary account being exported,
	   and so its ledger entries must come first.

	   accountIdsExported is a list of account Ids already processed;
	   we will skip any transactions with these accounts to avoid
	   duplicate transactions, which will keep export file smaller.
	 */
	function buildTransactions($mainAccountId, $ps, $lastAudit, $accountIdsExported) {
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
				$amount = $row['amount'];
				$descr = $row['trans_descr'];
				$comment = $row['trans_comment'];
				if (!empty($comment)) {
					$descr .= "; $comment";
				}
				$vendor = $row['trans_vendor'];
				$accountingSqlDate = $row['accounting_date'];
				$txDate = convert_date($accountingSqlDate, 2);
				$cleared = '';
				if ($lastAudit != NULL) {
					$auditSqlDate = $lastAudit->get_audit_date_sql();
					if ($auditSqlDate >= $accountingSqlDate) {
						$cleared = "CR". $lineBreak;
					}
				}

				$record .= "D$txDate". $lineBreak.  // Date
					"T$amount". $lineBreak.     // Amount
					"M$descr". $lineBreak.      // Memo
					$cleared .                  // Cleared status
					"P$vendor". $lineBreak;     // Payee

				$buildRecordHeader = false;  // don't print more than once

			} else {
				// secondary / split part of record
				$split = new Split();
				$split->account = $accountName;
				$split->accountId = $accountId;
				$split->memo = $row['memo'];
				$split->amount = $row['amount'];
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
		<tr> <td></td/>
			<td>
			<input type="submit" value="Generate QIF File" /></td>
		</tr>
	</div>
</form>


<?php require('footer.php'); ?>

</body>
</html>

