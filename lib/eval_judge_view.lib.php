<?php

/**
 * Admin Judge View helpers.
 * Lists judges with electronic evaluations and loads evaluations for a selected judge.
 */

/**
 * Judges who have at least one non-draft evaluation, sorted by last/first name.
 *
 * @return array
 */
function get_eval_judge_view_judges() {

	require(CONFIG.'config.php');
	mysqli_select_db($connection,$database);

	$judges = array();
	$draft_clause = eval_draft_filter_clause(TRUE);

	$query_judges = sprintf(
		"SELECT DISTINCT b.uid, b.brewerFirstName, b.brewerLastName, b.brewerJudgeID
		FROM %s e
		INNER JOIN %s b ON e.evalJudgeInfo = b.uid
		WHERE 1=1%s
		ORDER BY b.brewerLastName ASC, b.brewerFirstName ASC",
		$prefix."evaluation",
		$prefix."brewer",
		$draft_clause
	);
	$judges_rs = mysqli_query($connection, $query_judges) or die (mysqli_error($connection));

	while ($row = mysqli_fetch_assoc($judges_rs)) {
		$judges[] = array(
			"uid" => (int) $row['uid'],
			"first_name" => $row['brewerFirstName'],
			"last_name" => $row['brewerLastName'],
			"judge_id" => $row['brewerJudgeID']
		);
	}

	return $judges;

}

/**
 * Non-draft evaluations submitted by the given judge.
 *
 * @param int|string $judge_uid users.id / brewer.uid / evaluation.evalJudgeInfo
 * @return array
 */
function get_eval_judge_view_evaluations($judge_uid) {

	require(CONFIG.'config.php');
	mysqli_select_db($connection,$database);

	$out = array();
	$judge_uid = (int) $judge_uid;
	if ($judge_uid <= 0) return $out;

	$draft_clause = eval_draft_filter_clause(TRUE);

	$query_evals = sprintf(
		"SELECT e.id, e.eid, e.evalTable, e.evalAromaScore, e.evalAppearanceScore, e.evalFlavorScore,
			e.evalMouthfeelScore, e.evalOverallScore, e.evalFinalScore, e.evalInitialDate, e.evalUpdatedDate,
			e.evalScoresheet, b.brewJudgingNumber, b.brewCategory, b.brewCategorySort, b.brewSubCategory, b.brewStyle
		FROM %s e
		INNER JOIN %s b ON e.eid = b.id
		WHERE e.evalJudgeInfo='%s'%s
		ORDER BY b.brewCategorySort ASC, b.brewSubCategory ASC, b.brewJudgingNumber ASC",
		$prefix."evaluation",
		$prefix."brewing",
		$judge_uid,
		$draft_clause
	);
	$evals_rs = mysqli_query($connection, $query_evals) or die (mysqli_error($connection));

	while ($row = mysqli_fetch_assoc($evals_rs)) {
		$judge_score = (float) $row['evalAromaScore']
			+ (float) $row['evalAppearanceScore']
			+ (float) $row['evalFlavorScore']
			+ (float) $row['evalMouthfeelScore']
			+ (float) $row['evalOverallScore'];

		$out[] = array(
			"id" => (int) $row['id'],
			"eid" => (int) $row['eid'],
			"table" => $row['evalTable'],
			"judge_score" => $judge_score,
			"consensus_score" => $row['evalFinalScore'],
			"date_added" => $row['evalInitialDate'],
			"date_updated" => $row['evalUpdatedDate'],
			"scoresheet" => $row['evalScoresheet'],
			"brewJudgingNumber" => $row['brewJudgingNumber'],
			"brewCategory" => $row['brewCategory'],
			"brewCategorySort" => $row['brewCategorySort'],
			"brewSubCategory" => $row['brewSubCategory'],
			"brewStyle" => $row['brewStyle']
		);
	}

	return $out;

}
