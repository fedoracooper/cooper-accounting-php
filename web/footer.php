<?php
$endTime = microtime(true);

$elapsed = $endTime - $startTime;
$elapsedMessage = 'page built in '. sprintf('%.2f', $elapsed). ' seconds';
?>
<div style="padding-top: 5px; float: right; text-align: right; font-size: 9pt;"><?= $elapsedMessage ?></div>

