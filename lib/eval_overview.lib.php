<?php

/**
 * Admin Entry Evaluations overview helpers.
 * Builds per-table progress, issue counts, and import readiness for a
 * judging session (judging_locations.id via judging_tables.tableLocation).
 */

/**
 * Returns an array of table status rows for the given judging session.
 *
 * @param int|string $session_id judging_locations.id
 * @return array
 */
function get_eval_overview_tables($session_id) {

	require(CONFIG.'config.php');
	mysqli_select_db($connection,$database);

	$tables_out = array();
	$session_id = (int) $session_id;
	if ($session_id <= 0) return $tables_out;

	$disp_max = 0;
	if ((isset($_SESSION['jPrefsScoreDispMax'])) && (is_numeric($_SESSION['jPrefsScoreDispMax']))) $disp_max = (float) $_SESSION['jPrefsScoreDispMax'];

	$query_tables = sprintf("SELECT id, tableNumber, tableName, tableStyles FROM %s WHERE tableLocation='%s' ORDER BY tableNumber ASC", $prefix."judging_tables", $session_id);
	$tables = mysqli_query($connection, $query_tables) or die (mysqli_error($connection));
	$totalRows_tables = mysqli_num_rows($tables);
	if ($totalRows_tables == 0) return $tables_out;

	$table_meta = array();
	$table_ids = array();
	while ($row_table = mysqli_fetch_assoc($tables)) {
		$tid = (int) $row_table['id'];
		$table_ids[] = $tid;
		$table_meta[$tid] = array(
			"table_id" => $tid,
			"table_number" => $row_table['tableNumber'],
			"table_name" => $row_table['tableName'],
			"table_label" => $row_table['tableNumber']." - ".$row_table['tableName'],
			"tableStyles" => $row_table['tableStyles'],
			"entry_ids" => array(),
			"entries" => 0,
			"scored" => 0,
			"imported" => 0,
			"issues" => array(
				"single_eval" => 0,
				"score_disparity" => 0,
				"duplicate_judge_evals" => 0,
				"duplicate_places" => 0,
				"mini_bos_mismatch" => 0,
				"none_submitted" => 0
			),
			"places" => array()
		);
	}

	// Resolve received entries for each table's styles.
	foreach ($table_meta as $tid => $meta) {

		if (empty($meta['tableStyles'])) continue;
		$style_ids = array_unique(array_filter(explode(",", $meta['tableStyles'])));
		sort($style_ids);

		foreach ($style_ids as $style_id) {

			$score_style_data = score_style_data($style_id);
			if (empty($score_style_data)) continue;
			$score_style_data = explode("^", $score_style_data);

			$query_entries = sprintf(
				"SELECT id FROM %s WHERE brewCategorySort='%s' AND brewSubCategory='%s' AND brewReceived='1'",
				$prefix."brewing",
				mysqli_real_escape_string($connection, $score_style_data[0]),
				mysqli_real_escape_string($connection, $score_style_data[1])
			);
			$entries = mysqli_query($connection, $query_entries) or die (mysqli_error($connection));
			while ($row_entry = mysqli_fetch_assoc($entries)) {
				$eid = (int) $row_entry['id'];
				if (!isset($table_meta[$tid]['entry_ids'][$eid])) {
					$table_meta[$tid]['entry_ids'][$eid] = TRUE;
					$table_meta[$tid]['entries'] += 1;
				}
			}

		}

	}

	// Load non-draft evaluations for these tables in one pass.
	$evals_by_table_eid = array();
	$table_id_list = implode(",", array_map('intval', $table_ids));
	$eval_draft_sql = eval_draft_filter_clause(TRUE);
	$query_evals = sprintf(
		"SELECT id, eid, evalJudgeInfo, evalTable, evalAromaScore, evalAppearanceScore, evalFlavorScore, evalMouthfeelScore, evalOverallScore, evalFinalScore, evalPlace, evalMiniBOS FROM %s WHERE evalTable IN (%s)%s",
		$prefix."evaluation",
		$table_id_list,
		$eval_draft_sql
	);
	$evals = mysqli_query($connection, $query_evals) or die (mysqli_error($connection));
	while ($row_eval = mysqli_fetch_assoc($evals)) {
		$tid = (int) $row_eval['evalTable'];
		$eid = (int) $row_eval['eid'];
		if (!isset($evals_by_table_eid[$tid])) $evals_by_table_eid[$tid] = array();
		if (!isset($evals_by_table_eid[$tid][$eid])) $evals_by_table_eid[$tid][$eid] = array();
		$evals_by_table_eid[$tid][$eid][] = $row_eval;
	}

	// Official imported scores for entries on these tables.
	$imported_eids = array();
	$all_entry_ids = array();
	foreach ($table_meta as $meta) {
		foreach (array_keys($meta['entry_ids']) as $eid) $all_entry_ids[$eid] = TRUE;
	}
	if (!empty($all_entry_ids)) {
		$eid_list = implode(",", array_map('intval', array_keys($all_entry_ids)));
		$query_scores = sprintf("SELECT eid, scoreEntry FROM %s WHERE eid IN (%s)", $prefix."judging_scores", $eid_list);
		$scores = mysqli_query($connection, $query_scores) or die (mysqli_error($connection));
		while ($row_score = mysqli_fetch_assoc($scores)) {
			if ((isset($row_score['scoreEntry'])) && ($row_score['scoreEntry'] !== "") && ($row_score['scoreEntry'] !== NULL)) {
				$imported_eids[(int) $row_score['eid']] = TRUE;
			}
		}
	}

	foreach ($table_meta as $tid => $meta) {

		$scored = 0;
		$imported = 0;
		$places_awarded = array();

		foreach (array_keys($meta['entry_ids']) as $eid) {

			if (isset($imported_eids[$eid])) $imported += 1;

			$entry_evals = array();
			if ((isset($evals_by_table_eid[$tid])) && (isset($evals_by_table_eid[$tid][$eid]))) {
				$entry_evals = $evals_by_table_eid[$tid][$eid];
			}
			$count_evals = count($entry_evals);

			if ($count_evals == 0) {
				$table_meta[$tid]['issues']['none_submitted'] += 1;
				continue;
			}

			$scored += 1;

			if ($count_evals == 1) $table_meta[$tid]['issues']['single_eval'] += 1;

			$judge_scores = array();
			$judge_ids = array();
			$mini_bos_count = 0;
			$eval_places = array();

			foreach ($entry_evals as $row_eval) {
				$judge_total = (float)$row_eval['evalAromaScore'] + (float)$row_eval['evalAppearanceScore'] + (float)$row_eval['evalFlavorScore'] + (float)$row_eval['evalMouthfeelScore'] + (float)$row_eval['evalOverallScore'];
				$judge_scores[] = $judge_total;
				$judge_ids[] = $row_eval['evalJudgeInfo'];
				if (!empty($row_eval['evalMiniBOS'])) $mini_bos_count += (int) $row_eval['evalMiniBOS'];
				if ((!empty($row_eval['evalPlace'])) && ($row_eval['evalPlace'] !== "0")) $eval_places[] = $row_eval['evalPlace'];
			}

			if ((!empty($judge_scores)) && ($disp_max > 0)) {
				if ((max($judge_scores) - min($judge_scores)) > $disp_max) {
					$table_meta[$tid]['issues']['score_disparity'] += 1;
				}
			}

			if (!empty($judge_ids)) {
				foreach (array_count_values($judge_ids) as $jid_count) {
					if ($jid_count > 1) {
						$table_meta[$tid]['issues']['duplicate_judge_evals'] += 1;
						break;
					}
				}
			}

			if (($mini_bos_count > 0) && ($count_evals > $mini_bos_count)) {
				$table_meta[$tid]['issues']['mini_bos_mismatch'] += 1;
			}

			if (!empty($eval_places)) {
				if (count(array_unique($eval_places)) === 1) $places_awarded[] = $eval_places[0];
				else {
					foreach ($eval_places as $place_val) $places_awarded[] = $place_val;
				}
			}

		}

		$table_meta[$tid]['scored'] = $scored;
		$table_meta[$tid]['imported'] = $imported;

		if ((isset($_SESSION['prefsWinnerMethod'])) && ($_SESSION['prefsWinnerMethod'] == "0") && (!empty($places_awarded))) {
			$places_for_dupe_check = array();
			foreach ($places_awarded as $place_val) {
				if (in_array((string)$place_val, array("1","2","3"), true)) $places_for_dupe_check[] = (string)$place_val;
			}
			if ((!empty($places_for_dupe_check)) && (count(array_unique($places_for_dupe_check)) < count($places_for_dupe_check))) {
				$table_meta[$tid]['issues']['duplicate_places'] = 1;
			}
		}

		$issue_total =
			$table_meta[$tid]['issues']['single_eval'] +
			$table_meta[$tid]['issues']['score_disparity'] +
			$table_meta[$tid]['issues']['duplicate_judge_evals'] +
			$table_meta[$tid]['issues']['duplicate_places'] +
			$table_meta[$tid]['issues']['mini_bos_mismatch'];

		$entries = (int) $table_meta[$tid]['entries'];
		$complete = (($entries > 0) && ($scored >= $entries));
		$has_issues = ($issue_total > 0);
		$already_imported = (($entries > 0) && ($imported >= $entries));
		$import_ready = (($complete) && (!$has_issues) && (!$already_imported));

		$percent = 0;
		if ($entries > 0) $percent = (int) round(($scored / $entries) * 100);
		if ($percent > 100) $percent = 100;

		$status = "in_progress";
		if ($already_imported) $status = "imported";
		elseif ($import_ready) $status = "ready";
		elseif ($has_issues) $status = "issues";

		$tables_out[] = array(
			"table_id" => $tid,
			"table_number" => $table_meta[$tid]['table_number'],
			"table_name" => $table_meta[$tid]['table_name'],
			"table_label" => $table_meta[$tid]['table_label'],
			"entries" => $entries,
			"scored" => $scored,
			"imported" => $imported,
			"percent" => $percent,
			"complete" => $complete ? 1 : 0,
			"has_issues" => $has_issues ? 1 : 0,
			"import_ready" => $import_ready ? 1 : 0,
			"already_imported" => $already_imported ? 1 : 0,
			"issue_total" => $issue_total,
			"issues" => $table_meta[$tid]['issues'],
			"status" => $status
		);

	}

	return $tables_out;

}
