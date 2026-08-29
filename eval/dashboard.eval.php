<?php

/**
 * -------- User Judging/Evaluation Dashboard --------
 * 
 * Dashboard for judges to add/edit evaluations and scores for entries they've judged.
 * Hooks:
 *    - Judge info
 *    - Table assignments
 *    - Flight assignments (if non-queued judging)
 *
 * TO DO:
 *    - Add check to see if all scores have been imported. If so, don't show or disable the import button.
 *    - Dynamically check at interval to see if entry currently evaluating has score entered by another judge.
 * 
 */

include(LIB.'output.lib.php');

$judging_open = FALSE;
$hidden_session_judging_available = FALSE;
$queued = FALSE;
$admin = FALSE;
$head_judge = FALSE;
$assignment_display = "";
$table_assignment_entries = "";
$dt_js = "";
$jscore_disparity = "";
$judge_score_disparity = array();
$table_places_alert = array();
$places_alert = "";
$dup_judge_evals_alert = "";
$duplicate_judge_evals_alert = array();
$entries_evaluated = array();
$mini_bos_mismatch = array();
$mini_bos_mismatch_alert = "";
$total_evals_alert = "";
$single_eval = "";
$single_evaluation = array();
$table_assignments_user = array();
$on_the_fly_display = "";
$on_the_fly_display_tbody = "";
$roles = "";
$latest_submitted = array();
$date_submitted = array();
$latest_updated = array();
$date_updated = array();
$diff = 3600; // Differential of seconds (60 minutes)
$admin_add_eval = "";

$count_none = "";
$count_total = "";
$count_unique = "";

function find_next($arr,$needle,$diff) {
	$last = 0;
	foreach ($arr as $key => $value) {
		if ($value > ($needle-$diff))  {
			return $value;
		}
	}
	return $last;
}

function count_past($arr,$needle,$diff) {
	$count = 0;
	foreach ($arr as $key => $value) {
		if ($value < ($needle-$diff))  {
			$count += 1;
		}
	}
	return $count;
}

function count_future($arr,$needle,$diff) {
	$count = 0;
	foreach ($arr as $key => $value) {
		if ($value > ($needle-$diff)) {
			$count += 1;
		}
	}
	return $count;
}

// Get last non-hidden judging session end date/time (if any).
// Hidden sessions stay open for assigned judges via per-table logic and must not force overall open.
$query_session_end = sprintf("SELECT judgingDateEnd FROM %s WHERE (judgingLocHidden IS NULL OR judgingLocHidden <> '1')",$prefix."judging_locations");
if (check_update("judgingLocComplete", $prefix."judging_locations")) $query_session_end .= " AND (judgingLocComplete IS NULL OR judgingLocComplete <> '1')";
if (SINGLE) $query_session_end .= sprintf(" AND comp_id='%s'",$_SESSION['comp_id']);
$query_session_end .= " ORDER BY judgingDateEnd DESC LIMIT 1";
$session_end = mysqli_query($connection,$query_session_end) or die (mysqli_error($connection));
$row_session_end = mysqli_fetch_assoc($session_end);
$totalRows_session_end = mysqli_num_rows($session_end);

if ((time() > $row_judging_prefs['jPrefsJudgingOpen']) && (time() < $row_judging_prefs['jPrefsJudgingClosed'])) $judging_open = TRUE;
if (($totalRows_session_end > 0) && (!empty($row_session_end['judgingDateEnd'])) && (time() < $row_session_end['judgingDateEnd'])) $judging_open = TRUE;

if ($row_judging_prefs['jPrefsQueued'] == "Y") $queued = TRUE;
if (($view == "admin") && ($_SESSION['userLevel'] <= 1)) $admin = TRUE;
if ($admin) include(DB.'admin_common.db.php');

// Session filter: lets admins narrow Manage Entry Evaluations to the tables
// assigned to a single judging session/location. Mirrors the tableLocation
// concept used on Manage Tables.
$judging_session_filter_label = "";
$judging_session_options = "";

if ($admin) {

	$query_judging_sessions_filter = sprintf("SELECT id, judgingLocName, judgingDate FROM %s WHERE judgingLocType < 2 ORDER BY judgingDate ASC", $prefix."judging_locations");
	$judging_sessions_filter = mysqli_query($connection, $query_judging_sessions_filter) or die (mysqli_error($connection));
	$row_judging_sessions_filter = mysqli_fetch_assoc($judging_sessions_filter);
	$totalRows_judging_sessions_filter = mysqli_num_rows($judging_sessions_filter);

	if ($totalRows_judging_sessions_filter > 1) {

		$judging_session_options .= "<div class=\"bcoem-admin-element hidden-print\" style=\"margin-bottom:15px;\">";
		$judging_session_options .= "<form class=\"form-inline\" method=\"get\" action=\"".$base_url."index.php\">";
		$judging_session_options .= "<input type=\"hidden\" name=\"section\" value=\"admin\">";
		$judging_session_options .= "<input type=\"hidden\" name=\"go\" value=\"evaluation\">";
		$judging_session_options .= "<input type=\"hidden\" name=\"view\" value=\"admin\">";
		$judging_session_options .= "<div class=\"form-group\">";
		$judging_session_options .= "<label for=\"judging-session-filter\" style=\"margin-right:8px;\">".$label_session_filter."</label>";
		$judging_session_options .= "<select id=\"judging-session-filter\" name=\"session\" class=\"form-control\" onchange=\"this.form.submit();\">";
		$judging_session_options .= "<option value=\"default\"";
		if ($judging_session_filter == "default") $judging_session_options .= " selected";
		$judging_session_options .= ">".$label_all_sessions."</option>";

		do {

			$session_option_label = $row_judging_sessions_filter['judgingLocName']." (".getTimeZoneDateTime($_SESSION['prefsTimeZone'], $row_judging_sessions_filter['judgingDate'], $_SESSION['prefsDateFormat'], $_SESSION['prefsTimeFormat'], "short", "date-no-gmt").")";
			$judging_session_options .= "<option value=\"".$row_judging_sessions_filter['id']."\"";
			if ($judging_session_filter == $row_judging_sessions_filter['id']) {
				$judging_session_options .= " selected";
				$judging_session_filter_label = $session_option_label;
			}
			$judging_session_options .= ">".htmlspecialchars($session_option_label, ENT_QUOTES, "UTF-8")."</option>";

		} while ($row_judging_sessions_filter = mysqli_fetch_assoc($judging_sessions_filter));

		$judging_session_options .= "</select>";
		$judging_session_options .= "</div>";
		$judging_session_options .= "</form>";
		$judging_session_options .= "</div>";

	}

}

// If viewing in admin mode, present a quick form for Admins to add an
// evaluation on behalf of a judge.
$admin_add_eval .= "<section style=\"margin-top:15px\" id=\"collapse-add-eval\" class=\"collapse bcoem-admin-element\">";
$admin_add_eval .= "<h3>Add an Evaluation</h3>";
$admin_add_eval .= "<p>To add an evaluation on behalf of a judge, choose the judge and input the entry number.</p>";
$admin_add_eval .= "<div class=\"row\">";
$admin_add_eval .= "<div class=\"col col-md-5 col-sm-7 col-xs-12\">";
$admin_add_eval .= "<form class=\"hide-loader-form-submit form-horizontal \" name=\"form1\" data-toggle=\"validator\" role=\"form\" action=\"".$base_url."index.php?section=evaluation&amp;go=scoresheet&amp;action=add\" method=\"post\">";
$admin_add_eval .= "<div class=\"form-group\">";
$admin_add_eval .= sprintf("<label for=\"entry_number\" class=\"col-sm-4 control-label\">%s</label>",$label_judge);
$admin_add_eval .= "<div class=\"col-sm-8\">";
$admin_add_eval .= participant_choose($brewer_db_table,$_SESSION['prefsProEdition'],"1","1");
$admin_add_eval .= "</div>";
$admin_add_eval .= "</div>";
$admin_add_eval .= "<div class=\"form-group\">";
$admin_add_eval .= sprintf("<label for=\"entry_number\" class=\"col-sm-4 control-label\">%s</label>",$label_number);
$admin_add_eval .= "<div class=\"col-sm-8\">";
$admin_add_eval .= "<input id=\"entry-number-input\" name=\"entry_number\" type=\"text\" pattern=\".{6,6}\" maxlength=\"6\" class=\"form-control small\" style=\"width:100%;\" data-error=\"".$evaluation_info_015."\" required>";
$admin_add_eval .= "</div>";
$admin_add_eval .= "</div>"; // form group
$admin_add_eval .= "<div class=\"help-block with-errors\"></div>";
$admin_add_eval .= "<div class=\"col-sm-offset-4 col-sm-8\">";
$admin_add_eval .= sprintf("<button onclick=\"bcoemClearScoresheetDraftStorage();\" class=\"btn btn-success\" style=\"margin-top:5px;\" type=\"submit\">%s</button>",$label_add);
$admin_add_eval .= "</div>";
$admin_add_eval .= "</form>";
$admin_add_eval .= "</div>"; // ./col
$admin_add_eval .= "</div>"; // ./row
$admin_add_eval .= "</section>";


$header = sprintf("<p class=\"lead\">%s <small>%s</small></p>",$evaluation_info_000,$evaluation_info_008);
if ($queued) $header .= sprintf("<div class=\"alert alert-info\"><p><strong>%s</strong>: %s</p><p>%s</p></div>",ucfirst(strtolower($label_please_note)),$evaluation_info_001,$evaluation_info_002); 
	
$query_table_assignments = sprintf("SELECT * FROM %s ORDER BY tableNumber ASC",$prefix."judging_tables");
$table_assignments = mysqli_query($connection,$query_table_assignments) or die (mysqli_error($connection));
$row_table_assignments = mysqli_fetch_assoc($table_assignments);
$totalRows_table_assignments = mysqli_num_rows($table_assignments);

$eval_draft_filter_sql = "";
if (check_update("evalDraft", $prefix."evaluation")) $eval_draft_filter_sql = " WHERE evalDraft <> '1' OR evalDraft IS NULL";
$query_eval_sub = sprintf("SELECT * FROM %s%s", $prefix."evaluation", $eval_draft_filter_sql);
$eval_sub = mysqli_query($connection,$query_eval_sub) or die (mysqli_error($connection));
$row_eval_sub = mysqli_fetch_assoc($eval_sub);
$totalRows_eval_sub = mysqli_num_rows($eval_sub);

$eval_scores = array();
$eval_judge_evaluations = array();
$eval_judge_tables = array();
$eval_no_evaluations = array();

if ($totalRows_eval_sub > 0) {

	do {

		$judge_score = $row_eval_sub['evalAromaScore'] + $row_eval_sub['evalAppearanceScore'] + $row_eval_sub['evalFlavorScore'] + $row_eval_sub['evalMouthfeelScore'] + $row_eval_sub['evalOverallScore'];

		if (!$admin) {
			
			$eval_judge_evaluations[] = array(
				"entry_id" => $row_eval_sub['eid']
			);

			$eval_judge_tables[] = array(
				"judge_id" => $row_eval_sub['evalJudgeInfo'],
				"table_id" => $row_eval_sub['evalTable']
			);

		}

		$eval_scores[] = array(
			"id" => $row_eval_sub['id'],
			"eid" => $row_eval_sub['eid'],
			"judge_id" => $row_eval_sub['evalJudgeInfo'],
			"judge_score" => $judge_score,
			"consensus_score" => $row_eval_sub['evalFinalScore'],
			"table" => $row_eval_sub['evalTable'],
			"place" => $row_eval_sub['evalPlace'],
			"ordinal_position" => $row_eval_sub['evalPosition'],
			"date_added" => $row_eval_sub['evalInitialDate'],
			"date_updated" => $row_eval_sub['evalUpdatedDate'],
			"scoresheet" => $row_eval_sub['evalScoresheet'],
			"mini_bos" => $row_eval_sub['evalMiniBOS']
		);

	} while($row_eval_sub = mysqli_fetch_assoc($eval_sub));
	
}

$total_scored_entries_count = 0;
$total_entries_count = 0;
$status_sidebar_table_info = "";
$status_sidebar_js = "";
$status_sidebar_js_icons = "";
$status_sidebar_js_timing = 0;

if ($totalRows_table_assignments > 0) {

	$table_assignment_start = array();
	$session_blocks = array();
	$current_time = time();

	do {

		$table_places = array();
		$table_places_display = "";
		$disable_add_edit = FALSE;
		$table_entries_count = 0;
		$table_scored_entries_count = 0;
		$flight_entries_count = 0;
		$user_flight_entries_count = 0;
		$flight_scored_entries_count = 0;
		$user_flight_scored_entries_count = 0;
		$table_assignment_stats = "";
		$table_judges = array();
		
		$tbl_id = $row_table_assignments['id'];
		$tbl_name_disp = $row_table_assignments['tableName'];
		$tbl_loc_disp = $row_table_assignments['tableLocation'];
		$tbl_num_disp = $row_table_assignments['tableNumber'];

		// Session filter: only show tables assigned to the selected judging session.
		if (($admin) && ($judging_session_filter != "default") && ($tbl_loc_disp != $judging_session_filter)) continue;

		$table_location = get_table_info($tbl_loc_disp,"location",$tbl_id,"default","default");
		$table_location = explode("^", $table_location);

		// Completed sessions are removed from judge dashboards (admins still see them).
		if ((!$admin) && (isset($table_location[7])) && ($table_location[7] == "1")) continue;

		$session_id = (!empty($tbl_loc_disp)) ? $tbl_loc_disp : $tbl_id;
		$session_dom_id = preg_replace("/[^A-Za-z0-9_-]/", "_", (string)$session_id);
		$session_start_ts = (!empty($table_location[0]) && is_numeric($table_location[0])) ? (int)$table_location[0] : 0;
		$session_end_ts = (!empty($table_location[1]) && is_numeric($table_location[1])) ? (int)$table_location[1] : 0;
		$session_name = (!empty($table_location[2])) ? $table_location[2] : $label_session;
		$session_time_display = "";
		if ($session_start_ts > 0) $session_time_display = getTimeZoneDateTime($_SESSION['prefsTimeZone'], $session_start_ts, $_SESSION['prefsDateFormat'], $_SESSION['prefsTimeFormat'], "short", "date-time");
		if ($session_end_ts > 0) $session_time_display .= " - ".getTimeZoneDateTime($_SESSION['prefsTimeZone'], $session_end_ts, $_SESSION['prefsDateFormat'], $_SESSION['prefsTimeFormat'], "short", "date-time");

		if (!empty($table_location[0])) $location_start_date = $table_location[0];
		else $location_start_date = time();

		$table_assignment_start[] = $location_start_date;

		/**
		 * Open up for non-admins 60 minutes before the stated session start time.
		 * Useful when judging is in-person and judges wish to review their assigned
		 * entries prior to "officially" starting.
		 * Uses $diff var.
		 */

		if (($admin) || ((!$admin) && (time() > ($table_location[0] - $diff)))) { 

			if ((!empty($table_location[1]) && (time() > $table_location[1]))) $disable_add_edit = TRUE;

			// Hidden sessions can stay open for assigned judges after overall judging closes,
			// as long as this session's own end has not passed.
			$table_judging_open = $judging_open;
			if ((isset($table_location[6])) && ($table_location[6] == "1") && (!$disable_add_edit) && (at_table($_SESSION['user_id'], $tbl_id))) {
				$table_judging_open = TRUE;
				$hidden_session_judging_available = TRUE;
			}

			$random = random_generator(7,2);
			$assigned_judges = assigned_judges($tbl_id,$dbTable,$judging_assignments_db_table,1);
			
			$table_start_time = getTimeZoneDateTime($_SESSION['prefsTimeZone'], $location_start_date, $_SESSION['prefsDateFormat'],  $_SESSION['prefsTimeFormat'], "short", "date-time");

			$table_assignment_heading = "";
			if (isset($table_location[1])) {

				if (empty($table_location[1])) $table_assignment_heading .= sprintf("<a name=\"table".$tbl_id."\"></a><h3 style=\"margin-top: 30px;\">%s %s - %s <br><small>%s &#8226; %s</small></h3>",$label_table,$tbl_num_disp,$tbl_name_disp,$table_location[2],$table_start_time);
				
				else {
					$table_end_time = getTimeZoneDateTime($_SESSION['prefsTimeZone'], $table_location[1], $_SESSION['prefsDateFormat'],  $_SESSION['prefsTimeFormat'], "short", "date-time");
					
					if (time() < $table_location[1]) $table_assignment_heading .= sprintf("<a name=\"table".$tbl_id."\"></a><h3 style=\"margin-top: 30px;\">%s %s - %s<br><small>%s &#8226; %s %s %s</small></h3>",$label_table,$tbl_num_disp,$tbl_name_disp,$table_location[2],$table_start_time,$entry_info_text_001,$table_end_time);

					else $table_assignment_heading .= sprintf("<a name=\"table".$tbl_id."\"></a><h3 style=\"margin-top: 30px;\">%s %s - %s<br><small>%s &#8226; %s %s <span class=\"text-warning\">%s</span> - %s</small></h3>",$label_table,$tbl_num_disp,$tbl_name_disp,$table_location[2],$table_start_time,$entry_info_text_001,$table_end_time,strtolower($evaluation_info_028));
				}

			}

			$table_assignment_pre = "";
			$table_assignment_data = "";
			$table_assignment_post = "";

			if (((isset($_SESSION['jPrefsTablePlanning'])) && ($_SESSION['jPrefsTablePlanning'] == 0)) || (!isset($_SESSION['jPrefsTablePlanning']))) {
				
				$table_assignment_pre .= "<table id=\"table-".$random."\" class=\"table table-condensed table-striped table-bordered table-responsive\">";
				$table_assignment_pre .= "<thead>";
				$table_assignment_pre .= "<tr>";
				$table_assignment_pre .= "<th width=\"5%\" nowrap>".$label_number."</th>";
				$table_assignment_pre .= "<th width=\"20%\" class=\"hidden-xs\">".$label_style."</th>";
				$table_assignment_pre .= "<th width=\"20%\">".$label_info."</th>";
				$table_assignment_pre .= "<th width=\"25%\">".$label_notes."</th>";
				$table_assignment_pre .= "<th>".$label_actions."</th>";
				$table_assignment_pre .= "</tr>";
				$table_assignment_pre .= "</thead>";
				$table_assignment_pre .= "<tbody>";

				$dt_js .= "
				$('#table-".$random."').dataTable({
					\"bPaginate\" : false,
					\"sDom\": 'rt',
					\"bStateSave\" : false,
					\"bLengthChange\" : false,
					\"aaSorting\": [[0,'asc']],
					\"bProcessing\" : false,
					\"aoColumns\": [
						null,
						null,
						null,
						null,
						null
						]
					});
				";
				
				if ($admin) {
					$a = explode(",", $row_table_assignments['tableStyles']);
				}

				else {
					$query_tables = sprintf("SELECT tableStyles FROM %s WHERE id='%s'",$prefix."judging_tables",$tbl_id);
					$tables = mysqli_query($connection,$query_tables) or die (mysqli_error($connection));
					$row_tables = mysqli_fetch_assoc($tables);
					$totalRows_tables = mysqli_num_rows($tables);
					$a = explode(",", $row_tables['tableStyles']);
				}
				
				sort($a);

				foreach (array_unique($a) as $value) {

					$score_style_data = score_style_data($value);

					if (!empty($score_style_data)) {

						$score_style_data = explode("^",$score_style_data);
				        
						$query_entries = sprintf("SELECT * FROM %s WHERE (brewCategorySort='%s' AND brewSubCategory='%s') AND brewReceived='1'", $prefix."brewing", $score_style_data[0], $score_style_data[1]);
						$query_entries .= " ORDER BY brewJudgingNumber, brewCategorySort, brewSubCategory ASC;";
						$entries = mysqli_query($connection,$query_entries) or die (mysqli_error($connection));
						$row_entries = mysqli_fetch_assoc($entries);
						$totalRows_entries = mysqli_num_rows($entries);

				        if ($totalRows_entries > 0) {

				        	do {

				        		if ($_SESSION['prefsDisplaySpecial'] == "J") $number = sprintf("%06s",$row_entries['brewJudgingNumber']);
					    		else $number = sprintf("%06s",$row_entries['id']);

					    		// Store total entry count in array for use later
								$table_entries_count += 1;

				        		$notes = "";
				        		$score = "";
				        		$scored_by_user = FALSE;
				        		$add_disabled = FALSE;
				        		$score_previous = FALSE;
				        		$score_previous_other = FALSE;
				        		$actions = "";
				        		$eval_place_actions = "";
				        		$count_evals = 0;
				        		$assigned_score = array();
				        		$judge_score = array();
								$eval_places = array();
								$eval_place = "";
								$score_entry_data = score_entry_data($row_entries['id']);
								$score_entry_data = explode("^",$score_entry_data);
								$eval_all_judges = array();
								$ordinal_position = array();
								$ord_position = "";
								
								// Classic
								if ($row_judging_prefs['jPrefsScoresheet'] == 1) {
									$output_form = "full-scoresheet";
									$scoresheet_form = "full_scoresheet.eval.php";
								}

								// Beer Checklist
								if ($row_judging_prefs['jPrefsScoresheet'] == 2) {

									if ($score_style_data[3] == 1) {
										$output_form = "checklist-scoresheet";
										$scoresheet_form = "checklist_scoresheet.eval.php";
									}

									else  {
										$output_form = "full-scoresheet";
										$scoresheet_form = "full_scoresheet.eval.php";
									}

								}

								// Structured (Includes NW Cider Cup)
								if (($row_judging_prefs['jPrefsScoresheet'] == 3) || ($row_judging_prefs['jPrefsScoresheet'] == 4)) {

									if ($score_style_data[3] <= 3) {
										$output_form = "structured-scoresheet";
										$scoresheet_form = "structured_scoresheet.eval.php";
									}

									else {
										$output_form = "full-scoresheet";
										$scoresheet_form = "full_scoresheet.eval.php";
									}
									
								}
								
				        		$style = style_number_const($row_entries['brewCategorySort'],$row_entries['brewSubCategory'],$_SESSION['style_set_display_separator'],1);
								$style_display = $style." ".$row_entries['brewStyle'];

								$info_display = "";
								$allergen_display = "";
								$abv_display = "";
								$pouring_display = "";
								$pouring_arr = "";
								$juice_src_display = "";
								$carb_display = "";
								$sweetness_display = "";
								$sweetness_level_display = "";
								$strength_display = "";
								$additional_info = 0;
								
								if (!empty($row_entries['brewInfo'])) {
									$additional_info++;
									if ((($_SESSION['prefsStyleSet'] == "BJCP2021") || ($_SESSION['prefsStyleSet'] == "BJCP2025")) && ($row_entries['brewCategorySort'] == "02") && ($row_entries['brewSubCategory'] == "A")) $info_display .= "<strong>".$label_regional_variation; 
									else $info_display .= "<strong>".$label_required_info;
									$info_display .= ":</strong> ".$row_entries['brewInfo'];
								}

								if (!empty($row_entries['brewMead1'])) {
									$additional_info++;
									$carb_display .= "<strong>".$label_carbonation.":</strong> ".$row_entries['brewMead1'];
								}

								if (!empty($row_entries['brewMead2'])) {
									$additional_info++;
									$sweetness_display .= "<strong>".$label_sweetness.":</strong> ".$row_entries['brewMead2'];
								}

								if (!empty($row_entries['brewSweetnessLevel'])) {

									$additional_info++;
									$sweetness_json = json_decode($row_entries['brewSweetnessLevel'],true);
									
									if (json_last_error() === JSON_ERROR_NONE) {

										if (!empty($sweetness_json['OG'])) $sweetness_level_display .= "<li><strong>".$label_original_gravity.":</strong> ".$sweetness_json['OG']."</li>";
										if (!empty($sweetness_json['FG'])) $sweetness_level_display .= "<li><strong>".$label_final_gravity.":</strong> ".$sweetness_json['FG']."</li>";

									}
									
									else {
										$sweetness_level_display .= "<strong>".$label_final_gravity.":</strong> ".$row_entries['brewSweetnessLevel'];
									}

								}

								if (!empty($row_entries['brewMead3'])) {
									$additional_info++;
									$strength_display .= "<strong>".$label_strength.":</strong> ".$row_entries['brewMead3'];
								}

								if (!empty($row_entries['brewPossAllergens'])) {
									$additional_info++;
									$allergen_display .= "<strong>".$label_possible_allergens.":</strong> ".$row_entries['brewPossAllergens'];
								}
								
								if (!empty($row_entries['brewABV'])) {
									$additional_info++;
									$abv_display .= "<strong>".$label_abv.":</strong> ".number_format($row_entries['brewABV'],1);
								}

								if (!empty($row_entries['brewPouring'])) {
									$pouring_arr = json_decode($row_entries['brewPouring'],true);
									$pouring_display .= "<li><strong>".$label_pouring.":</strong> ".$pouring_arr['pouring']."</li>";
									if ((isset($pouring_arr['pouring_notes'])) && (!empty($pouring_arr['pouring_notes']))) $pouring_display .= "<li><strong>".$label_pouring_notes.":</strong> ".$pouring_arr['pouring_notes']."</li>";
									$pouring_display .= "<li><strong>".$label_rouse_yeast.":</strong> ".$pouring_arr['pouring_rouse']."</li>";
									unset($pouring_arr);
								}

								if (($admin) && ($_SESSION['prefsStyleSet'] == "NWCiderCup") && (!empty($row_entries['brewJuiceSource']))) {

									$additional_info++;

									$juice_src_arr = json_decode($row_entries['brewJuiceSource'],true);
									$juice_src_disp = "";

									if (is_array($juice_src_arr['juice_src'])) {
										$juice_src_disp .= implode(", ",$juice_src_arr['juice_src']);
										$juice_src_disp .= ", ";
									}

									if ((isset($juice_src_arr['juice_src_other'])) && (is_array($juice_src_arr['juice_src_other']))) {
										$juice_src_disp .= implode(", ",$juice_src_arr['juice_src_other']);
										$juice_src_disp .= ", ";
									}

									$juice_src_disp = rtrim($juice_src_disp,",");
									$juice_src_disp = rtrim($juice_src_disp,", ");

									$juice_src_display .= "<strong>".$label_juice_source.":</strong> ".$juice_src_disp;
								
								}
								
				        		// Admin: Entry Evaluations
				        		if ($admin) {
				        			$add_link = $base_url."index.php?section=admin&amp;go=evaluation&amp;action=add&amp;filter=".$tbl_id."&amp;id=".$row_entries['id'];
				        			include (EVALS.'judging_admin.eval.php');
				        		}

				        		// Judging Dashboard
				        		else {
				        			$add_link = $base_url."index.php?section=evaluation&amp;go=scoresheet&amp;action=add&amp;filter=".$tbl_id."&amp;id=".$row_entries['id'];
				        			include (EVALS.'judging_dashboard.eval.php');
				        		}
					            
					            // Build table data
					            if (($table_judging_open) || ($admin) || ((!$table_judging_open) && ($scored_by_user))) {
						            if ($add_disabled) $table_assignment_data .= "<tr class=\"text-muted\">";
						            elseif ((!$queued) && (!$add_disabled) && (!$admin)) $table_assignment_data .= "<tr class=\"text-primary\">";
						            else $table_assignment_data .= "<tr>";
						        	$table_assignment_data .= "<td><a class=\"anchor\" name=\"".$number."\"></a>".$number."</td>";
						        	$table_assignment_data .= "<td class=\"hidden-xs\">";
						        	$table_assignment_data .= $style_display;
						        	$table_assignment_data .= "</td>";
						        	
						        	$table_assignment_data .= "<td>";
						        	if ($additional_info > 0) {
						        		$table_assignment_data .= "<small><ul class=\"list-unstyled\">";
						        		if (!empty($info_display)) $table_assignment_data .= "<li>".str_replace("^",", ",$info_display)."</li>";
						        		if (!empty($carb_display)) $table_assignment_data .= "<li>".$carb_display."</li>";
						        		if (!empty($sweetness_display)) $table_assignment_data .= "<li>".$sweetness_display."</li>";
						        		if (!empty($sweetness_level_display)) $table_assignment_data .= "<li>".$sweetness_level_display."</li>";
						        		if (!empty($allergen_display)) $table_assignment_data .= "<li>".$allergen_display."</li>";
						        		if (!empty($abv_display)) $table_assignment_data .= "<li>".$abv_display."%</li>";
						        		if (!empty($juice_src_display)) $table_assignment_data .= "<li>".$juice_src_display."</li>";
						        		if (!empty($strength_display)) $table_assignment_data .= "<li>".$strength_display."</li>";
						        		if (!empty($pouring_display)) $table_assignment_data .= $pouring_display;
						        		$table_assignment_data .= "</ul></small>";
						        	}
						        	$table_assignment_data .= "</td>";

						        	$table_assignment_data .= "<td>".$notes."</td>";
						        	$table_assignment_data .= "<td>".$eval_place_actions.$actions."</td>";
						            $table_assignment_data .= "</tr>";
						        }

						        // Check to see if any judges have more than one evaluation for this
						        // entry. If so, add to duplicate judges alert array.
						        if (!empty($eval_all_judges)) {
						        	$all_judges_count = array_count_values($eval_all_judges);
						        	foreach ($all_judges_count as $key => $value) {
						        		if ($value > 1) {
						        			$duplicate_judge_evals_alert[] = array(
						        				"table_id" => $tbl_id,
												"table_name" => $tbl_num_disp." - ".$tbl_name_disp,
												"id" => $row_entries['id'],
												"brewJudgingNumber" => $number,
												"brewCategorySort" => $row_entries['brewCategorySort'],
												"brewSubCategory" => $row_entries['brewSubCategory'],
												"brewStyle" => $row_entries['brewStyle']
						        			);
						        		}
						        	}
						        }

					        } while ($row_entries = mysqli_fetch_assoc($entries));

					    } // end if ($totalRows_entries > 0)

					} // end if (!empty($score_style_data)  

				} // end foreach

				if (empty($table_assignment_data)) $table_assignment_data .= "<tr><td colspan=\"4\">".$evaluation_info_016."</td></tr>";
				
				$table_assignment_post .= "</tbody>";
				$table_assignment_post .= "</table>";

				$table_assignment_post .= "<p><small><a href=\"#top\"><i class=\"fa fa-sm fa-arrow-circle-up\"></i> Top</a></small></p>";
			}

			
			
			

			// If places have been awarded at the table, but there are duplicates, list them for admins
			if (($admin) && (!empty($table_places))) {

				$places_table_flag_arr = array();
				$table_places_display_ul = "";
				
				foreach ($table_places as $key => $value) {
					foreach ($value as $k => $v) {
						if (in_array((string)$v, array("1","2","3"), true)) $places_table_flag_arr[] = (string)$v;
						$table_places_display_ul .= "<li id=\"place-display-".$k."\">".$k." - <span id=\"place-display-num-".$k."\">".display_place($v,1)."</span></li>";	
					}	
				}

				if (($_SESSION['prefsWinnerMethod'] == "0") && (!empty($places_table_flag_arr)) && (count(array_unique($places_table_flag_arr)) < count($places_table_flag_arr))) {
					
					$table_places_display .= "<div class=\"alert alert-danger\">";
					$table_places_display .=sprintf("<p><strong><i class=\"fa fa-exclamation-circle\"></i> %s</strong></p><p>%s:</p>",$label_attention,ucfirst(strtolower($label_places_awarded_duplicate)));
					$table_places_display .= "<ul id=\"places-awarded-table-".$tbl_id."\">";
					$table_places_display .= $table_places_display_ul;
					$table_places_display .= "</ul>";		
					$table_places_display .= "</div>";
					$table_places_alert[] = array(
						"table_id" => $tbl_id,
						"table_name" => $tbl_num_disp." - ".$tbl_name_disp,
					);

				}

			}

			if ($admin) {

				/**
				 * -------------------------------------------
				 * Build Table Counts Sidebar Data
				 * For each table, get count data and build
				 * the associated javascript ajax calls.
				 * -------------------------------------------
				 */

				$table_scored_entries_count = get_evaluation_count("table-unique",$tbl_id);
				
				$tbl_name_disp = truncate($tbl_name_disp,"25","...");
				$status_sidebar_timing = $status_sidebar_js_timing += 2000;
				$status_sidebar_js .= sprintf("
					setTimeout(function() {
						fetchRecordCount(ajax_url,'total-evaluations-table-%s','1','evaluation','eid','table','evalTable','%s');
						$('.refresh-link-table-%s').removeClass('hidden');
			        	$('.refresh-link-table-%s').fadeIn('fast');
						$('.icon-sync-table-%s').removeClass('hidden');
			        	$('.icon-sync-table-%s').fadeIn('fast');
			        	setInterval(function() { 
			                $('.icon-sync-table-%s').fadeOut('fast');
			            }, 10000);
					}, %s);\n
					",$tbl_id,$tbl_id,$tbl_id,$tbl_id,$tbl_id,$tbl_id,$tbl_id,$status_sidebar_timing);

				$status_sidebar_table_info .= "<section class=\"bcoem-sidebar-panel\">";
				$status_sidebar_table_info .= sprintf("<strong class=\"text-info\"><a href=\"#table%s\">%s</a> - %s</strong> <i class=\"fa fa-xs fa-sync fa-spin icon-sync-table-%s hidden\"></i>",$tbl_id,$tbl_num_disp,$tbl_name_disp,$tbl_id);
				$status_sidebar_table_info .= sprintf("<span style=\"margin-left: 15px;\" class=\"pull-right\"><span class=\"total-evaluations-table-%s\">%s</span> / %s</span>",$tbl_id,$table_scored_entries_count,$table_entries_count);
				$status_sidebar_table_info .= "</section>";

				/**
				 * -------------------------------------------
				 * Build Table Assignment Statistics
				 * For each table, get count data and other
				 * statistics (judges, number of entries, 
				 * scored entries) to display below the table
				 * name and location.
				 * -------------------------------------------
				 */

				if ($table_entries_count == $table_scored_entries_count) {
					$table_assignment_stats .= "<div class=\"alert alert-success\">";
					if ((isset($_SESSION['jPrefsTablePlanning'])) && ($_SESSION['jPrefsTablePlanning'] == 1)) {
						$table_assignment_stats .= "<i class=\"fa fa-lg fa-info-circle\"></i> <strong>Tables Planning Mode enabled.</strong> Tables Competition Mode must be enabled view or entry evaluations at this table.";
					}
					else $table_assignment_stats .= sprintf("<i class=\"fa fa-lg fa-check-circle\"></i> <strong>%s</strong>",$evaluation_info_037);
					$table_assignment_stats .= "</div>";
				}
				
				$table_assignment_stats .= "<div class=\"row small bcoem-account-info\">";
				$table_assignment_stats .= "<div class=\"col col-lg-8 col-md-10 col-sm-12 col-xs-12\">";

				$assigned_judge_names_display = "";

				if ($assigned_judges > 0) {

					$query_assigned_judge_names = sprintf("SELECT a.brewerFirstName,a.brewerLastName, b.assignment FROM %s a, %s b WHERE b.assignTable='%s' AND a.uid = b.bid AND b.assignment='J' ORDER BY a.brewerLastName, a.brewerFirstName ASC",$prefix."brewer",$prefix."judging_assignments",$tbl_id);
					$assigned_judge_names = mysqli_query($connection,$query_assigned_judge_names);
					$row_assigned_judge_names = mysqli_fetch_assoc($assigned_judge_names);
					
					do {
						$assigned_judge_names_display .= $row_assigned_judge_names['brewerFirstName']." ".$row_assigned_judge_names['brewerLastName'].", ";
					} while ($row_assigned_judge_names = mysqli_fetch_assoc($assigned_judge_names));

					$assigned_judge_names_display = rtrim($assigned_judge_names_display, ", ");
				
				}

				$table_assignment_stats .= "<section class=\"row\">";
				$table_assignment_stats .= "<div class=\"col col-lg-3 col-md-5 col-sm-5 col-xs-6\">";
				$table_assignment_stats .= "<strong>".$evaluation_info_025."</strong>";
				$table_assignment_stats .= "</div>";
				$table_assignment_stats .= "<div class=\"col col-lg-9 col-md-7 col-sm-7 col-xs-6\">";
				$table_assignment_stats .= $assigned_judges;
				if (!empty($assigned_judge_names_display)) $table_assignment_stats .= " &ndash; ".$assigned_judge_names_display;
				$table_assignment_stats .= "</div>";
				$table_assignment_stats .= "</section>";

				if ($table_scored_entries_count > 0) {

					$columns = array_column($table_judges, "tj_last_name");
					array_multisort($columns, SORT_ASC, $table_judges);
					$table_judges = array_unique($table_judges, SORT_REGULAR);
					
					$judge_names = "";
					foreach ($table_judges as $key => $value) {
						$judge_names .= $value['tj_first_name']." ".$value['tj_last_name'].", ";
					}
					$judge_names = rtrim($judge_names, ", ");

					$table_assignment_stats .= "<section class=\"row\">";
					$table_assignment_stats .= "<div class=\"col col-lg-3 col-md-5 col-sm-5 col-xs-6\">";
					$table_assignment_stats .= "<strong>".$evaluation_info_043."</strong>";
					$table_assignment_stats .= "</div>";
					$table_assignment_stats .= "<div class=\"col col-lg-9 col-md-7 col-sm-7 col-xs-6\">";
					$table_assignment_stats .= $judge_names;
					$table_assignment_stats .= "</div>";
					$table_assignment_stats .= "</section>";
				}

				$table_assignment_stats .= "<section class=\"row\">";
				$table_assignment_stats .= "<div class=\"col col-lg-3 col-md-5 col-sm-5 col-xs-6\">";
				$table_assignment_stats .= "<strong>".$evaluation_info_039."</strong>";
				$table_assignment_stats .= "</div>";
				$table_assignment_stats .= "<div class=\"col col-lg-9 col-md-7 col-sm-7 col-xs-6\">";
				$table_assignment_stats .= $table_entries_count;
				$table_assignment_stats .= "</div>";
				$table_assignment_stats .= "</section>";

				$table_assignment_stats .= "<section class=\"row\">";
				$table_assignment_stats .= "<div class=\"col col-lg-3 col-md-5 col-sm-5 col-xs-6\">";
				$table_assignment_stats .= "<strong>".$evaluation_info_040."</strong>";
				$table_assignment_stats .= "</div>";
				$table_assignment_stats .= "<div class=\"col col-lg-9 col-md-7 col-sm-7 col-xs-6\">";
				$table_assignment_stats .= sprintf("<span class=\"total-evaluations-table-%s\">%s</span> <i class=\"fa fa-xs fa-sync fa-spin icon-sync-table-%s hidden\"></i>",$tbl_id,$table_scored_entries_count,$tbl_id);
				$table_assignment_stats .= sprintf(" <span style=\"margin-left: 10px;\" class=\"refresh-link refresh-link-table-%s small hidden\"><a href=\"#\" onClick=\"window.location.reload()\">Refresh</a> to review updates.</span>",$tbl_id);
				$table_assignment_stats .= "</div>";
				$table_assignment_stats .= "</section>";

				$table_assignment_stats .= "</div>";
				$table_assignment_stats .= "</div>";

				$table_assignment_stats .= sprintf(
					"<p><button type=\"button\" class=\"btn btn-default btn-xs import-scores-scoped-btn\" data-toggle=\"modal\" data-target=\"#eval-import-modal\" data-scope=\"table\" data-table-id=\"%s\" data-table-label=\"%s\"><i class=\"fa fa-file-import\"></i> %s</button></p>",
					$tbl_id,
					htmlspecialchars($tbl_num_disp." - ".$tbl_name_disp, ENT_QUOTES, "UTF-8"),
					$label_import_this_table
				);

				$table_assignment_stats .= "<p><small><a href=\"#top\"><i class=\"fa fa-xs fa-arrow-circle-up\"></i> Top</a></small></p>";

				$total_entries_count += $table_entries_count;
				$total_scored_entries_count += $table_scored_entries_count;

			}

			$table_block_html = $table_assignment_heading.$table_places_display.$table_assignment_stats.$table_assignment_pre.$table_assignment_data.$table_assignment_post;
			if ($admin) {
				$table_assignment_entries .= $table_block_html;
			}
			else {
				if (!isset($session_blocks[$session_id])) {
					$session_sort_group = 2;
					if (($session_start_ts > 0) && ($session_start_ts <= $current_time) && (($session_end_ts == 0) || ($session_end_ts > $current_time))) $session_sort_group = 0;
					elseif (($session_end_ts > 0) && ($session_end_ts <= $current_time)) $session_sort_group = 1;
					$session_blocks[$session_id] = array(
						"session_id" => $session_id,
						"session_dom_id" => $session_dom_id,
						"name" => $session_name,
						"time_display" => $session_time_display,
						"start_ts" => $session_start_ts,
						"end_ts" => $session_end_ts,
						"sort_group" => $session_sort_group,
						"tables" => array()
					);
				}
				$session_blocks[$session_id]['tables'][] = array(
					"table_number" => (is_numeric($tbl_num_disp)) ? (int)$tbl_num_disp : 0,
					"table_number_display" => (string)$tbl_num_disp,
					"html" => $table_block_html
				);
			}
			
		} // end if (time() > $table_location[0])

	} while ($row_table_assignments = mysqli_fetch_assoc($table_assignments));

	if ((!$admin) && (!empty($session_blocks))) {
		uasort($session_blocks, function($a, $b) {
			if ($a['sort_group'] != $b['sort_group']) return ($a['sort_group'] < $b['sort_group']) ? -1 : 1;

			if ($a['sort_group'] == 1) {
				if ($a['end_ts'] != $b['end_ts']) return ($a['end_ts'] > $b['end_ts']) ? -1 : 1;
			}

			if ($a['start_ts'] != $b['start_ts']) return ($a['start_ts'] > $b['start_ts']) ? -1 : 1;
			return strnatcasecmp($a['name'], $b['name']);
		});

		$table_assignment_entries = "";
		foreach ($session_blocks as $session_block) {
			$session_tables = $session_block['tables'];
			usort($session_tables, function($a, $b) {
				if ($a['table_number'] == $b['table_number']) return strnatcasecmp($a['table_number_display'], $b['table_number_display']);
				return ($a['table_number'] < $b['table_number']) ? -1 : 1;
			});

			$table_assignment_entries .= "<div class=\"bcoem-judge-session\" style=\"margin-bottom: 20px;\" data-session-id=\"".$session_block['session_id']."\">";
			$table_assignment_entries .= "<div id=\"judgeSessionToggle-".$session_block['session_dom_id']."\" class=\"bcoem-judge-session-toggle\" style=\"display:flex;justify-content:space-between;align-items:center;border:1px solid #b2dfdb;border-radius:4px;padding:8px 12px;background:#e0f2f1;cursor:pointer;\" data-toggle=\"collapse\" href=\"#judge-session-".$session_block['session_dom_id']."\" role=\"button\" aria-expanded=\"true\" aria-controls=\"judge-session-".$session_block['session_dom_id']."\">";
			$table_assignment_entries .= "<div>";
			$table_assignment_entries .= "<strong class=\"text-info\"><i class=\"fa fa-calendar\" style=\"padding-right: 6px;\"></i>".$session_block['name']."</strong>";
			if (!empty($session_block['time_display'])) $table_assignment_entries .= "<br><small class=\"text-muted\">".$session_block['time_display']."</small>";
			$table_assignment_entries .= "</div>";
			$table_assignment_entries .= "<i class=\"fa fa-chevron-up bcoem-judge-session-toggle-icon\"></i>";
			$table_assignment_entries .= "</div>";
			$table_assignment_entries .= "<div class=\"collapse in\" id=\"judge-session-".$session_block['session_dom_id']."\">";
			foreach ($session_tables as $session_table) {
				$table_assignment_entries .= $session_table['html'];
			}
			$table_assignment_entries .= "</div>";
			$table_assignment_entries .= "</div>";
		}
	}

	if ((!$judging_open) && ($hidden_session_judging_available)) {
		$header .= "<div class=\"alert alert-info\"><p><i class=\"fa fa-info-circle\"></i> Overall judging is closed, but you still have one or more <strong>hidden session</strong> table assignments available for scoring.</p></div>";
	}

	asort($table_assignment_start);

	$next_date = find_next($table_assignment_start,time(),0);
	$next_judging_date_open = getTimeZoneDateTime($_SESSION['prefsTimeZone'], ($next_date - $diff) , "999",  $_SESSION['prefsTimeFormat'], "short", "date-no-gmt");
	$current_or_past_sessions = count_past($table_assignment_start,time(),0);
	$future_sessions = count_future($table_assignment_start,time(),0);

	/**
	 * -------------------------------------------
	 * Build Alerts
	 * These alerts will be at the top of the page
	 * -------------------------------------------
	 */
	
	// Judge Score Disparity Alert
	if (!empty($judge_score_disparity)) {
		$jscore_disparity .= "<div class=\"alert alert-warning alert-dismissible\">";
		$jscore_disparity .= sprintf("<button type=\"button\" class=\"close\" data-dismiss=\"alert\" aria-label=\"Close\"><span aria-hidden=\"true\">&times;</span></button><p><strong><i class=\"fa fa-exclamation-circle\"></i> %s %s</strong></p><p> %s</p>",$label_attention,$evaluation_info_036,$evaluation_info_018);
		$jscore_disparity .= "<ul>";
		asort($judge_score_disparity);
		foreach ($judge_score_disparity as $key => $value) {
			$jscore_disparity .= "<li>";
			$jscore_disparity .= "<a href=\"#".$value['brewJudgingNumber']."\">".$value['brewJudgingNumber']."</a>";
			$jscore_disparity .= " - ".style_number_const($value['brewCategorySort'],$value['brewSubCategory'],$_SESSION['style_set_display_separator'],0)." ".$value['brewStyle'];
			if (empty($value['table_name'])) $jscore_disparity .= " (".$label_unassigned_eval.")";
			else $jscore_disparity .= " (".$label_table." ".$value['table_name'].")";
			$jscore_disparity .= "</li>";
		}
		$jscore_disparity .= "</ul>";
		$jscore_disparity .= "</div>";
	}

	// Build duplicate judge evaluations alert
	if (!empty($duplicate_judge_evals_alert)) {
		$dup_judge_evals_alert .= "<div class=\"alert alert-warning alert-dismissible\">";
		$dup_judge_evals_alert .= sprintf("<button type=\"button\" class=\"close\" data-dismiss=\"alert\" aria-label=\"Close\"><span aria-hidden=\"true\">&times;</span></button><p><strong><i class=\"fa fa-exclamation-circle\"></i> %s %s</strong> %s</p><p> %s</p>",$label_attention,$evaluation_info_032,$evaluation_info_033,$evaluation_info_018);
		$dup_judge_evals_alert .= "<ul>";
		asort($duplicate_judge_evals_alert);
		foreach ($duplicate_judge_evals_alert as $key => $value) {
			$dup_judge_evals_alert .= "<li>";
			$dup_judge_evals_alert .= "<a href=\"#".$value['brewJudgingNumber']."\">".$value['brewJudgingNumber']."</a>";
			$dup_judge_evals_alert .= " - ".style_number_const($value['brewCategorySort'],$value['brewSubCategory'],$_SESSION['style_set_display_separator'],0)." ".$value['brewStyle'];
			if (empty($value['table_name'])) $dup_judge_evals_alert .= " (".$label_unassigned_eval.")";
			else $dup_judge_evals_alert .= " (".$label_table." ".$value['table_name'].")";
			$dup_judge_evals_alert .= "</li>";
		}
		$dup_judge_evals_alert .= "</ul>";
		$dup_judge_evals_alert .= "</div>";
	}

	// Build single evaluation list alert
	if (!empty($single_evaluation)) {	
		$single_eval .= "<div class=\"alert alert-warning alert-dismissible\">";
		$single_eval .= sprintf("<button type=\"button\" class=\"close\" data-dismiss=\"alert\" aria-label=\"Close\"><span aria-hidden=\"true\">&times;</span></button><p><strong><i class=\"fa fa-exclamation-circle\"></i> %s</strong></p><p>%s</p>",$label_attention,$evaluation_info_019);
		$single_eval .= "<ul>";
		asort($single_evaluation);
		foreach ($single_evaluation as $key => $value) {
			$single_eval .= "<li>";
			$single_eval .= "<a href=\"#".$value['brewJudgingNumber']."\">".$value['brewJudgingNumber']."</a>";
			$single_eval .= " - ".style_number_const($value['brewCategorySort'],$value['brewSubCategory'],$_SESSION['style_set_display_separator'],0)." ".$value['brewStyle'];
			$single_eval .= " (".$label_table." ".$value['table_name'].")";
			$single_eval .= "</li>";
		}
		$single_eval .= "</ul>";
		$single_eval .= "</div>";
	}

	// Build duplicate places at table alert
	if (!empty($table_places_alert)) {
		$places_alert .= "<div class=\"alert alert-danger alert-dismissible\">";
		$places_alert .= sprintf("<button type=\"button\" class=\"close\" data-dismiss=\"alert\" aria-label=\"Close\"><span aria-hidden=\"true\">&times;</span></button><p><strong><i class=\"fa fa-exclamation-circle\"></i> %s</strong></p><p>%s</p>",$label_attention,$evaluation_info_029);
		$places_alert .= "<ul>";
		asort($table_places_alert);
		foreach ($table_places_alert as $key => $value) {
			$places_alert .= "<li>";
			$places_alert .= "<a href=\"#table".$value['table_id']."\">".$label_table." ".$value['table_name']."</a>";
			$places_alert .= "</li>";
		}
		$places_alert .= "</ul>";
		$places_alert .= "</div>";
	}

	// Build mini-bos mismatch alert
	if (!empty($mini_bos_mismatch)) {
		$mini_bos_mismatch_alert .= "<div class=\"alert alert-info alert-dismissible\">";
		$mini_bos_mismatch_alert .= sprintf("<button type=\"button\" class=\"close\" data-dismiss=\"alert\" aria-label=\"Close\"><span aria-hidden=\"true\">&times;</span></button><p><strong><i class=\"fa fa-info-circle\"></i> %s</strong></p><p>%s</p>",$label_please_note,$evaluation_info_105);
		$mini_bos_mismatch_alert .= "<ul>";
		asort($mini_bos_mismatch);
		foreach ($mini_bos_mismatch as $key => $value) {
			$mini_bos_mismatch_alert .= "<li>";
			$mini_bos_mismatch_alert .= "<a href=\"#".$value['brewJudgingNumber']."\">".$value['brewJudgingNumber']."</a>";
			$mini_bos_mismatch_alert .= " - ".style_number_const($value['brewCategorySort'],$value['brewSubCategory'],$_SESSION['style_set_display_separator'],0)." ".$value['brewStyle'];
			$mini_bos_mismatch_alert .= " (".$label_table." ".$value['table_name'].")";
			$mini_bos_mismatch_alert .= "</li>";
		}
		$mini_bos_mismatch_alert .= "</ul>";
		$mini_bos_mismatch_alert .= "</div>";
	}

	// Build display datatable if judge has evaluated entries 
	// at any judging table besides their assigned ones (on-the-fly)
	// if (!$admin) include (EVALS.'judging_not_assigned.eval.php');

	$top_alert = "";

	$two_to_end_prefs = ($row_judging_prefs['jPrefsJudgingClosed'] - 172800);
	if ((!empty($row_session_end['judgingDateEnd'])) && (is_numeric($row_session_end['judgingDateEnd'])) && ($totalRows_session_end > 0)) $two_to_end_sess = ($row_session_end['judgingDateEnd'] - 172800);	
	else $two_to_end_sess = $two_to_end_prefs;

	if ($two_to_end_sess > $two_to_end_prefs) {
		$two_days = $two_to_end_sess;
		$judging_end = getTimeZoneDateTime($_SESSION['prefsTimeZone'], $row_session_end['judgingDateEnd'], "999",  $_SESSION['prefsTimeFormat'], "short", "date-no-gmt");
	}
	else {
		$two_days = $two_to_end_prefs;
		$judging_end = getTimeZoneDateTime($_SESSION['prefsTimeZone'], $row_judging_prefs['jPrefsJudgingClosed'], "999",  $_SESSION['prefsTimeFormat'], "short", "date-no-gmt");
	}

	$count_none = count($eval_no_evaluations);
	$count_total = get_evaluation_count('total');
	$count_unique = get_evaluation_count('unique');

	if (($admin) && ($totalRows_eval_sub > 0)) {

		//$top_alert .= sprintf("<i style=\"padding-right: 5px;\" class=\"fa fa-comments-o\"></i><strong>%s</strong> %s %s %s, %s.", $totalRows_eval_sub, $evaluation_info_031, strtolower($reg_closed_text_005), $current_time, $current_date_display);
		
		if (($judging_open && (time() > $two_days)) && ($count_none > 0)) {
			if ($count_none == 1) $top_alert .= sprintf(" <button type=\"button\" style=\"margin-bottom: 15px;\" class=\"btn btn-default btn-xs\" data-toggle=\"collapse\" data-target=\"#no-eval\">%s %s <i class=\"fa fa-chevron-down small\"></i></button>",$count_none,$label_entry_without_eval);
			else $top_alert .= sprintf(" <button type=\"button\" style=\"margin-bottom: 15px;\" class=\"btn btn-default btn-xs\" data-toggle=\"collapse\" data-target=\"#no-eval\">%s %s <i class=\"fa fa-chevron-down small\"></i></button>",$count_none,$label_entries_without_eval);
			$top_alert .= "<section style=\"margin-bottom: 15px;\" class=\"collapse small\" id=\"no-eval\">";
			$top_alert .= sprintf("<p>%s:</p>",$evaluation_info_049);
			$top_alert .= "<ul class=\"list-inline\">";
			asort($eval_no_evaluations);
			foreach ($eval_no_evaluations as $value) {
				$top_alert .= "<li><a href=\"#".$value."\">".$value."</a></li>";
			}
			$top_alert .= "</ul>";
			$top_alert .= "</section>";
		}

		/*
		else {
			$top_alert .= sprintf("<br><i style=\"padding-right: 5px;\" class=\"fa fa-check-circle\"></i><strong>%s</strong>: <span class=\"total-evaluations-unique\">%s</span>",$label_entries_with_eval,$count_unique);
			// $top_alert .= sprintf("<br><i style=\"padding-right: 5px;\" class=\"fa fa-times-circle\"></i><strong>%s</strong>: %s",$label_entries_without_eval,$count_none);
		}
		*/
	}

	if ($judging_open) {

		$top_alert .= sprintf("<p><i style=\"padding-right: 5px;\" class=\"fa fa-clock-o\"></i><strong>%s:</strong> <span id=\"judging-ends\"></span></p>", $label_judging_close);
		if ($next_date-$diff > time()) $top_alert .= "<p><i style=\"padding-right: 5px;\" class=\"fa fa-clock\"></i><strong>Next Session Open:</strong> <span id=\"next-session-open\"></span></p>";

	}

	if (!empty($top_alert)) {

		$total_evals_alert .= "<div class=\"alert alert-teal alert-dismissible\">";
		$total_evals_alert .= "<button type=\"button\" class=\"close\" data-dismiss=\"alert\" aria-label=\"Close\"><span aria-hidden=\"true\">&times;</span></button>";
		$total_evals_alert .= $top_alert;
		$total_evals_alert .= "</div>";

	}


?>

<style>
	.bcoem-judge-session-toggle-icon { transition: transform 0.2s ease; }
	.bcoem-judge-session-toggle.collapsed .bcoem-judge-session-toggle-icon { transform: rotate(-180deg); }
</style>
<script type="text/javascript" language="javascript">
	
	function update_place_display(number,element_id,table_id) {
		
		var value = $("#"+element_id).val();
		
		if ((value == 0) || (value == "")) {
			$("#place-display-"+number).hide();
		}

		if (value > 0) {
			$("#place-display-"+number).show();
			if (value == 1) disp_val = "1st";
			if (value == 2) disp_val = "2nd";
			if (value == 3) disp_val = "3rd";
			if (value == 4) disp_val = "4th";
			if (value == 5) disp_val = "HM";
			$("#place-display-num-"+number).html(disp_val);
		}

	}

	$(document).ready(function() {
		$("#next-session-refresh-button").hide();
		$('#judge_assignments').dataTable( {
			"bPaginate" : false,
			"sDom": 'rt',
			"bStateSave" : false,
			"bLengthChange" : false,
			"aaSorting": [[1,'asc']],
			"aoColumns": [
				null,
				null,
				null
				]
			});
			<?php echo $dt_js; ?>
		$('.dropdown').each(function (key, dropdown) {
	        var $dropdown = $(dropdown);
	        $dropdown.find('.dropdown-menu a').on('click', function () {
	            $dropdown.find('button').text($(this).text()).append(' <span class="caret"></span>');
	        });
	    });

		var judgeSessionStorageKey = 'bcoemJudgeSessionCollapsed-<?php echo md5($prefix.$_SESSION['user_id']); ?>';
		var collapsedSessions = {};
		try {
			var storedSessionPrefs = localStorage.getItem(judgeSessionStorageKey);
			if (storedSessionPrefs) collapsedSessions = JSON.parse(storedSessionPrefs) || {};
		}
		catch (error) {
			collapsedSessions = {};
		}

		function persistSessionPrefs() {
			try {
				if (Object.keys(collapsedSessions).length === 0) localStorage.removeItem(judgeSessionStorageKey);
				else localStorage.setItem(judgeSessionStorageKey, JSON.stringify(collapsedSessions));
			}
			catch (error) {
				// Preferences simply won't persist when browser storage is unavailable.
			}
		}

		$('.bcoem-judge-session').each(function() {
			var $session = $(this);
			var sessionId = String($session.data('session-id'));
			var $toggle = $session.find('.bcoem-judge-session-toggle').first();
			var targetSelector = $toggle.attr('href');
			if (!targetSelector) return;
			var $collapse = $session.find(targetSelector).first();
			if ($collapse.length === 0) return;

			if (collapsedSessions[sessionId]) {
				$collapse.removeClass('in').hide();
				$toggle.addClass('collapsed').attr('aria-expanded','false');
			}

			$collapse.on('hidden.bs.collapse', function() {
				collapsedSessions[sessionId] = 1;
				persistSessionPrefs();
			});

			$collapse.on('shown.bs.collapse', function() {
				delete collapsedSessions[sessionId];
				persistSessionPrefs();
			});
		});
	});

</script>
<script src="<?php if (TESTING) echo $base_url."js_source/admin_ajax.js?t=".time(); else echo $js_url."admin_ajax.min.js"; ?>"></script>
<?php
} // end if ($totalRows_table_assignments > 0)

$columns = array_column($date_submitted, "date_submitted");
array_multisort($columns, SORT_DESC, $date_submitted);
$date_submitted = array_unique($date_submitted, SORT_REGULAR);
$show_submitted = 0;
$latest_submitted_accordion = "";

foreach ($date_submitted as $key => $value) {
	$show_submitted += 1;
	if ($show_submitted <=20) {
		$submitted_date = getTimeZoneDateTime($_SESSION['prefsTimeZone'], $value['date_submitted'], $_SESSION['prefsDateFormat'],  $_SESSION['prefsTimeFormat'], "short", "date-time");
		$latest_submitted_accordion .= sprintf("<li><a href=\"#%s\">%s</a> - %s%s: %s (%s) - Score: %s</li>",$value['brewJudgingNumber'],$value['brewJudgingNumber'],$value['brewCategorySort'],$value['brewSubCategory'],$value['brewStyle'],$submitted_date,$value['consensus_score']);
	}
}

$columns = array_column($date_submitted, "date_updated");
array_multisort($columns, SORT_DESC, $date_submitted);
$date_submitted = array_unique($date_submitted, SORT_REGULAR);
$show_updated = 0;
$latest_updated_accordion = "";
foreach ($date_submitted as $key => $value) {
	$show_updated += 1;
	if ($show_updated <=20) {
		$updated_date = getTimeZoneDateTime($_SESSION['prefsTimeZone'], $value['date_updated'], $_SESSION['prefsDateFormat'], $_SESSION['prefsTimeFormat'], "short", "date-time");
		$latest_updated_accordion .= sprintf("<li><a href=\"#%s\">%s</a> - %s%s: %s (%s) - Score: %s</li>",$value['brewJudgingNumber'],$value['brewJudgingNumber'],$value['brewCategorySort'],$value['brewSubCategory'],$value['brewStyle'],$updated_date,$value['consensus_score']);
	}
}

if (!$admin) {
	echo $header;
	if (($judging_open) && (empty($table_assign_judge))) echo sprintf("<p>%s</p>",$evaluation_info_009);
}

$show_alerts = TRUE;
if ((empty($total_evals_alert)) && (empty($places_alert)) && (empty($judge_score_disparity)) && (empty($dup_judge_evals_alert)) && (empty($single_evaluation)) && (empty($mini_bos_mismatch_alert))) $show_alerts = FALSE;

// Counts Sidebar

$sidebar_buttons = "";
$sidebar_buttons .= "<button class=\"btn btn-dark btn-sm btn-block\" type=\"button\" data-toggle=\"collapse\" data-target=\"#collapse-add-eval\" aria-expanded=\"false\" aria-controls=\"collapse-add-eval\">Add an Evaluation on Behalf of Judge</button>";

if ($show_alerts) $sidebar_buttons .= "<a class=\"btn btn-dark btn-sm btn-block\" role=\"button\" data-toggle=\"collapse\" href=\"#all-alerts\" aria-expanded=\"false\" aria-controls=\"latest-submitted\"><i style=\"padding-right: 5px;\" class=\"fa fa-chevron-down\"></i>Expand/Collapse Alerts</a>";

if ((!empty($latest_submitted_accordion)) || (!empty($latest_updated_accordion))) {
	if (!empty($latest_submitted_accordion)) $sidebar_buttons .= "<a class=\"btn btn-dark btn-sm btn-block\" role=\"button\" data-toggle=\"collapse\" href=\"#latest-submitted\" aria-expanded=\"false\" aria-controls=\"latest-submitted\"><i style=\"padding-right: 5px;\" class=\"fa fa-clock-o\"></i>Expand/Collapse 20 Latest Submitted</a>";
	if (!empty($latest_updated_accordion)) $sidebar_buttons .= "<a class=\"btn btn-dark btn-sm btn-block\" role=\"button\" data-toggle=\"collapse\" href=\"#latest-updated\" aria-expanded=\"false\" aria-controls=\"latest-updated\"><i style=\"padding-right: 5px;\" class=\"fa fa-clock-o\"></i>Expand/Collapse 20 Latest Updated</a>";
}

$buttons_small_viewport = "";
$buttons_small_viewport .= "<div class=\"bcoem-admin-element hidden-sm hidden-md hidden-lg\">";
$buttons_small_viewport .= $sidebar_buttons;
$buttons_small_viewport .= "<a href=\"#status-sidebar\" class=\"btn btn-dark btn-sm btn-block\" role=\"button\">View Status</a>";
$buttons_small_viewport .= "</div>";

$status_sidebar = "";
$status_sidebar .= "<div class=\"bcoem-admin-element hidden-xs\">";
$status_sidebar .= $sidebar_buttons;
$status_sidebar .= "</div>";
$status_sidebar .= "<a name=\"status-sidebar\"></a>";
$status_sidebar .= "<div class=\"panel panel-info\">";
$status_sidebar .= "<div class=\"panel-heading\">";

$status_sidebar .= "<h4 style=\"margin: 0px; padding-bottom: 5px;\">Status<span class=\"fa fa-2x fa-bar-chart text-info pull-right\"></span></h4>";

$status_sidebar .= "<p style=\"margin: 0px;\" class=\"small text-muted\"><span class=\"small\">Updated <span id=\"eval-count-new-timestamp\">".getTimeZoneDateTime($_SESSION['prefsTimeZone'], time(), $_SESSION['prefsDateFormat'], $_SESSION['prefsTimeFormat'], "short", "date-time")."</span></span></p>";

if (!HOSTED) $status_sidebar .= "<p style=\"margin: 0px;\" class=\"small text-muted updates-indicators\"><span class=\"small\" id=\"count-two-minute-info\">".$brew_text_061."</span></p>";

$status_sidebar .= "<p style=\"margin: 0px;\" class=\"small text-muted updates-indicators\">";
$status_sidebar .= "<span class=\"small\"><a href=\"#\" onClick=\"window.location.reload()\">Refresh this page</a> to review updated evaluations and/or consensus scores.</span></span>";
$status_sidebar .= "</p>";

/*
$status_sidebar .= "<p style=\"margin: 0px;\" class=\"small text-muted updates-indicators\">";
$status_sidebar .= "<span class=\"small\" id=\"resume-updates\"><a href=\"#\" class=\"hide-loader\" onclick=\"resumeUpdates()\">Resume Updates</a></span>";
$status_sidebar .= "<span class=\"small\" id=\"stop-updates\"><a href=\"#\" class=\"hide-loader\" onclick=\"stopUpdates()\">Pause Updates</a> <a href=\"#\" class=\"hide-loader pull-right\" onclick=\"resumeUpdates()\">Update Now</a></span>";
$status_sidebar .= "</p>";
*/

$status_sidebar .= "<div class=\"updates-indicators small\" style=\"margin-top: 5px;\">";
if (!HOSTED) {
	$status_sidebar .= "<span class=\"small\" id=\"resume-updates\">";
	$status_sidebar .= "<button class=\"btn btn-primary btn-xs\" onclick=\"resumeUpdates()\"><i class=\"fa fa-xs fa-play\" style=\"padding-right:5px;\"></i> Resume Updates</button>";
	$status_sidebar .= "</span>";
}
$status_sidebar .= "<span class=\"small\" id=\"stop-updates\">";
$status_sidebar .= "<button href=\"#\" class=\"btn btn-primary btn-xs\" onclick=\"resumeUpdates()\"><i class=\"fa fa-xs fa-exchange\" style=\"padding-right:5px;\"></i> Update Status Now</button>";
if (!HOSTED) $status_sidebar .= "<button class=\"btn btn-primary btn-xs pull-right\" onclick=\"stopUpdates()\"><i class=\"fa fa-xs fa-pause\" style=\"padding-right:5px;\"></i> Pause Updates</button>";
$status_sidebar .= "</span>";
$status_sidebar .= "</div>";

$status_sidebar .= "</div>"; // end panel-heading

$status_sidebar .= "<div class=\"panel-body\">";

$status_sidebar .= "<section class=\"bcoem-sidebar-panel\">";
$status_sidebar .= "<strong class=\"text-teal\">Total Evaluations </strong> <i id=\"icon-sync-total-evaluations\" class=\"fa fa-xs fa-sync fa-spin hidden\"></i>";
$status_sidebar .= "<span id=\"total-evaluations\" class=\"pull-right\" style=\"margin-left: 15px;\">".$count_total."</span>";
$status_sidebar .= "</section>";

$status_sidebar .= "<section class=\"bcoem-sidebar-panel\">";
$status_sidebar .= "<strong class=\"text-teal\">Total Entries to Evaluate</strong>";
$status_sidebar .= "<span class=\"pull-right\" style=\"margin-left: 15px;\">".get_entry_count("paid-received")."</span>";
$status_sidebar .= "</section>";

$status_sidebar .= "<section class=\"bcoem-sidebar-panel\">";
$status_sidebar .= "<strong class=\"text-teal\">Total Entries with Evaluations </strong> <i id=\"icon-sync-total-evaluations-unique\" class=\"fa fa-xs fa-sync fa-spin hidden\"></i>";
$status_sidebar .= "<span class=\"pull-right total-evaluations-unique\" style=\"margin-left: 15px;\">".$count_unique."</span>";
$status_sidebar .= "</section>";

$status_sidebar .= "<section style=\"margin: 15px 0 8px 0; border-bottom: 1px solid #dedede;\" class=\"bcoem-sidebar-panel\">";
$status_sidebar .= "<small><strong class=\"text-info\">Evaluations</strong><span class=\"pull-right\">Count / Total</span></small>";
$status_sidebar .= "</section>";
$status_sidebar .= "<div class=\"small\">";
$status_sidebar .= $status_sidebar_table_info;
$status_sidebar .= "</div>";
$status_sidebar .= "</div>"; // end panel-body
$status_sidebar .= "</div>"; // end panel panel-info

$left_side = "";

if ($show_alerts) {
	$left_side .= "<div id=\"all-alerts\" class=\"collapse in\">";
	if (!empty($total_evals_alert)) $left_side .= $total_evals_alert;
	if (!empty($places_alert)) $left_side .= $places_alert;
	if (!empty($judge_score_disparity)) $left_side .= $jscore_disparity;
	if (!empty($dup_judge_evals_alert)) $left_side .= $dup_judge_evals_alert;
	if (!empty($single_evaluation)) $left_side .= $single_eval;
	if (!empty($mini_bos_mismatch_alert)) $left_side .= $mini_bos_mismatch_alert;
	$left_side .= "</div>";
}

if (!empty($latest_submitted_accordion)) {
	$left_side .= "<div id=\"latest-submitted\" class=\"collapse alert alert-teal\">";
	$left_side .= "<p><i style=\"padding-right: 5px;\" class=\"fa fa-clock-o\"></i>The <strong>20 most recently submitted</strong> evaluations:</p>";
	$left_side .= "<ul>";
	$left_side .= $latest_submitted_accordion;
	$left_side .= "</ul>";
	$left_side .= "</div>";
}

if (!empty($latest_updated_accordion)) {
	$left_side .= "<div id=\"latest-updated\" class=\"collapse alert alert-teal\">";
	$left_side .= "<p><i style=\"padding-right: 5px;\" class=\"fa fa-clock-o\"></i>The <strong>20 most recently updated</strong> evaluations:</p>";
	$left_side .= "<ul>";
	$left_side .= $latest_updated_accordion;
	$left_side .= "</ul>";
	$left_side .= "</div>";
}

if (!$admin) $left_side .= $assignment_display;
if (!empty($on_the_fly_display)) $left_side .= $on_the_fly_display;

?>
<a name="top"></a>
<div class="row">
	<div class="col-xs-12 col-sm-6 col-md-9">
		<?php 
		if ($admin) {
			if (session_pref_enabled('prefsEvalAdminTools', 1)) {
				$overview_link = $base_url."index.php?section=admin&amp;go=evaluation&amp;action=overview&amp;view=admin";
				if ($judging_session_filter != "default") $overview_link .= "&amp;session=".$judging_session_filter;
				echo "<div class=\"bcoem-admin-element hidden-print\" style=\"margin-bottom:10px;\"><a class=\"btn btn-default\" href=\"".$overview_link."\"><i class=\"fa fa-bar-chart\"></i> ".(isset($label_eval_overview_dashboard) ? $label_eval_overview_dashboard : "Progress Overview")."</a></div>";
			}
		}
		echo $judging_session_options;
		include (EVALS.'import_scores.eval.php');
		echo $buttons_small_viewport;
		echo $left_side;
		echo $admin_add_eval;
		echo $table_assignment_entries;
		?>
	</div>
	<div class="col-xs-12 col-sm-6 col-md-3">
		<?php echo $status_sidebar; ?>
	</div>
</div>

<script type="text/javascript">
function bcoemClearScoresheetDraftStorage() {
	try {
		if ((typeof jQuery !== "undefined") && (jQuery.saveMyForm) && (typeof jQuery.saveMyForm.clearStorage === "function")) jQuery.saveMyForm.clearStorage("scoresheet-form");
		var keysToRemove = [];
		for (var i = 0; i < localStorage.length; i++) {
			var key = localStorage.key(i);
			if ((key == "elementList_scoresheet-form") || (key.indexOf("scoresheet-form_") === 0) || (key.indexOf("evalDraftMeta_scoresheet-form") === 0)) keysToRemove.push(key);
		}
		for (var j = 0; j < keysToRemove.length; j++) localStorage.removeItem(keysToRemove[j]);
	} catch (e) {}
}
</script>

<?php if (($action == "success") && ($view == "clear")) { ?>
<script type="text/javascript">
	bcoemClearScoresheetDraftStorage();
</script>
<?php } ?>

<!-- Modal -->
<div class="modal fade" id="noDupeModal" tabindex="-1" role="dialog" aria-labelledby="noDupeModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        <h4 class="modal-title" id="noDupeModalLabel"><?php echo $label_place_previously_selected; ?></h4>
      </div>
      <div class="modal-body">
      	<?php echo $evaluation_info_048; ?>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-danger" data-dismiss="modal"><?php echo $label_close; ?></button>
      </div>
    </div>
  </div>
</div>

<!-- Modal: Next Session Open -->
<div class="modal fade" id="next-session-open-modal" tabindex="-1" role="dialog" aria-labelledby="next-session-open-modal-label">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        <h4 class="modal-title" id="next-session-open-modal-label"><?php echo $label_please_note; ?></h4>
      </div>
      <div class="modal-body">
        <p><?php echo "<strong>".$evaluation_info_097."</strong> ".$evaluation_info_098; ?></p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo $label_stay_here; ?></button>
        <button type="button" class="btn btn-success" data-dismiss="modal" onclick="window.location.reload()"><?php echo $label_refresh; ?></button>
      </div>
    </div>
  </div>
</div>

<?php if ($admin) { ?>

<!-- Modal: Set Consensus (Admin) -->
<div class="modal fade" id="setConsensusModal" tabindex="-1" role="dialog" aria-labelledby="setConsensusModalLabel">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        <h4 class="modal-title" id="setConsensusModalLabel"><?php echo $label_set_consensus; ?></h4>
      </div>
      <div class="modal-body">
        <p><strong><?php echo $label_number; ?>:</strong> <span id="set-consensus-number"></span></p>
        <div id="set-consensus-judges" class="small"></div>
        <p class="small text-muted"><?php echo $evaluation_info_131; ?></p>
        <div class="form-group">
          <label for="set-consensus-input"><?php echo $label_set_consensus; ?></label>
          <input type="number" min="5" max="50" step="0.5" class="form-control" id="set-consensus-input">
        </div>
        <div id="set-consensus-error" class="alert alert-danger" style="display:none;"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo $label_cancel; ?></button>
        <button type="button" id="set-consensus-save" class="btn btn-success"><?php echo $label_save_consensus; ?></button>
      </div>
    </div>
  </div>
</div>

<script type="text/javascript">

	var setConsensusEid = null;
	var $setConsensusBtn = null;
	var setConsensusLabelNone = <?php echo json_encode($label_none); ?>;

	function setConsensusJudgesHtml(judges) {
		var judgesHtml = '<ul class="list-unstyled">';
		if (judges && judges.length) {
			$.each(judges, function(i, j) {
				judgesHtml += '<li>' + j.judge + ' &ndash; Score: ' + (j.score || j.score === 0 ? j.score : setConsensusLabelNone) + ', Consensus: ' + (j.consensus || setConsensusLabelNone) + '</li>';
			});
		}
		judgesHtml += '</ul>';
		return judgesHtml;
	}

	$(document).on('click', '.set-consensus-btn', function() {

		$setConsensusBtn = $(this);
		setConsensusEid = $setConsensusBtn.data('eid');

		$('#set-consensus-number').text($setConsensusBtn.data('number'));
		$('#set-consensus-input').val($setConsensusBtn.data('current') || '');
		$('#set-consensus-error').hide().text('');
		$('#set-consensus-judges').html(setConsensusJudgesHtml($setConsensusBtn.data('judges')));

		$('#setConsensusModal').modal('show');

	});

	$('#set-consensus-save').on('click', function() {

		var score = parseFloat($('#set-consensus-input').val());

		if (isNaN(score) || (score < 5) || (score > 50)) {
			$('#set-consensus-error').text(<?php echo json_encode($evaluation_info_132); ?>).show();
			return;
		}

		if (!confirm(<?php echo json_encode($evaluation_info_133); ?>)) return;

		$('#set-consensus-error').hide().text('');

		var url = ajax_url + 'save.ajax.php?action=evaluation&go=evalSetConsensus&id=' + setConsensusEid;
		var eid = setConsensusEid;
		var $btn = $setConsensusBtn;

		$.post(url, { evalFinalScore: score }, function(data) {

			if (data.status == '1') {

				$('#setConsensusModal').modal('hide');

				// Update the button's cached state in place (no page reload)
				// so re-opening the modal, or saving again, reflects the new
				// consensus score for this entry. Judge-by-judge consensus
				// values shown in this list won't reflect the change until
				// the page is refreshed - a link is provided below.
				if ($btn && $btn.length) $btn.data('current', score).attr('data-current', score);

				var $status = $('#set-consensus-ajax-' + eid + '-status');
				$status.html(' <span class="text-success"><i class="fa fa-check"></i> ' + <?php echo json_encode($evaluation_info_123); ?> + '</span>');
				setTimeout(function() { $status.fadeOut('slow', function() { $(this).html('').show(); }); }, 4000);

			}
			else $('#set-consensus-error').text(<?php echo json_encode($evaluation_info_134); ?>).show();

		}, 'json');

	});

	// Scoped import: overrides the generic Import Score Data handler so admins
	// can import a single table, the currently filtered session, or (default)
	// all tables/sessions, without touching the minified admin_ajax bundle.
	var evalImportSessionId = <?php echo ($judging_session_filter != "default") ? json_encode($judging_session_filter) : "null"; ?>;
	var evalImportSessionLabel = <?php echo json_encode($judging_session_filter_label); ?>;
	var evalImportScope = evalImportSessionId ? 'session' : 'all';
	var evalImportTableId = null;

	$('#eval-import-modal').on('show.bs.modal', function(event) {

		var $trigger = $(event.relatedTarget);
		var scopeText = <?php echo json_encode($evaluation_info_137); ?>;

		if ($trigger.data('scope') == 'table') {
			evalImportScope = 'table';
			evalImportTableId = $trigger.data('table-id');
			scopeText = <?php echo json_encode($evaluation_info_135); ?> + ' (' + $trigger.data('table-label') + ')';
		}
		else {
			evalImportTableId = null;
			if (evalImportSessionId) {
				evalImportScope = 'session';
				scopeText = <?php echo json_encode($evaluation_info_136); ?> + ' (' + evalImportSessionLabel + ')';
			}
			else evalImportScope = 'all';
		}

		$('#eval-import-modal-scope').text(scopeText);

	});

	$('#import-scores').off('click').on('click', function() {

		var params = {};
		if ((evalImportScope == 'table') && (evalImportTableId)) params.table_id = evalImportTableId;
		else if ((evalImportScope == 'session') && (evalImportSessionId)) params.session_id = evalImportSessionId;

		$('#import-scores-status-icon').attr('class', 'fa fa-spin fa-spinner');
		$('#import-scores-status').text('');

		$.post(ajax_url + 'import_scores.ajax.php', params, function(data) {

			$('#import-scores-status-icon').attr('class', 'fa fa-check-circle');
			$('#import-scores-status').text('Imported ' + data.scores_imported_count + ' score(s). ' + data.flagged_count + ' flagged for mismatched consensus.');

			if (data.scored_places_discrepency_count > 0) {
				$('#import-status-discrepency-icon').attr('class', 'fa fa-exclamation-triangle');
				$('#import-status-discrepency').text(data.scored_places_discrepency_count + ' place discrepancy(ies) found.');
			}

		}, 'json');

	});

</script>

<?php } ?>

<script type="text/javascript">

	var interval_onload = null;
    var interval_onfocus = null;
    var interval_timeout = null;

    var count_update_text = "Counts are updated every five minutes.";
    var count_paused_text = "<?php echo $brew_text_062; ?>";
    var count_paused_manually_text = "<?php echo $brew_text_064; ?>";
    var count_timeout_text = "<?php echo $brew_text_065; ?>";

    var base_url = "<?php echo $base_url; ?>";
	var ajax_url = "<?php echo $ajax_url; ?>";
	var judging_started = "<?php if ($judging_started) echo "1"; else echo "0"; ?>";;
	var results_published = "<?php if ($show_presentation) echo "1"; else echo "0"; ?>";

	$("#resume-updates").hide();

	if (results_published == 1) {
		$(".updates-indicators").hide();
	}

	if (judging_started == 0) {
		$(".updates-indicators").hide();
	}
    
    // Function to update all counters
    function updateAllCounters(ajax_url) {
	
        // Initial counter call
        fetchRecordCount(ajax_url,'total-evaluations','0','evaluation');
        $('#icon-sync-total-evaluations').removeClass('hidden');
    	$('#icon-sync-total-evaluations').fadeIn('fast');
    	setInterval(function() { 
            $('#icon-sync-total-evaluations').fadeOut('fast');  
        }, 10000);

        setTimeout(function() {
            
            fetchRecordCount(ajax_url,'total-evaluations-unique','1','evaluation','eid','default');
	        $('#icon-sync-total-evaluations-unique').removeClass('hidden');
	    	$('#icon-sync-total-evaluations-unique').fadeIn('fast');
	    	setInterval(function() { 
	            $('#icon-sync-total-evaluations-unique').fadeOut('fast');  
	        }, 10000);

        }, 1000);

    }

    // Function to update all counters
    // JS dynamically generated in PHP loop
    function updateAllTableCounters(ajax_url) {

        <?php echo $status_sidebar_js; ?>

    }

	function stopUpdates() {
		clearInterval(interval_onload);
        clearInterval(interval_onfocus);
        clearInterval(interval_timeout);
    	$("#stop-updates").hide();
    	$("#resume-updates").show();
    	$("#count-two-minute-info").text(count_paused_manually_text);
    	$(".refresh-link").fadeOut('fast');
    	$(".refresh-link").addClass('hidden');
    	$(".fa-sync").addClass('hidden');
    }

    function resumeUpdates() {
    	clearInterval(interval_onload);
        clearInterval(interval_onfocus);
        clearInterval(interval_timeout);
        updateAllCounters(ajax_url);
        updateDateTime(ajax_url);
        interval_timeout = setTimeout(function() {
        	updateAllTableCounters(ajax_url);
        }, 5000);
        
        <?php if (!HOSTED) { ?>
    	interval_onfocus = setInterval(function() { 
            updateAllCounters(ajax_url);
            updateDateTime(ajax_url);  
            setTimeout(function() {
            	updateAllTableCounters(ajax_url);
            }, 5000);
        }, 300000);
        $("#resume-updates").hide();
    	<?php } ?>
    	
    	$("#stop-updates").show();
    	$("#count-two-minute-info").text(count_update_text);
    }

    function updateDateTime(ajax_url) {
    	fetchRecordCount(ajax_url,'eval-count-new-timestamp','0','updated-display');
    }

    <?php if (!HOSTED) { ?>
    
    $(document).ready(function() {

        window.onload = function () {
        	clearInterval(interval_onload);
            clearInterval(interval_onfocus);
        	if ((judging_started == 1) && (results_published == 0)) {
        		$(".refresh-link").addClass('hidden');
	            interval_onload = setInterval(function() {
	            	updateDateTime(ajax_url); 
	                updateAllCounters(ajax_url);
	                setTimeout(function() {
	                	updateAllTableCounters(ajax_url);
	                }, 5000);
	            }, 300000);
	            interval_timeout = setTimeout(function() {
                    stopUpdates();
                    $("#count-two-minute-info").text(count_timeout_text);
                }, 1200000);
	            $("#count-two-minute-info").text(count_update_text);
	        }
        }

        window.onfocus = function () {
            clearInterval(interval_onload);
            clearInterval(interval_onfocus);
            if ((judging_started == 1) && (results_published == 0)) {
	            updateDateTime(ajax_url);
	            updateAllCounters(ajax_url);  
	            setTimeout(function() {
                	updateAllTableCounters(ajax_url);
                }, 5000);
	            interval_onfocus = setInterval(function() { 
	                updateDateTime(ajax_url);  
	                updateAllCounters(ajax_url);
	                setTimeout(function() {
	                	updateAllTableCounters(ajax_url);
	                }, 5000);
	            }, 300000);
	            interval_timeout = setTimeout(function() {
                    stopUpdates();
                    $("#count-two-minute-info").text(count_timeout_text);
                }, 1200000);
	            $("#count-two-minute-info").text(count_update_text);
	            $("#stop-updates").show();
	    		$("#resume-updates").hide();
	    	}
        }

        window.onblur = function () {
            clearInterval(interval_onload);
            clearInterval(interval_onfocus);
            clearInterval(interval_timeout);
            if ((judging_started == 1) && (results_published == 0)) $("#count-two-minute-info").text(count_paused_text);
        }

    });

    <?php } ?>

</script>