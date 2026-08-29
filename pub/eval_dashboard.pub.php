<?php
/**
 * -------- User Judging/Evaluation Dashboard --------
 * 
 * Dashboard for judges to add/edit evaluations and scores
 * for entries they've judged. 
 * 
 * Updated for 3.0 public interface.
 * 
 * Hooks:
 *    - Judge info
 *    - Table assignments
 *    - Flight assignments (if non-queued judging)
 *
 * TO DO:
 *    - Dynamically check at interval to see if entry currently evaluating has score entered by another judge.
 *
 */

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
$query_session_end .= " ORDER BY judgingDateEnd DESC LIMIT 1";
$session_end = mysqli_query($connection,$query_session_end) or die (mysqli_error($connection));
$row_session_end = mysqli_fetch_assoc($session_end);
$totalRows_session_end = mysqli_num_rows($session_end);

if ((time() > $row_judging_prefs['jPrefsJudgingOpen']) && (time() < $row_judging_prefs['jPrefsJudgingClosed'])) $judging_open = TRUE;
if (($totalRows_session_end > 0) && (!empty($row_session_end['judgingDateEnd'])) && (time() < $row_session_end['judgingDateEnd'])) $judging_open = TRUE;

if ($row_judging_prefs['jPrefsQueued'] == "Y") $queued = TRUE;

// If the judging window is not open, display the closed message
if (!$judging_open) $header = sprintf("<p class=\"lead\">%s <small>%s</small></p>",$evaluation_info_022,$evaluation_info_023);

// If the judging window is open, query db and display
else {
	$header = sprintf("<p class=\"lead\">%s <small>%s</small></p>",$evaluation_info_000,$evaluation_info_008);
	if ($queued) $header .= sprintf("<div class=\"mb-3 p-3 text-primary-emphasis bg-primary-subtle border border-primary-subtle rounded-2\"><p><i class=\"fa fa-sticky-note me-2\"></i><strong>%s:</strong> %s</p><p class=\"mb-0\">%s</p></div>",ucfirst(strtolower($label_please_note)),$evaluation_info_001,$evaluation_info_002); 
}
	
$query_table_assignments = sprintf("SELECT * FROM %s a, %s b WHERE a.bid='%s' AND a.assignment='%s' AND a.assignTable = b.id ORDER BY b.tableNumber",$prefix."judging_assignments",$prefix."judging_tables",$_SESSION['user_id'],"J");
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
			
		$eval_judge_evaluations[] = array(
			"entry_id" => $row_eval_sub['eid']
		);

		$eval_judge_tables[] = array(
			"judge_id" => $row_eval_sub['evalJudgeInfo'],
			"table_id" => $row_eval_sub['evalTable']
		);

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

	/*
	foreach ($eval_scores as $key => $value) {
		$entries_evaluated[] = $value['eid'];
	}

	$total_entries_eval = (count(array_unique($entries_evaluated)));
	*/
	
}

/*
print_r($eval_scores);
echo "<br>";
print_r($eval_judge_evaluations);
echo "<br>";
print_r($eval_judge_tables);
echo "<br>";
exit;
*/

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
		
		$tbl_id = $row_table_assignments['assignTable'];
		$table_name = get_table_info(1,"basic",$tbl_id,"default","default");
		$table_name = explode("^", $table_name);
		$tbl_name_disp = $table_name[1];
		$tbl_loc_disp = $table_name[2];
		$tbl_num_disp = $table_name[0];
		$table_assignments_user[] = $tbl_id;
		
		$table_location = get_table_info($tbl_loc_disp,"location",$tbl_id,"default","default");
		$table_location = explode("^", $table_location);

		// Completed sessions are removed from judge dashboards.
		if ((isset($table_location[7])) && ($table_location[7] == "1")) continue;

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

		if ((is_numeric($table_location[0])) && (time() > ($table_location[0] - $diff))) {

			if ((!empty($table_location[1]) && (time() > $table_location[1]))) $disable_add_edit = TRUE;

			// Hidden sessions can stay open for assigned judges after overall judging closes,
			// as long as this session's own end has not passed.
			$table_judging_open = $judging_open;
			if ((isset($table_location[6])) && ($table_location[6] == "1") && (!$disable_add_edit)) {
				$table_judging_open = TRUE;
				$hidden_session_judging_available = TRUE;
			}

			$random = random_generator(7,2);
			$assigned_judges = assigned_judges($tbl_id,$dbTable,$judging_assignments_db_table,1);
			
			$table_start_time = getTimeZoneDateTime($_SESSION['prefsTimeZone'], $location_start_date, $_SESSION['prefsDateFormat'],  $_SESSION['prefsTimeFormat'], "short", "date-time");

			$table_assignment_heading = "";
			if (isset($table_location[1])) {

				if (empty($table_location[1])) $table_assignment_heading .= sprintf("<a name=\"table".$tbl_id."\"></a><h3 class=\"mt-5\">%s %s - %s <br><small class=\"fs-5 fw-lighter text-body-secondary\">%s &#8226; %s</small></h3>",$label_table,$tbl_num_disp,$tbl_name_disp,$table_location[2],$table_start_time);
				
				else {
					$table_end_time = getTimeZoneDateTime($_SESSION['prefsTimeZone'], $table_location[1], $_SESSION['prefsDateFormat'],  $_SESSION['prefsTimeFormat'], "short", "date-time");
					
					if (time() < $table_location[1]) $table_assignment_heading .= sprintf("<a name=\"table".$tbl_id."\"></a><h3 class=\"mt-5\">%s %s - %s<br><small class=\"fs-5 fw-lighter text-body-secondary\">%s &#8226; %s %s %s</small></h3>",$label_table,$tbl_num_disp,$tbl_name_disp,$table_location[2],$table_start_time,$entry_info_text_001,$table_end_time);

					else $table_assignment_heading .= sprintf("<a name=\"table".$tbl_id."\"></a><h3 class=\"mt-5\">%s %s - %s<br><small class=\"fs-5 fw-lighter text-body-secondary\">%s &#8226; %s %s <span class=\"text-danger-emphasis\">%s - %s</span></small></h3>",$label_table,$tbl_num_disp,$tbl_name_disp,$table_location[2],$table_start_time,$entry_info_text_001,$table_end_time,strtolower($evaluation_info_028));
				}

			}

			$table_assignment_pre = "";
			$table_assignment_data = "";
			$table_assignment_post = "";
			$table_assignment_pre .= "<div class=\"table-responsive-md\">"; 
			$table_assignment_pre .= "<table id=\"table-".$random."\" class=\"table table-condensed table-striped table-bordered border-dark-subtle\">";
			$table_assignment_pre .= "<thead>";
			$table_assignment_pre .= "<tr class=\"table-dark\">";
			$table_assignment_pre .= "<th scope=\"col\" width=\"5%\" nowrap>".$label_number."</th>";
			$table_assignment_pre .= "<th scope=\"col\" width=\"30%\">".$label_style." / ".$label_info."</th>";
			$table_assignment_pre .= "<th scope=\"col\" width=\"30%\">".$label_notes."</th>";
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
					null
					]
				});
			";
			
			
			$query_tables = sprintf("SELECT tableStyles FROM %s WHERE id='%s'",$prefix."judging_tables",$tbl_id);
			$tables = mysqli_query($connection,$query_tables) or die (mysqli_error($connection));
			$row_tables = mysqli_fetch_assoc($tables);
			$totalRows_tables = mysqli_num_rows($tables);
			$a = explode(",", $row_tables['tableStyles']);
			
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
								$scoresheet_form = "eval_scoresheet_full.pub.php";
							}

							// Beer Checklist
							if ($row_judging_prefs['jPrefsScoresheet'] == 2) {

								if ($score_style_data[3] == 1) {
									$output_form = "checklist-scoresheet";
									$scoresheet_form = "eval_scoresheet_checklist.pub.php";
								}

								else  {
									$output_form = "full-scoresheet";
									$scoresheet_form = "eval_scoresheet_full.pub.php";
								}

							}

							// Structured (Includes NW Cider Cup)
							if (($row_judging_prefs['jPrefsScoresheet'] == 3) || ($row_judging_prefs['jPrefsScoresheet'] == 4)) {

								if ($score_style_data[3] <= 3) {
									$output_form = "structured-scoresheet";
									$scoresheet_form = "eval_scoresheet_structured.pub.php";
								}

								else {
									$output_form = "full-scoresheet";
									$scoresheet_form = "eval_scoresheet_full.pub.php";
								}
								
							}
			        		
							if ($_SESSION['prefsStyleSet'] == "BA") $style_display = $row_entries['brewStyle'];
							else {
								$style = style_number_const($row_entries['brewCategorySort'],$row_entries['brewSubCategory'],$_SESSION['style_set_display_separator'],1);
								$style_display = $style." ".$row_entries['brewStyle'];
							}

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

							$add_link = $base_url."index.php?section=evaluation&amp;go=scoresheet&amp;action=add&amp;filter=".$tbl_id."&amp;id=".$row_entries['id'];
							
			        		include (PUB.'eval_judging_dashboard.pub.php');
				            
				            // Build table data
				            if (($table_judging_open) || ((!$table_judging_open) && ($scored_by_user))) {
					            if ($add_disabled) $table_assignment_data .= "<tr class=\"text-muted\">";
					            elseif ((!$queued) && (!$add_disabled)) $table_assignment_data .= "<tr class=\"text-primary\">";
					            else $table_assignment_data .= "<tr>";
					        	$table_assignment_data .= "<td scope=\"col\"><a class=\"anchor\" name=\"".$number."\"></a>".$number."</td>";
					        	$table_assignment_data .= "<td scope=\"col\">";
					        	$table_assignment_data .= $style_display;
					        	
					        	if ($additional_info > 0) {
					        		$table_assignment_data .= "<div class=\"mt-2\"><small><ul class=\"list-unstyled\">";
					        		if (!empty($info_display)) $table_assignment_data .= "<li>".str_replace("^",", ",$info_display)."</li>";
					        		if (!empty($carb_display)) $table_assignment_data .= "<li>".$carb_display."</li>";
					        		if (!empty($sweetness_display)) $table_assignment_data .= "<li>".$sweetness_display."</li>";
					        		if (!empty($sweetness_level_display)) $table_assignment_data .= "<li>".$sweetness_level_display."</li>";
					        		if (!empty($allergen_display)) $table_assignment_data .= "<li>".$allergen_display."</li>";
					        		if (!empty($abv_display)) $table_assignment_data .= "<li>".$abv_display."%</li>";
					        		if (!empty($juice_src_display)) $table_assignment_data .= "<li>".$juice_src_display."</li>";
					        		if (!empty($strength_display)) $table_assignment_data .= "<li>".$strength_display."</li>";
					        		if (!empty($pouring_display)) $table_assignment_data .= $pouring_display;
					        		$table_assignment_data .= "</ul></small></div>";
					        	}
					        	$table_assignment_data .= "</td>";

					        	$table_assignment_data .= "<td scope=\"col\">".$notes."</td>";
					        	$table_assignment_data .= "<td scope=\"col\">".$eval_place_actions.$actions."</td>";
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
			$table_assignment_post .= "</div>";

			$table_assignment_post .= "<p class=\"mt-2\"><a href=\"#top\"><i class=\"fa fa-sm fa-arrow-circle-up\"></i> Top</a></p>";

			// If places have been awarded at the table, but there are duplicates, list them for admins	

			if ((strpos($row_table_assignments['assignRoles'], "HJ") !== false) && ($table_entries_count == $table_scored_entries_count)) {
				$table_assignment_stats .= "<div class=\"alert alert-success\">";
				$table_assignment_stats .= sprintf("<i class=\"fa fa-lg fa-check-circle\"></i> <strong>%s</strong> %s",$evaluation_info_037,$evaluation_info_038);
				$table_assignment_stats .= "</div>";
			}
			
			$table_assignment_stats .= "<div class=\"row small bcoem-account-info\">";
		
			if ($table_judging_open) {
				
				$table_assignment_stats .= "<div class=\"col-12 col-sm-8\">";

				$table_assignment_stats .= "<section class=\"row\">";
				$table_assignment_stats .= "<div class=\"col-12 col-sm-12 col-md-7 col-lg-6\">";
				$table_assignment_stats .= "<strong>".$evaluation_info_025."</strong>";
				$table_assignment_stats .= "</div>";
				$table_assignment_stats .= "<div class=\"col-12 col-sm-12 col-md-5 col-lg-6\">";
				$table_assignment_stats .= $assigned_judges;
				$table_assignment_stats .= "</div>";
				$table_assignment_stats .= "</section>";

				$table_assignment_stats .= "<section class=\"row\">";
				$table_assignment_stats .= "<div class=\"col-12 col-sm-12 col-md-7 col-lg-6\">";
				$table_assignment_stats .= "<strong>".$evaluation_info_039."</strong>";
				$table_assignment_stats .= "</div>";
				$table_assignment_stats .= "<div class=\"col-12 col-sm-12 col-md-5 col-lg-6\">";
				$table_assignment_stats .= $table_entries_count;
				$table_assignment_stats .= "</div>";
				$table_assignment_stats .= "</section>";

				$table_assignment_stats .= "<section class=\"row\">";
				$table_assignment_stats .= "<div class=\"col-12 col-sm-12 col-md-7 col-lg-6\">";
				$table_assignment_stats .= "<strong>".$evaluation_info_040."</strong>";
				$table_assignment_stats .= "</div>";
				$table_assignment_stats .= "<div class=\"col-12 col-sm-12 col-md-5 col-lg-6\">";
				$table_assignment_stats .= $table_scored_entries_count;
				$table_assignment_stats .= "<span class=\"fs-6 text-lowercase\"> ".$label_of." </span>".$table_entries_count;
				$table_assignment_stats .= "</div>";
				$table_assignment_stats .= "</section>";

				if ($queued) {
					$table_assignment_stats .= "<section class=\"row\">";
					$table_assignment_stats .= "<div class=\"col-12 col-sm-12 col-md-7 col-lg-6\">";
					$table_assignment_stats .= "<strong>".$evaluation_info_042."</strong>";
					$table_assignment_stats .= "</div>";
					$table_assignment_stats .= "<div class=\"col-12 col-sm-12 col-md-5 col-lg-6\">";
					$table_assignment_stats .= $user_flight_scored_entries_count;
					$table_assignment_stats .= "</div>";
					$table_assignment_stats .= "</section>";
				}
				
				if (!$queued) {
					$table_assignment_stats .= "<section class=\"row\">";
					$table_assignment_stats .= "<div class=\"col-12 col-sm-12 col-md-7 col-lg-6\">";
					$table_assignment_stats .= "<strong>".$evaluation_info_041."</strong>";
					$table_assignment_stats .= "</div>";
					$table_assignment_stats .= "<div class=\"col-12 col-sm-12 col-md-5 col-lg-6\">";
					$table_assignment_stats .= $flight_scored_entries_count;
					$table_assignment_stats .= "<span class=\"fs-6 text-lowercase\"> ".$label_of." </span>".$user_flight_entries_count;
					$table_assignment_stats .= "</div>";
					$table_assignment_stats .= "</section>";

					$table_assignment_stats .= "<section class=\"row\">";
					$table_assignment_stats .= "<div class=\"col-12 col-sm-12 col-md-7 col-lg-6\">";
					$table_assignment_stats .= "<strong>".$evaluation_info_042."</strong>";
					$table_assignment_stats .= "</div>";
					$table_assignment_stats .= "<div class=\"col-12 col-sm-12 col-md-5 col-lg-6\">";
					$table_assignment_stats .= $user_flight_scored_entries_count;
					$table_assignment_stats .= "<span class=\"fs-6 text-lowercase ps-1 pe-1\">".$label_of."</span>".$user_flight_entries_count;
					$table_assignment_stats .= "</div>";
					$table_assignment_stats .= "</section>";

				}
				

				$table_assignment_stats .= "</div>";

			}
		
			if ($table_judging_open) $table_assignment_stats .= "<div class=\"col-12 col-sm-4\">";
			else $table_assignment_stats .= "<div class=\"col-12 col-sm-12\">";
			if (strpos($row_table_assignments['assignRoles'], "HJ") !== false) {
				$table_assignment_stats .= "<div class=\"text-end text-teal \"><i class=\"fa fa-gavel me-1\"></i>".$label_head_judge."</div>";
			}
			if (strpos($row_table_assignments['assignRoles'], "MBOS") !== false) {
				$table_assignment_stats .= "<div class=\"text-end text-teal \"><i class=\"fa fa-trophy me-1\"></i>".$label_mini_bos_judge."</div>";
			}
			$table_assignment_stats .= "</div>";
			$table_assignment_stats .= "</div>";

			if ($table_judging_open) {
				$table_assignment_stats .= "<section class=\"row small mb-2\">";
				$table_assignment_stats .= "<div class=\"col col-xs-12\">";
				$table_assignment_stats .= sprintf("<em>%s</em>",$evaluation_info_007);
				$table_assignment_stats .= "</div>";
				$table_assignment_stats .= "</section>"; // end row
			}

			$table_block_html = $table_assignment_heading.$table_places_display.$table_assignment_stats.$table_assignment_pre.$table_assignment_data.$table_assignment_post;
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
			
		} // end if (time() > $table_location[0])

	} while ($row_table_assignments = mysqli_fetch_assoc($table_assignments));

	if ((!$judging_open) && ($hidden_session_judging_available)) {
		$header .= "<div class=\"mb-3 p-3 text-primary-emphasis bg-primary-subtle border border-primary-subtle rounded-2\"><p class=\"mb-0\"><i class=\"fa fa-info-circle me-2\"></i>Overall judging is closed, but you still have one or more <strong>hidden session</strong> table assignments available for scoring.</p></div>";
	}

	if (!empty($session_blocks)) {
		uasort($session_blocks, function($a, $b) {
			if ($a['sort_group'] != $b['sort_group']) return ($a['sort_group'] < $b['sort_group']) ? -1 : 1;

			if ($a['sort_group'] == 1) {
				if ($a['end_ts'] != $b['end_ts']) return ($a['end_ts'] > $b['end_ts']) ? -1 : 1;
			}

			if ($a['start_ts'] != $b['start_ts']) return ($a['start_ts'] > $b['start_ts']) ? -1 : 1;
			return strnatcasecmp($a['name'], $b['name']);
		});

		foreach ($session_blocks as $session_block) {
			$session_tables = $session_block['tables'];
			usort($session_tables, function($a, $b) {
				if ($a['table_number'] == $b['table_number']) return strnatcasecmp($a['table_number_display'], $b['table_number_display']);
				return ($a['table_number'] < $b['table_number']) ? -1 : 1;
			});

			$table_assignment_entries .= "<div class=\"bcoem-judge-session mb-4\" data-session-id=\"".$session_block['session_id']."\">";
			$table_assignment_entries .= "<div id=\"judgeSessionToggle-".$session_block['session_dom_id']."\" class=\"bcoem-judge-session-toggle d-flex justify-content-between align-items-center border border-info-subtle rounded-2 px-3 py-2\" data-bs-toggle=\"collapse\" href=\"#judge-session-".$session_block['session_dom_id']."\" role=\"button\" aria-expanded=\"true\" aria-controls=\"judge-session-".$session_block['session_dom_id']."\">";
			$table_assignment_entries .= "<div>";
			$table_assignment_entries .= "<strong class=\"text-teal\"><i class=\"fa fa-calendar me-2\"></i>".$session_block['name']."</strong>";
			if (!empty($session_block['time_display'])) $table_assignment_entries .= "<br><small class=\"text-muted\">".$session_block['time_display']."</small>";
			$table_assignment_entries .= "</div>";
			$table_assignment_entries .= "<i class=\"fa fa-chevron-up bcoem-judge-session-toggle-icon\"></i>";
			$table_assignment_entries .= "</div>";
			$table_assignment_entries .= "<div class=\"collapse show\" id=\"judge-session-".$session_block['session_dom_id']."\">";
			foreach ($session_tables as $session_table) {
				$table_assignment_entries .= $session_table['html'];
			}
			$table_assignment_entries .= "</div>";
			$table_assignment_entries .= "</div>";
		}
	}

	asort($table_assignment_start);
	//print_r($table_assignment_start);

	$next_date = find_next($table_assignment_start,time(),0);
	$next_judging_date_open = getTimeZoneDateTime($_SESSION['prefsTimeZone'], ($next_date - $diff) , "999",  $_SESSION['prefsTimeFormat'], "short", "date-no-gmt");
	$current_or_past_sessions = count_past($table_assignment_start,time(),0);
	$future_sessions = count_future($table_assignment_start,time(),0);

	// Display a summary of table(s) the judge is assigned.
	// Include the "on the fly" judging form so the judge can
	// add an evalation for any entry they are not assigned to.
	
	if ($totalRows_table_assignments > 0) $table_assign_judge = table_assignments($_SESSION['user_id'],"J",$_SESSION['prefsTimeZone'],$_SESSION['prefsDateFormat'],$_SESSION['prefsTimeFormat'],3,$label_table);
	
	$assignment_display .= "<h2>".$label_table_assignments."</h2>";

	if ($next_date-$diff > time()) {
		$assignment_display .= "<div class=\"bcoem-admin-element\">".$evaluation_info_095."<strong> <span id=\"next-session-open\"></span></strong><br><span class=\"small text-muted\">".$evaluation_info_096."</span></div>";
		$assignment_display .= "<div class=\"bcoem-admin-element text-success\" id=\"next-session-refresh-button\"><strong>".$evaluation_info_097."</strong> ".$evaluation_info_098." <button type=\"button\" class=\"btn btn-success btn-sm\" onClick=\"window.location.reload()\">".$label_refresh."</button></div>";
	}

	$assignment_display .= "<div class=\"bcoem-admin-element\">";
	$assignment_display .= $evaluation_info_024;
	$assignment_display .= sprintf("<br><span class=\"small text-muted\">%s %s &#8226; %s %s</span>",$evaluation_info_099,$current_or_past_sessions,$evaluation_info_100,$future_sessions);
	$assignment_display .= "</div>";
	
	$assignment_display .= "\n<table id=\"judge_assignments\" class=\"table table-condensed table-striped table-bordered table-responsive border-dark-subtle\">";
	$assignment_display .= "<thead>";
	$assignment_display .= "<tr class=\"table-dark\">";
	$assignment_display .= sprintf("<th>%s</th>",$label_session);
	$assignment_display .= sprintf("<th width=\"30%%\">%s</th>",$label_date);
	$assignment_display .= sprintf("<th width=\"30%%\">%s</th>",$label_table);
	$assignment_display .= "</tr>";
	$assignment_display .= "</thead>";
	$assignment_display .= "<tbody>";
	if (empty($table_assign_judge)) $assignment_display .= sprintf("<tr><td>%s</td><td>%s</td><td>%s</td></tr>",$label_na,$label_na,$label_na);
	else $assignment_display .= $table_assign_judge;
	$assignment_display .= "</tbody>";

	// On the fly form
	if ($judging_open) {
		$assignment_display .= "<tfoot>";
		$assignment_display .= "<tr>";
		$assignment_display .= sprintf("<td colspan=\"2\">%s<br><small><em>* %s</em></small></td>",$evaluation_info_011,$evaluation_info_012);
		$assignment_display .= "<td>";
		$assignment_display .= "<div class=\"d-grid mb-1\">";	
		$assignment_display .= sprintf("<a onclick=\"bcoemClearScoresheetDraftStorage();\" class=\"btn btn-sm btn-primary\" role=\"button\" href=\"#add-single-form\" data-bs-toggle=\"collapse\" aria-expanded=\"false\" aria-controls=\"add-single-form\">%s</a>",$label_add);
		$assignment_display .= "</div>";
		$assignment_display .= "<div class=\"collapse\" id=\"add-single-form\" style=\"margin-top:5px;\">";
		$assignment_display .= "<form class=\"hide-loader-form-submit needs-validation\"  name=\"form1\" role=\"form\" action=\"".$base_url."index.php?section=evaluation&amp;go=scoresheet&amp;action=add\" method=\"post\" novalidate>";
		$assignment_display .= "<div class=\"form-group small mt-2 text-teal\">";
		$assignment_display .= sprintf("<i class=\"fa fa-sm fa-star me-1\"></i><label class=\"form-label\" for=\"entry_number\">%s</label>",$label_entry_number);
		$assignment_display .= "<input id=\"entry-number-input\" name=\"entry_number\" type=\"text\" pattern=\".{6,6}\" maxlength=\"6\" class=\"form-control form-control-sm\" required>";
		$assignment_display .= "<div class=\"invalid-feedback text-danger\">".$evaluation_info_015."</div>";
		$assignment_display .= "</div>";
		$assignment_display .= "<div class=\"d-grid mb-1\">";
		$assignment_display .= sprintf("<button class=\"btn btn-sm btn-success btn-block\" style=\"margin-top:5px;\" type=\"submit\">%s</button>",$label_go);
		$assignment_display .= "</div>";
		$assignment_display .= "</form>";
		$assignment_display .= "</div>";
		$assignment_display .= "</td>";
		$assignment_display .= "</tr>";
		$assignment_display .= "</tfoot>";
	}
	
	$assignment_display .= "</table>";

	// Build judge score disparity alert

	//print_r($judge_score_disparity);
	
	if (!empty($judge_score_disparity)) {
		$jscore_disparity .= "<div class=\"alert alert-warning\">";
		$jscore_disparity .= sprintf("<p><strong><i class=\"fa fa-exclamation-circle\"></i> %s %s</strong></p><p> %s</p>",$label_attention,$evaluation_info_036,$evaluation_info_018);
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

	if (!empty($eval_judge_evaluations)) {

	}

	// Build assigned score mismatch alert
	if (!empty($duplicate_judge_evals_alert)) {
		$dup_judge_evals_alert .= "<div class=\"alert alert-warning\">";
		$dup_judge_evals_alert .= sprintf("<p><strong><i class=\"fa fa-exclamation-circle\"></i> %s %s</strong> %s</p><p> %s</p>",$label_attention,$evaluation_info_032,$evaluation_info_033,$evaluation_info_018);
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
		$single_eval .= "<div class=\"alert alert-warning\">";
		$single_eval .= sprintf("<p><strong><i class=\"fa fa-exclamation-circle\"></i> %s</strong></p><p>%s</p>",$label_attention,$evaluation_info_019);
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
		$places_alert .= "<div class=\"alert alert-danger\">";
		$places_alert .= sprintf("<p><strong><i class=\"fa fa-exclamation-circle\"></i> %s</strong></p><p>%s</p>",$label_attention,$evaluation_info_029);
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
	include (PUB.'eval_judging_not_assigned.pub.php');

		$total_evals_alert .= "<div class=\"mb-3 p-3 text-dark bg-dark-subtle border border-dark-subtle rounded-2\">";

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
		$count_unique = get_evaluation_count('unique');

		if ($judging_open) $total_evals_alert .= sprintf("<p class=\"mb-0\"><i class=\"fa fa-clock me-2\"></i><strong>%s:</strong> <span id=\"judging-ends\"></span></p>", $label_judging_close);

		$total_evals_alert .= "</div>";

?>
<style>
	.bcoem-judge-session-toggle { cursor: pointer; background-color: #E0F2F1; }
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
				$collapse.removeClass('show');
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
<script src="<?php echo $js_url; ?>admin_ajax.min.js<?php if (((DEBUG) || (TESTING)) && (strpos($base_url, 'test.brewingcompetitions.com') !== false)) echo "?t=".time(); ?>"></script>
<?php
} // end if ($totalRows_table_assignments > 0)

/**
 * Judge Analytics: "Your Judging Stats" panel.
 * Shows the judge's own average words typed / time spent per entry against the
 * competition-wide average, plus small rolling-average sparklines. Only shown
 * when the jPrefsJudgeStats preference is enabled and the judge has judged at
 * least one entry.
 */
$judge_stats_panel = "";

if ($_SESSION['jPrefsJudgeStats'] == "Y") {

	$judge_analytics = get_judge_analytics_stats($_SESSION['user_id']);

	if ($judge_analytics['judge_count'] > 0) {

		$words_pct = "";
		if ($judge_analytics['comp_avg_words'] > 0) $words_pct = round((($judge_analytics['judge_avg_words'] - $judge_analytics['comp_avg_words']) / $judge_analytics['comp_avg_words']) * 100);

		$duration_pct = "";
		if (($judge_analytics['comp_avg_duration'] > 0) && ($judge_analytics['judge_duration_count'] > 0)) $duration_pct = round((($judge_analytics['judge_avg_duration'] - $judge_analytics['comp_avg_duration']) / $judge_analytics['comp_avg_duration']) * 100);

		$judge_avg_duration_disp = ($judge_analytics['judge_duration_count'] > 0) ? gmdate("i:s", $judge_analytics['judge_avg_duration']) : $label_na;
		$comp_avg_duration_disp = ($judge_analytics['comp_duration_count'] > 0) ? gmdate("i:s", $judge_analytics['comp_avg_duration']) : $label_na;

		$judge_stats_panel .= "<div class=\"card bcoem-judge-stats-card mb-4\">";
		$judge_stats_panel .= "<div id=\"judgeStatsToggle\" class=\"card-header bcoem-judge-stats-card-header bcoem-judge-stats-toggle\" data-bs-toggle=\"collapse\" href=\"#judgeStatsCollapse\" role=\"button\" aria-expanded=\"true\" aria-controls=\"judgeStatsCollapse\"><strong class=\"text-teal\"><i class=\"fa fa-chart-line me-2\"></i>".$evaluation_info_106."</strong><i class=\"fa fa-chevron-up bcoem-judge-stats-toggle-icon float-end\"></i></div>";
		$judge_stats_panel .= "<div class=\"collapse show\" id=\"judgeStatsCollapse\">";
		$judge_stats_panel .= "<div class=\"card-body\">";

		$judge_stats_panel .= "<div class=\"row g-3 mb-3\">";

		$judge_stats_panel .= "<div class=\"col-12 col-md-6\">";
		$judge_stats_panel .= "<div class=\"border rounded-2 p-3 h-100\">";
		$judge_stats_panel .= "<div class=\"small text-muted text-uppercase\">".$evaluation_info_107."</div>";
		$judge_stats_panel .= "<div class=\"bcoem-judge-stat-value\">".$judge_analytics['judge_avg_words']." <span class=\"fs-6 fw-normal text-muted\">".$evaluation_info_110."</span></div>";
		$judge_stats_panel .= "<div class=\"small mt-1\"><span class=\"text-muted\">".$evaluation_info_109.":</span> <strong>".$judge_analytics['comp_avg_words']." ".$evaluation_info_110."</strong>";
		if ($words_pct !== "") {
			$words_badge_class = ($words_pct >= 0) ? "text-bg-success" : "text-bg-secondary";
			$words_icon = ($words_pct >= 0) ? "fa-arrow-up" : "fa-arrow-down";
			$judge_stats_panel .= " <span class=\"badge rounded-pill ".$words_badge_class."\"><i class=\"fa ".$words_icon." me-1\"></i>".abs($words_pct)."% ".$evaluation_info_118."</span>";
		}
		$judge_stats_panel .= "</div></div></div>";

		$judge_stats_panel .= "<div class=\"col-12 col-md-6\">";
		$judge_stats_panel .= "<div class=\"border rounded-2 p-3 h-100\">";
		$judge_stats_panel .= "<div class=\"small text-muted text-uppercase\">".$evaluation_info_108."</div>";
		$judge_stats_panel .= "<div class=\"bcoem-judge-stat-value\">".$judge_avg_duration_disp." <span class=\"fs-6 fw-normal text-muted\">min:sec</span></div>";
		$judge_stats_panel .= "<div class=\"small mt-1\"><span class=\"text-muted\">".$evaluation_info_109.":</span> <strong>".$comp_avg_duration_disp." min:sec</strong>";
		if ($duration_pct !== "") {
			$duration_badge_class = ($duration_pct >= 0) ? "text-bg-success" : "text-bg-secondary";
			$duration_icon = ($duration_pct >= 0) ? "fa-arrow-up" : "fa-arrow-down";
			$judge_stats_panel .= " <span class=\"badge rounded-pill ".$duration_badge_class."\"><i class=\"fa ".$duration_icon." me-1\"></i>".abs($duration_pct)."% ".$evaluation_info_118."</span>";
		}
		$judge_stats_panel .= "</div></div></div>";

		$judge_stats_panel .= "</div>"; // end row g-3

		$judge_stats_panel .= "<p class=\"small text-muted mb-0\"><i class=\"fa fa-circle-info me-1\"></i>";
		$judge_stats_panel .= sprintf($evaluation_info_111,$judge_analytics['judge_count'],$judge_analytics['comp_count'],$judge_analytics['comp_judge_count']);
		$judge_stats_panel .= "</p>";

		if (count($judge_analytics['words_trend']) > 1) {

			$judge_stats_panel .= "<hr class=\"my-3\">";

			$judge_stats_panel .= "<div class=\"d-flex justify-content-between align-items-baseline mb-2\">";
			$judge_stats_panel .= "<strong class=\"small text-uppercase text-muted\">".$evaluation_info_112."</strong>";
			$judge_stats_panel .= "<span class=\"small text-muted\"><span class=\"bcoem-sparkline-swatch bcoem-sparkline-swatch-you\"></span> ".$evaluation_info_113." &nbsp; <span class=\"bcoem-sparkline-swatch bcoem-sparkline-swatch-avg\"></span> ".$evaluation_info_114."</span>";
			$judge_stats_panel .= "</div>";

			$judge_stats_panel .= "<div class=\"row g-3\">";

			$judge_stats_panel .= "<div class=\"col-12 col-md-6\">";
			$judge_stats_panel .= "<div class=\"border rounded-2 p-2\">";
			$judge_stats_panel .= "<div class=\"small text-muted\">".$evaluation_info_115."</div>";
			$judge_stats_panel .= "<div class=\"bcoem-sparkline-wrap\"><canvas id=\"judgeStatsSparkWords\"></canvas></div>";
			$judge_stats_panel .= "</div></div>";

			$judge_stats_panel .= "<div class=\"col-12 col-md-6\">";
			$judge_stats_panel .= "<div class=\"border rounded-2 p-2\">";
			$judge_stats_panel .= "<div class=\"small text-muted\">".$evaluation_info_116."</div>";
			$judge_stats_panel .= "<div class=\"bcoem-sparkline-wrap\"><canvas id=\"judgeStatsSparkTime\"></canvas></div>";
			$judge_stats_panel .= "</div></div>";

			$judge_stats_panel .= "</div>"; // end row g-3

			$judge_stats_panel .= "<p class=\"small text-muted mt-2 mb-0\"><i class=\"fa fa-circle-info me-1\"></i>".$evaluation_info_117."</p>";

		}

		$judge_stats_panel .= "</div>"; // end card-body
		$judge_stats_panel .= "</div>"; // end collapse
		$judge_stats_panel .= "</div>"; // end card
?>
<style>
	.bcoem-judge-stats-card { border-color: #B2DFDB; }
	.bcoem-judge-stats-card-header { background: #E0F2F1; border-color: #B2DFDB; }
	.bcoem-judge-stat-value { font-size: 1.6rem; font-weight: 600; }
	.bcoem-judge-stats-toggle { cursor: pointer; }
	.bcoem-judge-stats-toggle-icon { transition: transform 0.2s ease; }
	.bcoem-judge-stats-toggle.collapsed .bcoem-judge-stats-toggle-icon { transform: rotate(-180deg); }
</style>
<script type="text/javascript">
	(function() {
		var judgeStatsToggle = document.getElementById('judgeStatsToggle');
		var judgeStatsCollapse = document.getElementById('judgeStatsCollapse');
		var judgeStatsStorageKey = 'bcoemJudgeStatsCollapsed-<?php echo md5($prefix.$_SESSION['user_id']); ?>';

		if (judgeStatsToggle && judgeStatsCollapse) {

			try {
				if (localStorage.getItem(judgeStatsStorageKey) === '1') {
					judgeStatsCollapse.classList.remove('show');
					judgeStatsToggle.classList.add('collapsed');
					judgeStatsToggle.setAttribute('aria-expanded','false');
				}
			}
			catch (error) {
				// Use the default expanded state when browser storage is unavailable.
			}

			judgeStatsCollapse.addEventListener('hidden.bs.collapse', function() {
				try { localStorage.setItem(judgeStatsStorageKey,'1'); }
				catch (error) {
					// Preference simply won't persist when browser storage is unavailable.
				}
			});

			judgeStatsCollapse.addEventListener('shown.bs.collapse', function() {
				try { localStorage.removeItem(judgeStatsStorageKey); }
				catch (error) {
					// Preference simply won't persist when browser storage is unavailable.
				}
			});

		}
	})();
</script>
<?php

		if (count($judge_analytics['words_trend']) > 1) {
?>
<style>
	.bcoem-sparkline-wrap { position: relative; height: 90px; cursor: crosshair; }
	.bcoem-sparkline-swatch { display: inline-block; width: 10px; height: 10px; border-radius: 2px; margin-right: 2px; }
	.bcoem-sparkline-swatch-you { background: #004D40; }
	.bcoem-sparkline-swatch-avg { background: #B2DFDB; border: 1px dashed #26A69A; }
</style>
<script type="text/javascript">
	function bcoemBuildSparkline(canvasId, rollingAvg, compAvg, formatValue) {
		return new Chart(document.getElementById(canvasId), {
			type: 'line',
			data: {
				labels: rollingAvg.map(function(v, i) { return "<?php echo $label_number; ?> " + (i + 1); }),
				datasets: [
					{
						label: "<?php echo $evaluation_info_113; ?>",
						data: rollingAvg,
						borderColor: '#004D40',
						backgroundColor: 'transparent',
						borderWidth: 2,
						pointRadius: 0,
						pointHoverRadius: 4,
						pointHoverBackgroundColor: '#004D40',
						pointHitRadius: 12,
						tension: 0.3
					},
					{
						label: "<?php echo $evaluation_info_114; ?>",
						data: rollingAvg.map(function() { return compAvg; }),
						borderColor: '#26A69A',
						borderDash: [4, 4],
						borderWidth: 1.5,
						pointRadius: 0,
						pointHoverRadius: 0
					}
				]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				interaction: { mode: 'index', intersect: false },
				plugins: {
					legend: { display: false },
					tooltip: {
						enabled: true,
						callbacks: {
							title: function(items) { return items[0].label; },
							label: function(context) { return context.dataset.label + ': ' + formatValue(context.raw); }
						}
					}
				},
				scales: { x: { display: false }, y: { display: false } },
				elements: { line: { borderJoinStyle: 'round' } }
			}
		});
	}

	document.addEventListener('DOMContentLoaded', function() {

		bcoemBuildSparkline(
			'judgeStatsSparkWords',
			<?php echo json_encode($judge_analytics['words_trend']); ?>,
			<?php echo json_encode((float) $judge_analytics['comp_avg_words']); ?>,
			function(v) { return v + ' <?php echo $evaluation_info_110; ?>'; }
		);

		bcoemBuildSparkline(
			'judgeStatsSparkTime',
			<?php echo json_encode($judge_analytics['duration_trend']); ?>,
			<?php echo json_encode(round((float) $judge_analytics['comp_avg_duration'] / 60, 2)); ?>,
			function(v) {
				var mins = Math.floor(v);
				var secs = Math.round((v - mins) * 60);
				return mins + ':' + String(secs).padStart(2, '0') + ' min';
			}
		);

	});
</script>
<?php
		}

	} // end if ($judge_analytics['judge_count'] > 0)

} // end if ($_SESSION['jPrefsJudgeStats'] == "Y")

echo $header;
if (($judging_open) && (empty($table_assign_judge))) echo sprintf("<p>%s</p>",$evaluation_info_009);


if (!empty($total_evals_alert)) {
	if ($judging_open) echo $total_evals_alert;
}

if (!empty($places_alert)) echo $places_alert;
if (!empty($judge_score_disparity)) echo $jscore_disparity;
if (!empty($dup_judge_evals_alert)) echo $dup_judge_evals_alert;
if (!empty($single_evaluation)) echo $single_eval;
if (!empty($mini_bos_mismatch_alert)) echo $mini_bos_mismatch_alert;

if (!empty($judge_stats_panel)) echo $judge_stats_panel;

if ((!empty($latest_submitted_accordion)) || (!empty($latest_updated_accordion))) {
	echo "<div class=\"bcoem-admin-element\">";

	if (!empty($latest_submitted_accordion)) {
		echo "<a style=\"margin:0 10px 15px 0;\" class=\"btn btn-secondary\" role=\"button\" data-toggle=\"collapse\" href=\"#latest-submitted\" aria-expanded=\"false\" aria-controls=\"latest-submitted\"><i class=\"fa fa-clock me-2\"></i>20 Latest Submitted</a>";
	}

	if (!empty($latest_updated_accordion)) {
		echo "<a style=\"margin:0 10px 15px 0;\" class=\"btn btn-secondary\" role=\"button\" data-toggle=\"collapse\" href=\"#latest-updated\" aria-expanded=\"false\" aria-controls=\"latest-updated\"><i class=\"fa fa-clock me-2\"></i>20 Latest Updated</a>";
	}

}

if (!empty($latest_submitted_accordion)) {
	echo "<div id=\"latest-submitted\" class=\"collapse alert alert-teal\">";
	echo "<p><i class=\"fa fa-clock me-2\"></i>The <strong>20 most recently submitted</strong> evaluations:</p>";
	echo "<ul>";
	echo $latest_submitted_accordion;
	echo "</ul>";
	echo "</div>";
}

if (!empty($latest_updated_accordion)) {
	echo "<div id=\"latest-updated\" class=\"collapse alert alert-teal\">";
	echo "<p><i class=\"fa fa-clock me-2\"></i>The <strong>20 most recently updated</strong> evaluations:</p>";
	echo "<ul>";
	echo $latest_updated_accordion;
	echo "</ul>";
	echo "</div>";
}

echo $assignment_display;
if (!empty($on_the_fly_display)) echo $on_the_fly_display;
if (!empty($table_assign_judge)) echo $table_assignment_entries;
?>
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
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title" id="noDupeModalLabel"><?php echo $label_place_previously_selected; ?></h4>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
      	<?php echo $evaluation_info_048; ?>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-dark" data-bs-dismiss="modal" aria-label="Close"><?php echo $label_close; ?></button>
      </div>
    </div>
  </div>
</div>
<!-- Modal: Next Session Open -->
<div class="modal fade" id="next-session-open-modal" tabindex="-1" role="dialog" aria-labelledby="next-session-open-modal-label">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title" id="next-session-open-modal-label"><?php echo $label_please_note; ?></h4>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
      	<p><?php echo "<strong>".$evaluation_info_097."</strong> ".$evaluation_info_098; ?></p>
      </div>
      <div class="modal-footer">
      	<button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $label_stay_here; ?></button>
        <button type="button" class="btn btn-dark" data-bs-dismiss="modal" onclick="window.location.reload()" aria-label="Close"><?php echo $label_refresh; ?></button>
      </div>
    </div>
  </div>
</div>