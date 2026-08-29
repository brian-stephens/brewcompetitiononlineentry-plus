<?php

include(LIB.'output.lib.php');

$eid = "";
$uid = "";
$style = "";
$entry_info_html = "";
$judge_info_html = "";
$scoresheet_version = "";
$entry_not_found = "";
$header_elements = "";
$sticky_score_tally = "";
$guidelines_toggle_btn = "";
$guidelines_offcanvas_html = "";
$eval_nav_buttons = "";
$eval_score = 0;
$eval_prevent_edit = FALSE;
$entry_found = FALSE;
$mead_cider = FALSE;
$beer = FALSE;
$cider = FALSE;
$mead = FALSE;
$nw_cider = FALSE;
$scored_previously = FALSE;
$consensus_match = FALSE;
$auto_logout_extension = FALSE;
$evals = array();
$judge_scores = array();
$consensus_scores = array();
$other_judge_scores = "";
$other_judge_consensus_scores = "";
$other_judge_previous_consensus = array();
$my_consensus_score = "";
$evalPosition = "";
$eval_draft = array();
$eval_draft_id = 0;
$has_eval_draft = FALSE;

/*
if (HOSTED) $styles_db_table = "bcoem_shared_styles";
else
*/
$styles_db_table = $prefix."styles";

/**
 * Default judge range is 7 points, a commonly accepted
 * range. 
 */

if (isset($_SESSION['jPrefsScoreDispMax'])) $score_range = $_SESSION['jPrefsScoreDispMax'];
else $score_range = 7;

/**
 * BJCP 2015 and BJCP 2021 exceptions.
 * BJCP 2021 will be integrated when the BJCP website is updated
 * with the 2021 guidelines. See coding below.
 */

$bjcp2015_exceptions = array(
  "27A1" => "//bjcp.org/style/2015/27/27A/historical-beer-gose/",
  "27A2" => "//bjcp.org/style/2015/27/27A/historical-beer-piwo-grodziskie/",
  "27A3" => "//bjcp.org/style/2015/27/27A/historical-beer-lichtenhainer/",
  "27A4" => "//bjcp.org/style/2015/27/27A/historical-beer-roggenbier/",
  "27A5" => "//bjcp.org/style/2015/27/27A/historical-beer-sahti/",
  "27A6" => "//bjcp.org/style/2015/27/27A/historical-beer-kentucky-common/",
  "27A7" => "//bjcp.org/style/2015/27/27A/historical-beer-pre-prohibition-lager/",
  "27A8" => "//bjcp.org/style/2015/27/27A/historical-beer-pre-prohibition-porter/",
  "27A9" => "//bjcp.org/style/2015/27/27A/historical-beer-london-brown-ale/",
  "21B1" => "//bjcp.org/style/2015/21/21B/specialty-ipa-belgian-ipa/",
  "21B2" => "//bjcp.org/style/2015/21/21B/specialty-ipa-black-ipa/",
  "21B3" => "//bjcp.org/style/2015/21/21B/specialty-ipa-brown-ipa/",
  "21B4" => "//bjcp.org/style/2015/21/21B/specialty-ipa-red-ipa/",
  "21B5" => "//bjcp.org/style/2015/21/21B/specialty-ipa-rye-ipa/",
  "21B6" => "//bjcp.org/style/2015/21/21B/specialty-ipa-white-ipa/",
  "21B7" => "//bjcp.org/beer-styles/21b-specialty-ipa-new-england-ipa/",
  "17A1" => "//bjcp.org/beer-styles/17a-british-strong-ale-burton-ale/",
  "PRX1" => "//bjcp.org/beer-styles/x1-dorada-pampeana/",
  "PRX2" => "//bjcp.org/beer-styles/x2-ipa-argenta/",
  "PRX3" => "//bjcp.org/beer-styles/x3-italian-grape-ale/",
  "PRX4" => "//bjcp.org/beer-styles/x4-catharina-sour/",
  "PRX5" => "//bjcp.org/beer-styles/x5-new-zealand-pilsner/"
);

$bjcp2021_exceptions = array(
  "17A1" => "//bjcp.org/beer-styles/17a-british-strong-ale-burton-ale/",
  "21B1" => "//bjcp.org/style/2021/21/21B/specialty-ipa-belgian-ipa/",
  "21B2" => "//bjcp.org/style/2021/21/21B/specialty-ipa-black-ipa/",
  "21B3" => "//bjcp.org/style/2021/21/21B/specialty-ipa-brown-ipa/",
  "21B4" => "//bjcp.org/style/2021/21/21B/specialty-ipa-red-ipa/",
  "21B5" => "//bjcp.org/style/2021/21/21B/specialty-ipa-rye-ipa/",
  "21B6" => "//bjcp.org/style/2021/21/21B/specialty-ipa-white-ipa/",
  "27A1" => "//bjcp.org/style/2021/27/27A/historical-beer-kellerbier/",
  "27A2" => "//bjcp.org/style/2021/27/27A/historical-beer-kentucky-common/",
  "27A3" => "//bjcp.org/style/2021/27/27A/historical-beer-lichtenhainer/",
  "27A4" => "//bjcp.org/style/2021/27/27A/historical-beer-london-brown-ale/",
  "27A5" => "//bjcp.org/style/2021/27/27A/historical-beer-piwo-grodziskie/",
  "27A6" => "//bjcp.org/style/2021/27/27A/historical-beer-pre-prohibition-lager/",
  "27A7" => "//bjcp.org/style/2021/27/27A/historical-beer-pre-prohibition-porter/",
  "27A8" => "//bjcp.org/style/2021/27/27A/historical-beer-roggenbier/",
  "27A9" => "//bjcp.org/style/2021/27/27A/historical-beer-sahti/",
  "LSX1" => "//bjcp.org/beer-styles/x1-dorada-pampeana/",
  "LSX2" => "//bjcp.org/beer-styles/x2-ipa-argenta/",
  "LSX3" => "//bjcp.org/beer-styles/x3-italian-grape-ale/",
  "LSX4" => "//bjcp.org/beer-styles/x4-catharina-sour/",
  "LSX5" => "//bjcp.org/beer-styles/x5-new-zealand-pilsner/"
);

/**
 * When admins edit a scoresheet, the $bid var will be in the URL.
 * $bid is judge's user id.
 */

if ($bid == "default") {
  $judge_id = $_SESSION['user_id'];
  $eval_source = 1; // From user judging dashboard
}

else {
  $judge_id = $bid;
  $eval_source = 0; // From Admin
}

if (isset($_POST['participants'])) $eval_source = 0;

if (empty($row_judging_prefs['jPrefsScoresheet'])) $judging_scoresheet = 1;
elseif (!isset($_SESSION['jPrefsScoresheet'])) $judging_scoresheet = 1;
else $judging_scoresheet = $_SESSION['jPrefsScoresheet'];

if (is_numeric($sort)) $judging_scoresheet = $sort; 

if ($judging_scoresheet == 1) {
  $output_form = "full_output.eval.php";
  $scoresheet_form = "eval_scoresheet_full.pub.php";
  $process_type = "process-eval-full";
  $scoresheet_version = $label_classic_version;
}

if ($judging_scoresheet == 2) {
  $output_form = "checklist_output.eval.php";
  $scoresheet_form = "eval_scoresheet_checklist.pub.php";
  $process_type = "process-eval-checklist";
  $scoresheet_version = $label_checklist_version;
}

if (($judging_scoresheet == 3) || ($judging_scoresheet == 4)) {
  $output_form = "structured_output.eval.php";
  $scoresheet_form = "eval_scoresheet_structured.pub.php";
  $process_type = "process-eval-structured";
  $scoresheet_version = $label_structured_version;
}

/** 
 * When a user is adding a new evaluation.
 * If there's an entry_number $_POST var, indicates
 * that the scoresheet is being added by a non-admin
 * on-the-fly.
 */

$query_style = "";

if ($action == "add") {

  $submit_button_text = $label_submit_evaluation;

  if (isset($_POST['entry_number'])) {

    $id = ltrim(sterilize($_POST['entry_number']),"0");
    
    if ($_SESSION['prefsDisplaySpecial'] == "E") {
      $query_entry_info = sprintf("SELECT * FROM %s WHERE id='%s'",$prefix."brewing",$id);
    }
    
    if ($_SESSION['prefsDisplaySpecial'] == "J") {
      $judging_number = sterilize($_POST['entry_number']);
      $query_entry_info = sprintf("SELECT * FROM %s WHERE brewJudgingNumber='%s'",$prefix."brewing",$judging_number);
    }

  }
  
  else $query_entry_info = sprintf("SELECT * FROM %s WHERE id='%s'",$prefix."brewing",$id);
  $entry_info = mysqli_query($connection,$query_entry_info) or die (mysqli_error($connection));
  $row_entry_info = mysqli_fetch_assoc($entry_info);
  $totalRows_entry_info = mysqli_num_rows($entry_info);

  if ($totalRows_entry_info > 0) {
    
    if ($_SESSION['prefsStyleSet'] == "BJCP2025") {
        $first_character = mb_substr($row_entry_info['brewCategorySort'], 0, 1);
        if ($first_character == "C") $chosen_style_set = "BJCP2025";
        else $chosen_style_set = "BJCP2021";
    }

    else $chosen_style_set = $_SESSION['prefsStyleSet'];
    
    $query_style = sprintf("SELECT * FROM %s WHERE brewStyleGroup = '%s' AND brewStyleNum = '%s' AND brewStyleVersion='%s'", $prefix."styles", $row_entry_info['brewCategorySort'], $row_entry_info['brewSubCategory'], $chosen_style_set);
  }

}

/**
 * When a user is editing an evaluation.
 * Checks are in place to determine whether the 
 * current user is associated with the original
 * eval add.
 */
if ($action == "edit") {

  $submit_button_text = $label_edit_evaluation;
 
  if ($id == "default") $query_eval = sprintf("SELECT * FROM evaluation WHERE evalToken='%s'", $token);
  else $query_eval = sprintf("SELECT * FROM %s WHERE id=%s",$prefix."evaluation",$id);
  $eval = mysqli_query($connection,$query_eval) or die (mysqli_error($connection));
  $row_eval = mysqli_fetch_assoc($eval);
  $totalRows_eval = mysqli_num_rows($eval);

  if ($totalRows_eval > 0) {

    $evals = eval_exits($row_eval['eid'],"default",$dbTable);
    $evals_json = json_encode($evals);
 
    $eval_score = $row_eval['evalAromaScore'] + $row_eval['evalAppearanceScore'] + $row_eval['evalFlavorScore'] + $row_eval['evalMouthfeelScore'] + $row_eval['evalOverallScore']; 
    $eid = $row_eval['eid'];
    $uid = $row_eval['uid'];
    $style = $row_eval['evalStyle'];
    if (($_SESSION['userLevel'] > 1) && ($row_eval['evalJudgeInfo'] != $_SESSION['user_id'])) $eval_prevent_edit = TRUE;
    
    $query_entry_info = sprintf("SELECT * FROM %s WHERE id='%s'", $prefix."brewing", $eid);
    $entry_info = mysqli_query($connection,$query_entry_info) or die (mysqli_error($connection));
    $row_entry_info = mysqli_fetch_assoc($entry_info);
    $totalRows_entry_info = mysqli_num_rows($entry_info);
    
    if ($totalRows_entry_info > 0) {
      /*
      if (HOSTED) $query_style = sprintf("SELECT * FROM %s WHERE id='%s' UNION ALL SELECT * FROM %s WHERE id='%s'", $styles_db_table, $style, $prefix."styles", $style);
      else 
      */
      $query_style = sprintf("SELECT * FROM %s WHERE id='%s'", $prefix."styles", $style);
    }

  }

}

if (!empty($query_style)) {
  $style = mysqli_query($connection,$query_style) or die (mysqli_error($connection));
  $row_style = mysqli_fetch_assoc($style);
  $totalRows_style = mysqli_num_rows($style);
}

if ($totalRows_entry_info > 0) {
  $judge_scores = eval_exits($row_entry_info['id'],"judge_scores",$dbTable);
  if ($action == "add") $flight_count_info = flight_count_info($id,0);
  if ($action == "edit") $flight_count_info = flight_count_info($eid,0);

  if (!empty($judge_scores)) {
    $scored_previously = TRUE;
    $consensus_scores = eval_exits($row_entry_info['id'],"consensus_scores",$dbTable);
    if (count(array_unique($consensus_scores)) === 1) $consensus_match = TRUE;
    $other_judge_scores .= sprintf("%s: ".rtrim(display_array_content($judge_scores,2),", "),$label_judge_score);
    $other_judge_consensus_scores .= sprintf("%s: ".rtrim(display_array_content($consensus_scores,2),", "),$label_judge_consensus_scores);
    if (isset($row_eval['evalFinalScore'])) $my_consensus_score .= sprintf("%s: <span id=\"my-consensus-score\">".$row_eval['evalFinalScore']."</span>",$label_your_consensus_score);
  }

  if (($action == "edit") && (!$consensus_match)) $consensus_scores = array_diff($consensus_scores,array($row_eval['evalFinalScore']));

  if (isset($_POST['entry_number'])) {
    
    // Get table info
    $query_flight_info = sprintf("SELECT flightTable FROM %s WHERE flightEntryID='%s'",$prefix."judging_flights",$row_entry_info['id']);
    $flight_info = mysqli_query($connection,$query_flight_info) or die (mysqli_error($connection));
    $row_flight_info = mysqli_fetch_assoc($flight_info);

    if ($row_flight_info) $filter = $row_flight_info['flightTable'];

  }

}


/**
 * Included Descriptors are used by multiple functions.
 * Depends upon query of style db table.
 */
include (EVALS.'descriptors.eval.php');

if ($totalRows_entry_info > 0) $entry_found = TRUE;

if ($entry_found) {

  if ($row_style['brewStyleType'] == 2) $cider = TRUE;
  elseif ($row_style['brewStyleType'] == 3) $mead = TRUE;
  else $beer = TRUE;

  if ($judging_scoresheet == 4) {
    $cider = TRUE;
    $nw_cider = TRUE;
    if ($sort == 4) $scoresheet_version .= " &ndash; Northwest Cider Cup";
    else $scoresheet_version .= " &ndash; ".$_SESSION['style_set_long_name'];
  }

  // If style is Cider (2) or Mead (3), only use full scoresheet instad of checklist
  if ((($judging_scoresheet == 1) || ($judging_scoresheet == 2)) && (($cider) || ($mead))) {
    $output_form = "full_output.eval.php";
    $scoresheet_form = "eval_scoresheet_full.pub.php";
    $process_type = "process-eval-full";
    $mead_cider = TRUE;
    $scoresheet_version = $label_classic_version;
  }

  if ($action == "add") {
    $eid = $id;
    if (isset($_POST['entry_number'])) {
      $eid = $row_entry_info['id'];
    }
    $uid = $row_entry_info['brewBrewerID'];
    $style = $row_style['id'];

    $query_eval_draft = sprintf("SELECT * FROM %s WHERE eid='%s' AND evalJudgeInfo='%s' AND evalDraft='1' ORDER BY id DESC LIMIT 1", $prefix."evaluation", $eid, $judge_id);
    $eval_draft_result = mysqli_query($connection,$query_eval_draft) or die (mysqli_error($connection));
    $row_eval_draft = mysqli_fetch_assoc($eval_draft_result);
    if ($row_eval_draft) {
      $has_eval_draft = TRUE;
      $eval_draft = $row_eval_draft;
      $eval_draft_id = $row_eval_draft['id'];
    }
  }

  // Standardize entry identifier to 6-digits
  if ($_SESSION['prefsDisplaySpecial'] == "J") $number = sprintf("%06s",$row_entry_info['brewJudgingNumber']);
  else $number = sprintf("%06s",$row_entry_info['id']);

  // Standardize style number display
  $style_num = style_number_const($row_style['brewStyleGroup'],$row_style['brewStyleNum'],$_SESSION['style_set_display_separator'],0);

  // Build auto logout extended display
  if ($auto_logout_extension) {
    $entry_info_html .= sprintf("<div class=\"alert alert-info\"><strong><i class=\"fa fa-info-circle\"></i> %s:</strong> %s</div>",$label_please_note,$evaluation_info_072);
  }

  // Build entry info display
  //$entry_info_html .= "<h3>".$label_info." <a id=\"show-hide-entry-info-btn\" data-bs-toggle=\"collapse\" href=\"#scoresheet-entry-info\" aria-controls=\"scoresheet-entry-info\"><i id=\"toggle-icon-entry-info\" class=\"fa fa-chevron-circle-up\"></i></a></h3>";

  $entry_info_html .= "<h3>".$label_info."</h3>";
  $entry_info_html .= "<section class=\"row mb-3\">";
  $entry_info_html .= "<div class=\"col-12 ps-0\">";

  $entry_info_html .= "<section class=\"alert alert-teal\">";

  $entry_info_html .= "<div class=\"row mb-3\">";
  $entry_info_html .= "<div class=\"col-12 col-lg-3 col-md-4 col-sm-4\"><strong>".$label_number."</strong></div>";
  $entry_info_html .= "<div class=\"col-12 col-lg-9 col-md-8 col-sm-8\">".$number."</div>";
  $entry_info_html .= "</div>";

  $entry_info_html .= "<div class=\"row mb-3\">";
  if ($nw_cider) $entry_info_html .= "<div class=\"col-12 col-lg-3 col-md-4 col-sm-4\"><strong>".$_SESSION['style_set_short_name']." ".$label_category."</strong></div>";
  else $entry_info_html .= "<div class=\"col-12 col-lg-3 col-md-4 col-sm-4\"><strong>".$_SESSION['style_set_short_name']." ".$label_style."</strong></div>";
  $entry_info_html .= "<div class=\"col-12 col-lg-9 col-md-8 col-sm-8\">";

  // Style Links
  $style_link = "";
  $style_concat = ltrim($row_style['brewStyleGroup'],"0").strtoupper($row_style['brewStyleNum']);

  if (!empty($row_style['brewStyleLink'])) {
    
    if ($_SESSION['prefsStyleSet'] == "BJCP2015") {

      if (array_key_exists($style_concat, $bjcp2015_exceptions)) $style_link = $bjcp2015_exceptions[$style_concat];
      else $style_link = "//bjcp.org/style/2015/".ltrim($row_style['brewStyleGroup'],"0")."/".$style_concat."/";
      
    }
    
    else $style_link = $row_style['brewStyleLink'];

  }

  elseif (($_SESSION['prefsStyleSet'] == "BJCP2021") || ($_SESSION['prefsStyleSet'] == "BJCP2025")) {

    $first_character = mb_substr($row_style['brewStyleGroup'], 0, 1);

    // Exceptions
    if (array_key_exists($style_concat, $bjcp2021_exceptions)) $style_link = $bjcp2021_exceptions[$style_concat];

    // 2021 update was beer only; find numbered styles
    elseif (is_numeric(ltrim($row_style['brewStyleGroup'],"0"))) $style_link = "//bjcp.org/style/2021/".ltrim($row_style['brewStyleGroup'],"0")."/".$style_concat."/";

    // 2025 update was cider only; find styles that begin with C
    elseif (($_SESSION['prefsStyleSet'] == "BJCP2025") && ($first_character == "C")) $style_link = "//bjcp.org/style/2025/".ltrim($row_style['brewStyleGroup'],"0")."/".$style_concat."/";

    // If mead, use 2015 link
    else $style_link = "//bjcp.org/style/2015/".ltrim($row_style['brewStyleGroup'],"0")."/".$style_concat."/";

  }

  if (empty($style_link)) {

    $entry_info_html .= $style_num." ".$row_style['brewStyle'];
    if (($_SESSION['prefsStyleSet'] == "BJCP2021") || ($_SESSION['prefsStyleSet'] == "BJCP2025")) $entry_info_html .= "<a style=\"margin-left:10px;\" href=\"https://www.bjcp.org/bjcp-style-guidelines\" target=\"_blank\"><i class=\"small fa fa-external-link\"></i></a>";
    if (($_SESSION['prefsStyleSet'] == "AABC") || ($_SESSION['prefsStyleSet'] == "AABC2022") || ($_SESSION['prefsStyleSet'] == "AABC2025")) $entry_info_html .= "<a style=\"margin-left:10px;\" href=\"https://aabc.asn.au/docs/AABC2025StyleGuidelines.pdf\" target=\"_blank\"><i class=\"small fa fa-external-link\"></i></a>";
    if ($_SESSION['prefsStyleSet'] == "BA") $entry_info_html .= "<a style=\"margin-left:10px;\" href=\"https://www.brewersassociation.org/edu/brewers-association-beer-style-guidelines/\" target=\"_blank\"><i class=\"small fa fa-external-link\"></i></a>";

  }

  else {

    $entry_info_html .= "<a href=\"".$style_link."\" target=\"_blank\">";
    $entry_info_html .= $style_num." ".$row_style['brewStyle'];
    $entry_info_html .= "<i style=\"margin-left:10px;\" class=\"small fa fa-external-link\"></i></a>";

  }

  $entry_info_html .= "</div>";
  $entry_info_html .= "</div>";


  if (!empty($row_entry_info['brewInfo'])) {
    $entry_info_html .= "<div class=\"row mb-3\">";
    if ((($_SESSION['prefsStyleSet'] == "BJCP2021") || ($_SESSION['prefsStyleSet'] == "BJCP2025")) && ($style_num == "2A")) $entry_info_html .= "<div class=\"col-12 col-lg-3 col-md-4 col-sm-4\"><strong>".$label_regional_variation."</strong></div>";
    else $entry_info_html .= "<div class=\"col-12 col-lg-3 col-md-4 col-sm-4\"><strong>".$label_required_info."</strong></div>";
    $entry_info_html .= "<div class=\"col-12 col-lg-9 col-md-8 col-sm-8\">".str_replace("^", " - ", $row_entry_info['brewInfo'])."</div>";
    $entry_info_html .= "</div>";
  }

  if (!empty($row_entry_info['brewInfoOptional'])) {
    $entry_info_html .= "<div class=\"row mb-3\">";
    $entry_info_html .= "<div class=\"col-12 col-lg-3 col-md-4 col-sm-4\"><strong>".$label_optional_info."</strong></div>";
    $entry_info_html .= "<div class=\"col-12 col-lg-9 col-md-8 col-sm-8\">".$row_entry_info['brewInfoOptional']."</div>";
    $entry_info_html .= "</div>";
  }

  if (!empty($row_entry_info['brewComments'])) {
    $entry_info_html .= "<div class=\"row mb-3\">";
    $entry_info_html .= "<div class=\"col-12 col-lg-3 col-md-4 col-sm-4\"><strong>".$label_brewer_specifics."</strong></div>";
    $entry_info_html .= "<div class=\"col-12 col-lg-9 col-md-8 col-sm-8\">".$row_entry_info['brewComments']."</div>";
    $entry_info_html .= "</div>";
  }

  if (!empty($row_entry_info['brewMead1'])) {
    $entry_info_html .= "<div class=\"row mb-3\">";
    $entry_info_html .= "<div class=\"col-12 col-lg-3 col-md-4 col-sm-4\"><strong>".$label_carbonation."</strong></div>";
    $entry_info_html .= "<div class=\"col-12 col-lg-9 col-md-8 col-sm-8\">".$row_entry_info['brewMead1']."</div>";
    $entry_info_html .= "</div>";
  }

  if (!empty($row_entry_info['brewMead3'])) {
    $entry_info_html .= "<div class=\"row mb-3\">";
    $entry_info_html .= "<div class=\"col-12 col-lg-3 col-md-4 col-sm-4\"><strong>".$label_strength."</strong></div>";
    $entry_info_html .= "<div class=\"col-12 col-lg-9 col-md-8 col-sm-8\">".$row_entry_info['brewMead3']."</div>";
    $entry_info_html .= "</div>";
  }

  if (!empty($row_entry_info['brewMead2'])) {
    $entry_info_html .= "<div class=\"row mb-3\">";
    $entry_info_html .= "<div class=\"col-12 col-lg-3 col-md-4 col-sm-4\"><strong>".$label_sweetness."</strong></div>";
    $entry_info_html .= "<div class=\"col-12 col-lg-9 col-md-8 col-sm-8\">".$row_entry_info['brewMead2']."</div>";
    $entry_info_html .= "</div>";
  }

  if (($_SESSION['prefsStyleSet'] == "NWCiderCup") && (!empty($row_entry_info['brewSweetnessLevel']))) {
    $entry_info_html .= "<div class=\"row mb-3\">";
    $entry_info_html .= "<div class=\"col-12 col-lg-3 col-md-4 col-sm-4\"><strong>".$label_final_gravity."</strong></div>";
    $entry_info_html .= "<div class=\"col-12 col-lg-9 col-md-8 col-sm-8\">".$row_entry_info['brewSweetnessLevel']."</div>";
    $entry_info_html .= "</div>";
  }

  if (($_SESSION['prefsStyleSet'] != "NWCiderCup") && (!empty($row_entry_info['brewSweetnessLevel']))) {

    $sweetness_json = json_decode($row_entry_info['brewSweetnessLevel'],true);
    
    if (json_last_error() === JSON_ERROR_NONE) {

      if (!empty($sweetness_json['OG'])) {
        $entry_info_html .= "<div class=\"row bcoem-admin-element\">";
        $entry_info_html .= "<div class=\"col col-lg-3 col-md-4 col-sm-4 col-xs-12\"><strong>".$label_original_gravity."</strong></div>";
        $entry_info_html .= "<div class=\"col col-lg-9 col-md-8 col-sm-8 col-xs-12\">".$sweetness_json['OG']."</div>";
        $entry_info_html .= "</div>";
      }

      if (!empty($sweetness_json['FG'])) {
        $entry_info_html .= "<div class=\"row bcoem-admin-element\">";
        $entry_info_html .= "<div class=\"col col-lg-3 col-md-4 col-sm-4 col-xs-12\"><strong>".$label_final_gravity."</strong></div>";
        $entry_info_html .= "<div class=\"col col-lg-9 col-md-8 col-sm-8 col-xs-12\">".$sweetness_json['FG']."</div>";
        $entry_info_html .= "</div>";
      }

    }
    
    else {

      $entry_info_html .= "<div class=\"row bcoem-admin-element\">";
      $entry_info_html .= "<div class=\"col col-lg-3 col-md-4 col-sm-4 col-xs-12\"><strong>".$label_final_gravity."</strong></div>";
      $entry_info_html .= "<div class=\"col col-lg-9 col-md-8 col-sm-8 col-xs-12\">".$row_entry_info['brewSweetnessLevel']."</div>";
      $entry_info_html .= "</div>";

      $sweetness_level_display .= "<strong>".$label_final_gravity.":</strong> ".$row_entries['brewSweetnessLevel'];
    }

  }

  if (!empty($row_entry_info['brewABV'])) {
    $entry_info_html .= "<div class=\"row mb-3\">";
    $entry_info_html .= "<div class=\"col-12 col-lg-3 col-md-4 col-sm-4\"><strong>".$label_abv."</strong></div>";
    $entry_info_html .= "<div class=\"col-12 col-lg-9 col-md-8 col-sm-8\">".number_format($row_entry_info['brewABV'],1)."&#37;</div>";
    $entry_info_html .= "</div>";
  }

  if (!empty($row_entry_info['brewPossAllergens'])) {
    $entry_info_html .= "<div class=\"row mb-3\">";
    $entry_info_html .= "<div class=\"col-12 col-lg-3 col-md-4 col-sm-4\"><strong>".$label_possible_allergens."</strong></div>";
    $entry_info_html .= "<div class=\"col-12 col-lg-9 col-md-8 col-sm-8\">".$row_entry_info['brewPossAllergens']."</div>";
    $entry_info_html .= "</div>";
  }

  if ((!empty($row_entry_info['brewPouring'])) && ((!empty($row_entry_info['brewStyleType'])) && ($row_entry_info['brewStyleType'] == 1))) {
    
    $pouring_arr = json_decode($row_entry_info['brewPouring'],true);

    $entry_info_html .= "<div class=\"row mb-3\">";
    $entry_info_html .= "<div class=\"col-12 col-lg-3 col-md-4 col-sm-4\"><strong>".$label_pouring."</strong></div>";
    $entry_info_html .= "<div class=\"col-12 col-lg-9 col-md-8 col-sm-8\">".$pouring_arr['pouring']."</div>";
    $entry_info_html .= "</div>";

    if ((isset($pouring_arr['pouring_notes'])) && (!empty($pouring_arr['pouring_notes'])))  {
      $entry_info_html .= "<div class=\"row mb-3\">";
      $entry_info_html .= "<div class=\"col-12 col-lg-3 col-md-4 col-sm-4\"><strong>".$label_pouring_notes."</strong></div>";
      $entry_info_html .= "<div class=\"col-12 col-lg-9 col-md-8 col-sm-8\">".$pouring_arr['pouring_notes']."</div>";
      $entry_info_html .= "</div>";
    }

    $entry_info_html .= "<div class=\"row mb-3\">";
    $entry_info_html .= "<div class=\"col-12 col-lg-3 col-md-4 col-sm-4\"><strong>".$label_rouse_yeast."</strong></div>";
    $entry_info_html .= "<div class=\"col-12 col-lg-9 col-md-8 col-sm-8\">".$pouring_arr['pouring_rouse']."</div>";
    $entry_info_html .= "</div>";

  }

  if (!empty($row_entry_info['brewStaffNotes'])) {
    $entry_info_html .= "<div class=\"row mb-3\">";
    $entry_info_html .= "<div class=\"col-12 col-lg-3 col-md-4 col-sm-4\"><strong>".$label_notes." &ndash; ".$label_staff."</strong></div>";
    $entry_info_html .= "<div class=\"col-12 col-lg-9 col-md-8 col-sm-8\">".$row_entry_info['brewStaffNotes']."</div>";
    $entry_info_html .= "</div>";
  }

  if (!empty($row_entry_info['brewAdminNotes'])) {
    $entry_info_html .= "<div class=\"row mb-3\">";
    $entry_info_html .= "<div class=\"col-12 col-lg-3 col-md-4 col-sm-4\"><strong>".$label_notes." &ndash; ".$label_admin_short."</strong></div>";
    $entry_info_html .= "<div class=\"col-12 col-lg-9 col-md-8 col-sm-8\">".$row_entry_info['brewAdminNotes']."</div>";
    $entry_info_html .= "</div>";
  }


  $entry_info_html .= "</section>"; // end alert-teal

  if ((isset($_POST['participants'])) || ($bid != "default")) {
    
    $eval_source = 0;

    if (isset($_POST['participants'])) {
      $judge_id = $_POST['participants'];
      $eval_judge = brewer_info($_POST['participants']);
      $view = "admin";
    }

    else {
      $judge_id = $bid;
      $eval_judge = brewer_info($bid);
    }

    

    $eval_judge = explode("^",$eval_judge);

    if (strpos($eval_judge[3], ",") !== false) {
        $judge_rank = explode(",",$eval_judge[3]);
        $judge_rank_display = $judge_rank[0];
    }

    else $judge_rank_display = $eval_judge[3];

    $judge_info_html .= "<section class=\"alert alert-info\">";
    $judge_info_html .= "<div class=\"row mb-3\">";
    $judge_info_html .= "<div class=\"col-12 col-lg-3 col-md-4 col-sm-4\"><strong>".$label_judge."</strong></div>";
    $judge_info_html .= "<div class=\"col-12 col-lg-9 col-md-8 col-sm-8\">".$eval_judge[0]." ".$eval_judge[1]."</div>";
    $judge_info_html .= "</div>"; // end row
    $judge_info_html .= "<div class=\"row mb-3\">";
    $judge_info_html .= "<div class=\"col-12 col-lg-3 col-md-4 col-sm-4\">";
    $judge_info_html .= "<strong>".$label_bjcp_rank;
    if (($eval_judge[4] != "&nbsp;") && ((isset($judge_rank)) && ($judge_rank[0] != "Non-BJCP"))) $judge_info_html .= " (".$label_bjcp_id.")";
    $judge_info_html .= "</strong>";
    $judge_info_html .= "</div>";
    $judge_info_html .= "<div class=\"col-12 col-lg-9 col-md-8 col-sm-8\">";
    $judge_info_html .= $judge_rank_display;
    if (($eval_judge[4] != "&nbsp;") && ((isset($judge_rank)) && ($judge_rank[0] != "Non-BJCP"))) $judge_info_html .= " (".$eval_judge[4].")";
    if ((isset($judge_rank)) && (is_array($judge_rank))) {
      $judge_credentials = "<br>";
      foreach ($judge_rank as $key => $value) {
        if ($key != 0) $judge_credentials .= $value.", ";
      }
      $judge_credentials = rtrim($judge_credentials, ", ");
      $judge_info_html .= $judge_credentials;
    }
    $judge_info_html .= "</div>";
    $judge_info_html .= "</div>"; // end row
    $judge_info_html .= "</section>"; // end alert-info
  
  }

  if (!empty($judge_info_html)) $entry_info_html .= $judge_info_html;

  $entry_info_html .= "</div>"; // end col-12 ps-0

  $entry_info_html .= "</section>"; // end row mb-3

  
  
  
  // If admin is adding eval on behalf of a judge, or if editing a judge's evaluation, display their judge's info
  
  
  // Sticky score
  $sticky_score_tally = "\n\n<div id=\"sticky-score\" class=\"float-end\">";

  $sticky_score_tally .= "<div class=\"float-end mb-3 pt-2 pb-2\">";
  $sticky_score_tally .= "<i id=\"warning-indicator-icon\" class=\"fa fa-lg fa-exclamation-triangle text-warning-emphasis mt-2 mb-2 me-1\"></i>";
  $sticky_score_tally .= "<a class=\"mt-2 mb-2\" role=\"button\" id=\"show-hide-status-btn\" data-bs-toggle=\"collapse\" href=\"#scoring-guide-status\" aria-controls=\"scoring-guide-status\"><i id=\"toggle-icon\" class=\"fa fa-lg fa-chevron-circle-up\"></i></a>";
  $sticky_score_tally .= "</div>";

  if (!$nw_cider) { 
    $sticky_score_tally .= "<section class=\"mb-3\">";
    $sticky_score_tally .= "<div class=\"fs-4\">";
    $sticky_score_tally .= "<span id=\"scoring-guide-badge\" class=\"badge text-bg-dark shadow\">".$label_score.": <span id=\"judge-score\" class=\"pe-2\">".$eval_score."</span> <span id=\"scoring-guide\" class=\"fw-light small\"></span></span>";
    $sticky_score_tally .= "</div>";
    $sticky_score_tally .= "</section>";
  }

  $sticky_score_tally .= "<section id=\"scoring-guide-status\" class=\"p-3 w-100 border border-secondary-subtle bg-secondary-subtle shadow rounded-3 collapse show\">";

  if (!$nw_cider) {
    
    $sticky_score_tally .= "<p class=\"mb-0 pb-1\"><i class=\"fa fa-info-circle\"></i> <strong>".$label_status." &ndash; ".$label_admin_scores."</strong></p>";
    $sticky_score_tally .= "<section class=\"row small\">";
    $sticky_score_tally .= "<div class=\"col-10\">";
    $sticky_score_tally .= "<i id=\"score-icon-aroma-status\" class=\"fa fa-fw text-danger fa-times-circle me-1\"></i>".truncate($label_aroma,"10","");
    $sticky_score_tally .= "</div>";
    $sticky_score_tally .= "<div class=\"col-2 text-end\">";
    $sticky_score_tally .= "<span id=\"score-aroma-status\"></span>";
    $sticky_score_tally .= "</div>";
    $sticky_score_tally .= "</section>";

    $sticky_score_tally .= "<section class=\"row small\">";
    $sticky_score_tally .= "<div class=\"col-10\">";
    $sticky_score_tally .= "<i id=\"score-icon-appearance-status\" class=\"fa fa-fw text-danger fa-times-circle me-1\"></i>".truncate($label_appearance,"10","");
    $sticky_score_tally .= "</div>";
    $sticky_score_tally .= "<div class=\"col-2 text-end\">";
    $sticky_score_tally .= "<span id=\"score-appearance-status\"></span>";
    $sticky_score_tally .= "</div>";
    $sticky_score_tally .= "</section>";

    $sticky_score_tally .= "<section class=\"row small\">";
    $sticky_score_tally .= "<div class=\"col-10\">";
    $sticky_score_tally .= "<i id=\"score-icon-flavor-status\" class=\"fa fa-fw text-danger fa-times-circle me-1\"></i>".truncate($label_flavor,"10","");
    $sticky_score_tally .= "</div>";
    $sticky_score_tally .= "<div class=\"col-2 text-end\">";
    $sticky_score_tally .= "<span id=\"score-flavor-status\"></span>";
    $sticky_score_tally .= "</div>";
    $sticky_score_tally .= "</section>";

    if ($beer) {
      $sticky_score_tally .= "<section class=\"row small\">";
      $sticky_score_tally .= "<div class=\"col-10\">";
      $sticky_score_tally .= "<i id=\"score-icon-mouthfeel-status\" class=\"fa fa-fw text-danger fa-times-circle me-1\"></i>".truncate($label_mouthfeel,"10","");
      $sticky_score_tally .= "</div>";
      $sticky_score_tally .= "<div class=\"col-2 text-end\">";
      $sticky_score_tally .= "<span id=\"score-mouthfeel-status\"></span>";
      $sticky_score_tally .= "</div>";
      $sticky_score_tally .= "</section>";
    }

    $sticky_score_tally .= "<section class=\"row small\">";
    $sticky_score_tally .= "<div class=\"col-10\">";
    $sticky_score_tally .= "<i id=\"score-icon-overall-status\" class=\"fa fa-fw text-danger fa-times-circle me-1\"></i>".truncate($label_overall_impression,"10","");
    $sticky_score_tally .= "</div>";
    $sticky_score_tally .= "<div class=\"col-2 text-end\">";
    $sticky_score_tally .= "<span id=\"score-overall-status\"></span>";
    $sticky_score_tally .= "</div>";
    $sticky_score_tally .= "</section>";

  }

  // Elapsed time
  $sticky_score_tally .= "<p class=\"mt-3 mb-0 pb-1\"><span id=\"elapsed-time-p\"><i class=\"fa fa-clock\"></i> <strong>".$label_elapsed_time.": <span id=\"elapsed-time\"></span></strong></span><br><small id=\"session-end-eval-p\">".$label_auto_log_out." <span id=\"session-end-eval\"></span></small><br><small id=\"eval-autosave-status\" class=\"text-muted\"></small>";
  $sticky_score_tally .= "</p>";

  // 15-minute courtesy warning.
  $sticky_score_tally .= "<p class=\"mt-3 mb-0 pb-1\" id=\"courtesy-alert-warning-15\">";
  $sticky_score_tally .= "<span id=\"courtesy-alert-warning-15-header\"><i class=\"fa fa-exclamation-circle\"></i> <strong>".$label_please_note."<span id=\"elapsed-time\"></strong></span>";
  $sticky_score_tally .= "<br>";
  $sticky_score_tally .= "<small>";
  $sticky_score_tally .= $evaluation_info_071;
  $sticky_score_tally .= "</small>";
  $sticky_score_tally .= "</p>";

  // Show score range status if scored previously. Consensus is no longer set on
  // this screen - it's reconciled with the other judge(s) on the waiting/reconcile
  // screen after submission.
  if ($scored_previously) {
    $sticky_score_tally .= "<p class=\"mt-3 mb-0 pb-1 lh-sm\">";
    $sticky_score_tally .= "<i id=\"scoring-guide-status-icon\" class=\"fa fa-chevron-circle-right\"></i> <span id=\"scoring-guide-status-msg\"><strong>".$label_score_range_status."</strong></span>";
    if (!empty($other_judge_scores)) $sticky_score_tally .= "<br><small>".$other_judge_scores."</small>";
    $sticky_score_tally .= "</p>";
  }


  $sticky_score_tally .= "</section>"; // end scoring-guide-status

  $sticky_score_tally .= "</div>\n\n"; // end sticky-score

  // Style Guidelines Flyout
  // $style_concat already set above, e.g. "1A", "M1A", "C1A"
  $guidelines_toggle_btn = '<button id="guidelines-tab-btn" type="button" class="btn d-print-none" data-bs-toggle="offcanvas" data-bs-target="#style-guidelines-offcanvas" aria-controls="style-guidelines-offcanvas" title="Style Guidelines" aria-label="Style Guidelines"><i class="fa fa-book"></i><span class="guidelines-tab-label">Style<br>Guide</span></button>';

  $guidelines_offcanvas_html  = "\n<!-- Style Guidelines Offcanvas -->\n";
  // Desktop: offcanvas-start (side). Mobile: swapped to offcanvas-bottom via JS.
  // backdrop=false on desktop so judges can keep scoring; mobile uses a light backdrop.
  $guidelines_offcanvas_html .= '<div class="offcanvas offcanvas-start d-print-none" data-bs-scroll="true" data-bs-backdrop="false" tabindex="-1" id="style-guidelines-offcanvas" aria-labelledby="style-guidelines-offcanvas-label">';
  // Collapse control — side chevron on desktop, top handle on mobile
  $guidelines_offcanvas_html .= '<button type="button" id="guidelines-collapse-tab" class="guidelines-collapse-tab" data-bs-dismiss="offcanvas" title="Hide style guidelines" aria-label="Hide style guidelines"><i class="fa fa-chevron-left guidelines-collapse-icon-side"></i><i class="fa fa-chevron-down guidelines-collapse-icon-bottom"></i></button>';
  $guidelines_offcanvas_html .= '<div class="offcanvas-header border-bottom">';
  $guidelines_offcanvas_html .= '<div class="w-100">';
  $guidelines_offcanvas_html .= '<h5 class="offcanvas-title mb-2" id="style-guidelines-offcanvas-label"><i class="fa fa-book me-2 text-teal"></i>Style Guidelines</h5>';
  $guidelines_offcanvas_html .= '<div class="d-flex gap-2 align-items-center">';
  $guidelines_offcanvas_html .= '<div class="flex-grow-1">';
  $guidelines_offcanvas_html .= '<select id="guidelines-style-select" placeholder="Search styles&hellip;"></select>';
  $guidelines_offcanvas_html .= '</div>';
  $guidelines_offcanvas_html .= '<button id="guidelines-reset-btn" type="button" class="btn btn-sm btn-outline-teal flex-shrink-0" title="Return to entry style"><i class="fa fa-undo"></i></button>';
  $guidelines_offcanvas_html .= '</div>';
  $guidelines_offcanvas_html .= '</div>';
  $guidelines_offcanvas_html .= '</div>';
  $guidelines_offcanvas_html .= '<div class="offcanvas-body" id="guidelines-offcanvas-body">';
  $guidelines_offcanvas_html .= '<div id="guidelines-loading" class="text-center py-5"><i class="fa fa-spinner fa-spin fa-2x text-secondary"></i></div>';
  $guidelines_offcanvas_html .= '<div id="guidelines-content" class="d-none"></div>';
  $guidelines_offcanvas_html .= '<div id="guidelines-error" class="d-none alert alert-warning"><i class="fa fa-exclamation-triangle me-2"></i>Guidelines not available for this style.</div>';
  $guidelines_offcanvas_html .= '</div>';
  $guidelines_offcanvas_html .= '</div>';
  $guidelines_offcanvas_html .= '<script>';
  $guidelines_offcanvas_html .= 'var guidelinesEntryCode = "'.htmlspecialchars($style_concat, ENT_QUOTES, 'UTF-8').'";';
  $guidelines_offcanvas_html .= 'var guidelinesAjaxUrl = "'.htmlspecialchars($ajax_url, ENT_QUOTES, 'UTF-8').'style_guidelines.ajax.php";';
  $guidelines_offcanvas_html .= '</script>';

}



else {
  $header_elements .= sprintf("<p class=\"alert alert-danger\"><strong><i class=\"fa fa-exclamation-triangle\"></i> %s</strong> %s</p>",$evaluation_info_013,$evaluation_info_014); 
  $entry_info_html .= "<form class=\"hide-loader-form-submit form-horizontal needs-validation\" name=\"form1\" role=\"form\" action=\"".$base_url."index.php?section=evaluation&amp;go=scoresheet&amp;action=add\" method=\"post\" novalidate>";
  $entry_info_html .= "<div class=\"mb-3 row\">";
  $entry_info_html .= "<div class=\"col-3\">";
  $entry_info_html .= sprintf("<label for=\"entry_number\" class=\"form-label\"><strong>%s</strong></label>",$label_entry_number);
  $entry_info_html .= "</div>";
  $entry_info_html .= "<div class=\"col-4\">";
  $entry_info_html .= "<input id=\"entry-number-input\" name=\"entry_number\" type=\"text\" pattern=\".{6,6}\" maxlength=\"6\" class=\"form-control small\" data-error=\"".$evaluation_info_015."\" required>";
  $entry_info_html .= "<div class=\"help-block small invalid-feedback text-danger\">".$evaluation_info_015."</div>";
  $entry_info_html .= "</div>";
  $entry_info_html .= "<div class=\"col-3\">";
  $entry_info_html .= sprintf("<button class=\"btn btn-sm btn-success\" type=\"submit\">%s</button>",$label_go);
  $entry_info_html .= "</div>";
  $entry_info_html .= "</div>";
  if (isset($_POST['participants'])) $entry_info_html .= "<input type=\"hidden\" name=\"participants\" value=\"".$_POST['participants']."\">";
  $entry_info_html .= "</form>";
  $scoresheet_version = "";
}

// Sub-nav Buttons
$eval_nav_buttons .= "<div class=\"d-print-none mb-3\">";
if ($eval_source == 0) $eval_nav_buttons .= "<a class=\"btn btn-dark me-2\" href=\"".$base_url."index.php?section=admin&amp;go=evaluation&amp;filter=default&amp;view=admin\"><i class=\"fa fa-chevron-circle-left me-2\"></i>".$label_admin.": ".$label_evaluations."</a>";
else $eval_nav_buttons .= "<button class=\"btn btn-dark\" data-bs-toggle=\"modal\" data-bs-target=\"#unsaved-modal\"><i class=\"fa fa-chevron-circle-left me-2\"></i>".$label_judging_dashboard."</button>";
$eval_nav_buttons .= "</div>";
if ($eval_prevent_edit) $header_elements .= sprintf("<p>%s</p>",$header_text_104);
?>
<!-- Unsaved Data Modal -->
<div id="unsaved-modal" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="unsaved-modal-label">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title" id="unsaved-modal-label">Caution &ndash; Possible Data Loss</h4>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <?php echo sprintf("<p>%s</p><p>%s</p><p>%s</p>",$evaluation_info_073,$evaluation_info_074,$evaluation_info_075); ?>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-dark" data-bs-dismiss="modal"><?php echo $label_close; ?></button>
        <a class="btn btn-primary" onclick="bcoemClearScoresheetDraftStorage();" href="<?php echo build_public_url("evaluation","default","default","default",$sef,$base_url,"default"); ?>"><?php echo $label_judging_dashboard; ?></a>
      </div>
    </div><!-- /.modal-content -->
  </div><!-- /.modal-dialog modal-lg -->
</div><!-- /.modal -->
<!-- Load Bootstrap Slider -->
<!-- https://github.com/seiyria/bootstrap-slider -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-slider/11.0.2/bootstrap-slider.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-slider/11.0.2/css/bootstrap-slider.min.css" />
<script>
var judgeScores = <?php echo json_encode($judge_scores); ?>;
var score_range = <?php echo $score_range; ?>;
var score_problematic = "<?php echo $label_problematic; ?>";
var score_fair = "<?php echo $label_fair; ?>";
var score_good = "<?php echo $label_good; ?>";
var score_very_good = "<?php echo $label_very_good; ?>";
var score_excellent = "<?php echo $label_excellent; ?>";
var score_outstanding = "<?php echo $label_outstanding; ?>";
var score_range_caution = "<?php echo $label_score_range_caution; ?>";
var score_range_caution_text = "<?php echo $evaluation_info_046; ?>";
var score_range_caution_output = "<span class=\"text-danger\"><strong>" + score_range_caution + "</strong><br><small><strong>" + score_range_caution_text + " " + score_range + ".</strong></small></span>";
var score_range_ok = "<?php echo $label_score_range_ok; ?>";
var score_range_ok_text = "<?php echo $evaluation_info_047; ?>";
var score_range_ok_output = "<span><strong class=\"text-success\">" + score_range_ok + "</strong><br><small><strong class=\"text-success-emphasis\">" + score_range_ok_text + "</strong></small></span>";
</script>
<script src="<?php echo $js_eval_url; ?>"></script>
<script>
$(document).ready(function() {

  $("#courtesy-alert-warning-15").hide();
  $("#warning-indicator-icon").hide();
  $("#score-icon-aroma").hide();
  $("#score-icon-appearance").hide();
  $("#score-icon-flavor").hide();
  $("#score-icon-mouthfeel").hide();
  $("#score-icon-overall").hide();
  $("#appearance-icon-aroma").hide();
  $("#flavor-icon-aroma").hide();
  $("#mouthfeel-icon-aroma").hide();
  $("#overall-icon-aroma").hide();
  
  <?php if ($action == "edit") { ?>
  displayCalc(<?php echo $eval_score; ?>);
  checkScoreRange(<?php echo $eval_score; ?>,judgeScores,score_range,0);
  <?php }?>
  
  $('#show-hide-status-btn').click(function(){
      $('#toggle-icon').toggleClass('fa-chevron-circle-up fa-chevron-circle-down');
  });

  $('#show-hide-aroma-btn').click(function(){
      $('#toggle-icon-aroma').toggleClass('fa-chevron-circle-up fa-chevron-circle-down');
  });

  $('#show-hide-appearance-btn').click(function(){
      $('#toggle-icon-appearance').toggleClass('fa-chevron-circle-up fa-chevron-circle-down');
  });

  $('#show-hide-flavor-btn').click(function(){
      $('#toggle-icon-flavor').toggleClass('fa-chevron-circle-up fa-chevron-circle-down');
  });

  $('#show-hide-mouthfeel-btn').click(function(){
      $('#toggle-icon-mouthfeel').toggleClass('fa-chevron-circle-up fa-chevron-circle-down');
  });

  $('#show-hide-overall-btn').click(function(){
      $('#toggle-icon-overall').toggleClass('fa-chevron-circle-up fa-chevron-circle-down');
  });

  $('#show-hide-flaws-btn').click(function(){
      $('#toggle-icon-flaws').toggleClass('fa-chevron-circle-up fa-chevron-circle-down');
  });

  $('#show-hide-entry-info-btn').click(function(){
      $('#toggle-icon-entry-info').toggleClass('fa-chevron-circle-up fa-chevron-circle-down');
  });

  /* ---- Style Guidelines Flyout ---- */
  if (typeof guidelinesAjaxUrl !== 'undefined') {

    // Reserve left gutter for the open tab so it never covers the scoresheet
    document.body.classList.add('guidelines-flyout-available');

    var guidelinesTomSelect = null;
    var guidelinesLoaded = false;
    // Style currently shown in the guidelines panel (starts as the entry being judged)
    var guidelinesCurrentCode = (typeof guidelinesEntryCode !== 'undefined') ? guidelinesEntryCode : '';
    var guidelinesMobileMq = window.matchMedia('(max-width: 991.98px)');

    function syncGuidelinesOffcanvasPlacement() {
      var el = document.getElementById('style-guidelines-offcanvas');
      if (!el || el.classList.contains('show')) return; // don't swap while open
      var mobile = guidelinesMobileMq.matches;
      el.classList.toggle('offcanvas-start', !mobile);
      el.classList.toggle('offcanvas-bottom', mobile);
      el.setAttribute('data-bs-backdrop', mobile ? 'true' : 'false');
      document.body.classList.toggle('guidelines-flyout-mobile', mobile);
    }
    syncGuidelinesOffcanvasPlacement();
    if (guidelinesMobileMq.addEventListener) guidelinesMobileMq.addEventListener('change', syncGuidelinesOffcanvasPlacement);
    else if (guidelinesMobileMq.addListener) guidelinesMobileMq.addListener(syncGuidelinesOffcanvasPlacement);

    // Section rendering order and labels
    var guidelinesSections = [
      { key: 'overall_impression',      label: 'Overall Impression' },
      { key: 'description',             label: 'Description' },
      { key: 'aroma',                   label: 'Aroma' },
      { key: 'aroma_and_flavor',        label: 'Aroma &amp; Flavor' },
      { key: 'appearance',              label: 'Appearance' },
      { key: 'flavor',                  label: 'Flavor' },
      { key: 'mouthfeel',              label: 'Mouthfeel' },
      { key: 'comments',               label: 'Comments' },
      { key: 'history',                label: 'History' },
      { key: 'characteristic_ingredients', label: 'Characteristic Ingredients' },
      { key: 'style_comparison',       label: 'Style Comparison' },
      { key: 'entry_instructions',     label: 'Entry Instructions' },
      { key: 'vital_statistics',       label: 'Vital Statistics' },
      { key: 'varieties',              label: 'Varieties' },
      { key: 'commercial_examples',    label: 'Commercial Examples' },
      { key: 'tags',                   label: 'Tags' },
    ];

    function escHtml(str) {
      if (!str) return '';
      return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }

    // Vital statistics come from BJCP data as a single run-on sentence, e.g.
    // "OG: 1.044 – 1.060 IBUs: 20 – 40 FG: 1.012 – 1.024 SRM: 30 – 40 ABV: 4.0 – 6.0%".
    // Break it apart into a label/value grid so each stat is on its own line.
    function formatVitalStatistics(str) {
      var labelPattern = /(OG|FG|IBUs?|SRM|ABV|Color|Strength classifications)\s*:/g;
      var matches = [];
      var match;
      while ((match = labelPattern.exec(str)) !== null) {
        matches.push({ label: match[1], index: match.index, valueStart: labelPattern.lastIndex });
      }

      if (!matches.length) return '<div class="guidelines-section-text">' + escHtml(str) + '</div>';

      var html = '<dl class="guidelines-vitals-grid mb-0">';
      matches.forEach(function(m, i) {
        var valueEnd = (i + 1 < matches.length) ? matches[i + 1].index : str.length;
        var value = str.slice(m.valueStart, valueEnd).trim();
        html += '<dt>' + escHtml(m.label) + '</dt><dd>' + escHtml(value) + '</dd>';
      });
      html += '</dl>';

      return html;
    }

    function renderGuidelineStyle(styleData) {
      var sections = styleData.sections || {};
      var typeLabel = styleData.type ? styleData.type.charAt(0).toUpperCase() + styleData.type.slice(1) : '';
      var guideLabel = styleData.guide || '';
      var html = '';

      html += '<div class="guidelines-style-title">' + escHtml(styleData.code) + ' &ndash; ' + escHtml(styleData.name) + '</div>';
      html += '<div class="guidelines-category-label">' + escHtml(styleData.category) + ' &bull; ' + typeLabel + ' &bull; ' + escHtml(guideLabel) + '</div>';

      if (styleData.category_description) {
        html += '<div class="mb-2">';
        html += '<a class="guidelines-intro-toggle text-decoration-none small" data-bs-toggle="collapse" href="#guidelines-category-intro" role="button" aria-expanded="false" aria-controls="guidelines-category-intro">';
        html += '<i class="fa fa-chevron-right me-1 guidelines-intro-chevron"></i>Category introduction';
        html += '</a>';
        html += '<div class="collapse" id="guidelines-category-intro">';
        html += '<div class="alert alert-teal py-2 px-3 mt-2 mb-0" style="font-size:.82rem;">' + escHtml(styleData.category_description) + '</div>';
        html += '</div>';
        html += '</div>';
      }

      guidelinesSections.forEach(function(s) {
        var val = sections[s.key];
        if (!val) return;
        if (s.key === 'vital_statistics') {
          html += '<div class="guidelines-section-heading">' + s.label + '</div>';
          html += formatVitalStatistics(val);
        } else if (s.key === 'commercial_examples') {
          html += '<div class="guidelines-section-heading">' + s.label + '</div>';
          html += '<div class="guidelines-examples guidelines-section-text">' + escHtml(val) + '</div>';
        } else if (s.key === 'tags') {
          html += '<div class="guidelines-tags mt-2"><i class="fa fa-tag me-1"></i>' + escHtml(val) + '</div>';
        } else {
          html += '<div class="guidelines-section-heading">' + s.label + '</div>';
          html += '<div class="guidelines-section-text">' + escHtml(val) + '</div>';
        }
      });

      return html;
    }

    function showGuidelinesLoading() {
      $('#guidelines-loading').removeClass('d-none');
      $('#guidelines-content').addClass('d-none').html('');
      $('#guidelines-error').addClass('d-none');
    }

    function showGuidelinesContent(html) {
      $('#guidelines-loading').addClass('d-none');
      $('#guidelines-content').removeClass('d-none').html(html);
      $('#guidelines-error').addClass('d-none');
    }

    function showGuidelinesError() {
      $('#guidelines-loading').addClass('d-none');
      $('#guidelines-content').addClass('d-none').html('');
      $('#guidelines-error').removeClass('d-none');
    }

    function loadGuidelinesStyle(code) {
      showGuidelinesLoading();
      $.ajax({
        url: guidelinesAjaxUrl,
        method: 'GET',
        data: { action: 'style', code: code },
        dataType: 'json',
        success: function(resp) {
          if (resp && resp.status == 1 && resp.data) {
            showGuidelinesContent(renderGuidelineStyle(resp.data));
          } else {
            showGuidelinesError();
          }
        },
        error: function() { showGuidelinesError(); }
      });
    }

    function initGuidelinesTomSelect(indexData) {
      // Group options by type
      var optGroups = [
        { value: 'beer',  label: 'Beer (BJCP 2021)' },
        { value: 'mead',  label: 'Mead (BJCP 2015)' },
        { value: 'cider', label: 'Cider &amp; Perry (BJCP 2025)' },
      ];
      var options = indexData.map(function(s) {
        return { value: s.code, text: s.code + ' \u2013 ' + s.name, optgroup: s.type };
      });

      if (guidelinesTomSelect) {
        guidelinesTomSelect.destroy();
      }

      if (!guidelinesCurrentCode) guidelinesCurrentCode = guidelinesEntryCode || '';
      var guidelinesBlurTimer = null;

      function clearGuidelinesSearchBox(ts) {
        if (!ts) return;
        if (guidelinesBlurTimer) {
          clearTimeout(guidelinesBlurTimer);
          guidelinesBlurTimer = null;
        }
        if (ts.items.length) {
          guidelinesCurrentCode = ts.getValue() || guidelinesCurrentCode;
          ts.clear(true);
        }
        ts.setTextboxValue('');
        ts.refreshOptions(false);
      }

      function positionGuidelinesDropdown(ts) {
        if (!ts || !ts.dropdown || !ts.control) return;
        var rect = ts.control.getBoundingClientRect();
        var available = window.innerHeight - rect.bottom - 12;
        // Keep a compact scrollable list (all 136 styles are loaded; just not all shown at once)
        var maxH = Math.min(320, Math.max(180, available));

        ts.dropdown.classList.add('guidelines-style-dropdown');
        ts.dropdown.style.position = 'fixed';
        ts.dropdown.style.top = Math.round(rect.bottom) + 'px';
        ts.dropdown.style.left = Math.round(rect.left) + 'px';
        ts.dropdown.style.width = Math.round(rect.width) + 'px';
        ts.dropdown.style.height = maxH + 'px';
        ts.dropdown.style.maxHeight = maxH + 'px';
        ts.dropdown.style.overflow = 'hidden';
        ts.dropdown.style.zIndex = '1060';
        ts.dropdown.style.display = 'flex';
        ts.dropdown.style.flexDirection = 'column';

        var content = ts.dropdown_content || ts.dropdown.querySelector('.ts-dropdown-content');
        if (content) {
          content.style.flex = '1 1 auto';
          content.style.height = maxH + 'px';
          content.style.maxHeight = maxH + 'px';
          content.style.overflowY = 'scroll';
          content.style.overflowX = 'hidden';
          content.style.webkitOverflowScrolling = 'touch';
        }
      }

      function getGuidelinesDropdownScroller(ts) {
        return ts.dropdown_content || (ts.dropdown && ts.dropdown.querySelector('.ts-dropdown-content')) || ts.dropdown;
      }

      function scrollGuidelinesDropdownToCurrent(ts) {
        // Center on the style currently shown in the panel (not always the entry style)
        var code = guidelinesCurrentCode || guidelinesEntryCode;
        if (!ts || !code) return;
        if (ts.control_input && ts.control_input.value) return; // don't fight an active search filter

        var opt = ts.getOption(code);
        if (!opt) return;

        // Avoid closing the menu: programmatic scroll can fire window scroll listeners
        ts._guidelinesIgnoreScrollClose = true;

        try { ts.setActiveOption(opt, false); } catch (e) { ts.setActiveOption(opt); }

        var scroller = getGuidelinesDropdownScroller(ts);
        if (scroller) {
          var optRect = opt.getBoundingClientRect();
          var scrollerRect = scroller.getBoundingClientRect();
          var currentOffset = optRect.top - scrollerRect.top + scroller.scrollTop;
          var target = currentOffset - (scroller.clientHeight / 2) + (opt.offsetHeight / 2);
          scroller.scrollTop = Math.max(0, Math.min(target, scroller.scrollHeight - scroller.clientHeight));
        }

        var prev = ts.dropdown.querySelectorAll('.guidelines-current-option');
        for (var i = 0; i < prev.length; i++) prev[i].classList.remove('guidelines-current-option');
        opt.classList.add('guidelines-current-option');

        setTimeout(function() { ts._guidelinesIgnoreScrollClose = false; }, 50);
      }

      function bindGuidelinesDropdownScrollLock(ts) {
        // Scroll only inside the options list; any page/panel scroll closes the menu
        // so it never drifts away from the control.
        if (ts._guidelinesWheelTrap) return;

        ts._guidelinesWheelTrap = function(e) {
          // Keep wheel inside the options list so categories past 16D are reachable
          var scroller = getGuidelinesDropdownScroller(ts);
          if (scroller) scroller.scrollTop += e.deltaY;
          e.preventDefault();
          e.stopPropagation();
        };
        ts._guidelinesOutsideScrollClose = function(e) {
          if (!ts.isOpen) return;
          if (ts._guidelinesIgnoreScrollClose) return;
          if (ts.dropdown && (ts.dropdown === e.target || ts.dropdown.contains(e.target))) return;
          if (ts.control && (ts.control === e.target || ts.control.contains(e.target))) return;
          ts.close();
        };

        ts.dropdown.addEventListener('wheel', ts._guidelinesWheelTrap, { passive: false });
        window.addEventListener('wheel', ts._guidelinesOutsideScrollClose, { capture: true, passive: true });
        window.addEventListener('touchmove', ts._guidelinesOutsideScrollClose, { capture: true, passive: true });
        window.addEventListener('scroll', ts._guidelinesOutsideScrollClose, { capture: true, passive: true });
      }

      function unbindGuidelinesDropdownScrollLock(ts) {
        if (!ts) return;
        if (ts._guidelinesWheelTrap && ts.dropdown) {
          ts.dropdown.removeEventListener('wheel', ts._guidelinesWheelTrap);
        }
        if (ts._guidelinesOutsideScrollClose) {
          window.removeEventListener('wheel', ts._guidelinesOutsideScrollClose, { capture: true });
          window.removeEventListener('touchmove', ts._guidelinesOutsideScrollClose, { capture: true });
          window.removeEventListener('scroll', ts._guidelinesOutsideScrollClose, { capture: true });
        }
        ts._guidelinesWheelTrap = null;
        ts._guidelinesOutsideScrollClose = null;
      }

      guidelinesTomSelect = new TomSelect('#guidelines-style-select', {
        options: options,
        optgroups: optGroups,
        optgroupField: 'optgroup',
        searchField: ['text'],
        maxItems: 1,
        // Default is 50 — that stopped the list at 16D (the 50th style)
        maxOptions: null,
        placeholder: 'Search or select a style\u2026',
        allowEmptyOption: false,
        selectOnTab: true,
        closeAfterSelect: true,
        hideSelected: false,
        // Render on body so the panel doesn't clip; position with fixed coords below
        dropdownParent: 'body',
        onChange: function(val) {
          if (val) {
            guidelinesCurrentCode = val;
            loadGuidelinesStyle(val);
          }
        },
        onFocus: function() {
          clearGuidelinesSearchBox(this);
        },
        onDropdownOpen: function() {
          var self = this;
          // Clear again on open — focus alone can miss a re-click while still focused
          clearGuidelinesSearchBox(self);
          positionGuidelinesDropdown(self);
          bindGuidelinesDropdownScrollLock(self);
          // Center on the style currently shown in the guidelines panel
          requestAnimationFrame(function() {
            positionGuidelinesDropdown(self);
            scrollGuidelinesDropdownToCurrent(self);
            requestAnimationFrame(function() {
              scrollGuidelinesDropdownToCurrent(self);
            });
          });
        },
        onDropdownClose: function() {
          unbindGuidelinesDropdownScrollLock(this);
        },
        onType: function() {
          positionGuidelinesDropdown(this);
          // Only re-center when the search box is cleared back to browsing mode
          if (!this.control_input.value) scrollGuidelinesDropdownToCurrent(this);
        },
        onBlur: function() {
          // Restore the viewed style label when leaving — cancel if they reopen quickly
          var self = this;
          if (guidelinesBlurTimer) clearTimeout(guidelinesBlurTimer);
          guidelinesBlurTimer = setTimeout(function() {
            guidelinesBlurTimer = null;
            if (self.isFocused || self.isOpen) return;
            if (!self.getValue() && guidelinesCurrentCode) {
              self.setValue(guidelinesCurrentCode, true);
            }
          }, 150);
        },
        render: {
          option: function(data, escape) {
            return '<div>' + escape(data.text) + '</div>';
          },
          item: function(data, escape) {
            return '<div>' + escape(data.text) + '</div>';
          }
        }
      });

      window.addEventListener('resize', function() {
        if (guidelinesTomSelect && guidelinesTomSelect.isOpen) positionGuidelinesDropdown(guidelinesTomSelect);
      });
    }

    // Load index and init Tom Select on first open; push scoresheet on desktop
    var guidelinesOffcanvasEl = document.getElementById('style-guidelines-offcanvas');
    if (guidelinesOffcanvasEl) {
      guidelinesOffcanvasEl.addEventListener('show.bs.offcanvas', function() {
        document.body.classList.add('guidelines-panel-open');
        if (!guidelinesLoaded) {
          guidelinesLoaded = true;
          $.ajax({
            url: guidelinesAjaxUrl,
            method: 'GET',
            data: { action: 'index' },
            dataType: 'json',
            success: function(resp) {
              if (resp && resp.status == 1 && resp.data) {
                initGuidelinesTomSelect(resp.data);
                // Set to entry style
                if (guidelinesEntryCode && guidelinesTomSelect) {
                  guidelinesTomSelect.setValue(guidelinesEntryCode, true);
                }
                loadGuidelinesStyle(guidelinesEntryCode);
              } else {
                showGuidelinesError();
              }
            },
            error: function() { showGuidelinesError(); }
          });
        }
      });
      // Remove at hide start so the scoresheet expands while the panel slides away
      guidelinesOffcanvasEl.addEventListener('hide.bs.offcanvas', function() {
        document.body.classList.remove('guidelines-panel-open');
      });
    }

    // Reset button: back to entry style
    $('#guidelines-reset-btn').on('click', function() {
      guidelinesCurrentCode = guidelinesEntryCode;
      if (guidelinesTomSelect) guidelinesTomSelect.setValue(guidelinesEntryCode, true);
      loadGuidelinesStyle(guidelinesEntryCode);
    });

  } /* end guidelines flyout */

  $("#evalAromaScore").on('change input keyup', function() {

    if ($(this).val() == "") {
      $('#score-icon-aroma-status').attr('class', 'fa fa-fw fa-times-circle text-danger me-1');
      $("#score-icon-aroma").fadeOut('fast');
      $("#score-aroma-status").html("");
    }

    if ($(this).val() > 0) {
      $('#score-icon-aroma-status').attr('class', 'fa fa-fw fa-check-circle text-success me-1');
      $("#score-icon-aroma").fadeIn('fast');
      $("#score-aroma-status").html($(this).val());
    }   

  });

  $("#evalAppearanceScore").on('change input keyup', function() {

    if ($(this).val() == "") {
      $('#score-icon-appearance-status').attr('class', 'fa fa-fw fa-times-circle text-danger me-1');
      $("#score-icon-appearance").fadeOut('fast');
      $("#score-appearance-status").html("");
    }

    if ($(this).val() > 0) {
      $('#score-icon-appearance-status').attr('class', 'fa fa-fw fa-check-circle text-success me-1');
      $("#score-icon-appearance").fadeIn('fast');
      $("#score-appearance-status").html($(this).val());
    }

  });

  $("#evalFlavorScore").on('change input keyup', function() {

    if ($(this).val() == "") {
      $('#score-icon-flavor-status').attr('class', 'fa fa-fw fa-times-circle text-danger me-1');
      $("#score-icon-flavor").fadeOut('fast');
      $("#score-flavor-status").html("");
    }

    if ($(this).val() > 0) {
      $('#score-icon-flavor-status').attr('class', 'fa fa-fw fa-check-circle text-success me-1');
      $("#score-icon-flavor").fadeIn('fast');
      $("#score-flavor-status").html($(this).val());
    }

  });

  $("#evalMouthfeelScore").on('change input keyup', function() {

    if ($(this).val() == "") {
      $('#score-icon-mouthfeel-status').attr('class', 'fa fa-fw fa-times-circle text-danger me-1');
      $("#score-icon-mouthfeel").fadeOut('fast');
      $("#score-mouthfeel-status").html("");
    }

    if ($(this).val() > 0) {
      $('#score-icon-mouthfeel-status').attr('class', 'fa fa-fw fa-check-circle text-success me-1');
      $("#score-icon-mouthfeel").fadeIn('fast');
      $("#score-mouthfeel-status").html($(this).val());
    }

  });

  $("#evalOverallScore").on('change input keyup', function() {

    if ($(this).val() == "") {
      $('#score-icon-overall-status').attr('class', 'fa fa-fw fa-times-circle text-danger me-1');
      $("#score-icon-overall").fadeOut('fast');
      $("#score-overall-status").html("");
    }

    if ($(this).val() > 0) {
      $('#score-icon-overall-status').attr('class', 'fa fa-fw fa-check-circle text-success me-1');
      $("#score-icon-overall").fadeIn('fast');
      $("#score-overall-status").html($(this).val());
    }

  });

});
</script>

<style type="text/css">

.scoring-guide-bottom-text {
  font-weight: bold;
}

#sticky-score {
  position: -webkit-sticky;
  position: sticky;
  top: 70px;
  z-index: 999;
  width: 250px;
  min-width: 250px;
  max-width: 300px;
  /* font-family: initial !important; */
  font-size: .9em;
}

.section-heading {
  padding-top: 35px;
  margin-top: 35px;
  border-top: 3px solid #cccccc;
}

/* Style Guidelines Flyout */
:root {
  --guidelines-panel-width: 400px;
  --guidelines-tab-width: 46px;
  --guidelines-tab-gutter: 12px; /* air between the open tab and the form */
  --guidelines-collapse-tab-width: 28px;
  --guidelines-nav-offset: 56px;
  --guidelines-footer-offset: 56px;
}

/*
 * Gutter the scoresheet only — not body — so header backgrounds stay full-bleed.
 * On wide screens the open tab sits in the container's natural side margin, so
 * we only add a closed-state gutter when that margin is too small.
 */
body.guidelines-flyout-available #main-content {
  transition: padding-left .3s ease;
}
body.guidelines-flyout-available #sticky-home {
  transition: margin-left .3s ease;
}

/* Tablet/smaller desktop: side tab needs a form gutter. Mobile uses a FAB (no gutter). */
@media (min-width: 992px) and (max-width: 1411.98px) {
  body.guidelines-flyout-available:not(.guidelines-panel-open) #main-content {
    padding-left: calc(var(--guidelines-tab-width) + var(--guidelines-tab-gutter));
  }
  body.guidelines-flyout-available:not(.guidelines-panel-open) #sticky-home {
    margin-left: calc(var(--guidelines-tab-width) + var(--guidelines-tab-gutter));
  }
}

/* Desktop / tablet: left-edge Style Guide tab */
#guidelines-tab-btn {
  position: fixed;
  left: 0;
  top: 50%;
  transform: translateY(-50%);
  z-index: 1040;
  background: #004D40;
  color: #fff;
  border-radius: 0 6px 6px 0;
  padding: 10px 8px;
  font-size: .75rem;
  line-height: 1.2;
  text-align: center;
  width: var(--guidelines-tab-width);
  writing-mode: horizontal-tb;
  box-shadow: 2px 0 8px rgba(0,0,0,.25);
  border: none;
}
#guidelines-tab-btn:hover,
#guidelines-tab-btn:focus {
  background: #00695C;
  color: #fff;
}
#guidelines-tab-btn .guidelines-tab-label {
  display: block;
  margin-top: 5px;
  font-size: .65rem;
  line-height: 1.1;
}
.guidelines-collapse-icon-bottom {
  display: none;
}

/* Sit between fixed nav and footer — do not cover site chrome */
#style-guidelines-offcanvas {
  top: var(--guidelines-nav-offset);
  bottom: var(--guidelines-footer-offset);
  height: auto;
  width: var(--guidelines-panel-width);
  max-width: 92vw;
  border-right: 1px solid rgba(0,0,0,.12);
  border-top: 1px solid rgba(0,0,0,.08);
  border-bottom: 1px solid rgba(0,0,0,.08);
  box-shadow: 2px 0 12px rgba(0,0,0,.08);
  overflow: visible; /* allow collapse tab to peek past the edge */
}
#style-guidelines-offcanvas .offcanvas-header {
  overflow: visible; /* don't clip Tom Select */
}
#style-guidelines-offcanvas .offcanvas-body {
  overflow-x: hidden;
  overflow-y: auto;
  max-height: none;
}
/* Tom Select menu is attached to body — compact + scrollable (all styles are in the list) */
body > .ts-dropdown.guidelines-style-dropdown {
  z-index: 1060;
  overflow: hidden !important;
}
body > .ts-dropdown.guidelines-style-dropdown .ts-dropdown-content {
  overflow-y: scroll !important;
  overscroll-behavior: contain;
}
body > .ts-dropdown .option.guidelines-current-option {
  font-weight: 600;
  background-color: #E0F2F1;
  color: #004D40;
}

/* Collapse tab on the panel's right edge */
.guidelines-collapse-tab {
  position: absolute;
  top: 50%;
  right: -28px;
  transform: translateY(-50%);
  z-index: 1046;
  width: 28px;
  height: 64px;
  padding: 0;
  border: 1px solid rgba(0,0,0,.12);
  border-left: none;
  border-radius: 0 6px 6px 0;
  background: #004D40;
  color: #fff;
  box-shadow: 2px 0 8px rgba(0,0,0,.18);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}
.guidelines-collapse-tab:hover,
.guidelines-collapse-tab:focus {
  background: #00695C;
  color: #fff;
  outline: none;
}

/*
 * Desktop: always reserve the full panel + collapse-tab width so the flyout
 * can never sit over the form, regardless of how the container is centered.
 * (A "only pad by the overlap" version was tried here, but 100vw-based math
 * doesn't account for the container's own padding or scrollbar width, so it
 * could still leave the collapse tab sitting on top of the form.)
 */
@media (min-width: 992px) {
  body.guidelines-panel-open #guidelines-tab-btn {
    display: none;
  }
  body.guidelines-panel-open #main-content,
  body.guidelines-panel-open #salutation > section {
    padding-left: calc(var(--guidelines-panel-width) + var(--guidelines-collapse-tab-width));
    transition: padding-left .3s ease;
  }
  body.guidelines-panel-open #sticky-home {
    margin-left: calc(var(--guidelines-panel-width) + var(--guidelines-collapse-tab-width));
  }
}

/* Mobile: FAB to open + bottom sheet that flies up */
@media (max-width: 991.98px) {
  body.guidelines-panel-open #main-content,
  body.guidelines-panel-open #sticky-home {
    padding-left: 0;
    margin-left: 0;
  }

  /*
   * Floating action button — bottom-right, stacked above #sticky-home
   * (#sticky-home is fixed at bottom:100px / right:30px in default-3.css).
   */
  #guidelines-tab-btn {
    left: auto;
    right: 22px;
    top: auto;
    bottom: calc(100px + 2.75rem + 10px);
    transform: none;
    width: 56px;
    height: 56px;
    border-radius: 50%;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    box-shadow: 0 4px 14px rgba(0,0,0,.28);
  }
  #guidelines-tab-btn .guidelines-tab-label {
    display: none;
  }
  body.guidelines-panel-open #guidelines-tab-btn {
    display: none;
  }

  /* Bottom sheet */
  #style-guidelines-offcanvas.offcanvas-bottom {
    top: auto;
    bottom: var(--guidelines-footer-offset);
    left: 0;
    right: 0;
    height: min(85vh, calc(100vh - var(--guidelines-nav-offset) - var(--guidelines-footer-offset) - 8px));
    max-height: min(85vh, calc(100vh - var(--guidelines-nav-offset) - var(--guidelines-footer-offset) - 8px));
    width: 100%;
    max-width: 100%;
    border-right: none;
    border-bottom: none;
    border-top: 1px solid rgba(0,0,0,.1);
    border-radius: 16px 16px 0 0;
    box-shadow: 0 -6px 24px rgba(0,0,0,.18);
    overflow: visible;
  }

  /* Close handle along the top edge of the sheet */
  .guidelines-collapse-tab {
    top: 8px;
    left: 50%;
    right: auto;
    transform: translateX(-50%);
    width: 48px;
    height: 28px;
    border: none;
    border-radius: 999px;
    background: #E0F2F1;
    color: #004D40;
    box-shadow: none;
  }
  .guidelines-collapse-tab:hover,
  .guidelines-collapse-tab:focus {
    background: #B2DFDB;
    color: #004D40;
  }
  .guidelines-collapse-icon-side {
    display: none;
  }
  .guidelines-collapse-icon-bottom {
    display: inline-block;
    font-size: .85rem;
  }
  #style-guidelines-offcanvas .offcanvas-header {
    padding-top: 1.75rem;
  }
}
.btn-outline-teal {
  color: #004D40;
  border-color: #004D40;
}
.btn-outline-teal:hover {
  background: #004D40;
  color: #fff;
}
.guidelines-section-heading {
  font-size: .8rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .06em;
  color: #004D40;
  border-bottom: 1px solid #B2DFDB;
  padding-bottom: 4px;
  margin-top: 1.25rem;
  margin-bottom: .4rem;
}
.guidelines-section-text {
  font-size: .88rem;
  line-height: 1.55;
  color: #212529;
}
.guidelines-vitals-grid {
  display: grid;
  grid-template-columns: max-content 1fr;
  gap: 4px 12px;
  font-size: .82rem;
  margin-top: .35rem;
}
.guidelines-vitals-grid dt {
  font-weight: 600;
  color: #004D40;
}
.guidelines-vitals-grid dd {
  margin: 0;
}
.guidelines-style-title {
  font-size: 1rem;
  font-weight: 700;
  color: #004D40;
}
.guidelines-category-label {
  font-size: .78rem;
  color: #555;
  margin-bottom: .6rem;
}
.guidelines-intro-toggle {
  color: #004D40;
  font-weight: 600;
  display: inline-flex;
  align-items: center;
}
.guidelines-intro-toggle:hover {
  color: #00695C;
}
.guidelines-intro-toggle .guidelines-intro-chevron {
  transition: transform .15s ease;
  width: 1em;
  text-align: center;
}
.guidelines-intro-toggle[aria-expanded="true"] .guidelines-intro-chevron {
  transform: rotate(90deg);
}
.guidelines-tags {
  font-size: .75rem;
  color: #666;
  margin-top: .5rem;
}
.guidelines-examples {
  font-size: .8rem;
  font-style: italic;
}
#style-guidelines-offcanvas .offcanvas-header .flex-grow-1 {
  min-width: 0; /* required for ellipsis in a flex row with the reset button */
}
#style-guidelines-offcanvas .ts-wrapper {
  min-width: 0;
  width: 100%;
}
#style-guidelines-offcanvas .ts-control {
  font-size: .85rem;
  flex-wrap: nowrap !important;
  overflow: hidden;
  align-items: center;
}
#style-guidelines-offcanvas .ts-control .item {
  display: block;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  max-width: 100%;
  line-height: 1.4;
}

</style>

<?php
$evalPos = FALSE; 
if ((isset($row_eval['evalPosition'])) && (!empty($row_eval['evalPosition']))) {
  $evalPosition = explode(",", $row_eval['evalPosition']);
  $evalPos = TRUE;
} 
echo $header_elements; 
if (!empty($scoresheet_version)) echo "<h2>".$scoresheet_version."</h2>"; 
echo $eval_nav_buttons;
if ($entry_found) echo $sticky_score_tally;
if ($entry_found && !empty($guidelines_toggle_btn)) echo $guidelines_toggle_btn;
echo $entry_info_html;
if ($entry_found && !empty($guidelines_offcanvas_html)) echo $guidelines_offcanvas_html;
if ($entry_found) {
?>

<form class="hide-loader-form-submit needs-validation" id="scoresheet-form" name="scoresheet-form" role="form" data-toggle="validator" action="<?php echo $base_url; ?>includes/process.inc.php?section=<?php echo $process_type; ?>&action=<?php echo $action; ?>&view=<?php echo $view; ?>&dbTable=<?php echo $prefix."evaluation"; if ($action == "edit") echo "&id=".$id; ?>" method="post" novalidate>
<input type="hidden" name="user_session_token" value ="<?php if (isset($_SESSION['user_session_token'])) echo htmlspecialchars($_SESSION['user_session_token'], ENT_QUOTES, 'UTF-8'); ?>">
<!-- Provide information about the judge -->
<input type="hidden" name="evalJudgeInfo" value="<?php if ($action == "add") echo $judge_id; else echo $row_eval['evalJudgeInfo']; ?>">
<!-- Type of scoresheet -->
<input type="hidden" name="evalScoresheet" value="<?php echo $judging_scoresheet; ?>">
<!-- User and Entry IDs, if applicable -->
<input type="hidden" name="uid" value="<?php echo $uid; ?>">
<input type="hidden" name="bid" value="<?php echo $uid; ?>">
<input type="hidden" name="eid" value="<?php echo $eid; ?>">
<input type="hidden" name="evalAutosaveId" id="evalAutosaveId" value="<?php echo $eval_draft_id; ?>">
<input type="hidden" name="evalTable" value="<?php echo $filter; ?>">
<!-- Brewer entered special ingredients, etc. -->
<input type="hidden" name="evalSpecialIngredients" value="<?php echo $row_entry_info['brewInfo']; ?>">
<input type="hidden" name="evalStyle" value="<?php echo $row_style['id']; ?>">
<!-- Source of eval form (judge user=1 or admin=0) -->
<input type="hidden" name="evalSource" value="<?php echo $eval_source; ?>">
<?php if ($_SESSION['jPrefsJudgeStats'] == "Y") { ?>
<!-- Seconds spent on the scoresheet, captured on submit for judge analytics -->
<input type="hidden" name="evalDurationSec" id="evalDurationSec" value="">
<?php } ?>

<div class="mb-3">
    <label class="form-label" for="evalPosition_0"><strong><?php echo $label_ordinal_position; ?></strong></label>
    <div class="row">
      <div class="col-12 col-sm-12 col-md-4 ms-0 ps-0"><input type="number" class="form-control" name="evalPosition_0" min="1" id="evalPosition_0" maxlength="3" size="30" placeholder="<?php echo $label_suggested.": ".($flight_count_info['total_flight_evals']+1); ?>" value="<?php if (($action == "edit") && ($evalPos)) { if (is_numeric($evalPosition[0])) echo $evalPosition[0]; else echo ($flight_count_info['total_flight_evals']+1); } ?>">
        <div id="ordinal-help-position" class="help-block small text-danger"><?php echo $evaluation_info_050; ?></div>
      </div> 
      <div class="col-12 col-sm-12 col-md-1 text-center text-lowercase ps-0 pe-0"><?php echo $label_of; ?></div>
      <div class="col-12 col-sm-12 col-md-4 ms-0 ps-0">
        <input type="number" class="form-control" name="evalPosition_1" min="1" id="evalPosition_1" maxlength="3" size="30" placeholder="<?php echo $label_suggested.": ".$flight_count_info['total_flight_entries']; ?>" value="<?php if (($action == "edit") && ($evalPos)) { if (is_numeric($evalPosition[1])) echo $evalPosition[1]; } ?>">
        <div id="ordinal-help-total" class="help-block small text-danger"><?php echo $evaluation_info_051; ?></div>
      </div>
    </div>
</div>

<?php if (!$nw_cider) { ?>
<div class="mb-3">
  <label class="form-label" for="evalBottle"><strong><?php echo $label_bottle_inspection; ?></strong></label>
  <div class="checkbox">
      <input class="form-check-input" type="checkbox" name="evalBottle" id="evalBottle" value="1" <?php if (($action == "edit") && ($row_eval['evalBottle'] == 1)) echo "checked"; ?>> <?php echo $evaluation_info_052; ?>
  </div>
</div>
<div class="mb-3">
  <label class="form-label" for="evalBottleNotes"><strong><?php echo $label_bottle_inspection_comments; ?></strong></label>
  <input type="text" class="form-control" name="evalBottleNotes" id="evalBottleNotes" maxlength="255" placeholder="" value="<?php if ($action == "edit") echo $row_eval['evalBottleNotes']; ?>">
</div>
<?php } ?>

<?php include (PUB.$scoresheet_form); ?>

<h3 class="section-heading mt-4 pt-4"><?php echo $label_score; ?></h3>

<?php if ((($_SESSION['jPrefsScoresheet'] == 4) || ($sort == 4)) && ($cider)) { ?>
<div class="mb-3">
  <label class="form-label" for="evalOverallScore"><strong><?php echo $label_your_score; ?></strong></label>
  <input type="number" min="5" max="50" name="evalOverallScore" id="evalOverallScore" class="form-control" placeholder="" data-error="<?php echo $evaluation_info_103; ?>" value="<?php if ($action == "edit") echo $row_eval['evalOverallScore']; ?>"required>
  <div class="help-block small"><?php echo $evaluation_info_102; ?></div>
  <div class="help-block small invalid-feedback text-danger"></div>
</div>
<?php } ?>

<!-- Scoring Guide -->
<label class="form-check-label mb-2" for="evalMiniBOS"><strong>Scoring Guide</strong></label>
<section class="alert bg-secondary-subtle mb-3">
  <div class="row small">
    <div class="col-12 col-sm-12 col-md-6">
        <div id="scoring-guide-bottom-outstanding" class="row">
              <div class="col-12 col-md-5 col-lg-4">
                <strong><?php echo $label_outstanding; ?> (45-50)</strong>
              </div>
              <div class="col-12 col-md-7 col-lg-8">
                <?php echo $descr_outstanding; ?>
              </div>
          </div>
          <div id="scoring-guide-bottom-excellent" class="row">
              <div class="col-12 col-md-5 col-lg-4">
                <strong><?php echo $label_excellent; ?> (38-44)</strong>
              </div>
              <div class="col-12 col-md-7 col-lg-8">
                <?php echo $descr_excellent; ?>
              </div>
          </div>
          <div id="scoring-guide-bottom-v-good" class="row">
              <div class="col-12 col-md-5 col-lg-4">
                <strong><?php echo $label_very_good; ?> (30-37)</strong>
              </div>
              <div class="col-12 col-md-7 col-lg-8">
                <?php echo $descr_very_good; ?>
              </div>
          </div>
      </div>
      <div class="col-12 col-sm-12 col-md-6">
        <div id="scoring-guide-bottom-good" class="row">
              <div class="col-12 col-md-5 col-lg-4">
                <strong><?php echo $label_good; ?> (21-29)</strong>
              </div>
              <div class="col-12 col-md-7 col-lg-8">
                <?php echo $descr_good; ?>
              </div>
          </div>
          <div id="scoring-guide-bottom-fair" class="row">
              <div class="col-12 col-md-5 col-lg-4">
                <strong><?php echo $label_fair; ?> (14-20)</strong>
              </div>
              <div class="col-12 col-md-7 col-lg-8">
                <?php echo $descr_fair; ?>
              </div>
          </div>
          <div id="scoring-guide-bottom-prob" class="row">
              <div class="col-12 col-md-5 col-lg-4">
                <strong><?php echo $label_problematic; ?> (00-13)</strong>
              </div>
              <div class="col-12 col-md-7 col-lg-8">
                <?php echo $descr_problematic; ?>
              </div>
          </div>
      </div>
  </div>
</section>

<!-- Mini-BOS -->
<div class="mb-3">
  <label class="form-check-label" for="evalMiniBOS"><strong><?php echo $label_mini_bos; ?></strong></label>
  <div class="form-check">
    <input class="form-check-input" type="checkbox" name="evalMiniBOS" id="evalMiniBOS" value="1" <?php if (($action == "edit") && ($row_eval['evalMiniBOS'] == 1)) echo "checked"; ?>> <?php echo $evaluation_info_054; ?>
  </div>
</div>

<!-- Minimum Words Warning -->
<div class="mb-3">
    <p class="text-danger" id="min-words-message"></p>
</div>

<!-- Submit -->
<div class="d-grid mb-3">
    <button id="submitForm" class="btn btn-lg btn-primary" type="submit"><?php echo $submit_button_text; ?></button>
</div>

</form>

<!-- Modals -->
<div class="modal fade" id="score-disparity-judges-modal" tabindex="-1" role="dialog" aria-labelledby="score-disparity-judges-modal-label">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title" id="score-disparity-judges-modal-label"><?php echo $label_score_out_range; ?></h4>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p><?php echo $evaluation_info_059; ?></p>
        <p><?php echo "<strong>".$label_score_range.":</strong> ".$score_range; ?></p>
      </div>
      <div class="modal-footer">
        <button id="disparity-button-submit" type="button" class="btn btn-primary" data-bs-dismiss="modal"><?php echo $label_submit; ?></button>
        <button type="button" class="btn btn-dark" data-bs-dismiss="modal"><?php echo $label_cancel; ?></button>
      </div>
    </div>
  </div>
</div>

<?php } ?>

<?php if ($entry_found) { ?>
<script src="<?php echo $js_url; ?>save_my_form.min.js"></script>
<script src="<?php echo $js_url; ?>eval_autosave.min.js"></script>

<script type="text/javascript">
var style_type = <?php echo (isset($row_style['brewStyleType'])) ? (int)$row_style['brewStyleType'] : 1; ?>;
var edit = <?php if ($action == "edit") echo "true"; else echo "false"; ?>;
var has_eval_draft = <?php echo ($has_eval_draft) ? "true" : "false"; ?>;
var eval_draft_data = <?php echo json_encode($eval_draft); ?>;
var eval_draft_updated_at = <?php echo (isset($eval_draft['evalUpdatedDate']) && is_numeric($eval_draft['evalUpdatedDate'])) ? (int)$eval_draft['evalUpdatedDate'] : 0; ?>;
var eval_autosave_config = {
  endpoint: "<?php echo $base_url; ?>ajax/save.ajax.php?action=evaluation&go=evalDraft",
  autosaveIntervalMs: 30000,
  autosaveDebounceMs: 3000,
  keepAliveThrottleMs: 60000,
  statusSaving: "<?php echo addslashes($evaluation_info_124 ?? 'Saving draft...'); ?>",
  statusSaved: "<?php echo addslashes($evaluation_info_125 ?? 'Draft saved at'); ?>",
  statusError: "<?php echo addslashes($evaluation_info_126 ?? 'Draft save failed. Keep working and submit when ready.'); ?>"
};

function bcoemClearScoresheetDraftStorage() {
  try {
    if (typeof jQuery !== "undefined" && jQuery.saveMyForm && typeof jQuery.saveMyForm.clearStorage === "function") {
      jQuery.saveMyForm.clearStorage("scoresheet-form");
    }
    var keysToRemove = [];
    for (var i = 0; i < localStorage.length; i++) {
      var key = localStorage.key(i);
      if ((key == "elementList_scoresheet-form") || (key.indexOf("scoresheet-form_") === 0) || (key.indexOf("evalDraftMeta_scoresheet-form") === 0)) keysToRemove.push(key);
    }
    for (var j = 0; j < keysToRemove.length; j++) localStorage.removeItem(keysToRemove[j]);
  } catch (e) {}
}

$(function() {
  // On edit, clear any prior scoresheet-form draft BEFORE saveMyForm initializes,
  // otherwise loadInputs would overwrite the DB values with stale localStorage.
  // Use form-scoped clearStorage (not localStorage.clear) so other drafts survive.
  // Also drop the elementList key - clearStorage alone leaves it behind, which can
  // leave orphaned keys that later reappear if the list and values get out of sync.
  if (edit && typeof jQuery !== "undefined" && jQuery.saveMyForm && typeof jQuery.saveMyForm.clearStorage === "function") {
    jQuery.saveMyForm.clearStorage("scoresheet-form");
    try { localStorage.removeItem("elementList_scoresheet-form"); } catch (e) {}
    try {
      if (typeof window.getEvalLocalDraftMeta === "function") {
        var edit_meta = window.getEvalLocalDraftMeta();
        if (edit_meta && edit_meta.key) localStorage.removeItem(edit_meta.key);
      }
    } catch (e) {}
  }

  // Do NOT clear the draft on submit. saveMyForm's default resetOnSubmit clears
  // localStorage as soon as the form is submitted - before the server responds.
  // If the POST fails (expired session/CSRF/network), the judge would lose their
  // entire scoresheet. Clear storage only after a successful landing on the
  // reconcile/dashboard page instead.
  //
  // On edit, never reload from localStorage (loadInputs:false) - the form must
  // stay populated from the DB. Autosave is also disabled on edit so we don't
  // create orphan draft rows or race the real submit.
  $('#scoresheet-form').saveMyForm({ resetOnSubmit: false, loadInputs: !edit });

  if ((!edit) && (has_eval_draft) && (typeof window.applyEvalDraftToForm === "function")) {
    var localDraftNewest = 0;
    try {
      if (typeof window.getEvalLocalDraftMeta === "function") {
        var localDraftMeta = window.getEvalLocalDraftMeta();
        localDraftNewest = Math.max(parseInt(localDraftMeta.saved_at || 0, 10) || 0, parseInt(localDraftMeta.dirty_at || 0, 10) || 0);
      }
    } catch (e) {}

    // Do not let an older DB draft overwrite newer browser-side draft data.
    if ((eval_draft_updated_at <= 0) || (eval_draft_updated_at >= localDraftNewest)) {
      window.applyEvalDraftToForm(eval_draft_data);
      if (typeof eval_draft_data.id !== "undefined") $("#evalAutosaveId").val(eval_draft_data.id);
    }
  }

  if ((!edit) && (typeof window.initEvalAutosave === "function")) window.initEvalAutosave(eval_autosave_config);

  if (edit) {
    $('#score-icon-aroma-status').attr('class', 'fa fa-fw fa-check-circle text-success me-1');
    $("#score-aroma-status").html("<?php if (isset($row_eval['evalAromaScore'])) echo $row_eval['evalAromaScore']; ?>");
    $('#score-icon-appearance-status').attr('class', 'fa fa-fw fa-check-circle text-success me-1');
    $("#score-appearance-status").html("<?php if (isset($row_eval['evalAppearanceScore'])) echo $row_eval['evalAppearanceScore']; ?>");
    $('#score-icon-flavor-status').attr('class', 'fa fa-fw fa-check-circle text-success me-1');
    $("#score-flavor-status").html("<?php if (isset($row_eval['evalFlavorScore'])) echo $row_eval['evalFlavorScore']; ?>");
    $('#score-icon-mouthfeel-status').attr('class', 'fa fa-fw fa-check-circle text-success me-1');
    $("#score-mouthfeel-status").html("<?php if (isset($row_eval['evalMouthfeelScore'])) echo $row_eval['evalMouthfeelScore']; ?>");
    $('#score-icon-overall-status').attr('class', 'fa fa-fw fa-check-circle text-success me-1');
    $("#score-overall-status").html("<?php if (isset($row_eval['evalOverallScore'])) echo $row_eval['evalOverallScore']; ?>");
  }
});
</script>
<?php } ?>

<?php if ($_SESSION['jPrefsJudgeStats'] == "Y") { ?>
<script type="text/javascript">
  $('#scoresheet-form').on('submit', function() {
    if (typeof elapsedTimeStart !== 'undefined') {
      $('#evalDurationSec').val(Math.round((Date.now() - elapsedTimeStart) / 1000));
    }
  });
</script>
<?php } ?>

<?php if ((isset($_SESSION['jPrefsMinWords'])) && ($_SESSION['jPrefsMinWords'] > 0)) { ?>
<script type="text/javascript">
var min_words = <?php echo $_SESSION['jPrefsMinWords']; ?>;
var min_wordcount_reached = '<strong class="text-success"><?php echo $evaluation_info_089; ?></strong> <?php echo $evaluation_info_090; ?>';
var min_wordcount_not = '<?php echo $evaluation_info_091; ?>';
var word_count_so_far = '<?php echo $evaluation_info_092; ?>';
</script>

<?php if (($judging_scoresheet == 3) || ($judging_scoresheet == 4)) { ?>
<script type="text/javascript">
    if (edit) var min_words_overall_ok = true;
    else var min_words_overall_ok = false;
    function min_words_ok() {
        $('#submitForm').attr('disabled','disabled');
        if (min_words_overall_ok) {
            $('#submitForm').removeAttr('disabled');
            $('#min-words-message').hide();
        } else {
          $('#min-words-message').show();
          $('#min-words-message').html('<i class="fa fa-lg fa-exclamation-circle me-2"></i><strong><?php echo $evaluation_info_093; ?></strong>');
        }
    }

    $(document).ready(function() {

        $('#min-words-message').hide();
        $('#evalOverallComments').on('keyup keydown click onmouseout oninput', function() {

            var currentWordCount = $('#evalOverallComments').val().match(/\S+/g).length;
            if (currentWordCount >= min_words) {
                min_words_overall_ok = true;
                $('#evalOverallComments-words').html(min_wordcount_reached + currentWordCount);      
            } 

            else {
               min_words_overall_ok = false;
               $('#evalOverallComments-words').html('<strong> ' + min_wordcount_not + min_words + '</strong>');
               if (currentWordCount > 1) $('#evalOverallComments-words').html('<strong>' + min_wordcount_not + min_words + '</strong>. <strong class="text-danger">' + word_count_so_far + currentWordCount + '</strong>');
            }  

            min_words_ok();

        });

    });
</script>
<?php } // end if (($judging_scoresheet == 3) || ($judging_scoresheet == 4)) ?>

<?php if ((($judging_scoresheet == 1) || ($judging_scoresheet == 2)) && ((isset($_SESSION['jPrefsMinWords'])) && ($_SESSION['jPrefsMinWords'] > 0))) { 

    if (($cider) || ($mead)) {
      $comment_fields = array(
        "aroma" => "#evalAromaComments",
        "appearance" => "#evalAppearanceComments",
        "flavor" => "#evalFlavorComments",
        "overall" => "#evalOverallComments"
      );
    } else {
      $comment_fields = array(
          "aroma" => "#evalAromaComments",
          "appearance" => "#evalAppearanceComments",
          "flavor" => "#evalFlavorComments",
          "mouthfeel" => "#evalMouthfeelComments",
          "overall" => "#evalOverallComments"
      );
    }

?>
<script type="text/javascript">

    if (edit) {
      var min_words_aroma_ok = true;
      var min_words_appearance_ok = true;
      var min_words_flavor_ok = true;
      var min_words_mouthfeel_ok = true;  
      var min_words_overall_ok = true;
    } else {
      var min_words_aroma_ok = false;
      var min_words_appearance_ok = false;
      var min_words_flavor_ok = false;
      if ((style_type == 2) || (style_type == 3)) var min_words_mouthfeel_ok = true;
      else var min_words_mouthfeel_ok = false;
      var min_words_overall_ok = false;
    }

    function min_words_ok() {
        $('#submitForm').attr('disabled','disabled');
        if ((min_words_aroma_ok) && (min_words_appearance_ok) && (min_words_flavor_ok) && (min_words_mouthfeel_ok) && (min_words_overall_ok)) {
            $('#submitForm').removeAttr('disabled');
            $('#min-words-message').hide();
        } else {
          $('#min-words-message').show();
          $('#min-words-message').html('<i class="fa fa-lg fa-exclamation-circle"></i> <strong><?php echo $evaluation_info_094; ?></strong>');
        }

    }

    $(document).ready(function() {

        $('#min-words-message').hide();

        <?php foreach ($comment_fields as $key => $value) { 
            $value_words = $value."-words";
            $key_ok = "min_words_".$key."_ok";
        ?>

        $('<?php echo $value; ?>').on('keyup keydown click onmouseout oninput', function() { //evalFinalScore

            var currentWordCount_<?php echo $key; ?> = $('<?php echo $value; ?>').val().match(/\S+/g).length;

            if (currentWordCount_<?php echo $key; ?> >= min_words) {
                <?php echo $key_ok; ?> = true;
                $('<?php echo $value_words; ?>').html(min_wordcount_reached + currentWordCount_<?php echo $key; ?>);      
            } 

            else {
               <?php echo $key_ok; ?> = false;
               $('<?php echo $value_words; ?>').html('<strong> ' + min_wordcount_not + min_words + '</strong>');
               if (currentWordCount_<?php echo $key; ?> > 1) $('<?php echo $value_words; ?>').html('<strong>' + min_wordcount_not +  min_words + '</strong>. <strong class="text-danger">' + word_count_so_far + currentWordCount_<?php echo $key; ?> + '</strong>.');
            }

            min_words_ok();        

        });
            
        <?php } // end foreach ?>

    });
</script>
<?php } // end if ((($judging_scoresheet == 1) || ($judging_scoresheet == 2)) && ((isset($_SESSION['jPrefsMinWords'])) && ($_SESSION['jPrefsMinWords'] > 0))) ?>
<?php } // End if ((isset($_SESSION['jPrefsMinWords'])) && ($_SESSION['jPrefsMinWords'] > 0)) ?>

<script>
  $(document).ready(function() {
    initCollapseStateManager({
      collapseElementId: "scoring-guide-status",
      toggleButtonId: "show-hide-status-btn",
      toggleIconId: "toggle-icon",
      iconClassExpanded: "fa-chevron-circle-up",
      iconClassCollapsed: "fa-chevron-circle-down"
    });
  });
</script>