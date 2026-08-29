<?php 

$scoresheet_display = array();
$archive_suffix = "";
$authorized_scoresheet_view = FALSE;
$is_logged_in = isset($_SESSION['loginUsername']);

if ($dbTable != "default") {
    $archive_suffix = "_".get_suffix($dbTable);
}

if (($is_logged_in) && (isset($_SESSION['user_id'])) && (isset($_SESSION['userLevel']))) {

	$entry_id_for_auth = 0;

	if ($view == "all") {
		$entry_id_for_auth = (int)$id;
	}

	else {
		$entry_id_for_auth = resolve_eval_entry_id($id, $dbTable);
	}

	if ($entry_id_for_auth > 0) {
		$authorized_scoresheet_view = can_view_entry_evaluation(
			$entry_id_for_auth,
			$_SESSION['user_id'],
			$_SESSION['userLevel'],
			TRUE,
			TRUE,
			$archive_suffix
		);
	}
}

if (!$authorized_scoresheet_view) {
    $redirect = "../../403.php";
    $redirect_go_to = sprintf("Location: %s", $redirect);
    header($redirect_go_to);
    exit();
}

if ($dbTable == "default") $dbTable = $prefix."evaluation";

if ($view == "all") {   

    $query_eval_all = sprintf("SELECT id FROM %s WHERE eid=%s", $dbTable, $id);
    $eval_all = mysqli_query($connection,$query_eval_all) or die (mysqli_error($connection));
    $row_eval_all = mysqli_fetch_assoc($eval_all);
    $totalRows_eval_all = mysqli_num_rows($eval_all);

    if ($totalRows_eval_all > 0) {
        do {
            $scoresheet_display[] = $row_eval_all['id'];
        } while($row_eval_all = mysqli_fetch_assoc($eval_all));
    }

}

else $scoresheet_display[] = $id;

foreach ($scoresheet_display as $id) {
	
	include (EVALS.'db.eval.php');
    include (EVALS.'scoresheet_head.eval.php');

    // Display scoresheet based upon type declared in the record
    if ($row_eval['evalScoresheet'] == 1) include (EVALS.'full_output.eval.php');
	if ($row_eval['evalScoresheet'] == 2) include (EVALS.'checklist_output.eval.php');
	if ($row_eval['evalScoresheet'] == 3) include (EVALS.'structured_output.eval.php');
    if ($row_eval['evalScoresheet'] == 4) include (EVALS.'structured_output.eval.php');

} // end foreach ($scoresheet_display as $id)


?>

