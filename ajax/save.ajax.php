<?php

ob_start();
require('../paths.php');
require(CONFIG.'bootstrap.php');
ini_set('display_errors', 0); // Change to 0 for prod; change to 1 for testing.
ini_set('display_startup_errors', 0); // Change to 0 for prod; change to 1 for testing.
error_reporting(0); // Change to error_reporting(0) for prod; change to E_ALL for testing.


/**
 * The action variable cooresponds to a table in the DB.
 *
 * @param $id varable will be used as an identifier - either as the 
 * record id in the table or a relational component (bid, eid).
 * 
 * @param $ridX variables are for other relational vars.
 */

$rid1 = "default";
$rid2 = "default";
$rid3 = "default";
$rid4 = "default";

if (isset($_GET['rid1'])) $rid1 = sterilize($_GET['rid1']);
if (isset($_GET['rid2'])) $rid2 = sterilize($_GET['rid2']);
if (isset($_GET['rid3'])) $rid3 = sterilize($_GET['rid3']);
if (isset($_GET['rid4'])) $rid4 = sterilize($_GET['rid4']);

$return_json = array();
$status = 0;
$process = FALSE;
$sql = "";
$input = "";
$post = 0;
$error_type = 0;
$message = "";
$saved_at = "";
$autosave_id = "";

function eval_autosave_token_valid() {
	$posted = filter_input(INPUT_POST, 'user_session_token', FILTER_UNSAFE_RAW);
	$posted = is_string($posted) ? trim($posted) : '';
	$session = $_SESSION['user_session_token'] ?? '';
	$session_prev = $_SESSION['user_session_token_prev'] ?? '';
	$valid_shape = (bool) preg_match('/^[a-f0-9]{64}$/i', $posted);
	if ((!$valid_shape) || ($posted === '')) return FALSE;
	if (($session !== '') && (hash_equals($session, $posted))) return TRUE;
	if (($session_prev !== '') && (hash_equals($session_prev, $posted))) return TRUE;
	return FALSE;
}

function eval_autosave_text($value, $purifier) {
	return $purifier->purify(sterilize((string)$value));
}

function eval_autosave_basic_data($now, $judge_id) {
	$data = array(
		'evalJudgeInfo' => $judge_id,
		'evalUpdatedDate' => $now,
		'evalDraft' => 1
	);

	if (isset($_POST['eid'])) $data['eid'] = sterilize($_POST['eid']);
	if (isset($_POST['uid'])) $data['uid'] = sterilize($_POST['uid']);
	if (isset($_POST['evalScoresheet'])) $data['evalScoresheet'] = sterilize($_POST['evalScoresheet']);
	if (isset($_POST['evalStyle'])) $data['evalStyle'] = sterilize($_POST['evalStyle']);
	if (isset($_POST['token'])) $data['evalToken'] = sterilize($_POST['token']);
	if (isset($_POST['evalTable'])) $data['evalTable'] = sterilize($_POST['evalTable']);
	if (isset($_POST['evalPosition_0'])) {
		$evalPosition = sterilize($_POST['evalPosition_0']);
		if ((isset($_POST['evalPosition_1'])) && ($_POST['evalPosition_1'] !== "")) $evalPosition .= ",".sterilize($_POST['evalPosition_1']);
		$data['evalPosition'] = $evalPosition;
	}
	if (isset($_POST['evalBottle'])) $data['evalBottle'] = sterilize($_POST['evalBottle']);
	if (isset($_POST['evalMiniBOS'])) $data['evalMiniBOS'] = sterilize($_POST['evalMiniBOS']);

	return $data;
}

$session_active = FALSE;
if ((isset($_SESSION['session_set_'.$prefix_session])) && (isset($_SESSION['loginUsername']))) $session_active = TRUE;

if (($session_active) && ($_SESSION['userLevel'] <= 2)) {

	if ($action == "evaluation") {

		$update_table = $prefix.$action;

		if ($go == "evalDraft") {

			if (!eval_autosave_token_valid()) {
				$error_type = 4;
				$message = "Invalid session token.";
			}

			else {

				require (CLASSES.'htmlpurifier/HTMLPurifier.standalone.php');
				$config_html_purifier = HTMLPurifier_Config::createDefault();
				$purifier = new HTMLPurifier($config_html_purifier);

				$now = time();
				$judge_id = sterilize($_SESSION['user_id']);
				$eid = isset($_POST['eid']) ? sterilize($_POST['eid']) : "";
				$form_type = isset($_POST['evalFormType']) ? sterilize($_POST['evalFormType']) : "";
				$target_id = ((is_numeric($id)) && ($id > 0)) ? sterilize($id) : 0;
				$is_admin = ($_SESSION['userLevel'] <= 1);

				if ((empty($eid)) || (!is_numeric($eid))) {
					$error_type = 1;
					$message = "Invalid entry id.";
				}

				elseif (($form_type !== "1") && ($form_type !== "2") && ($form_type !== "3") && ($form_type !== "4")) {
					$error_type = 1;
					$message = "Invalid form type.";
				}

				elseif ((!$is_admin) && (isset($_POST['evalJudgeInfo'])) && (sterilize($_POST['evalJudgeInfo']) != $judge_id)) {
					$error_type = 2;
					$message = "Insufficient permissions.";
				}

				else {

					$incoming_judge_id = $judge_id;
					if (($is_admin) && (isset($_POST['evalJudgeInfo'])) && (!empty($_POST['evalJudgeInfo']))) $incoming_judge_id = sterilize($_POST['evalJudgeInfo']);
					if ((empty($incoming_judge_id)) || (!is_numeric($incoming_judge_id))) $incoming_judge_id = $judge_id;

					$data = eval_autosave_basic_data($now, $incoming_judge_id);

					if (isset($_POST['evalSpecialIngredients'])) $data['evalSpecialIngredients'] = eval_autosave_text($_POST['evalSpecialIngredients'], $purifier);
					if (isset($_POST['evalOtherNotes'])) $data['evalOtherNotes'] = eval_autosave_text($_POST['evalOtherNotes'], $purifier);
					if (isset($_POST['evalBottleNotes'])) $data['evalBottleNotes'] = eval_autosave_text($_POST['evalBottleNotes'], $purifier);
					if (isset($_POST['evalAromaScore'])) $data['evalAromaScore'] = sterilize($_POST['evalAromaScore']);
					if (isset($_POST['evalAppearanceScore'])) $data['evalAppearanceScore'] = sterilize($_POST['evalAppearanceScore']);
					if (isset($_POST['evalFlavorScore'])) $data['evalFlavorScore'] = sterilize($_POST['evalFlavorScore']);
					if (isset($_POST['evalMouthfeelScore'])) $data['evalMouthfeelScore'] = sterilize($_POST['evalMouthfeelScore']);
					if (isset($_POST['evalOverallScore'])) $data['evalOverallScore'] = sterilize($_POST['evalOverallScore']);

					if ($form_type == "1") {
						if (isset($_POST['evalAromaComments'])) $data['evalAromaComments'] = eval_autosave_text($_POST['evalAromaComments'], $purifier);
						if (isset($_POST['evalAppearanceComments'])) $data['evalAppearanceComments'] = eval_autosave_text($_POST['evalAppearanceComments'], $purifier);
						if (isset($_POST['evalFlavorComments'])) $data['evalFlavorComments'] = eval_autosave_text($_POST['evalFlavorComments'], $purifier);
						if (isset($_POST['evalMouthfeelComments'])) $data['evalMouthfeelComments'] = eval_autosave_text($_POST['evalMouthfeelComments'], $purifier);
						if (isset($_POST['evalOverallComments'])) $data['evalOverallComments'] = eval_autosave_text($_POST['evalOverallComments'], $purifier);
						if (isset($_POST['evalStyleAccuracy'])) $data['evalStyleAccuracy'] = sterilize($_POST['evalStyleAccuracy']);
						if (isset($_POST['evalTechMerit'])) $data['evalTechMerit'] = sterilize($_POST['evalTechMerit']);
						if (isset($_POST['evalIntangibles'])) $data['evalIntangibles'] = sterilize($_POST['evalIntangibles']);
						if ((isset($_POST['evalDescriptors'])) && (is_array($_POST['evalDescriptors']))) $data['evalDescriptors'] = implode(", ", $_POST['evalDescriptors']);
					}

					if ($form_type == "2") {
						$evalAromaChecklistDesc = "";
						$evalAppearanceChecklistDesc = "";
						$evalFlavorChecklistDesc = "";
						$evalMouthfeelChecklistDesc = "";
						$evalOverallChecklistDesc = "";
						$evalFlaws = "";

						if ((!empty($_POST['evalAromaChecklistDesc'])) && (is_array($_POST['evalAromaChecklistDesc']))) $evalAromaChecklistDesc = implode(", ", $_POST['evalAromaChecklistDesc']);
						if ((!empty($_POST['evalAppearanceChecklistDesc'])) && (is_array($_POST['evalAppearanceChecklistDesc']))) $evalAppearanceChecklistDesc = implode(", ", $_POST['evalAppearanceChecklistDesc']);
						if ((!empty($_POST['evalFlavorChecklistDesc'])) && (is_array($_POST['evalFlavorChecklistDesc']))) $evalFlavorChecklistDesc = implode(", ", $_POST['evalFlavorChecklistDesc']);
						if ((!empty($_POST['evalMouthfeelChecklistDesc'])) && (is_array($_POST['evalMouthfeelChecklistDesc']))) $evalMouthfeelChecklistDesc = implode(", ", $_POST['evalMouthfeelChecklistDesc']);
						if ((!empty($_POST['evalOverallChecklistDesc'])) && (is_array($_POST['evalOverallChecklistDesc']))) $evalOverallChecklistDesc = implode(", ", $_POST['evalOverallChecklistDesc']);
						if ((!empty($_POST['evalFlaws'])) && (is_array($_POST['evalFlaws']))) $evalFlaws = implode(", ", $_POST['evalFlaws']);

						$evalAromaCheck = array($_POST['evalAromaMalt'] ?? "", $_POST['evalAromaHops'] ?? "", $_POST['evalAromaEsters'] ?? "", $_POST['evalAromaPhenols'] ?? "", $_POST['evalAromaAlcohol'] ?? "", $_POST['evalAromaSweetness'] ?? "", $_POST['evalAromaAcidity'] ?? "");
						$evalAppearanceCheck = array($_POST['evalAppearanceClarity'] ?? "", $_POST['evalAppearanceHeadSize'] ?? "", $_POST['evalAppearanceHeadRetention'] ?? "");
						$evalFlavorCheck = array($_POST['evalFlavorMalt'] ?? "", $_POST['evalFlavorHops'] ?? "", $_POST['evalFlavorEsters'] ?? "", $_POST['evalFlavorPhenols'] ?? "", $_POST['evalFlavorSweetness'] ?? "", $_POST['evalFlavorBitterness'] ?? "", $_POST['evalFlavorAlcohol'] ?? "", $_POST['evalFlavorAcidity'] ?? "", $_POST['evalFlavorHarshness'] ?? "");
						$evalMouthfeelCheck = array($_POST['evalMouthfeelBody'] ?? "", $_POST['evalMouthfeelCarbonation'] ?? "", $_POST['evalMouthfeelWarmth'] ?? "", $_POST['evalMouthfeelCreaminess'] ?? "", $_POST['evalMouthfeelAstringency'] ?? "");

						$data['evalAromaChecklist'] = implode(", ", $evalAromaCheck);
						$data['evalAppearanceChecklist'] = implode(", ", $evalAppearanceCheck);
						$data['evalFlavorChecklist'] = implode(", ", $evalFlavorCheck);
						$data['evalMouthfeelChecklist'] = implode(", ", $evalMouthfeelCheck);
						$data['evalAromaChecklistDesc'] = $evalAromaChecklistDesc;
						$data['evalAppearanceChecklistDesc'] = $evalAppearanceChecklistDesc;
						$data['evalFlavorChecklistDesc'] = $evalFlavorChecklistDesc;
						$data['evalMouthfeelChecklistDesc'] = $evalMouthfeelChecklistDesc;
						$data['evalOverallChecklistDesc'] = $evalOverallChecklistDesc;
						$data['evalFlaws'] = $evalFlaws;

						if (isset($_POST['evalAromaComments'])) $data['evalAromaComments'] = eval_autosave_text($_POST['evalAromaComments'], $purifier);
						if (isset($_POST['evalAppearanceComments'])) $data['evalAppearanceComments'] = eval_autosave_text($_POST['evalAppearanceComments'], $purifier);
						if (isset($_POST['evalFlavorComments'])) $data['evalFlavorComments'] = eval_autosave_text($_POST['evalFlavorComments'], $purifier);
						if (isset($_POST['evalMouthfeelComments'])) $data['evalMouthfeelComments'] = eval_autosave_text($_POST['evalMouthfeelComments'], $purifier);
						if (isset($_POST['evalOverallComments'])) $data['evalOverallComments'] = eval_autosave_text($_POST['evalOverallComments'], $purifier);
						if (isset($_POST['evalStyleAccuracy'])) $data['evalStyleAccuracy'] = sterilize($_POST['evalStyleAccuracy']);
						if (isset($_POST['evalTechMerit'])) $data['evalTechMerit'] = sterilize($_POST['evalTechMerit']);
						if (isset($_POST['evalIntangibles'])) $data['evalIntangibles'] = sterilize($_POST['evalIntangibles']);
						if (isset($_POST['evalDrinkability'])) $data['evalDrinkability'] = sterilize($_POST['evalDrinkability']);
					}

					if (($form_type == "3") || ($form_type == "4")) {
						$exceptions = array(
							"evalSpecialIngredients","evalOtherNotes","evalAromaScore","evalAppearanceScore","evalFlavorScore","evalMouthfeelScore","evalOverallScore","evalOverallComments","evalStyleAccuracy","evalTechMerit","evalIntangibles","evalMiniBOS","evalBottle","evalBottleNotes","evalPosition_0","evalPosition_1","evalStyle","eid","uid","token","evalJudgeInfo","evalScoresheet","evalTable","evalSource","evalFormType","user_session_token"
						);
						$evalAroma = array();
						$evalAppearance = array();
						$evalFlavor = array();
						$evalMouthfeel = array();
						$evalFlaws = array();

						foreach ($_POST as $key => $value) {
							if (in_array($key, $exceptions)) continue;
							if (is_array($value)) {
								$new_value = array();
								foreach ($value as $v) {
									if ($v === "") continue;
									$new_value[] = is_numeric($v) ? sterilize($v) : eval_autosave_text($v, $purifier);
								}
								$value = implode(", ", $new_value);
							}
							else {
								if ($value === "") continue;
								$value = is_numeric($value) ? sterilize($value) : eval_autosave_text($value, $purifier);
							}

							if (strpos($key, "evalAroma") !== FALSE) $evalAroma[sterilize($key)] = $value;
							if (strpos($key, "evalAppearance") !== FALSE) {
								$clean_key = sterilize($key);
								if ($clean_key == "evalAppearanceColorChoice") {
									$clean_key = "evalAppearanceColor";
									if (($value == "999") && (isset($_POST['evalAppearanceColorOther']))) $value = eval_autosave_text($_POST['evalAppearanceColorOther'], $purifier);
								}
								$evalAppearance[$clean_key] = $value;
							}
							if (strpos($key, "evalFlavor") !== FALSE) $evalFlavor[sterilize($key)] = $value;
							if (strpos($key, "evalMouthfeel") !== FALSE) $evalMouthfeel[sterilize($key)] = $value;
							if (strpos($key, "evalFlaws") !== FALSE) $evalFlaws[] = $value;
						}

						$data['evalAromaChecklist'] = json_encode($evalAroma);
						$data['evalAppearanceChecklist'] = json_encode($evalAppearance);
						$data['evalFlavorChecklist'] = json_encode($evalFlavor);
						$data['evalMouthfeelChecklist'] = json_encode($evalMouthfeel);
						$data['evalFlaws'] = (!empty($evalFlaws)) ? implode(", ", $evalFlaws) : "";

						if (isset($_POST['evalOverallComments'])) $data['evalOverallComments'] = eval_autosave_text($_POST['evalOverallComments'], $purifier);
						if (isset($_POST['evalStyleAccuracy'])) $data['evalStyleAccuracy'] = sterilize($_POST['evalStyleAccuracy']);
						if (isset($_POST['evalTechMerit'])) $data['evalTechMerit'] = sterilize($_POST['evalTechMerit']);
						if (isset($_POST['evalIntangibles'])) $data['evalIntangibles'] = sterilize($_POST['evalIntangibles']);
					}

					if ($target_id > 0) {
						$query_eval_row = sprintf("SELECT id, evalJudgeInfo FROM %s WHERE id='%s' LIMIT 1", $update_table, $target_id);
						$eval_row = mysqli_query($connection, $query_eval_row) or die (mysqli_error($connection));
						$row_eval = mysqli_fetch_assoc($eval_row);
						if ((!$row_eval) || ((!$is_admin) && ($row_eval['evalJudgeInfo'] != $judge_id))) {
							$error_type = 2;
							$message = "Autosave target not allowed.";
							$target_id = 0;
						}
					}

					if (($target_id == 0) && ($error_type == 0)) {
						$query_draft = sprintf("SELECT id FROM %s WHERE eid='%s' AND evalJudgeInfo='%s' AND evalDraft='1' ORDER BY id DESC LIMIT 1", $update_table, $eid, $incoming_judge_id);
						$draft = mysqli_query($connection, $query_draft) or die (mysqli_error($connection));
						$row_draft = mysqli_fetch_assoc($draft);
						if ($row_draft) $target_id = $row_draft['id'];
					}

					if (($target_id > 0) && ($error_type == 0)) {
						$db_conn->where('id', $target_id);
						if ($db_conn->update($update_table, $data)) {
							$status = 1;
							$autosave_id = $target_id;
							$saved_at = $now;
						}
						else $error_type = 3;
					}

					if (($target_id == 0) && ($error_type == 0)) {
						$data['evalInitialDate'] = $now;
						$insert_id = $db_conn->insert($update_table, $data);
						if ($insert_id) {
							$status = 1;
							$autosave_id = $insert_id;
							$saved_at = $now;
						}
						else $error_type = 3;
					}

				}

			}

		}

		if ($go == "evalPlace") {
			$input = sterilize($_POST['evalPlace']);
			if (empty($input)) $data = array($go => NULL);
			else {
				if ($input == "0") $data = array($go => NULL);			
				else {
					if ($input == "4") $input = "5";
					$data = array($go => $input);
				}
			}
		}

		if ($go == "evalMiniBOS") {
			$input = sterilize($rid1);
			if (empty($input)) $data = array($go => 0);
			else $data = array($go => $input);
		}

		// Admin-only override: writes one agreed consensus score across every
		// non-draft evaluation row for the entry, bypassing per-judge ownership.
		// Used to fix missing/mismatched consensus so import_scores.ajax.php can
		// pick up the entry. Draft autosave rows are left untouched.
		elseif ($go == "evalSetConsensus") {

			if ($_SESSION['userLevel'] > 1) $error_type = 2; // admin only

			else {

				$eid = ((is_numeric($id)) && ($id > 0)) ? (int)$id : 0;
				$input = isset($_POST['evalFinalScore']) ? sterilize($_POST['evalFinalScore']) : "";

				if ($eid <= 0) $error_type = 1; // missing/invalid entry id

				elseif ((!is_numeric($input)) || ($input < 5) || ($input > 50)) $error_type = 1; // out of range

				else {

					$data = array('evalFinalScore' => (float)$input);

					$db_conn->where ('eid', $eid);
					if (check_update("evalDraft", $prefix."evaluation")) $db_conn->where("(evalDraft <> 1 OR evalDraft IS NULL)");

					if ($db_conn->update ($update_table, $data)) $status = 1;
					else $error_type = 3; // SQL error

				}

			}

		}

		elseif ($go != "evalDraft") {
			$db_conn->where ('eid', $id);
			if ($db_conn->update ($update_table, $data)) $status = 1;
			else $error_type = 3; // SQL error
		}

	} // end if ($action == "evaluation")

}

if (($session_active) && ($_SESSION['userLevel'] <= 1)) {

	if ($action == "brewing") {
		
		$eid = $id;
		if ($rid1 != "default") $brewBrewerID = $rid1;

		if ($go == "brewAdminNotes") {
			$input = sterilize($_POST['brewAdminNotes']);
		}

		if ($go == "brewStaffNotes") {
			$input = sterilize($_POST['brewStaffNotes']);
		}

		if ($go == "brewBoxNum") {
			$input = sterilize($_POST['brewBoxNum']);
		}

		if ($go == "brewJudgingNumber") {
			$post = str_replace("^","-",$_POST['brewJudgingNumber']);
			$input = sterilize($post);
			$input = strtolower($input);
		}

		if ($go == "brewPaid") {
			$input = sterilize($_POST['brewPaid']);
		}

		if ($go == "brewReceived") {
			$input = sterilize($_POST['brewReceived']);
		}

		$update_table = $prefix."brewing";

		if (empty($input)) {

			if ($rid2 == "text-col") {
				$data = array($go => '', 'brewUpdated' => date('Y-m-d H:i:s', time()));
			}

			else {
				$data = array($go => NULL, 'brewUpdated' => date('Y-m-d H:i:s', time()));
			}

		}

		else {

			if ($input == "0") {
				$data = array($go => NULL, 'brewUpdated' => date('Y-m-d H:i:s', time()));
			}

			else {
				$data = array($go => $input, 'brewUpdated' => date('Y-m-d H:i:s', time()));
			}

		}

		$db_conn->where ('id', $id);
		if ($db_conn->update ($update_table, $data)) $status = 1;
		else $error_type = 3; // SQL error

	} // END if ($action == "brewing")

	if ($action == "sponsors") {

		if ($go == "sponsorEnable") {
			$input = sterilize($_POST['sponsorEnable']);
		}

		if ($go == "sponsorLevel") {
			$input = sterilize($_POST['sponsorLevel']);
		}

		if ($go == "sponsorText") {
			$input = sterilize($_POST['sponsorText']);
		}

		if ($go == "sponsorImage") {
			$input = sterilize($_POST['sponsorImage']);
		}

		$update_table = $prefix."sponsors";

		if (empty($input)) {
			if ($rid2 == "text-col")  $data = array($go => '');
			else $data = array($go => NULL);
		}

		else {
			if ($input == "0") $data = array($go => NULL); 
			else $data = array($go => $input);
		}

		$db_conn->where ('id', $id);
		if ($db_conn->update ($update_table, $data)) $status = 1;
		else $error_type = 3; // SQL error
		
	} // END if ($action == "sponsors")

	if ($action == "judging_staff") {

		$update_table = $prefix."staff";

		if ($go == "staff_judge") $post = sterilize($_POST['staff_judge']);
		if ($go == "staff_steward") $post = sterilize($_POST['staff_steward']);
		if ($go == "staff_staff") $post = sterilize($_POST['staff_staff']);
		if ($go == "staff_judge_bos") $post = sterilize($_POST['staff_judge_bos']);
		
		if ($go == "staff_organizer") {

			$uid = sterilize($_POST['staff_organizer']);

			if (!empty($uid)) {

				// Clear organizer from the staff table
				$data = array('staff_organizer' => 0);
				$result = $db_conn->update ($update_table, $data);

				$query_org = sprintf("SELECT uid FROM %s WHERE uid='%s'", $prefix."staff", $uid);
				$org = mysqli_query($connection,$query_org) or die (mysqli_error($connection));
				$row_org = mysqli_fetch_assoc($org);
				$totalRows_org = mysqli_num_rows($org);
				
				if ($totalRows_org == 0) {
					
					$data = array(
						'staff_organizer' => 1,
						'staff_staff' => 0,
						'staff_judge' => 0,
						'staff_judge_bos' => 0,
						'staff_steward' => 0,
						'uid' => $uid
					);
					if ($db_conn->insert ($update_table, $data)) $status = 1;
					else $error_type = 3; // SQL error

				}

				else {

					if ($uid == $row_org['uid']) {

						$data = array(
							'staff_organizer' => 1,
							'staff_staff' => 0,
							'staff_judge' => 0,
							'staff_judge_bos' => 0,
							'staff_steward' => 0
						);
						$db_conn->where ('uid', $uid);
						if ($db_conn->update ($update_table, $data)) $status = 1;
						else $error_type = 3; // SQL error

					}

					else $error_type = 3; // SQL error
					
				}
				

			}

			else $error_type = 3;
			
		}

		else {

			if ((empty($post)) || ($post == 0)) $post = 0;
			else $post = 1;

			$staff_organizer = 0;
			$staff_staff = 0;
			$staff_judge = 0;
			$staff_judge_bos = 0;
			$staff_steward = 0;

			if ($go == "staff_staff") $staff_staff = $post;
			if ($go == "staff_judge") $staff_judge = $post;
			if ($go == "staff_steward") $staff_steward = $post;

			$query_staff_assign = sprintf("SELECT uid FROM %s WHERE uid='%s'",$update_table,$id);
			$staff_assign = mysqli_query($connection,$query_staff_assign) or die (mysqli_error($connection));
			$row_staff_assign = mysqli_fetch_assoc($staff_assign);
			$totalRows_staff_assign = mysqli_num_rows($staff_assign);

			if ($totalRows_staff_assign == 0) {

				$data = array(
					'staff_organizer' => $staff_organizer,
					'staff_staff' => $staff_staff,
					'staff_judge' => $staff_judge,
					'staff_judge_bos' => $staff_judge_bos,
					'staff_steward' => $staff_steward,
					'uid' => $id
				);
				if ($db_conn->insert ($update_table, $data)) $status = 1;
				else $error_type = 3; // SQL error

			}

			else {

				$data = array($go => $post);
				$db_conn->where ('uid', $id);
				if ($db_conn->update ($update_table, $data)) $status = 1;
				else $error_type = 3; // SQL error

			}
			
			if (($go == "staff_judge") || ($go == "staff_steward")) {

				// Unassign from any tables
				if ((empty($post)) || ($post == 0)) {

					if ($go == "staff_judge") $query_table_assign = sprintf("SELECT id FROM %s WHERE bid='%s' AND assignment='J'",$prefix."judging_assignments",$id);
					if ($go == "staff_steward") $query_table_assign = sprintf("SELECT id FROM %s WHERE bid='%s' AND assignment='S'",$prefix."judging_assignments",$id);
					$table_assign = mysqli_query($connection,$query_table_assign) or die (mysqli_error($connection));
					$row_table_assign = mysqli_fetch_assoc($table_assign);
					$totalRows_table_assign = mysqli_num_rows($table_assign);

					if ($totalRows_table_assign > 0) {

						do {

							$update_table = $prefix."judging_assignments";
							$db_conn->where ('id', $row_table_assign['id']);
							$result = $db_conn->delete($update_table);

						} while ($row_table_assign = mysqli_fetch_assoc($table_assign));
					}

				}
			
			}

		}		

	}
	
	// judging_scores DB Table
	if (($action == "judging_scores") || ($action == "judging_scores_bos")) {

		$eid = $id;
		$bid = "";
		$scoreTable = "";
		$scoreType = "";
		$scoreEntry = NULL;
		$scorePlace = NULL;
		$scoreMiniBOS = NULL;
		
		if ($rid1 != "default") $bid = $rid1;
		if ($rid2 != "default") $scoreTable = $rid2;
		if ($rid3 != "default") $scoreType = $rid3;

		if ($go == "scoreEntry") $post = $_POST['scoreEntry'];
		if ($go == "scorePlace") $post = $_POST['scorePlace'];
		if (($go == "scoreMiniBOS") && (!empty($_POST['scoreMiniBOS']))) $post = $_POST['scoreMiniBOS'];

		if ((empty($post)) || ($post == "null")) $post = 0;

		if (is_numeric($post)) {

			// For scores, all ajax input will be an integer - filter as such
			$input = sterilize($post);
			if (($go == "scorePlace") && ($input == "4")) $input = 5;

			// However, if that number is actually zero, make the value null instead for storage in DB
			if ($input == 0) $input = NULL;
			
			// First, query if there is a record with the eid
			$query_already_scored = sprintf("SELECT * FROM %s WHERE eid=%s", $prefix.$action, $eid);
			$already_scored = mysqli_query($connection,$query_already_scored) or die (mysqli_error($connection));
			$row_already_scored = mysqli_fetch_assoc($already_scored);
			$totalRows_already_scored = mysqli_num_rows($already_scored);

			if ($totalRows_already_scored == 1) {				
				
				$process = TRUE;

				$update_table = $prefix.$action;
				$data = array($go => $input);

				if ($process) {
					$db_conn->where ('id', $row_already_scored['id']);
					if ($db_conn->update ($update_table, $data)) $status = 1;
				}
				else $error_type = 3; // SQL error

			}

			else if ($totalRows_already_scored == 0) {

				if (($action == "judging_scores") && ($rid1 != "default") && ($rid2 != "default") && ($rid3 != "default")) $process = TRUE;
				if (($action == "judging_scores_bos") && ($rid1 != "default") && ($rid3 != "default")) $process = TRUE;
				if ($go == "scoreEntry") $scoreEntry = $input;	
				if ($go == "scorePlace") $scorePlace = $input;		
				if ($go == "scoreMiniBOS") $scoreMiniBOS = $input;

				$update_table = $prefix.$action;

				if ($action == "judging_scores") {

					$data = array(
						'eid' => $eid,
						'bid' => $bid,
						'scoreTable' => $scoreTable,
						'scoreEntry' => $scoreEntry,
						'scorePlace' => $scorePlace,
						'scoreType' => $scoreType,
						'scoreMiniBOS' => $scoreMiniBOS
					);

					if ($process) {
						if ($db_conn->insert ($update_table, $data)) $status = 1;
					}

					else $error_type = 3; // SQL error

				}

				if ($action == "judging_scores_bos") {

					$data = array(
						'eid' => $eid,
						'bid' => $bid,
						'scoreEntry' => $scoreEntry,
						'scorePlace' => $scorePlace,
						'scoreType' => $scoreType
					);

					if ($process) {
						if ($db_conn->insert ($update_table, $data)) $status = 1;
					}

					else $error_type = 3; // SQL error

				}

			}

			// If more than one in the DB, perform some functions
			else {
				if (($rid1 != "default") && ($rid2 != "default") && ($rid3 != "default")) $process = TRUE;
			}

		} // END if (is_numeric($post))

		else {
			$error_type = 1;
		}

	} // END if ($action == "scores")

}

if (!$session_active) $status = 9; // Session expired, not enabled, etc.

$return_json = array(
	"status" => "$status",
	"query" => "$sql",
	"post" => "$post",
	"input" => "$input",
	"id" => (!empty($autosave_id) ? $autosave_id : $id),
	"error_type" => "$error_type",
	"message" => $message,
	"saved_at" => $saved_at
);

// Return the json
echo json_encode($return_json);

/**  
 * The following is unfinished. Need more
 * thought into the various scenarios
 * associated with assigning judges and 
 * stewards to tables/flights/rounds.
 */

/*
if ($action == "judging_assignments") {

	if ($go == "assignFlight") {

		// https://test.brewcomp.com/ajax/save.ajax.php?action=judging_assignments&go=assignTable&id=212&rid1=1&rid2=J&rid3=1
		// $id = judge's uid
		// $rid1 = table id
		// $rid2 = judge or steward
		// $rid3 = round
		// $rid4 = assigned location
		// $_POST['assignFlight']

		// Do query if judge is already assigned in their specified role
		$query_already_assigned = sprintf("SELECT * FROM %s WHERE bid='%s' AND assignment='%s'", $prefix.$action, $id, $rid2);
		$already_assigned = mysqli_query($connection,$query_already_assigned) or die (mysqli_error($connection));
		$row_already_assigned = mysqli_fetch_assoc($already_assigned);
		$totalRows_already_assigned = mysqli_num_rows($already_assigned);

		// If no record of the user assigned to any table as either a judge or steward,
		// simply add a record.
		if ($totalRows_already_assigned == 0) {
			if ($_POST['assignFlight'] > 0) $sql .= sprintf("INSERT INTO `%s` (bid, assignment, assignTable, assignFlight, assignRound, assignLocation) VALUES (%s, %s, %s, %s, %s, %s)", $prefix.$action, $id, $rid2, $rid1, $_POST['assignFlight'], $rid3, $rid4);
		}

		// Otherwise, loop through user's assignments for the role
		
		else {

			do {

				// If assigned to the same round, check to see if the user chose to assign to current table. 
				// If the choice is to assign to current table, delete the previous record and add a new one with this assignment.
				// If the choice is NOT assign to current table, clear any records that may be there

				// Check if assigned to this round and table
				if (($row_already_assigned['assignRound'] == $rid3) && ($row_already_assigned['assignTable'] == $rid1)) {

					// Check to see if the user chose to assign. If so, update the record.
					if ($_POST['assignFlight'] > 0) {

						$sql .= sprintf("UPDATE `%s` SET assignFlight='%s' WHERE bid='%s' AND assignTable='%s' AND assignRound='%s'", $prefix.$action, $_POST['assignFlight'], $id, $rid1, $rid3);

						echo $sql."<br>";

					}

					// If the choice is NOT assign to current table, clear any records that may be there
					if ($_POST['assignFlight'] == 0) {
						$sql = sprintf("DELETE FROM `%s` WHERE bid='%s' AND assignTable='%s' AND assignRound='%s' AND assignFlight='%s'", $prefix.$action, $rid2, $id, $rid1, $rid3, $_POST['assignFlight']);

						echo $sql."<br>";
					}
				
				}

				// Check if assigned to this round at another table, but at same location:
				// add a second assignment for this table (multi-table support).
				if (($row_already_assigned['assignRound'] == $rid3) && ($row_already_assigned['assignTable'] != $rid1) && ($row_already_assigned['assignLocation'] == $rid4)) {
					
					if ($_POST['assignFlight'] > 0) $sql .= sprintf("INSERT INTO `%s` (bid, assignment, assignTable, assignFlight, assignRound, assignLocation) VALUES (%s, %s, %s, %s, %s, %s)", $prefix.$action, $id, $rid2, $rid1, $_POST['assignFlight'], $rid3, $rid4);
				}

			} while ($row_already_assigned = mysqli_fetch_assoc($already_assigned));

		}

	}

	if ($go == "assignRoles") {
		
		$input = sterilize($rid1);
		
		if (empty($input)) {
			$sql = sprintf("UPDATE `%s` SET %s=NULL WHERE assignTable='%s'", $prefix.$action, $go, $id);
		}

		else {

			// Check if ID is assigned to the table, if so, change
			// If not, flag
			$sql = sprintf("UPDATE `%s` SET %s='HJ' WHERE bid='%s' AND assignTable='%s'", $prefix.$action, $go, $input, $id);

		}

		mysqli_real_escape_string($connection,$sql);
		$result = mysqli_query($connection,$sql) or die (mysqli_error($connection));

	} // end if ($go == "assignRoles")

	mysqli_real_escape_string($connection,$sql);
	$result = mysqli_query($connection,$sql) or die (mysqli_error($connection));

	// If successful, change $status from fail (0) to success (1)
	if ($result) $status = 1;
	else $error_type = 3; // SQL error

}

*/

?>