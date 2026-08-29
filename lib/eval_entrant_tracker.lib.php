<?php

/**
 * Entrant placement tracker helpers.
 * Supports entrant search and live placement status for selected entrants.
 */

function eval_entrant_tracker_normalize_uids($uids_raw) {

	$uids = array();

	if (is_array($uids_raw)) $uids_in = $uids_raw;
	else $uids_in = explode(",", (string) $uids_raw);

	foreach ($uids_in as $uid_raw) {
		$uid = (int) sterilize($uid_raw);
		if ($uid > 0) $uids[$uid] = $uid;
	}

	return array_values($uids);
}

function eval_entrant_tracker_normalize_category_ids($category_raw) {

	$categories = array();
	if (is_array($category_raw)) $category_in = $category_raw;
	else $category_in = explode(",", (string) $category_raw);

	foreach ($category_in as $category_item) {
		$category = trim((string) $category_item);
		if ($category === "") continue;
		$category = preg_replace("/[^A-Za-z0-9_-]/", "", $category);
		if ($category === "") continue;
		$categories[$category] = $category;
	}

	return array_values($categories);
}

function eval_entrant_tracker_normalize_session_ids($session_raw) {

	$sessions = array();
	if (is_array($session_raw)) $session_in = $session_raw;
	else $session_in = explode(",", (string) $session_raw);

	foreach ($session_in as $session_item) {
		$session_id = (int) sterilize($session_item);
		if ($session_id <= 0) continue;
		$sessions[$session_id] = $session_id;
	}

	return array_values($sessions);
}

function get_entrant_tracker_search($query, $limit = 15) {

	require(CONFIG.'config.php');
	mysqli_select_db($connection,$database);

	$results = array();
	$query = trim((string)$query);
	$limit = (int) $limit;
	if ($limit <= 0) $limit = 15;
	if ($limit > 50) $limit = 50;
	if (strlen($query) < 2) return $results;

	$q_esc = mysqli_real_escape_string($connection, $query);
	$like = "%".$q_esc."%";
	$like_esc = mysqli_real_escape_string($connection, $like);

	$query_search = sprintf(
		"SELECT b.uid, b.brewerFirstName, b.brewerLastName, b.brewerClubs, COUNT(br.id) AS entry_count
		 FROM %s b
		 INNER JOIN %s br ON CAST(br.brewBrewerID AS CHAR) = CAST(b.uid AS CHAR) AND br.brewReceived = '1'
		 WHERE (
			b.brewerFirstName LIKE '%s'
			OR b.brewerLastName LIKE '%s'
			OR CONCAT(IFNULL(b.brewerFirstName,''), ' ', IFNULL(b.brewerLastName,'')) LIKE '%s'
			OR CONCAT(IFNULL(b.brewerLastName,''), ', ', IFNULL(b.brewerFirstName,'')) LIKE '%s'
			OR b.brewerClubs LIKE '%s'
		 )
		 GROUP BY b.uid, b.brewerFirstName, b.brewerLastName, b.brewerClubs
		 ORDER BY b.brewerLastName ASC, b.brewerFirstName ASC
		 LIMIT %d",
		$prefix."brewer",
		$prefix."brewing",
		$like_esc,
		$like_esc,
		$like_esc,
		$like_esc,
		$like_esc,
		$limit
	);
	$search = mysqli_query($connection, $query_search);
	if (!$search) return $results;

	while ($row = mysqli_fetch_assoc($search)) {
		$name = trim($row['brewerFirstName']." ".$row['brewerLastName']);
		if (empty($name)) $name = "Entrant #".(int)$row['uid'];
		$results[] = array(
			"uid" => (int) $row['uid'],
			"name" => $name,
			"club" => (!empty($row['brewerClubs'])) ? $row['brewerClubs'] : "",
			"entry_count" => (int) $row['entry_count']
		);
	}

	return $results;
}

function get_category_tracker_search($query, $limit = 15) {

	require(CONFIG.'config.php');
	mysqli_select_db($connection,$database);

	$results = array();
	$query = trim((string)$query);
	$limit = (int) $limit;
	if ($limit <= 0) $limit = 15;
	if ($limit > 50) $limit = 50;
	if (strlen($query) < 2) return $results;

	$q_esc = mysqli_real_escape_string($connection, $query);
	$like = "%".$q_esc."%";
	$like_esc = mysqli_real_escape_string($connection, $like);

	$query_search = sprintf(
		"SELECT
			br.brewCategorySort AS category_id,
			MAX(br.brewCategory) AS category_name,
			MAX(br.brewStyle) AS sample_style,
			COUNT(br.id) AS entry_count
		 FROM %s br
		 WHERE br.brewReceived = '1'
		 AND br.brewCategorySort IS NOT NULL
		 AND br.brewCategorySort <> ''
		 AND (
			br.brewCategorySort LIKE '%s'
			OR br.brewCategory LIKE '%s'
			OR br.brewStyle LIKE '%s'
		 )
		 GROUP BY br.brewCategorySort
		 ORDER BY CAST(br.brewCategorySort AS UNSIGNED) ASC, br.brewCategorySort ASC
		 LIMIT %d",
		$prefix."brewing",
		$like_esc,
		$like_esc,
		$like_esc,
		$limit
	);
	$search = mysqli_query($connection, $query_search);
	if (!$search) return $results;

	while ($row = mysqli_fetch_assoc($search)) {
		$category_id = preg_replace("/[^A-Za-z0-9_-]/", "", (string)$row['category_id']);
		if ($category_id === "") continue;
		$category_label = "Category ".$category_id;
		if (!empty($row['sample_style'])) $category_label = trim((string)$row['sample_style']);
		elseif (!empty($row['category_name'])) $category_label = trim((string)$row['category_name']);
		$results[] = array(
			"id" => $category_id,
			"cid" => $category_id,
			"name" => $category_label,
			"meta" => $category_id,
			"entry_count" => (int)$row['entry_count']
		);
	}

	return $results;
}

function get_session_tracker_search($query, $limit = 15) {

	require(CONFIG.'config.php');
	mysqli_select_db($connection,$database);

	$results = array();
	$query = trim((string)$query);
	$limit = (int) $limit;
	if ($limit <= 0) $limit = 15;
	if ($limit > 50) $limit = 50;
	if (strlen($query) < 2) return $results;

	$q_esc = mysqli_real_escape_string($connection, $query);
	$like = "%".$q_esc."%";
	$like_esc = mysqli_real_escape_string($connection, $like);

	$query_search = sprintf(
		"SELECT
			jl.id,
			jl.judgingLocName,
			jl.judgingDate,
			COUNT(jt.id) AS table_count
		 FROM %s jl
		 LEFT JOIN %s jt ON jt.tableLocation = jl.id
		 WHERE jl.judgingLocType < 2
		 AND (
			jl.judgingLocName LIKE '%s'
			OR CAST(jl.id AS CHAR) LIKE '%s'
		 )
		 GROUP BY jl.id, jl.judgingLocName, jl.judgingDate
		 ORDER BY jl.judgingDate DESC, jl.id DESC
		 LIMIT %d",
		$prefix."judging_locations",
		$prefix."judging_tables",
		$like_esc,
		$like_esc,
		$limit
	);
	$search = mysqli_query($connection, $query_search);
	if (!$search) return $results;

	while ($row = mysqli_fetch_assoc($search)) {
		$sid = (int)$row['id'];
		$date_text = "";
		if (!empty($row['judgingDate'])) {
			$date_text = getTimeZoneDateTime($_SESSION['prefsTimeZone'], $row['judgingDate'], $_SESSION['prefsDateFormat'], $_SESSION['prefsTimeFormat'], "short", "date-no-gmt");
		}
		$name = (!empty($row['judgingLocName'])) ? $row['judgingLocName'] : ("Session ".$sid);
		$results[] = array(
			"id" => $sid,
			"sid" => $sid,
			"name" => $name,
			"meta" => $date_text,
			"entry_count" => (int)$row['table_count']
		);
	}

	return $results;
}

function eval_entrant_tracker_place_to_int($place) {
	if ($place === NULL) return 0;
	if ($place === "") return 0;
	if (!is_numeric($place)) return 0;
	$place_num = (int) $place;
	if ($place_num <= 0) return 0;
	return $place_num;
}

function eval_entrant_tracker_is_bos_pull($place_num, $style_type, $style_type_map) {
	if ($place_num <= 0) return FALSE;
	if ($place_num > 3) return FALSE;
	if (($style_type <= 0) || (!isset($style_type_map[$style_type]))) return FALSE;

	$type = $style_type_map[$style_type];
	if ((string)$type['styleTypeBOS'] !== "Y") return FALSE;

	$method = (int) $type['styleTypeBOSMethod'];
	if ($method <= 0) $method = 1;
	if ($place_num <= $method) return TRUE;
	return FALSE;
}

function get_entrant_tracker_status($uids, $include_eval_places = TRUE) {

	require(CONFIG.'config.php');
	mysqli_select_db($connection,$database);

	$uids = eval_entrant_tracker_normalize_uids($uids);
	$data = array();
	if (empty($uids)) return $data;

	$uid_lookup = array();
	foreach ($uids as $uid) $uid_lookup[$uid] = $uid;
	$uid_list = implode(",", array_map('intval', $uids));

	$query_style_types = sprintf("SELECT id, styleTypeBOS, styleTypeBOSMethod, styleTypeName FROM %s", $prefix."style_types");
	$style_types = mysqli_query($connection, $query_style_types) or die (mysqli_error($connection));
	$style_type_map = array();
	while ($row_style_type = mysqli_fetch_assoc($style_types)) {
		$style_type_id = (int) $row_style_type['id'];
		$style_type_map[$style_type_id] = array(
			"styleTypeBOS" => $row_style_type['styleTypeBOS'],
			"styleTypeBOSMethod" => (int) $row_style_type['styleTypeBOSMethod'],
			"styleTypeName" => $row_style_type['styleTypeName']
		);
	}

	$query_entrants = sprintf(
		"SELECT b.uid, b.brewerFirstName, b.brewerLastName, b.brewerClubs, b.brewerJudge, COALESCE(s.staff_judge, 0) AS staff_judge
		 FROM %s b
		 LEFT JOIN %s s ON s.uid = b.uid
		 WHERE b.uid IN (%s)
		 ORDER BY b.brewerLastName ASC, b.brewerFirstName ASC",
		$prefix."brewer",
		$prefix."staff",
		$uid_list
	);
	$entrants = mysqli_query($connection, $query_entrants) or die (mysqli_error($connection));

	$entrant_map = array();
	while ($row_entrant = mysqli_fetch_assoc($entrants)) {
		$uid = (int)$row_entrant['uid'];
		$name = trim($row_entrant['brewerFirstName']." ".$row_entrant['brewerLastName']);
		if (empty($name)) $name = "Entrant #".$uid;
		$entrant_map[$uid] = array(
			"uid" => $uid,
			"name" => $name,
			"club" => (!empty($row_entrant['brewerClubs'])) ? $row_entrant['brewerClubs'] : "",
			"is_judge" => (($row_entrant['brewerJudge'] == "Y") || ((int)$row_entrant['staff_judge'] == 1)) ? 1 : 0,
			"status" => "clear",
			"has_gold" => 0,
			"has_bos_pull" => 0,
			"has_place" => 0,
			"has_pending" => 0,
			"entry_count" => 0,
			"placed_count" => 0,
			"pending_count" => 0,
			"entries" => array()
		);
	}

	if (empty($entrant_map)) return $data;

	// Do not join styles by group/num alone — multiple style-set versions share
	// those keys and would duplicate every entry row.
	$query_entries = sprintf(
		"SELECT
			br.id,
			br.brewBrewerID,
			br.brewName,
			br.brewJudgingNumber,
			br.brewCategorySort,
			br.brewSubCategory,
			br.brewStyle,
			br.brewStyleType,
			js.id AS score_id,
			js.scoreEntry AS official_score,
			js.scorePlace AS official_place,
			js.scoreType AS official_style_type,
			js.scoreTable,
			jt.tableNumber,
			jt.tableName
		 FROM %s br
		 LEFT JOIN %s js ON js.eid = br.id
		 LEFT JOIN %s jt ON jt.id = js.scoreTable
		 WHERE br.brewReceived = '1' AND br.brewBrewerID IN (%s)
		 ORDER BY br.brewBrewerID ASC, br.id ASC",
		$prefix."brewing",
		$prefix."judging_scores",
		$prefix."judging_tables",
		$uid_list
	);
	$entries = mysqli_query($connection, $query_entries) or die (mysqli_error($connection));

	$rows = array();
	$entry_ids = array();
	$seen_eids = array();
	while ($row_entry = mysqli_fetch_assoc($entries)) {
		$eid = (int) $row_entry['id'];
		// Guard against duplicate score rows for the same entry.
		if (isset($seen_eids[$eid])) continue;
		$seen_eids[$eid] = TRUE;
		$rows[] = $row_entry;
		$entry_ids[] = $eid;
	}

	$eval_place_by_eid = array();
	$eval_count_by_eid = array();
	$eval_consensus_by_eid = array();
	$eval_consensus_score_by_eid = array();
	if (!empty($entry_ids)) {
		$eval_where = eval_draft_filter_clause(TRUE);
		$entry_id_list = implode(",", array_map('intval', array_unique($entry_ids)));
		$query_eval_progress = sprintf(
			"SELECT
				eid,
				COUNT(*) AS eval_count,
				MAX(CASE WHEN evalPlace IS NOT NULL AND evalPlace > 0 THEN evalPlace ELSE NULL END) AS eval_place,
				MAX(CASE WHEN evalFinalScore IS NOT NULL AND evalFinalScore > 0 THEN 1 ELSE 0 END) AS has_consensus,
				MAX(CASE WHEN evalFinalScore IS NOT NULL AND evalFinalScore > 0 THEN evalFinalScore ELSE NULL END) AS consensus_score
			 FROM %s
			 WHERE eid IN (%s)%s
			 GROUP BY eid",
			$prefix."evaluation",
			$entry_id_list,
			$eval_where
		);
		$eval_progress = mysqli_query($connection, $query_eval_progress) or die (mysqli_error($connection));
		while ($row_eval_progress = mysqli_fetch_assoc($eval_progress)) {
			$eid = (int) $row_eval_progress['eid'];
			$eval_count_by_eid[$eid] = (int) $row_eval_progress['eval_count'];
			$eval_consensus_by_eid[$eid] = ((int) $row_eval_progress['has_consensus'] == 1) ? 1 : 0;
			if (!empty($row_eval_progress['consensus_score'])) {
				$eval_consensus_score_by_eid[$eid] = (float) $row_eval_progress['consensus_score'];
			}
			if (($include_eval_places) && (!empty($row_eval_progress['eval_place']))) {
				$eval_place_by_eid[$eid] = (int) $row_eval_progress['eval_place'];
			}
		}
	}

	foreach ($rows as $row) {

		$uid = (int) $row['brewBrewerID'];
		if ((!isset($uid_lookup[$uid])) || (!isset($entrant_map[$uid]))) continue;

		$eid = (int) $row['id'];
		$official_place_num = eval_entrant_tracker_place_to_int($row['official_place']);
		$eval_place_num = (isset($eval_place_by_eid[$eid])) ? (int)$eval_place_by_eid[$eid] : 0;
		$eval_count = (isset($eval_count_by_eid[$eid])) ? (int)$eval_count_by_eid[$eid] : 0;
		$has_consensus = (isset($eval_consensus_by_eid[$eid])) ? (int)$eval_consensus_by_eid[$eid] : 0;
		$has_official_score = ((!empty($row['score_id'])) && (($row['official_score'] !== NULL) && ($row['official_score'] !== "")));
		$official_score = ($has_official_score) ? (float)$row['official_score'] : NULL;
		$consensus_score = (isset($eval_consensus_score_by_eid[$eid])) ? (float)$eval_consensus_score_by_eid[$eid] : NULL;
		$display_score = NULL;
		$score_source = "none";
		if ($official_score !== NULL) {
			$display_score = $official_score;
			$score_source = "official";
		}
		elseif ($consensus_score !== NULL) {
			$display_score = $consensus_score;
			$score_source = "eval";
		}
		$place_num = 0;
		$place_source = "none";
		if ($official_place_num > 0) {
			$place_num = $official_place_num;
			$place_source = "official";
		}
		elseif (($include_eval_places) && ($eval_place_num > 0)) {
			$place_num = $eval_place_num;
			$place_source = "eval";
		}

		$style_type = 0;
		if (!empty($row['official_style_type'])) $style_type = (int) $row['official_style_type'];
		elseif (!empty($row['brewStyleType'])) $style_type = (int) $row['brewStyleType'];

		$bos_pull = eval_entrant_tracker_is_bos_pull($place_num, $style_type, $style_type_map);

		$bos_method = 0;
		if (($style_type > 0) && (isset($style_type_map[$style_type]))) {
			$bos_method = (int) $style_type_map[$style_type]['styleTypeBOSMethod'];
			if ($bos_method <= 0) $bos_method = 1;
		}

		$table_label = "";
		if (!empty($row['tableNumber']) || !empty($row['tableName'])) {
			$table_bits = array();
			if (!empty($row['tableNumber'])) $table_bits[] = $row['tableNumber'];
			if (!empty($row['tableName'])) $table_bits[] = $row['tableName'];
			$table_label = implode(" - ", $table_bits);
		}

		// Progress: once officially scored, treat as complete and show whether an official place exists.
		$progress = "not_started";
		$progress_pending = 1;
		if ($has_official_score) {
			$progress = ($official_place_num > 0) ? "placed_official" : "complete_no_place";
			$progress_pending = 0;
		}
		elseif ($place_num > 0) {
			$progress = ($place_source == "official") ? "placed_official" : "placed_pending";
			$progress_pending = ($place_source == "official") ? 0 : 1;
		}
		elseif ($eval_count <= 0) {
			$progress = "not_started";
			$progress_pending = 1;
		}
		elseif (($eval_count == 1) || ($has_consensus == 0)) {
			$progress = "in_progress";
			$progress_pending = 1;
		}
		else {
			// 2+ evals with consensus, but no place and not imported yet.
			$progress = "awaiting_place";
			$progress_pending = 1;
		}

		$entry = array(
			"eid" => $eid,
			"entry_name" => (!empty($row['brewName'])) ? $row['brewName'] : "",
			"judging_number" => (!empty($row['brewJudgingNumber'])) ? $row['brewJudgingNumber'] : "",
			"style" => (!empty($row['brewStyle'])) ? $row['brewStyle'] : "",
			"category" => (!empty($row['brewCategorySort'])) ? $row['brewCategorySort'] : "",
			"subcategory" => (!empty($row['brewSubCategory'])) ? $row['brewSubCategory'] : "",
			"table_label" => $table_label,
			"place" => ($place_num > 0) ? $place_num : NULL,
			"place_source" => $place_source,
			"official_place" => ($official_place_num > 0) ? $official_place_num : NULL,
			"eval_place" => ($eval_place_num > 0) ? $eval_place_num : NULL,
			"place_display" => ($place_num > 0) ? display_place((string)$place_num, "2") : "",
			"bos_pull" => $bos_pull ? 1 : 0,
			"style_type" => $style_type,
			"bos_method" => $bos_method,
			"eval_count" => $eval_count,
			"has_consensus" => $has_consensus,
			"has_official_score" => $has_official_score ? 1 : 0,
			"consensus_score" => $consensus_score,
			"official_score" => $official_score,
			"score" => $display_score,
			"score_source" => $score_source,
			"progress" => $progress,
			"progress_pending" => $progress_pending
		);

		$entrant_map[$uid]['entries'][] = $entry;
		$entrant_map[$uid]['entry_count'] += 1;

		if ($place_num > 0) {
			$entrant_map[$uid]['has_place'] = 1;
			$entrant_map[$uid]['placed_count'] += 1;
		}
		if ($place_num == 1) $entrant_map[$uid]['has_gold'] = 1;
		if ($bos_pull) $entrant_map[$uid]['has_bos_pull'] = 1;
		if ($progress_pending) {
			$entrant_map[$uid]['has_pending'] = 1;
			$entrant_map[$uid]['pending_count'] += 1;
		}
	}

	foreach ($uids as $uid) {
		if (!isset($entrant_map[$uid])) continue;
		if ($entrant_map[$uid]['has_gold']) $entrant_map[$uid]['status'] = "gold";
		elseif ($entrant_map[$uid]['has_bos_pull']) $entrant_map[$uid]['status'] = "bos_pull";
		elseif ($entrant_map[$uid]['has_pending']) $entrant_map[$uid]['status'] = "pending";
		elseif ($entrant_map[$uid]['has_place']) $entrant_map[$uid]['status'] = "has_place";
		else $entrant_map[$uid]['status'] = "clear";
		$data[] = $entrant_map[$uid];
	}

	return $data;
}

function get_category_tracker_status($categories, $include_eval_places = TRUE) {

	require(CONFIG.'config.php');
	mysqli_select_db($connection,$database);

	$categories = eval_entrant_tracker_normalize_category_ids($categories);
	$data = array();
	if (empty($categories)) return $data;

	$category_lookup = array();
	$category_sql_parts = array();
	foreach ($categories as $category) {
		$category_lookup[$category] = $category;
		$category_sql_parts[] = "'".mysqli_real_escape_string($connection, $category)."'";
	}
	$category_sql_list = implode(",", $category_sql_parts);

	$query_style_types = sprintf("SELECT id, styleTypeBOS, styleTypeBOSMethod, styleTypeName FROM %s", $prefix."style_types");
	$style_types = mysqli_query($connection, $query_style_types) or die (mysqli_error($connection));
	$style_type_map = array();
	while ($row_style_type = mysqli_fetch_assoc($style_types)) {
		$style_type_id = (int) $row_style_type['id'];
		$style_type_map[$style_type_id] = array(
			"styleTypeBOS" => $row_style_type['styleTypeBOS'],
			"styleTypeBOSMethod" => (int) $row_style_type['styleTypeBOSMethod'],
			"styleTypeName" => $row_style_type['styleTypeName']
		);
	}

	$category_map = array();
	foreach ($categories as $category) {
		$category_map[$category] = array(
			"uid" => $category,
			"name" => "Category ".$category,
			"club" => "",
			"is_judge" => 0,
			"status" => "clear",
			"has_gold" => 0,
			"has_bos_pull" => 0,
			"has_place" => 0,
			"has_pending" => 0,
			"entry_count" => 0,
			"placed_count" => 0,
			"pending_count" => 0,
			"entries" => array()
		);
	}

	$query_entries = sprintf(
		"SELECT
			br.id,
			br.brewBrewerID,
			br.brewName,
			br.brewJudgingNumber,
			br.brewCategorySort,
			br.brewCategory,
			br.brewSubCategory,
			br.brewStyle,
			br.brewStyleType,
			js.id AS score_id,
			js.scoreEntry AS official_score,
			js.scorePlace AS official_place,
			js.scoreType AS official_style_type,
			js.scoreTable,
			jt.tableNumber,
			jt.tableName,
			b.brewerFirstName,
			b.brewerLastName
		 FROM %s br
		 LEFT JOIN %s js ON js.eid = br.id
		 LEFT JOIN %s jt ON jt.id = js.scoreTable
		 LEFT JOIN %s b ON CAST(b.uid AS CHAR) = CAST(br.brewBrewerID AS CHAR)
		 WHERE br.brewReceived = '1' AND br.brewCategorySort IN (%s)
		 ORDER BY CAST(br.brewCategorySort AS UNSIGNED) ASC, br.brewCategorySort ASC, br.id ASC",
		$prefix."brewing",
		$prefix."judging_scores",
		$prefix."judging_tables",
		$prefix."brewer",
		$category_sql_list
	);
	$entries = mysqli_query($connection, $query_entries) or die (mysqli_error($connection));

	$rows = array();
	$entry_ids = array();
	$seen_eids = array();
	while ($row_entry = mysqli_fetch_assoc($entries)) {
		$eid = (int) $row_entry['id'];
		if (isset($seen_eids[$eid])) continue;
		$seen_eids[$eid] = TRUE;
		$rows[] = $row_entry;
		$entry_ids[] = $eid;
	}

	$eval_place_by_eid = array();
	$eval_count_by_eid = array();
	$eval_consensus_by_eid = array();
	$eval_consensus_score_by_eid = array();
	if (!empty($entry_ids)) {
		$eval_where = eval_draft_filter_clause(TRUE);
		$entry_id_list = implode(",", array_map('intval', array_unique($entry_ids)));
		$query_eval_progress = sprintf(
			"SELECT
				eid,
				COUNT(*) AS eval_count,
				MAX(CASE WHEN evalPlace IS NOT NULL AND evalPlace > 0 THEN evalPlace ELSE NULL END) AS eval_place,
				MAX(CASE WHEN evalFinalScore IS NOT NULL AND evalFinalScore > 0 THEN 1 ELSE 0 END) AS has_consensus,
				MAX(CASE WHEN evalFinalScore IS NOT NULL AND evalFinalScore > 0 THEN evalFinalScore ELSE NULL END) AS consensus_score
			 FROM %s
			 WHERE eid IN (%s)%s
			 GROUP BY eid",
			$prefix."evaluation",
			$entry_id_list,
			$eval_where
		);
		$eval_progress = mysqli_query($connection, $query_eval_progress) or die (mysqli_error($connection));
		while ($row_eval_progress = mysqli_fetch_assoc($eval_progress)) {
			$eid = (int) $row_eval_progress['eid'];
			$eval_count_by_eid[$eid] = (int) $row_eval_progress['eval_count'];
			$eval_consensus_by_eid[$eid] = ((int) $row_eval_progress['has_consensus'] == 1) ? 1 : 0;
			if (!empty($row_eval_progress['consensus_score'])) {
				$eval_consensus_score_by_eid[$eid] = (float) $row_eval_progress['consensus_score'];
			}
			if (($include_eval_places) && (!empty($row_eval_progress['eval_place']))) {
				$eval_place_by_eid[$eid] = (int) $row_eval_progress['eval_place'];
			}
		}
	}

	foreach ($rows as $row) {

		$category_id = preg_replace("/[^A-Za-z0-9_-]/", "", (string)$row['brewCategorySort']);
		if (($category_id === "") || (!isset($category_lookup[$category_id])) || (!isset($category_map[$category_id]))) continue;

		if (!empty($row['brewStyle'])) {
			$category_map[$category_id]['name'] = trim((string)$row['brewStyle']);
		}
		elseif (!empty($row['brewCategory'])) {
			$category_map[$category_id]['name'] = trim((string)$row['brewCategory']);
		}

		$eid = (int) $row['id'];
		$official_place_num = eval_entrant_tracker_place_to_int($row['official_place']);
		$eval_place_num = (isset($eval_place_by_eid[$eid])) ? (int)$eval_place_by_eid[$eid] : 0;
		$eval_count = (isset($eval_count_by_eid[$eid])) ? (int)$eval_count_by_eid[$eid] : 0;
		$has_consensus = (isset($eval_consensus_by_eid[$eid])) ? (int)$eval_consensus_by_eid[$eid] : 0;
		$has_official_score = ((!empty($row['score_id'])) && (($row['official_score'] !== NULL) && ($row['official_score'] !== "")));
		$official_score = ($has_official_score) ? (float)$row['official_score'] : NULL;
		$consensus_score = (isset($eval_consensus_score_by_eid[$eid])) ? (float)$eval_consensus_score_by_eid[$eid] : NULL;
		$display_score = NULL;
		$score_source = "none";
		if ($official_score !== NULL) {
			$display_score = $official_score;
			$score_source = "official";
		}
		elseif ($consensus_score !== NULL) {
			$display_score = $consensus_score;
			$score_source = "eval";
		}
		$place_num = 0;
		$place_source = "none";
		if ($official_place_num > 0) {
			$place_num = $official_place_num;
			$place_source = "official";
		}
		elseif (($include_eval_places) && ($eval_place_num > 0)) {
			$place_num = $eval_place_num;
			$place_source = "eval";
		}

		$style_type = 0;
		if (!empty($row['official_style_type'])) $style_type = (int) $row['official_style_type'];
		elseif (!empty($row['brewStyleType'])) $style_type = (int) $row['brewStyleType'];

		$bos_pull = eval_entrant_tracker_is_bos_pull($place_num, $style_type, $style_type_map);

		$bos_method = 0;
		if (($style_type > 0) && (isset($style_type_map[$style_type]))) {
			$bos_method = (int) $style_type_map[$style_type]['styleTypeBOSMethod'];
			if ($bos_method <= 0) $bos_method = 1;
		}

		$table_label = "";
		if (!empty($row['tableNumber']) || !empty($row['tableName'])) {
			$table_bits = array();
			if (!empty($row['tableNumber'])) $table_bits[] = $row['tableNumber'];
			if (!empty($row['tableName'])) $table_bits[] = $row['tableName'];
			$table_label = implode(" - ", $table_bits);
		}

		$progress = "not_started";
		$progress_pending = 1;
		if ($has_official_score) {
			$progress = ($official_place_num > 0) ? "placed_official" : "complete_no_place";
			$progress_pending = 0;
		}
		elseif ($place_num > 0) {
			$progress = ($place_source == "official") ? "placed_official" : "placed_pending";
			$progress_pending = ($place_source == "official") ? 0 : 1;
		}
		elseif ($eval_count <= 0) {
			$progress = "not_started";
			$progress_pending = 1;
		}
		elseif (($eval_count == 1) || ($has_consensus == 0)) {
			$progress = "in_progress";
			$progress_pending = 1;
		}
		else {
			$progress = "awaiting_place";
			$progress_pending = 1;
		}

		$entrant_name = trim((string)$row['brewerFirstName']." ".(string)$row['brewerLastName']);
		if ($entrant_name === "") $entrant_name = "Entrant #".(int)$row['brewBrewerID'];

		$entry = array(
			"eid" => $eid,
			"entry_name" => (!empty($row['brewName'])) ? $row['brewName'] : "",
			"entrant_name" => $entrant_name,
			"judging_number" => (!empty($row['brewJudgingNumber'])) ? $row['brewJudgingNumber'] : "",
			"style" => (!empty($row['brewStyle'])) ? $row['brewStyle'] : "",
			"category" => (!empty($row['brewCategorySort'])) ? $row['brewCategorySort'] : "",
			"subcategory" => (!empty($row['brewSubCategory'])) ? $row['brewSubCategory'] : "",
			"table_label" => $table_label,
			"place" => ($place_num > 0) ? $place_num : NULL,
			"place_source" => $place_source,
			"official_place" => ($official_place_num > 0) ? $official_place_num : NULL,
			"eval_place" => ($eval_place_num > 0) ? $eval_place_num : NULL,
			"place_display" => ($place_num > 0) ? display_place((string)$place_num, "2") : "",
			"bos_pull" => $bos_pull ? 1 : 0,
			"style_type" => $style_type,
			"bos_method" => $bos_method,
			"eval_count" => $eval_count,
			"has_consensus" => $has_consensus,
			"has_official_score" => $has_official_score ? 1 : 0,
			"consensus_score" => $consensus_score,
			"official_score" => $official_score,
			"score" => $display_score,
			"score_source" => $score_source,
			"progress" => $progress,
			"progress_pending" => $progress_pending
		);

		$category_map[$category_id]['entries'][] = $entry;
		$category_map[$category_id]['entry_count'] += 1;

		if ($place_num > 0) {
			$category_map[$category_id]['has_place'] = 1;
			$category_map[$category_id]['placed_count'] += 1;
		}
		if ($place_num == 1) $category_map[$category_id]['has_gold'] = 1;
		if ($bos_pull) $category_map[$category_id]['has_bos_pull'] = 1;
		if ($progress_pending) {
			$category_map[$category_id]['has_pending'] = 1;
			$category_map[$category_id]['pending_count'] += 1;
		}
	}

	foreach ($categories as $category) {
		if (!isset($category_map[$category])) continue;
		if ($category_map[$category]['has_gold']) $category_map[$category]['status'] = "gold";
		elseif ($category_map[$category]['has_bos_pull']) $category_map[$category]['status'] = "bos_pull";
		elseif ($category_map[$category]['has_pending']) $category_map[$category]['status'] = "pending";
		elseif ($category_map[$category]['has_place']) $category_map[$category]['status'] = "has_place";
		else $category_map[$category]['status'] = "clear";
		$data[] = $category_map[$category];
	}

	return $data;
}

function get_session_tracker_status($sessions) {

	require(CONFIG.'config.php');
	mysqli_select_db($connection,$database);
	include_once(LIB.'admin.lib.php');
	include_once(LIB.'eval_overview.lib.php');

	$sessions = eval_entrant_tracker_normalize_session_ids($sessions);
	$data = array();
	if (empty($sessions)) return $data;

	$session_lookup = array();
	foreach ($sessions as $session_id) $session_lookup[$session_id] = $session_id;
	$session_list = implode(",", array_map('intval', $sessions));

	$query_sessions = sprintf(
		"SELECT id, judgingLocName, judgingDate
		 FROM %s
		 WHERE judgingLocType < 2 AND id IN (%s)
		 ORDER BY judgingDate DESC, id DESC",
		$prefix."judging_locations",
		$session_list
	);
	$sessions_rs = mysqli_query($connection, $query_sessions) or die (mysqli_error($connection));

	$session_map = array();
	while ($row_session = mysqli_fetch_assoc($sessions_rs)) {
		$sid = (int)$row_session['id'];
		$name = (!empty($row_session['judgingLocName'])) ? $row_session['judgingLocName'] : ("Session ".$sid);
		$date_text = "";
		if (!empty($row_session['judgingDate'])) {
			$date_text = getTimeZoneDateTime($_SESSION['prefsTimeZone'], $row_session['judgingDate'], $_SESSION['prefsDateFormat'], $_SESSION['prefsTimeFormat'], "short", "date-no-gmt");
		}
		$session_map[$sid] = array(
			"uid" => $sid,
			"name" => $name,
			"club" => $date_text,
			"is_judge" => 0,
			"status" => "in_progress",
			"entry_count" => 0,
			"placed_count" => 0,
			"pending_count" => 0,
			"entries" => array(),
			"table_status_counts" => array(
				"in_progress" => 0,
				"issues" => 0,
				"ready" => 0,
				"imported" => 0
			)
		);
	}

	foreach ($sessions as $session_id) {
		if ((!isset($session_lookup[$session_id])) || (!isset($session_map[$session_id]))) continue;
		$tables = get_eval_overview_tables($session_id);

		foreach ($tables as $tbl) {
			$status_key = (isset($tbl['status'])) ? (string)$tbl['status'] : "in_progress";
			if (!isset($session_map[$session_id]['table_status_counts'][$status_key])) $session_map[$session_id]['table_status_counts'][$status_key] = 0;
			$session_map[$session_id]['table_status_counts'][$status_key] += 1;

			$session_map[$session_id]['entries'][] = array(
				"eid" => (int)$tbl['table_id'],
				"entry_name" => "Table ".$tbl['table_label'],
				"entrant_name" => "",
				"judging_number" => $tbl['table_label'],
				"style" => "",
				"category" => "",
				"subcategory" => "",
				"table_label" => $tbl['table_label'],
				"place" => NULL,
				"place_source" => "none",
				"official_place" => NULL,
				"eval_place" => NULL,
				"place_display" => "",
				"bos_pull" => 0,
				"style_type" => 0,
				"bos_method" => 0,
				"eval_count" => (int)$tbl['scored'],
				"has_consensus" => 0,
				"has_official_score" => 0,
				"consensus_score" => NULL,
				"official_score" => NULL,
				"score" => "",
				"score_source" => "none",
				"progress" => $status_key,
				"progress_pending" => ($status_key === "imported") ? 0 : 1,
				"table_entries_total" => (int)$tbl['entries'],
				"table_scored_total" => (int)$tbl['scored'],
				"table_imported_total" => (int)$tbl['imported'],
				"table_percent" => (int)$tbl['percent'],
				"table_issue_total" => (int)$tbl['issue_total'],
				"table_import_ready" => (!empty($tbl['import_ready'])) ? 1 : 0,
				"table_issues" => (isset($tbl['issues']) && is_array($tbl['issues'])) ? $tbl['issues'] : array(
					"single_eval" => 0,
					"score_disparity" => 0,
					"duplicate_judge_evals" => 0,
					"duplicate_places" => 0,
					"mini_bos_mismatch" => 0,
					"none_submitted" => 0
				)
			);
		}

		$counts = $session_map[$session_id]['table_status_counts'];
		$table_total = count($session_map[$session_id]['entries']);
		$session_map[$session_id]['entry_count'] = $table_total;
		$session_map[$session_id]['placed_count'] = (int)$counts['imported'];
		$session_map[$session_id]['pending_count'] = (int)$counts['in_progress'] + (int)$counts['issues'];

		if ($table_total <= 0) $session_map[$session_id]['status'] = "in_progress";
		elseif ($counts['issues'] > 0) $session_map[$session_id]['status'] = "issues";
		elseif ($counts['in_progress'] > 0) $session_map[$session_id]['status'] = "in_progress";
		elseif (($counts['ready'] > 0) && ($counts['imported'] <= 0)) $session_map[$session_id]['status'] = "ready";
		elseif ($counts['imported'] >= $table_total) $session_map[$session_id]['status'] = "imported";
		elseif ($counts['ready'] > 0) $session_map[$session_id]['status'] = "ready";
		else $session_map[$session_id]['status'] = "in_progress";
	}

	foreach ($sessions as $session_id) {
		if (!isset($session_map[$session_id])) continue;
		$data[] = $session_map[$session_id];
	}

	return $data;
}
