<?php
$container_eval = $container_main;

include (LIB.'admin.lib.php');
if ($go == "default") include (PUB.'eval_dashboard.pub.php');
if ($go == "scoresheet") include (PUB.'eval_scoresheet.pub.php');
if ($go == "reconcile") include (PUB.'eval_reconcile.pub.php');

?>
