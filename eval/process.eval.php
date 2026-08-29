<?php

/**
 * Define universal vars
 */

if ((isset($_SERVER['HTTP_REFERER'])) && ((isset($_SESSION['loginUsername'])) && (isset($_SESSION['userLevel'])))) {

	$errors = FALSE;
	$error_output = array();
	$_SESSION['error_output'] = "";

	$eid = "";
	$uid = "";
	$token = "";
	$evalSpecialIngredients = "";
	$evalAppearanceComments = "";
	$evalAromaComments = "";
	$evalOtherNotes = "";
	$evalFlavorComments = "";
	$evalMouthfeelComments = "";
	$evalOverallComments = "";
	$evalIntangibles = "";
	$evalDescriptors = "";
	$evalMiniBOS = 0;
	$evalBottle = 0;
	$evalBottleNotes = "";
	$evalPosition = "";
	$evalStyleAccuracy = "";
	$evalTechMerit = "";
	$evalDrinkability = "";
	$evalAromaScore = 0;
	$evalAppearanceScore = 0;
	$evalFlavorScore = 0;
	$evalMouthfeelScore = 0;
	$evalDurationSec = NULL;

	if ($_SESSION['prefsSEF'] == "Y") $sef = TRUE;
	else $sef = FALSE;

	// Judges land on the waiting/reconcile screen after add/edit so they can see
	// everyone's scores and the auto-computed consensus, instead of going straight
	// back to the dashboard. Carry the entry id forward for that redirect.
	$eid_for_redirect = "";
	if (isset($_POST['eid'])) $eid_for_redirect = sterilize($_POST['eid']);

	$dashboard_go_to = $base_url."index.php?section=evaluation&go=default&action=success&view=clear";
	$reconcile_go_to = $base_url."index.php?section=evaluation&go=reconcile&view=clear";
	if (!empty($eid_for_redirect)) $reconcile_go_to .= "&id=".$eid_for_redirect;

	// Both add and edit land on the reconcile screen so the judge immediately sees
	// the scores they just saved (and can update consensus). Admin edits stay in admin.
	if ($view == "admin") $insertGoTo = $base_url."index.php?section=admin&go=evaluation&filter=default&view=admin";	
	elseif ((!empty($eid_for_redirect)) && (($action == "add") || ($action == "edit"))) $insertGoTo = $reconcile_go_to;
	else $insertGoTo = $dashboard_go_to;
	if ($action == "add") $insertGoTo .= "&msg=3";
	if ($action == "edit") $insertGoTo .= "&msg=2";

	// Fallback used if an insert/update fails and the form did not post a relocate URL.
	$error_go_to = $dashboard_go_to."&msg=3";
	if ((isset($_POST['relocate'])) && (!empty($_POST['relocate']))) {
		if (strpos($_POST['relocate'],"?") === false) $error_go_to = $_POST['relocate']."?msg=3";
		else $error_go_to = $_POST['relocate']."&msg=3";
	}

	require (CLASSES.'htmlpurifier/HTMLPurifier.standalone.php');
	$config_html_purifier = HTMLPurifier_Config::createDefault();
	$purifier = new HTMLPurifier($config_html_purifier);

	if (isset($_POST['evalJudgeInfo'])) $evalJudgeInfo = sterilize($_POST['evalJudgeInfo']);
	if (isset($_POST['evalScoresheet'])) $evalScoresheet = sterilize($_POST['evalScoresheet']);
	if (isset($_POST['evalAromaScore'])) $evalAromaScore = sterilize($_POST['evalAromaScore']);
	if (isset($_POST['evalAppearanceScore'])) $evalAppearanceScore = sterilize($_POST['evalAppearanceScore']);
	if (isset($_POST['evalFlavorScore'])) $evalFlavorScore = sterilize($_POST['evalFlavorScore']);
	if (isset($_POST['evalMouthfeelScore'])) $evalMouthfeelScore = sterilize($_POST['evalMouthfeelScore']);
	
	// The consensus/assigned score (evalFinalScore) is never posted from the
	// scoresheet - it's computed automatically after save (see recompute_eval_consensus()
	// below), as the average of every judge's total score for the entry.
	$evalOverallScore = sterilize($_POST['evalOverallScore']);
	
	if (isset($_POST['evalStyleAccuracy'])) $evalStyleAccuracy = sterilize($_POST['evalStyleAccuracy']);
	if (isset($_POST['evalTechMerit'])) $evalTechMerit = sterilize($_POST['evalTechMerit']);
	if (isset($_POST['evalDrinkability'])) $evalDrinkability = sterilize($_POST['evalDrinkability']);
	$evalTable = sterilize($_POST['evalTable']);

	// Only capture/persist time-to-submit when the judge analytics preference is enabled,
	// and only on the initial submission (edits shouldn't overwrite the original time spent).
	if (($_SESSION['jPrefsJudgeStats'] == "Y") && ($action == "add") && (isset($_POST['evalDurationSec'])) && (is_numeric($_POST['evalDurationSec']))) {
		$evalDurationSec = sterilize($_POST['evalDurationSec']);
	}
	

	if ($action == "edit") $id = sterilize($id);
	if (isset($_POST['eid'])) $eid = sterilize($_POST['eid']);
	if (isset($_POST['uid'])) $uid = sterilize($_POST['uid']);
	if (isset($_POST['token'])) $token = sterilize($_POST['token']);
	if (isset($_POST['evalSpecialIngredients'])) $evalSpecialIngredients = $purifier->purify(sterilize($_POST['evalSpecialIngredients']));
	if (isset($_POST['evalOtherNotes'])) $evalOtherNotes = $purifier->purify(sterilize($_POST['evalOtherNotes']));
	if (isset($_POST['evalAromaComments'])) $evalAromaComments = $purifier->purify(sterilize($_POST['evalAromaComments']));
	if (isset($_POST['evalAppearanceComments'])) $evalAppearanceComments = $purifier->purify(sterilize($_POST['evalAppearanceComments']));
	if (isset($_POST['evalFlavorComments'])) $evalFlavorComments = $purifier->purify(sterilize($_POST['evalFlavorComments'])); 
	if (isset($_POST['evalMouthfeelComments'])) $evalMouthfeelComments = $purifier->purify(sterilize($_POST['evalMouthfeelComments'])); 
	if (isset($_POST['evalOverallComments'])) $evalOverallComments = $purifier->purify(sterilize($_POST['evalOverallComments']));
	if (isset($_POST['evalIntangibles'])) $evalIntangibles = $purifier->purify(sterilize($_POST['evalIntangibles']));
	if (isset($_POST['evalMiniBOS'])) $evalMiniBOS = sterilize($_POST['evalMiniBOS']);
	if (isset($_POST['evalBottle'])) $evalBottle = sterilize($_POST['evalBottle']);
	if (isset($_POST['evalBottleNotes'])) $evalBottleNotes = $purifier->purify(sterilize($_POST['evalBottleNotes'])); 
	if ((isset($_POST['evalPosition_0'])) && (is_numeric($_POST['evalPosition_0']))) {
		$evalPosition = $purifier->purify(sterilize($_POST['evalPosition_0']));
		if ((isset($_POST['evalPosition_1'])) && (is_numeric($_POST['evalPosition_1']))) $evalPosition .= ",".$purifier->purify(sterilize($_POST['evalPosition_1']));
	}

	$evalStyle = sterilize($_POST['evalStyle']);

	$draft_eval_id = 0;
	$draft_eval_initial_date = NULL;
	if (($action == "add") && (!empty($eid)) && (is_numeric($eid)) && (!empty($evalJudgeInfo)) && (is_numeric($evalJudgeInfo))) {
		$query_draft_eval = sprintf("SELECT id, evalInitialDate FROM %s WHERE eid='%s' AND evalJudgeInfo='%s' AND evalDraft='1' ORDER BY id DESC LIMIT 1", $prefix."evaluation", $eid, $evalJudgeInfo);
		$draft_eval = mysqli_query($connection, $query_draft_eval) or die (mysqli_error($connection));
		$row_draft_eval = mysqli_fetch_assoc($draft_eval);
		if ($row_draft_eval) {
			$draft_eval_id = $row_draft_eval['id'];
			$draft_eval_initial_date = $row_draft_eval['evalInitialDate'];
		}
	}

	$exceptions = array(
		"evalSpecialIngredients",
		"evalOtherNotes",
		"evalAromaScore",
		"evalAromaComments",
		"evalAppearanceScore",
		"evalAppearanceComments",
		"evalFlavorScore",
		"evalFlavorComments",
		"evalMouthfeelScore",
		"evalMouthfeelComments",
		"evalIntangibles",
		"evalMiniBOS",
		"evalBottle",
		"evalBottleNotes",
		"evalPosition_0",
		"evalPosition_1",
		"evalDrinkability",
		"evalStyle",
		"eid",
		"uid",
		"token"
	);

	if ($section == "process-eval-structured") {

		// The data from the structured scoresheet form is stored in json format in
		// the respective Checklist columns (evalAromaChecklist, etc.).

		$evalAroma = array();
		$evalAppearance = array();
		$evalFlavor = array();
		$evalMouthfeel = array();
		$evalFlaws = array();

		foreach ($_POST as $key => $value) {

			if (!empty($value)) {
				
				if (is_numeric($value)) $value = sterilize($value);	
				
				if (is_array($value)) {

					$new_value = array();
					
					foreach ($value as $v) {
						if (is_numeric($v)) $v = sterilize($value);
						else  $v = $purifier->purify(sterilize($v));
						$new_value[] = $v;
					}
					
					$value = implode(", ",$new_value);
					
				}

			}

			// Build Aroma Insert
			if ((strpos($key, "evalAroma") !== FALSE) && (!in_array($key, $exceptions))) {
				$key = sterilize($key);
				$evalAroma[$key] = $value;
			}

			// Build Appearance Insert
			// When processing NW, convert evalAppearanceColorChoice to evalAppearanceColor
			// If evalAppearanceColorChoice is "Other", convert evalAppearanceColorOther to evalAppearanceColor
			if ((strpos($key, "evalAppearance") !== FALSE) && (!in_array($key, $exceptions))) {
				$key = sterilize($key);
				if ($key == "evalAppearanceColorChoice") {
					$key = "evalAppearanceColor";
					if ($value == "999") $value = $_POST['evalAppearanceColorOther'];
					else $value = $_POST['evalAppearanceColorChoice'];
				}
				$evalAppearance[$key] = $value;
			}

			// Build Flavor Insert
			if ((strpos($key, "evalFlavor") !== FALSE) && (!in_array($key, $exceptions))) {
				$key = sterilize($key);
				$evalFlavor[$key] = $value;
			}

			// Build Mouthfeel Insert
			if ((strpos($key, "evalMouthfeel") !== FALSE) && (!in_array($key, $exceptions))) {
				$key = sterilize($key);
				$evalMouthfeel[$key] = $value;
			}

			// Build Flaws Insert
			if (strpos($key, "evalFlaws") !== FALSE) {
				if (!empty($value)) $evalFlaws[] = $value;
			}

		}

		$evalAromaChecklist = json_encode($evalAroma);
		$evalAppearanceChecklist = json_encode($evalAppearance);
		$evalFlavorChecklist = json_encode($evalFlavor);
		$evalMouthfeelChecklist = json_encode($evalMouthfeel);
		if ((is_array($evalFlaws)) && (!empty($evalFlaws))) $evalFlaws = implode(", ",$evalFlaws); 
		else $evalFlaws = "";

		/*
		echo "<br><br>";
		echo $evalAromaChecklist."<br><br>";
		echo $evalAppearanceChecklist."<br><br>";
		echo $evalFlavorChecklist."<br><br>";
		echo $evalMouthfeelChecklist."<br><br>";
		echo $evalFlaws."<br><br>";
		*/

		if (($action == "add") || ($action == "edit")) {

			$update_table = $prefix."evaluation";
			$data = array(
				'eid' => $eid,
				'uid' => $uid,
				'evalJudgeInfo' => $evalJudgeInfo, 
				'evalScoresheet' => $evalScoresheet, 
				'evalStyle' => $evalStyle, 	
				'evalSpecialIngredients' => $evalSpecialIngredients, 
				'evalOtherNotes' => $evalOtherNotes, 
				'evalAromaScore' => $evalAromaScore, 
				'evalAromaChecklist' => $evalAromaChecklist, 
				'evalAppearanceScore' => $evalAppearanceScore, 
				'evalAppearanceChecklist' => $evalAppearanceChecklist, 
				'evalFlavorScore' => $evalFlavorScore, 
				'evalFlavorChecklist' => $evalFlavorChecklist, 
				'evalMouthfeelScore' => $evalMouthfeelScore, 
				'evalMouthfeelChecklist' => $evalMouthfeelChecklist, 
				'evalOverallScore' => $evalOverallScore, 
				'evalOverallComments' => $evalOverallComments, 
				'evalStyleAccuracy' => $evalStyleAccuracy, 
				'evalTechMerit' => $evalTechMerit, 
				'evalIntangibles' => $evalIntangibles, 
				'evalFlaws' => $evalFlaws, 
				'evalInitialDate' => (($draft_eval_initial_date) ? $draft_eval_initial_date : time()), 
				'evalUpdatedDate' => time(), 
				'evalToken' => $token,
				'evalTable' => $evalTable, 
				'evalMiniBOS' => $evalMiniBOS,
				'evalBottle' => $evalBottle,
				'evalBottleNotes' => $evalBottleNotes,
				'evalPosition' => $evalPosition,
				'evalDurationSec' => $evalDurationSec,
				'evalDraft' => 0
			);

		}

		if ($action == "add") {

			if ($draft_eval_id > 0) {
				$db_conn->where ('id', $draft_eval_id);
				$result = $db_conn->update ($update_table, $data);
			}
			else $result = $db_conn->insert ($update_table, $data);
			if (!$result) {
				$error_output[] = $db_conn->getLastError();
				$errors = TRUE;
			}

			if (!empty($error_output)) $_SESSION['error_output'] = $error_output;
			else recompute_eval_consensus($eid);
			
			if ($errors) $insertGoTo = $error_go_to;
			$insertGoTo = prep_redirect_link($insertGoTo);
			$redirect_go_to = sprintf("Location: %s", $insertGoTo);
			header($redirect_go_to);
			exit();
			
		} // if ($action == "add") 

		if ($action == "edit") {
			
			$db_conn->where ('id', $id);
			$result = $db_conn->update ($update_table, $data);
			if (!$result) {
				$error_output[] = $db_conn->getLastError();
				$errors = TRUE;
			}

			if (!empty($error_output)) $_SESSION['error_output'] = $error_output;
			else recompute_eval_consensus($eid);
			
			if ($errors) $insertGoTo = $error_go_to;
			$insertGoTo = prep_redirect_link($insertGoTo);
			$redirect_go_to = sprintf("Location: %s", $insertGoTo);
			header($redirect_go_to);
			exit();
			
		} // end if ($action == "edit")

	}

	if ($section == "process-eval-full") {
		
		if ((!empty($_POST['evalDescriptors'])) && (is_array($_POST['evalDescriptors']))) $evalDescriptors = implode(", ",$_POST['evalDescriptors']);

		if (($action == "add") || ($action == "edit")) {

			$update_table = $prefix."evaluation";
			$data = array(
				'eid' => $eid,
				'uid' => $uid,
				'evalJudgeInfo' => $evalJudgeInfo, 
				'evalScoresheet' => $evalScoresheet, 
				'evalStyle' => $evalStyle, 	
				'evalSpecialIngredients' => $evalSpecialIngredients, 
				'evalOtherNotes' => $evalOtherNotes, 
				'evalAromaScore' => $evalAromaScore, 
				'evalAromaChecklist' => $evalAromaChecklist, 
				'evalAromaComments' => $evalAromaComments, 
				'evalAppearanceScore' => $evalAppearanceScore, 
				'evalAppearanceChecklist' => $evalAppearanceChecklist, 
				'evalAppearanceComments' => $evalAppearanceComments, 
				'evalFlavorScore' => $evalFlavorScore, 
				'evalFlavorChecklist' => $evalFlavorChecklist, 
				'evalFlavorComments' => $evalFlavorComments, 
				'evalMouthfeelScore' => $evalMouthfeelScore, 
				'evalMouthfeelChecklist' => $evalMouthfeelChecklist, 
				'evalMouthfeelComments' => $evalMouthfeelComments, 
				'evalOverallScore' => $evalOverallScore, 
				'evalOverallComments' => $evalOverallComments, 
				'evalStyleAccuracy' => $evalStyleAccuracy, 
				'evalTechMerit' => $evalTechMerit, 
				'evalIntangibles' => $evalIntangibles, 
				'evalFlaws' => $evalFlaws, 
				'evalInitialDate' => (($draft_eval_initial_date) ? $draft_eval_initial_date : time()), 
				'evalUpdatedDate' => time(), 
				'evalToken' => $token,
				'evalTable' => $evalTable, 
				'evalMiniBOS' => $evalMiniBOS,
				'evalBottle' => $evalBottle,
				'evalBottleNotes' => $evalBottleNotes,
				'evalPosition' => $evalPosition,
				'evalDescriptors' => $evalDescriptors,
				'evalDurationSec' => $evalDurationSec,
				'evalDraft' => 0
			);

		}
		
		if ($action == "add") {
			
			if ($draft_eval_id > 0) {
				$db_conn->where ('id', $draft_eval_id);
				$result = $db_conn->update ($update_table, $data);
			}
			else $result = $db_conn->insert ($update_table, $data);
			if (!$result) {
				$error_output[] = $db_conn->getLastError();
				$errors = TRUE;
			}

			if (!empty($error_output)) $_SESSION['error_output'] = $error_output;
			else recompute_eval_consensus($eid);
			
			if ($errors) $insertGoTo = $error_go_to;
			$insertGoTo = prep_redirect_link($insertGoTo);
			$redirect_go_to = sprintf("Location: %s", $insertGoTo);
			header($redirect_go_to);
			exit();
			
		} // end if ($action == "add")
		
		
		if ($action == "edit") {
				
			$db_conn->where ('id', $id);
			$result = $db_conn->update ($update_table, $data);
			if (!$result) {
				$error_output[] = $db_conn->getLastError();
				$errors = TRUE;
			}

			if (!empty($error_output)) $_SESSION['error_output'] = $error_output;
			else recompute_eval_consensus($eid);
			
			if ($errors) $insertGoTo = $error_go_to;
			$insertGoTo = prep_redirect_link($insertGoTo);
			$redirect_go_to = sprintf("Location: %s", $insertGoTo);
			header($redirect_go_to);
			exit();
			
		} // end if ($action == "edit")
		
	}

	if ($section == "process-eval-checklist") {

		$evalAromaChecklistDesc = "";
		$evalAppearanceChecklistDesc = "";
		$evalFlavorChecklistDesc = "";
		$evalMouthfeelChecklistDesc = "";
		$evalOverallChecklistDesc = "";
		$evalFlaws = "";
		
		if ((!empty($_POST['evalAromaChecklistDesc'])) && (is_array($_POST['evalAromaChecklistDesc']))) $evalAromaChecklistDesc = implode(", ",$_POST['evalAromaChecklistDesc']); 
		if ((!empty($_POST['evalAppearanceChecklistDesc'])) && (is_array($_POST['evalAppearanceChecklistDesc']))) $evalAppearanceChecklistDesc = implode(", ",$_POST['evalAppearanceChecklistDesc']);
		if ((!empty($_POST['evalFlavorChecklistDesc'])) && (is_array($_POST['evalFlavorChecklistDesc']))) $evalFlavorChecklistDesc = implode(", ",$_POST['evalFlavorChecklistDesc']);
		if ((!empty($_POST['evalMouthfeelChecklistDesc'])) && (is_array($_POST['evalMouthfeelChecklistDesc']))) $evalMouthfeelChecklistDesc = implode(", ",$_POST['evalMouthfeelChecklistDesc']);
		if ((!empty($_POST['evalOverallChecklistDesc'])) && (is_array($_POST['evalOveralllChecklistDesc']))) $evalOverallChecklistDesc = implode(", ",$_POST['evalOverallChecklistDesc']);
		if ((!empty($_POST['evalFlaws'])) && (is_array($_POST['evalFlaws']))) $evalFlaws = implode(", ",$_POST['evalFlaws']);
		
		// Aroma
		$evalAromaCheck = array();
		$evalAromaCheck[] = $_POST['evalAromaMalt'];
		$evalAromaCheck[] = $_POST['evalAromaHops'];
		$evalAromaCheck[] = $_POST['evalAromaEsters'];
		$evalAromaCheck[] = $_POST['evalAromaPhenols'];
		$evalAromaCheck[] = $_POST['evalAromaAlcohol'];
		$evalAromaCheck[] = $_POST['evalAromaSweetness'];
		$evalAromaCheck[] = $_POST['evalAromaAcidity'];
		$evalAromaChecklist = implode(", ",$evalAromaCheck);
		
		// Appearance
		$evalAppearanceCheck = array();
		$evalAppearanceCheck[] = $_POST['evalAppearanceClarity'];
		$evalAppearanceCheck[] = $_POST['evalAppearanceHeadSize'];
		$evalAppearanceCheck[] = $_POST['evalAppearanceHeadRetention'];
		$evalAppearanceChecklist = implode(", ",$evalAppearanceCheck);
		
		// Flavor
		$evalFlavorCheck = array();
		$evalFlavorCheck[] = $_POST['evalFlavorMalt'];
		$evalFlavorCheck[] = $_POST['evalFlavorHops'];
		$evalFlavorCheck[] = $_POST['evalFlavorEsters'];
		$evalFlavorCheck[] = $_POST['evalFlavorPhenols'];
		$evalFlavorCheck[] = $_POST['evalFlavorSweetness'];
		$evalFlavorCheck[] = $_POST['evalFlavorBitterness'];
		$evalFlavorCheck[] = $_POST['evalFlavorAlcohol'];
		$evalFlavorCheck[] = $_POST['evalFlavorAcidity'];
		$evalFlavorCheck[] = $_POST['evalFlavorHarshness'];
		$evalFlavorChecklist = implode(", ",$evalFlavorCheck);
		
		// Mouthfeel
		$evalMouthfeelCheck = array();
		$evalMouthfeelCheck[] = $_POST['evalMouthfeelBody'];
		$evalMouthfeelCheck[] = $_POST['evalMouthfeelCarbonation'];
		$evalMouthfeelCheck[] = $_POST['evalMouthfeelWarmth'];
		$evalMouthfeelCheck[] = $_POST['evalMouthfeelCreaminess'];
		$evalMouthfeelCheck[] = $_POST['evalMouthfeelAstringency'];
		$evalMouthfeelChecklist = implode(", ",$evalMouthfeelCheck);
		
		/*
		// echo $_POST['evalBeerName']."<br>";
		echo $evalAromaChecklist."<br>";
		echo $evalAromaChecklistDesc."<br>";
		echo $evalAppearanceChecklist."<br>";
		echo $evalAppearanceChecklistDesc."<br>";
		echo $evalFlavorChecklist."<br>";
		echo $evalFlavorChecklistDesc."<br>";
		echo $evalMouthfeelChecklist."<br>";
		echo $evalMouthfeelChecklistDesc."<br>";
		echo $evalFlaws;
		exit;
		*/

		if (($action == "add") || ($action == "edit")) {

			$update_table = $prefix."evaluation";
			$data = array(
				'eid' => $eid,
				'uid' => $uid,
				'evalJudgeInfo' => $evalJudgeInfo, 
				'evalScoresheet' => $evalScoresheet, 
				'evalStyle' => $evalStyle, 	
				'evalSpecialIngredients' => $evalSpecialIngredients, 
				'evalOtherNotes' => $evalOtherNotes, 
				'evalAromaScore' => $evalAromaScore, 
				'evalAromaChecklist' => $evalAromaChecklist, 
				'evalAromaChecklistDesc' => $evalAromaChecklistDesc, 
				'evalAromaComments' => $evalAromaComments, 
				'evalAppearanceScore' => $evalAppearanceScore, 
				'evalAppearanceChecklist' => $evalAppearanceChecklist, 
				'evalAppearanceChecklistDesc' => $evalAppearanceChecklistDesc, 
				'evalAppearanceComments' => $evalAppearanceComments, 
				'evalFlavorScore' => $evalFlavorScore, 
				'evalFlavorChecklist' => $evalFlavorChecklist, 
				'evalFlavorChecklistDesc' => $evalFlavorChecklistDesc, 
				'evalFlavorComments' => $evalFlavorComments, 
				'evalMouthfeelScore' => $evalMouthfeelScore, 
				'evalMouthfeelChecklist' => $evalMouthfeelChecklist, 
				'evalMouthfeelChecklistDesc' => $evalMouthfeelChecklistDesc, 
				'evalMouthfeelComments' => $evalMouthfeelComments, 
				'evalOverallScore' => $evalOverallScore, 
				'evalOverallComments' => $evalOverallComments, 
				'evalOverallChecklistDesc' => $evalOverallChecklistDesc, 
				'evalStyleAccuracy' => $evalStyleAccuracy, 
				'evalTechMerit' => $evalTechMerit, 
				'evalIntangibles' => $evalIntangibles, 
				'evalFlaws' => $evalFlaws, 
				'evalInitialDate' => (($draft_eval_initial_date) ? $draft_eval_initial_date : time()), 
				'evalUpdatedDate' => time(), 
				'evalToken' => $token,
				'evalTable' => $evalTable, 
				'evalMiniBOS' => $evalMiniBOS,
				'evalBottle' => $evalBottle,
				'evalBottleNotes' => $evalBottleNotes,
				'evalPosition' => $evalPosition,
				'evalDrinkability' => $evalDrinkability,
				'evalDurationSec' => $evalDurationSec,
				'evalDraft' => 0
			);

		}
		
		if ($action == "add") {

			if ($draft_eval_id > 0) {
				$db_conn->where ('id', $draft_eval_id);
				$result = $db_conn->update ($update_table, $data);
			}
			else $result = $db_conn->insert ($update_table, $data);
			if (!$result) {
				$error_output[] = $db_conn->getLastError();
				$errors = TRUE;
			}

			if (!empty($error_output)) $_SESSION['error_output'] = $error_output;
			else recompute_eval_consensus($eid);
			
			if ($errors) $insertGoTo = $error_go_to;
			$insertGoTo = prep_redirect_link($insertGoTo);
			$redirect_go_to = sprintf("Location: %s", $insertGoTo);
			header($redirect_go_to);
			exit();
			
		} // end if ($action == "add") 
		
		if ($action == "edit") {
			
			$db_conn->where ('id', $id);
			$result = $db_conn->update ($update_table, $data);
			if (!$result) {
				$error_output[] = $db_conn->getLastError();
				$errors = TRUE;
			}

			if (!empty($error_output)) $_SESSION['error_output'] = $error_output;
			else recompute_eval_consensus($eid);
			
			if ($errors) $insertGoTo = $error_go_to;
			$insertGoTo = prep_redirect_link($insertGoTo);
			$redirect_go_to = sprintf("Location: %s", $insertGoTo);
			header($redirect_go_to);
			exit();
			
		} // end if ($action == "edit")

	} // end if (($section == "process-eval-full") || ($section == "process-eval-checklist")) 

} else {

	$redirect = $base_url."403.php";
	$redirect = prep_redirect_link($redirect);
	$redirect_go_to = sprintf("Location: %s", $redirect);
	header($redirect_go_to);
	exit();
	
}
?>