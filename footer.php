<?php
$endTime = microtime(true);

$elapsed = $endTime - $startTime;
$elapsedMessage = 'page built in '. sprintf('%.2f', $elapsed). ' seconds';
$enableMetrics = false;
if ($connTime > 0.0 && $enableMetrics) {
	$metrics = 'conn time: '. sprintf('%.3f', $connTime) .
		'; execution time: '. sprintf('%.3f', $execTime) .
		'; read time: '. sprintf('%.3f', $readTime).
		//'; tx time: '. sprintf('%.3f', $txTime).
		'; count: '. $sqlCount;
} else {
	$metrics = '';
}
?>
<div style="padding-top: 5px; float: right; text-align: right; font-size: 9pt;"><?= $elapsedMessage ?><br/><br/><?= $metrics ?></div>

