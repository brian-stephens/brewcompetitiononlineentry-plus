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
	"results" => array(),
	"message" => ""
);

if ((isset($_SESSION['session_set_'.$prefix_session])) && (isset($_SESSION['loginUsername'])) && ($_SESSION['userLevel'] <= 1) && (isset($_SESSION['prefsEval'])) && ($_SESSION['prefsEval'] == 1) && (session_pref_enabled('prefsEvalAdminTools', 1))) {
	$mode = "entrant";
	if (isset($_GET['mode'])) $mode = strtolower(trim((string)$_GET['mode']));
	elseif (isset($_POST['mode'])) $mode = strtolower(trim((string)$_POST['mode']));
	if (($mode !== "entrant") && ($mode !== "category") && ($mode !== "session")) $mode = "entrant";

	$query = "";
	if (isset($_GET['q'])) $query = trim((string)$_GET['q']);
	elseif (isset($_POST['q'])) $query = trim((string)$_POST['q']);

	// Keep search text usable; escape happens in the query builder.
	$query = strip_tags($query);
	$query = trim($query);

	$limit = 15;
	if ((isset($_GET['limit'])) && (is_numeric($_GET['limit']))) $limit = (int)$_GET['limit'];
	elseif ((isset($_POST['limit'])) && (is_numeric($_POST['limit']))) $limit = (int)$_POST['limit'];

	$response['success'] = true;
	if (strlen($query) >= 2) {
		try {
			if ($mode === "category") $response['results'] = get_category_tracker_search($query, $limit);
			elseif ($mode === "session") $response['results'] = get_session_tracker_search($query, $limit);
			else $response['results'] = get_entrant_tracker_search($query, $limit);
		}
		catch (Exception $e) {
			$response['success'] = false;
			$response['message'] = "Search failed.";
			$response['results'] = array();
		}
	}
}

else $response['message'] = "Unauthorized.";

ob_clean();
echo json_encode($response);
