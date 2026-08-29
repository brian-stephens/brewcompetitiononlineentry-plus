<?php

ob_start();
require('../paths.php');
require(CONFIG.'bootstrap.php');
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

// Polling endpoint used by the waiting/reconcile screen (eval_reconcile.pub.php)
// to check whether another judge has since submitted or updated an evaluation
// for the same entry, without the judge having to refresh the page themselves.
// The consensus score itself is auto-computed elsewhere (recompute_eval_consensus())
// and is the same for every judge on the entry - this endpoint just reports it.

$status = 0;
$message = "";
$judge_count = 0;
$consensus_score = "";
$judges = array();

$eid = isset($_GET['eid']) ? sterilize($_GET['eid']) : "";

$session_active = FALSE;
if ((isset($_SESSION['session_set_'.$prefix_session])) && (isset($_SESSION['loginUsername']))) $session_active = TRUE;

$can_view_partner_data = FALSE;
if (($session_active) && (isset($_SESSION['user_id'])) && (isset($_SESSION['userLevel'])) && (!empty($eid)) && (is_numeric($eid))) {
	$can_view_partner_data = can_view_entry_evaluation($eid, $_SESSION['user_id'], $_SESSION['userLevel'], FALSE, FALSE);
}

if ($can_view_partner_data) {

	$eval_draft_filter_sql = "";
	if (check_update("evalDraft", $prefix."evaluation")) $eval_draft_filter_sql = " AND (evalDraft <> '1' OR evalDraft IS NULL)";

	$query_eval_rows = sprintf("SELECT a.id, a.evalJudgeInfo, a.evalAromaScore, a.evalAppearanceScore, a.evalFlavorScore, a.evalMouthfeelScore, a.evalOverallScore, a.evalFinalScore, b.brewerFirstName, b.brewerLastName FROM %s a LEFT JOIN %s b ON a.evalJudgeInfo = b.uid WHERE a.eid='%s'%s ORDER BY a.id ASC", $prefix."evaluation", $prefix."brewer", $eid, $eval_draft_filter_sql);
	$eval_rows = mysqli_query($connection,$query_eval_rows) or die (mysqli_error($connection));

	while ($row_eval = mysqli_fetch_assoc($eval_rows)) {

		$judge_total = (float)$row_eval['evalAromaScore'] + (float)$row_eval['evalAppearanceScore'] + (float)$row_eval['evalFlavorScore'] + (float)$row_eval['evalMouthfeelScore'] + (float)$row_eval['evalOverallScore'];

		$judge_name = "";
		if ((!empty($row_eval['brewerFirstName'])) || (!empty($row_eval['brewerLastName']))) {
			$judge_name = trim($row_eval['brewerFirstName']." ".mb_substr($row_eval['brewerLastName'],0,1).".");
		}

		if (($row_eval['evalFinalScore'] !== NULL) && ($row_eval['evalFinalScore'] !== "")) $consensus_score = (float)$row_eval['evalFinalScore'];

		$judges[] = array(
			"id" => $row_eval['id'],
			"judge_id" => $row_eval['evalJudgeInfo'],
			"is_me" => ((isset($_SESSION['user_id'])) && ($row_eval['evalJudgeInfo'] == $_SESSION['user_id'])),
			"name" => $judge_name,
			"total" => $judge_total
		);
	}

	$status = 1;
	$judge_count = count($judges);

}

else {
	$status = 9;
	$message = "Session expired, insufficient permissions, or invalid entry id.";
}

$return_json = array(
	"status" => $status,
	"message" => $message,
	"judge_count" => $judge_count,
	"consensus_score" => $consensus_score,
	"judges" => $judges
);

header('Content-Type: application/json');
echo json_encode($return_json);
mysqli_close($connection);

?>
