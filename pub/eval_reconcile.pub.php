<?php

include (LIB.'output.lib.php');

// Fallbacks so a missing language string can't blank the page header or break output.
if (!isset($label_waiting_for_partner)) $label_waiting_for_partner = "Waiting for Other Judge(s)";
if (!isset($label_consensus_set)) $label_consensus_set = "Consensus Score";
if (!isset($label_waiting)) $label_waiting = "Waiting";
if (!isset($evaluation_info_119)) $evaluation_info_119 = "Your scoresheet has been submitted. Once another judge assigned to this entry finishes their evaluation, you'll see everyone's scores and the automatically calculated consensus score here.";
if (!isset($evaluation_info_120)) $evaluation_info_120 = "This page checks automatically for other judges' scores. You may also leave this page and come back later from the Judging Dashboard.";
if (!isset($evaluation_info_128)) $evaluation_info_128 = "Sync checks every few seconds.";
if (!isset($evaluation_info_138)) $evaluation_info_138 = "This is the average of every judge's score for this entry, calculated automatically - no action is needed from you. It updates as judges add or edit their evaluations.";

$eid = "";
if (isset($_GET['id'])) $eid = sterilize($_GET['id']);

$reconcile_error = "";
$row_my_eval = "";
$eval_rows = array();
$other_judges_count = 0;
$my_row_id = "";
$judges_scored = array();
$consensus_score = "";
$entry_number_display = "";

if ((empty($eid)) || (!is_numeric($eid))) {

	$reconcile_error = $error_text_000;

}

else {

	$eval_draft_filter_sql = "";
	if (check_update("evalDraft", $prefix."evaluation")) $eval_draft_filter_sql = " AND (evalDraft <> '1' OR evalDraft IS NULL)";
	$query_eval_rows = sprintf("SELECT * FROM %s WHERE eid='%s'%s ORDER BY id ASC", $prefix."evaluation", $eid, $eval_draft_filter_sql);
	$eval_rows_result = mysqli_query($connection,$query_eval_rows) or die (mysqli_error($connection));

	while ($row = mysqli_fetch_assoc($eval_rows_result)) {
		$eval_rows[] = $row;
		if ((isset($_SESSION['user_id'])) && ($row['evalJudgeInfo'] == $_SESSION['user_id'])) $row_my_eval = $row;
	}

	// If we can't find an evaluation from the current judge for this entry, they
	// have no business being on this page.
	if (empty($row_my_eval)) $reconcile_error = $error_text_000;

}

if (empty($reconcile_error)) {

	$query_entry_info = sprintf("SELECT * FROM %s WHERE id='%s'", $prefix."brewing", $eid);
	$entry_info = mysqli_query($connection,$query_entry_info) or die (mysqli_error($connection));
	$row_entry_info = mysqli_fetch_assoc($entry_info);

	if (empty($row_entry_info)) $reconcile_error = $error_text_000;
	else {

		if ($_SESSION['prefsDisplaySpecial'] == "J") $entry_number_display = sprintf("%06s",$row_entry_info['brewJudgingNumber']);
		else $entry_number_display = $row_entry_info['brewName'];

		// Build a judge-friendly list of everyone who has scored this entry so far.
		// The consensus/assigned score (evalFinalScore) is computed automatically as
		// the average of every judge's total, and kept in sync across every row for
		// the entry - so it's the same value regardless of which judge's row we read.
		foreach ($eval_rows as $row_judge_eval) {

			$judge_total = $row_judge_eval['evalAromaScore'] + $row_judge_eval['evalAppearanceScore'] + $row_judge_eval['evalFlavorScore'] + $row_judge_eval['evalMouthfeelScore'] + $row_judge_eval['evalOverallScore'];

			$judge_name = $label_judge_score;
			$query_judge_name = sprintf("SELECT brewerFirstName,brewerLastName FROM %s WHERE uid='%s'", $prefix."brewer", $row_judge_eval['evalJudgeInfo']);
			$judge_name_result = mysqli_query($connection,$query_judge_name) or die (mysqli_error($connection));
			$row_judge_name = mysqli_fetch_assoc($judge_name_result);
			if (!empty($row_judge_name)) $judge_name = $row_judge_name['brewerFirstName']." ".mb_substr($row_judge_name['brewerLastName'],0,1).".";

			$is_me = ((isset($_SESSION['user_id'])) && ($row_judge_eval['evalJudgeInfo'] == $_SESSION['user_id']));

			$judges_scored[] = array(
				"judge_id" => $row_judge_eval['evalJudgeInfo'],
				"is_me" => $is_me,
				"name" => $judge_name,
				"total" => $judge_total
			);

		}

		$other_judges_count = count($eval_rows) - 1;
		$my_row_id = $row_my_eval['id'];

		if (($row_my_eval['evalFinalScore'] !== NULL) && ($row_my_eval['evalFinalScore'] !== "")) $consensus_score = $row_my_eval['evalFinalScore'];

	}

}

?>

<?php if (empty($reconcile_error)) { ?>
<script type="text/javascript">
// Successful submit landing - safe to drop this scoresheet's draft now.
// Prefer form-scoped clear when available so unrelated drafts are preserved.
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
</script>
<?php } ?>

<?php if (!empty($reconcile_error)) { ?>

<p class="alert alert-danger"><?php echo $reconcile_error; ?></p>
<a class="btn btn-primary" href="<?php echo $base_url; ?>index.php?section=evaluation&amp;go=default"><?php echo $label_judging_dashboard; ?></a>

<?php } else { ?>

<p class="lead mb-1"><strong><?php echo $entry_number_display; ?></strong></p>

<div class="card mb-4">
	<div class="card-body">
		<div class="table-responsive mb-2" style="max-width:24rem;">
			<table class="table table-sm align-middle mb-0" id="reconcile-judges-table">
				<thead>
					<tr>
						<th scope="col"><?php echo $label_judge; ?></th>
						<th scope="col" class="text-end text-nowrap" style="width:5.5rem;"><?php echo $label_score; ?></th>
					</tr>
				</thead>
				<tbody id="reconcile-judges-list">
					<?php foreach ($judges_scored as $judge_scored) { ?>
					<tr>
						<td><?php if ($judge_scored['is_me']) echo "<strong>".$label_your_score."</strong>"; else echo htmlentities($judge_scored['name']); ?></td>
						<td class="text-end"><?php echo $judge_scored['total']; ?></td>
					</tr>
					<?php } ?>
				</tbody>
			</table>
		</div>
		<div class="small text-muted d-flex align-items-center gap-2" id="reconcile-sync-wrap">
			<div class="spinner-border spinner-border-sm text-secondary" role="status" aria-hidden="true"></div>
			<span id="reconcile-sync-status"><?php echo $evaluation_info_128; ?></span>
		</div>
		<div class="small text-success mt-1 d-none" id="reconcile-partner-activity"></div>
	</div>
</div>

<?php if ($other_judges_count < 1) { ?>

<!-- Waiting screen: no other judge has scored this entry yet -->
<div id="reconcile-waiting" class="card border-info-subtle mb-4">
	<div class="card-body">
		<h3 class="card-title"><i class="fa fa-clock text-info-emphasis me-2"></i><?php echo $label_waiting_for_partner; ?></h3>
		<p><?php echo $evaluation_info_119; ?></p>
		<p class="small text-muted"><?php echo $evaluation_info_120; ?></p>
		<div class="d-flex align-items-center gap-2 mb-3">
			<div class="spinner-border spinner-border-sm text-info" role="status"></div>
			<span id="reconcile-waiting-status"><?php echo $label_waiting; ?>&hellip;</span>
		</div>
	</div>
</div>

<?php } else { ?>

<!-- Consensus screen: at least one other judge has scored this entry -->
<div id="reconcile-form" class="card border-success-subtle mb-4">
	<div class="card-body">

		<h3 class="card-title"><?php echo $label_consensus_set; ?></h3>

		<div id="reconcile-success" class="alert alert-success">
			<i class="fa fa-check-circle me-2"></i><span id="reconcile-success-msg"><?php echo $label_consensus_set; ?>: <span id="reconcile-success-score"><?php echo $consensus_score; ?></span></span>
		</div>

		<p class="small text-muted"><?php echo $evaluation_info_138; ?></p>

	</div>
</div>

<?php } ?>

<a class="btn btn-secondary" href="<?php echo $base_url; ?>index.php?section=evaluation&amp;go=scoresheet&amp;action=edit&amp;id=<?php echo $my_row_id; ?>"><i class="fa fa-pencil me-2"></i><?php echo $label_edit; ?> <?php echo $label_view_my_eval; ?></a>
<a class="btn btn-primary" href="<?php echo $base_url; ?>index.php?section=evaluation&amp;go=default"><?php echo $label_judging_dashboard; ?></a>

<style type="text/css">
.reconcile-flash {
	animation: reconcileFlash 1.2s ease;
}
@keyframes reconcileFlash {
	0% { background-color: rgba(25, 135, 84, 0.25); }
	100% { background-color: transparent; }
}
</style>

<script type="text/javascript">
$(document).ready(function() {

	var eid = <?php echo json_encode($eid); ?>;
	var otherJudgesCount = <?php echo json_encode($other_judges_count); ?>;
	var ajaxUrl = "<?php echo htmlspecialchars($ajax_url, ENT_QUOTES, 'UTF-8'); ?>";
	var pollInterval = null;
	var pollEveryMs = 4000;
	var partnerJoinedTemplate = <?php echo json_encode($evaluation_info_127 ?? "%s just submitted a score of %s."); ?>;
	var yourScoreLabel = <?php echo json_encode($label_your_score); ?>;
	var genericJudgeLabel = <?php echo json_encode($label_judge_score); ?>;
	var syncHelpText = <?php echo json_encode($evaluation_info_128); ?>;

	// Track each judge's last-seen total (keyed by judge id) so we only
	// flash/announce when a partner's score actually appears or changes.
	var lastKnownJudgeTotal = {};
	<?php foreach ($judges_scored as $judge_scored) { ?>
	lastKnownJudgeTotal[<?php echo json_encode((string)$judge_scored['judge_id']); ?>] = <?php echo json_encode($judge_scored['total']); ?>;
	<?php } ?>
	var lastKnownConsensus = <?php echo json_encode($consensus_score); ?>;

	function escapeHtml(value) {
		return $("<div/>").text(value).html();
	}

	function formatScore(value) {
		var parsed = parseFloat(value);
		if (isNaN(parsed)) return value;
		if (Math.round(parsed) === parsed) return parsed.toString();
		return parsed.toFixed(1);
	}

	function flashConsensusUi() {
		$("#reconcile-judges-list, #reconcile-success").addClass("reconcile-flash");
		setTimeout(function() {
			$("#reconcile-judges-list, #reconcile-success").removeClass("reconcile-flash");
		}, 1300);
	}

	function setSyncStatus(text) {
		$("#reconcile-sync-status").text(text);
	}

	function getJudgeDisplayName(j) {
		return (j.name && j.name !== "") ? j.name : genericJudgeLabel;
	}

	function renderJudges(judges) {
		if (!judges || !judges.length) return;
		var html = "";
		for (var i = 0; i < judges.length; i++) {
			var j = judges[i];
			var displayName = (j.is_me) ? "<strong>" + escapeHtml(yourScoreLabel) + "</strong>" : escapeHtml(getJudgeDisplayName(j));
			html += "<tr>";
			html += "<td>" + displayName + "</td>";
			html += "<td class=\"text-end\">" + escapeHtml(formatScore(j.total)) + "</td>";
			html += "</tr>";
		}
		$("#reconcile-judges-list").html(html);
	}

	// Compares this poll's per-judge totals against what we last saw and
	// returns the first *partner* whose score newly appeared or changed, so
	// we can attribute a "so-and-so just submitted..." message to them.
	function findChangedPartner(judges) {
		var changed = null;
		for (var i = 0; i < judges.length; i++) {
			var j = judges[i];
			var key = String(j.judge_id);
			var previous = lastKnownJudgeTotal.hasOwnProperty(key) ? lastKnownJudgeTotal[key] : null;
			if ((!j.is_me) && (j.total !== previous)) changed = j;
			lastKnownJudgeTotal[key] = j.total;
		}
		return changed;
	}

	var partnerActivityTimeout = null;

	function announcePartnerActivity(partner) {
		var scoreFormatted = formatScore(partner.total);
		var msg = partnerJoinedTemplate.replace("%s", escapeHtml(getJudgeDisplayName(partner))).replace("%s", escapeHtml(scoreFormatted));
		$("#reconcile-partner-activity").html(msg).removeClass("d-none");
		if (partnerActivityTimeout) clearTimeout(partnerActivityTimeout);
		partnerActivityTimeout = setTimeout(function() {
			$("#reconcile-partner-activity").addClass("d-none");
		}, 8000);
	}

	function updateConsensusDisplay(consensusScore) {
		var scoreFormatted = formatScore(consensusScore);
		$("#reconcile-success-score").text(scoreFormatted);
	}

	function pollReconcileData() {
		$.get(ajaxUrl + "check_partner_eval.ajax.php", { eid: eid }, function(data) {
			if (!data) {
				setSyncStatus(syncHelpText);
				return;
			}

			renderJudges(data.judges);
			setSyncStatus("Last checked just now.");

			var changedPartner = findChangedPartner(data.judges || []);
			var consensusChanged = (data.consensus_score !== lastKnownConsensus);
			lastKnownConsensus = data.consensus_score;

			if ((otherJudgesCount < 1) && (data.judge_count > 1)) {
				clearInterval(pollInterval);
				$("#reconcile-waiting-status").html("<?php echo $label_consensus_set; ?>...");
				location.reload();
				return;
			}

			if (otherJudgesCount >= 1) {
				if (changedPartner) announcePartnerActivity(changedPartner);
				if ((changedPartner) || (consensusChanged)) {
					updateConsensusDisplay(data.consensus_score);
					flashConsensusUi();
				}
			}
		}, "json")
		.fail(function() {
			setSyncStatus("Sync paused. Retrying...");
		});
	}

	pollReconcileData();
	pollInterval = setInterval(function() {
		pollReconcileData();
	}, pollEveryMs);

});
</script>

<?php } ?>
