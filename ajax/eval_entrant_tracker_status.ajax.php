<?php

ob_start();
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

require('../paths.php');
require(CONFIG.'bootstrap.php');
include (LIB.'eval_entrant_tracker.lib.php');

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

$response = array(
	"success" => false,
	"updated" => "",
	"updated_ts" => time(),
	"entrants" => array(),
	"counts" => array(
		"selected" => 0,
		"clear" => 0,
		"pending" => 0,
		"has_place" => 0,
		"bos_pull" => 0,
		"gold" => 0
	),
	"message" => ""
);

if ((isset($_SESSION['session_set_'.$prefix_session])) && (isset($_SESSION['loginUsername'])) && ($_SESSION['userLevel'] <= 1) && (isset($_SESSION['prefsEval'])) && ($_SESSION['prefsEval'] == 1) && (session_pref_enabled('prefsEvalAdminTools', 1))) {

	$mode = "entrant";
	if (isset($_GET['mode'])) $mode = strtolower(trim((string)$_GET['mode']));
	elseif (isset($_POST['mode'])) $mode = strtolower(trim((string)$_POST['mode']));
	if (($mode !== "entrant") && ($mode !== "category") && ($mode !== "session")) $mode = "entrant";

	$uids_raw = "";
	$cids_raw = "";
	$sids_raw = "";
	if (isset($_GET['uids'])) $uids_raw = sterilize($_GET['uids']);
	elseif (isset($_POST['uids'])) $uids_raw = sterilize($_POST['uids']);
	if (isset($_GET['cids'])) $cids_raw = sterilize($_GET['cids']);
	elseif (isset($_POST['cids'])) $cids_raw = sterilize($_POST['cids']);
	if (isset($_GET['sids'])) $sids_raw = sterilize($_GET['sids']);
	elseif (isset($_POST['sids'])) $sids_raw = sterilize($_POST['sids']);

	$uids = eval_entrant_tracker_normalize_uids($uids_raw);
	$cids = eval_entrant_tracker_normalize_category_ids($cids_raw);
	$sids = eval_entrant_tracker_normalize_session_ids($sids_raw);
	$include_eval_places = ((isset($_SESSION['prefsEval'])) && ($_SESSION['prefsEval'] == 1));

	$response['success'] = true;
	$response['updated_ts'] = time();
	$response['updated'] = getTimeZoneDateTime($_SESSION['prefsTimeZone'], time(), $_SESSION['prefsDateFormat'], $_SESSION['prefsTimeFormat'], "short", "date-time");

	if (($mode === "category") && (!empty($cids))) {
		$response['entrants'] = get_category_tracker_status($cids, $include_eval_places);
		$response['counts']['selected'] = count($response['entrants']);
	}
	elseif (($mode === "session") && (!empty($sids))) {
		$response['entrants'] = get_session_tracker_status($sids);
		$response['counts']['selected'] = count($response['entrants']);
	}
	elseif (($mode === "entrant") && (!empty($uids))) {
		$response['entrants'] = get_entrant_tracker_status($uids, $include_eval_places);
		$response['counts']['selected'] = count($response['entrants']);
	}

	if (!empty($response['entrants'])) {
		foreach ($response['entrants'] as $entrant) {
			if ($mode === "session") {
				if ($entrant['status'] == "ready") $response['counts']['gold'] += 1;
				elseif ($entrant['status'] == "issues") $response['counts']['bos_pull'] += 1;
				elseif ($entrant['status'] == "in_progress") $response['counts']['pending'] += 1;
				else $response['counts']['clear'] += 1;
			}
			else {
				if ($entrant['status'] == "gold") $response['counts']['gold'] += 1;
				elseif ($entrant['status'] == "bos_pull") $response['counts']['bos_pull'] += 1;
				elseif ($entrant['status'] == "pending") $response['counts']['pending'] += 1;
				elseif ($entrant['status'] == "has_place") $response['counts']['has_place'] += 1;
				else $response['counts']['clear'] += 1;
			}
		}
	}
}

else $response['message'] = "Unauthorized.";

echo json_encode($response);
