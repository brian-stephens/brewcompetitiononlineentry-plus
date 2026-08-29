<?php

// Redirect if directly accessed without authenticated session
if ((!isset($_SESSION['loginUsername'])) || ((isset($_SESSION['loginUsername'])) && ($_SESSION['userLevel'] > 1))) {
    $redirect = "../../403.php";
    $redirect_go_to = sprintf("Location: %s", $redirect);
    header($redirect_go_to);
    exit();
}

// Redirect if the judge analytics preference is disabled
if ($_SESSION['jPrefsJudgeStats'] != "Y") {
    $redirect = $base_url."index.php?section=admin";
    $redirect_go_to = sprintf("Location: %s", $redirect);
    header($redirect_go_to);
    exit();
}

$judging_analytics = get_all_judges_analytics_stats();

$html = "";

if (!empty($judging_analytics['judges'])) {

	foreach ($judging_analytics['judges'] as $judge_id => $judge_stats) {

		$query_judge_name = sprintf("SELECT brewerFirstName,brewerLastName FROM %s WHERE uid='%s'", $prefix."brewer", $judge_id);
		$judge_name_result = mysqli_query($connection,$query_judge_name) or die (mysqli_error($connection));
		$row_judge_name = mysqli_fetch_assoc($judge_name_result);

		if (!empty($row_judge_name)) $judge_name = $row_judge_name['brewerLastName'].", ".$row_judge_name['brewerFirstName'];
		else $judge_name = "Judge #".$judge_id;

		$words_delta = "";
		if ($judging_analytics['competition']['comp_avg_words'] > 0) $words_delta = round((($judge_stats['judge_avg_words'] - $judging_analytics['competition']['comp_avg_words']) / $judging_analytics['competition']['comp_avg_words']) * 100);

		$duration_delta = "";
		if (($judging_analytics['competition']['comp_avg_duration'] > 0) && ($judge_stats['judge_duration_count'] > 0)) $duration_delta = round((($judge_stats['judge_avg_duration'] - $judging_analytics['competition']['comp_avg_duration']) / $judging_analytics['competition']['comp_avg_duration']) * 100);

		$judge_avg_duration_disp = ($judge_stats['judge_duration_count'] > 0) ? gmdate("i:s", $judge_stats['judge_avg_duration']) : "N/A";

		// Flag judges notably below the competition average - possible early signal of rushed evaluations
		$row_class = "";
		if ((($words_delta !== "") && ($words_delta <= -30)) || (($duration_delta !== "") && ($duration_delta <= -30))) $row_class = " class=\"table-warning\"";

		$html .= "<tr".$row_class.">";
		$html .= "<td>".htmlspecialchars($judge_name, ENT_QUOTES, 'UTF-8')."</td>";
		$html .= "<td>".$judge_stats['judge_count']."</td>";
		$html .= "<td>".$judge_stats['judge_avg_words']."</td>";
		$html .= "<td>".($words_delta !== "" ? $words_delta."%" : "N/A")."</td>";
		$html .= "<td>".$judge_avg_duration_disp."</td>";
		$html .= "<td>".($duration_delta !== "" ? $duration_delta."%" : "N/A")."</td>";
		$html .= "</tr>";

	}

}

?>

<p class="lead"><?php echo $_SESSION['contestName']; ?>: Judge Analytics</p>

<div class="bcoem-admin-element hidden-print">
	<p>Compares each judge's average words typed and average time spent per entry against the competition-wide average. Rows highlighted in yellow are judges averaging 30% or more below the competition on either metric - possibly worth a friendly check-in.</p>
	<p><strong>Competition average:</strong> <?php echo $judging_analytics['competition']['comp_avg_words']; ?> words/entry
	<?php if ($judging_analytics['competition']['comp_duration_count'] > 0) { ?> &nbsp;|&nbsp; <?php echo gmdate("i:s", $judging_analytics['competition']['comp_avg_duration']); ?> min:sec/entry<?php } ?>
	</p>
</div>

<?php if (!empty($judging_analytics['judges'])) { ?>
<script type="text/javascript" language="javascript">
	$(document).ready(function() {
		$('#judging-analytics-table').dataTable( {
			"bPaginate" : false,
			"sDom": 'fprtp',
			"bStateSave" : false,
			"bLengthChange" : false,
			"aaSorting": [[0,'asc']],
			"aoColumns": [
				null,
				null,
				null,
				null,
				null,
				null
				]
		} );
	} );
</script>
<table class="table table-responsive table-bordered" id="judging-analytics-table">
<thead>
	<tr>
		<th>Judge</th>
		<th>Entries Judged</th>
		<th>Avg. Words/Entry</th>
		<th>Words Delta vs. Avg.</th>
		<th>Avg. Time/Entry</th>
		<th>Time Delta vs. Avg.</th>
	</tr>
</thead>
<tbody>
<?php echo $html; ?>
</tbody>
</table>
<?php } else { ?>
<p>No evaluations have been submitted yet.</p>
<?php } ?>
