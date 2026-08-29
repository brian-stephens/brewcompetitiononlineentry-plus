<?php

ob_start();
require('../paths.php');
require(CONFIG.'bootstrap.php');
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

$status  = 0;
$message = "";
$data    = array();

$action_req = isset($_GET['action']) ? sterilize($_GET['action']) : "";
$code       = isset($_GET['code'])   ? sterilize($_GET['code'])   : "";

$session_active = FALSE;
if ((isset($_SESSION['session_set_'.$prefix_session])) && (isset($_SESSION['loginUsername']))) $session_active = TRUE;

if ($session_active && $_SESSION['userLevel'] <= 2) {

  $data_dir = ROOT.'data'.DIRECTORY_SEPARATOR.'bjcp'.DIRECTORY_SEPARATOR;

  // Helper: load a JSON file and decode
  function load_json($path) {
    if (!file_exists($path)) return NULL;
    $raw = file_get_contents($path);
    return json_decode($raw, TRUE);
  }

  // Determine which guideline file a style code belongs to
  function guideline_file_for_code($code, $data_dir) {
    $first = strtoupper(substr($code, 0, 1));
    if ($first === 'M') return $data_dir.'bjcp_2015_mead.json';
    if ($first === 'C') return $data_dir.'bjcp_2025_cider.json';
    return $data_dir.'bjcp_2021_beer.json';
  }

  if ($action_req === "index") {

    // Return the full combined style list for Tom Select
    $index = load_json($data_dir.'style_index.json');
    if ($index !== NULL) {
      $status = 1;
      $data   = $index;
    } else {
      $status  = 0;
      $message = "Style index not found.";
    }

  }

  elseif ($action_req === "style" && !empty($code)) {

    // Sanitize: only allow alphanumeric codes
    $code = preg_replace('/[^A-Za-z0-9]/', '', $code);
    $code = strtoupper($code);

    $file = guideline_file_for_code($code, $data_dir);
    $json = load_json($file);
    if ($json !== NULL && isset($json['styles'][$code])) {
      $status = 1;
      $data   = $json['styles'][$code];
    } else {
      $status  = 0;
      $message = "Style not found: ".$code;
    }

  }

  else {
    $status  = 0;
    $message = "Unknown action.";
  }

}

else {
  $status  = 9;
  $message = "Session expired or insufficient permissions.";
}

$return_json = array(
  "status"  => $status,
  "message" => $message,
  "data"    => $data,
);

header('Content-Type: application/json');
echo json_encode($return_json, JSON_UNESCAPED_UNICODE);
mysqli_close($connection);

?>
