<?php

ob_start();
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
require('../paths.php');
require(CONFIG.'bootstrap.php');
include (LIB.'eval_overview.lib.php');
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

$response = array(
	"success" => false,
	"session_id" => 0,
	"updated" => "",
	"updated_ts" => time(),
	"tables" => array(),
	"message" => ""
);

$session_id = 0;
if ((isset($_GET['session_id'])) && (is_numeric($_GET['session_id']))) $session_id = (int) sterilize($_GET['session_id']);
elseif ((isset($_POST['session_id'])) && (is_numeric($_POST['session_id']))) $session_id = (int) sterilize($_POST['session_id']);

if ((isset($_SESSION['session_set_'.$prefix_session])) && (isset($_SESSION['loginUsername'])) && ($_SESSION['userLevel'] <= 1)) {

	if (($session_id > 0) && (isset($_SESSION['prefsEval'])) && ($_SESSION['prefsEval'] == 1) && (session_pref_enabled('prefsEvalAdminTools', 1))) {

		$response['success'] = true;
		$response['session_id'] = $session_id;
		$response['tables'] = get_eval_overview_tables($session_id);
		$response['updated_ts'] = time();
		$response['updated'] = getTimeZoneDateTime($_SESSION['prefsTimeZone'], time(), $_SESSION['prefsDateFormat'], $_SESSION['prefsTimeFormat'], "short", "date-time");

	}
	else $response['message'] = "Invalid session.";

}
else $response['message'] = "Unauthorized.";

echo json_encode($response);
