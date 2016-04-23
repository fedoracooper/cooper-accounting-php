<?php
	$current_page = 'export';
	$buildHtmlHeaders = true;

	$lineBreak = "\r\n";
	
	$account_id = -1;
	$error = '';

	if (isset($_POST['account_id'])) {
		$buildHtmlHeaders = false;
		require('include.php');

		$account_id = $_POST['account_id'];
		
		// Generate the output file
		$error = '';
		$ps = Transaction::Get_transactions_export($login_id, $account_id, $error);
		if ($error == '') {
			// clear output buffer & set output headers
			ob_end_clean();
			header('Content-type', 'text/plain; charset=utf-8');
			header('Content-Disposition: attachment; filename="accounting-export.qif"');
			// loop through results
			buildTransactions($account_id, $ps);

			// All done!  Don't output footer HTML
			exit();
		}
	} else {
		// Normal load
		require('include.php');
	}


	class Split {
		public $account;
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
	 */
	function buildTransactions($mainAccountId, $ps) {
		$splits = array();
		$record = '';
		global $lineBreak;
		$lastTxId = -1;
		$buildHeader = true;

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
				if (count($splits) == 1) {
					$record .= $splits[0]->getCategoryRecord();
				} else {
					foreach ($splits as $split) {
						// More than one split, so use QIF split records
						$record .= $split->getSplitRecord();
					}
				}
				$record .= "^". $lineBreak;  // End of Record indicator
				// Output!
				echo $record;

				// reset fields
				$record = '';
				$splits = array();
				$lastTxId = $txId;
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
				$isMainAccount);
			if ($isMainAccount) {
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
				}
				$amount = $row['amount'];
				$descr = $row['trans_descr'];
				$comment = $row['trans_comment'];
				if (!empty($comment)) {
					$descr .= "; $comment";
				}
				$vendor = $row['trans_vendor'];
				$txDate = convert_date($row['accounting_date'], 2);

				$record .= "D$txDate". $lineBreak.  // Date
					"T$amount". $lineBreak.     // Amount
					"M$descr". $lineBreak.      // Memo
					"P$vendor". $lineBreak;     // Payee
			} else {
				// secondary / split part of record
				$split = new Split();
				$split->account = $accountName;
				$split->memo = $row['memo'];
				$split->amount = $row['amount'];
				$splits[] = $split;
			}
		} while (true);
	}

	$account_list = Account::Get_account_list($login_id, 
		'L',	// LHS:  Asset / Liability accounts only
		-2,	// Parent ID value (don't show root accounts)
		false,	// don't force to one level
		false,	// show debit flag
		true	// show inactive
	);
	
	$account_dropdown = Build_dropdown ($account_list, 'account_id',
		$account_id);
		
	// disable buffering; we're not outputting a file
	ob_end_flush();
	
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
	<div id="export-div">
		<label for="export-account">Account to Export</label>
		<?= $account_dropdown ?>
		
		<input type="submit" value="Generate QIF File" />
	</div>
</form>


<?php require('footer.php'); ?>

</body>
</html>

