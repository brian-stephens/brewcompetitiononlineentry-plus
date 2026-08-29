<?php

/**
 * Admin: Entry Evaluations Overview
 * Session-scoped progress dashboard with per-table progress bars,
 * issue summaries, gated import, and AJAX refresh.
 */

if ((!isset($_SESSION['userLevel'])) || ($_SESSION['userLevel'] > 1) || (!isset($_SESSION['prefsEval'])) || ($_SESSION['prefsEval'] != 1) || (!session_pref_enabled('prefsEvalAdminTools', 1))) {
	echo "<div class=\"alert alert-danger\"><p><strong>Access denied.</strong></p></div>";
	return;
}

include_once (LIB.'admin.lib.php');
include_once (LIB.'eval_overview.lib.php');
include_once (DB.'admin_common.db.php');

$overview_session_id = 0;
$overview_session_label = "";
$overview_sessions = array();
$overview_tables = array();

$query_sessions = sprintf("SELECT id, judgingLocName, judgingDate, judgingDateEnd FROM %s WHERE judgingLocType < 2 ORDER BY judgingDate ASC", $prefix."judging_locations");
$sessions_rs = mysqli_query($connection, $query_sessions) or die (mysqli_error($connection));
while ($row_session = mysqli_fetch_assoc($sessions_rs)) {
	$overview_sessions[] = $row_session;
}

$session_count = count($overview_sessions);

// Resolve selected session from ?session=
if (($judging_session_filter != "default") && (is_numeric($judging_session_filter))) {
	$overview_session_id = (int) $judging_session_filter;
}
elseif ($session_count == 1) {
	$overview_session_id = (int) $overview_sessions[0]['id'];
}
elseif ($session_count > 1) {
	// Prefer a currently open session; else most recent started.
	$now = time();
	$open_id = 0;
	$latest_started_id = 0;
	$latest_started_ts = 0;
	foreach ($overview_sessions as $sess) {
		$start = (!empty($sess['judgingDate'])) ? (int) $sess['judgingDate'] : 0;
		$end = (!empty($sess['judgingDateEnd'])) ? (int) $sess['judgingDateEnd'] : 0;
		if (($start > 0) && ($start <= $now) && (($end == 0) || ($end > $now))) {
			$open_id = (int) $sess['id'];
			break;
		}
		if (($start > $latest_started_ts) && ($start <= $now)) {
			$latest_started_ts = $start;
			$latest_started_id = (int) $sess['id'];
		}
	}
	if ($open_id > 0) $overview_session_id = $open_id;
	elseif ($latest_started_id > 0) $overview_session_id = $latest_started_id;
}

foreach ($overview_sessions as $sess) {
	if ((int) $sess['id'] == $overview_session_id) {
		$overview_session_label = $sess['judgingLocName']." (".getTimeZoneDateTime($_SESSION['prefsTimeZone'], $sess['judgingDate'], $_SESSION['prefsDateFormat'], $_SESSION['prefsTimeFormat'], "short", "date-no-gmt").")";
		break;
	}
}

if ($overview_session_id > 0) $overview_tables = get_eval_overview_tables($overview_session_id);

$detail_url = $base_url."index.php?section=admin&amp;go=evaluation&amp;filter=default&amp;view=admin";
if ($overview_session_id > 0) $detail_url .= "&amp;session=".$overview_session_id;

$issue_labels = array(
	"single_eval" => $evaluation_info_019,
	"score_disparity" => $evaluation_info_036,
	"duplicate_judge_evals" => $evaluation_info_032,
	"duplicate_places" => $evaluation_info_029,
	"mini_bos_mismatch" => $evaluation_info_105
);

$status_labels = array(
	"in_progress" => (isset($label_eval_overview_in_progress)) ? $label_eval_overview_in_progress : "In Progress",
	"issues" => (isset($label_eval_overview_issues)) ? $label_eval_overview_issues : "Issues",
	"ready" => (isset($label_eval_overview_ready)) ? $label_eval_overview_ready : "Ready to Import",
	"imported" => (isset($label_eval_overview_imported)) ? $label_eval_overview_imported : "Imported"
);

function eval_overview_render_issues_html($issues, $issue_labels) {
	$html = "";
	$parts = array();
	if (!empty($issues['score_disparity'])) $parts[] = "<span class=\"label label-warning\" title=\"".htmlspecialchars($issue_labels['score_disparity'], ENT_QUOTES, "UTF-8")."\">".$issues['score_disparity']." score range</span>";
	if (!empty($issues['single_eval'])) $parts[] = "<span class=\"label label-warning\" title=\"".htmlspecialchars($issue_labels['single_eval'], ENT_QUOTES, "UTF-8")."\">".$issues['single_eval']." single eval</span>";
	if (!empty($issues['duplicate_judge_evals'])) $parts[] = "<span class=\"label label-warning\" title=\"".htmlspecialchars($issue_labels['duplicate_judge_evals'], ENT_QUOTES, "UTF-8")."\">".$issues['duplicate_judge_evals']." duplicate eval</span>";
	if (!empty($issues['duplicate_places'])) $parts[] = "<span class=\"label label-danger\" title=\"".htmlspecialchars($issue_labels['duplicate_places'], ENT_QUOTES, "UTF-8")."\">duplicate places</span>";
	if (!empty($issues['mini_bos_mismatch'])) $parts[] = "<span class=\"label label-info\" title=\"".htmlspecialchars($issue_labels['mini_bos_mismatch'], ENT_QUOTES, "UTF-8")."\">".$issues['mini_bos_mismatch']." Mini-BOS</span>";
	if (empty($parts)) $html = "<span class=\"text-muted small\">".((isset($GLOBALS['label_eval_overview_no_issues'])) ? $GLOBALS['label_eval_overview_no_issues'] : "No issues")."</span>";
	else $html = implode(" ", $parts);
	return $html;
}

function eval_overview_status_badge($status, $status_labels) {
	$map = array(
		"in_progress" => "label-default",
		"issues" => "label-warning",
		"ready" => "label-success",
		"imported" => "label-primary"
	);
	$cls = (isset($map[$status])) ? $map[$status] : "label-default";
	$label = (isset($status_labels[$status])) ? $status_labels[$status] : $status;
	return "<span class=\"label ".$cls." eval-overview-status-badge\">".htmlspecialchars($label, ENT_QUOTES, "UTF-8")."</span>";
}

?>

<style>
.eval-overview-table-card { margin-bottom: 15px; }
.eval-overview-table-card .panel-heading { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px; }
.eval-overview-progress { margin: 10px 0 5px 0; }
.eval-overview-progress .progress { margin-bottom: 5px; height: 22px; }
.eval-overview-progress .progress-bar { line-height: 22px; font-size: 12px; }
.eval-overview-actions { margin-top: 10px; }
.eval-overview-meta { margin-top: 5px; }
#eval-overview-sync-icon.hidden { display: none !important; }
</style>

<div class="bcoem-admin-element hidden-print">
	<div class="btn-group" role="group">
		<a role="button" class="btn btn-default" href="<?php echo $base_url; ?>index.php?section=admin&amp;go=judging_scores"><span class="fa fa-chevron-circle-left"></span> Manage Scores</a>
		<a role="button" class="btn btn-default" href="<?php echo $detail_url; ?>"><i class="fa fa-list"></i> <?php echo (isset($label_eval_overview_manage_details)) ? $label_eval_overview_manage_details : "Manage Entry Details"; ?></a>
		<button type="button" class="btn btn-default" id="eval-overview-refresh-now"><i class="fa fa-refresh" id="eval-overview-sync-icon"></i> <?php echo (isset($label_eval_overview_update_now)) ? $label_eval_overview_update_now : "Update Now"; ?></button>
	</div>
</div>

<?php if ($session_count > 0) { ?>
<div class="bcoem-admin-element hidden-print" style="margin-bottom:15px;">
	<form class="form-inline" method="get" action="<?php echo $base_url; ?>index.php">
		<input type="hidden" name="section" value="admin">
		<input type="hidden" name="go" value="evaluation">
		<input type="hidden" name="action" value="overview">
		<input type="hidden" name="view" value="admin">
		<div class="form-group">
			<label for="judging-session-filter" style="margin-right:8px;"><?php echo $label_session_filter; ?></label>
			<select id="judging-session-filter" name="session" class="form-control" onchange="this.form.submit();">
				<?php if ($session_count > 1) { ?>
				<option value="default"<?php if ($overview_session_id == 0) echo " selected"; ?>><?php echo (isset($label_eval_overview_select_session)) ? $label_eval_overview_select_session : "Select a session..."; ?></option>
				<?php } ?>
				<?php foreach ($overview_sessions as $sess) {
					$opt_label = $sess['judgingLocName']." (".getTimeZoneDateTime($_SESSION['prefsTimeZone'], $sess['judgingDate'], $_SESSION['prefsDateFormat'], $_SESSION['prefsTimeFormat'], "short", "date-no-gmt").")";
					$selected = ((int)$sess['id'] == $overview_session_id) ? " selected" : "";
					echo "<option value=\"".$sess['id']."\"".$selected.">".htmlspecialchars($opt_label, ENT_QUOTES, "UTF-8")."</option>";
				} ?>
			</select>
		</div>
	</form>
</div>
<?php } ?>

<?php include (EVALS.'import_scores.eval.php'); ?>

<p class="text-muted small">
	<span id="eval-overview-updated-label"><?php echo (isset($label_eval_overview_updated)) ? $label_eval_overview_updated : "Updated"; ?></span>:
	<span id="eval-overview-updated"><?php echo getTimeZoneDateTime($_SESSION['prefsTimeZone'], time(), $_SESSION['prefsDateFormat'], $_SESSION['prefsTimeFormat'], "short", "date-time"); ?></span>
	<span class="text-muted"> &mdash; <?php echo (isset($evaluation_info_139)) ? $evaluation_info_139 : "Table status refreshes automatically every 30 seconds."; ?></span>
</p>

<?php if ($session_count == 0) { ?>
	<div class="alert alert-warning">
		<p><i class="fa fa-exclamation-circle"></i> <?php echo (isset($evaluation_info_143)) ? $evaluation_info_143 : "No judging sessions have been defined yet."; ?></p>
	</div>
<?php } elseif ($overview_session_id == 0) { ?>
	<div class="alert alert-info">
		<p><i class="fa fa-info-circle"></i> <?php echo (isset($evaluation_info_140)) ? $evaluation_info_140 : "Select a judging session to view table evaluation progress."; ?></p>
	</div>
<?php } elseif (empty($overview_tables)) { ?>
	<div class="alert alert-warning">
		<p><i class="fa fa-exclamation-circle"></i> <?php echo (isset($evaluation_info_141)) ? $evaluation_info_141 : "No tables are assigned to this judging session."; ?></p>
	</div>
<?php } else { ?>

<div id="eval-overview-tables">
<?php foreach ($overview_tables as $tbl) {
	$bar_class = "progress-bar-info";
	if ($tbl['status'] == "issues") $bar_class = "progress-bar-warning";
	elseif ($tbl['status'] == "ready") $bar_class = "progress-bar-success";
	elseif ($tbl['status'] == "imported") $bar_class = "progress-bar-primary";
	$detail_table_url = $detail_url."#table".$tbl['table_id'];
?>
	<div class="panel panel-default eval-overview-table-card" id="eval-overview-table-<?php echo $tbl['table_id']; ?>" data-table-id="<?php echo $tbl['table_id']; ?>">
		<div class="panel-heading">
			<div>
				<strong><?php echo $label_table; ?> <?php echo htmlspecialchars($tbl['table_label'], ENT_QUOTES, "UTF-8"); ?></strong>
				<span class="eval-overview-status" style="margin-left:8px;"><?php echo eval_overview_status_badge($tbl['status'], $status_labels); ?></span>
			</div>
			<div class="eval-overview-count text-muted">
				<span class="eval-overview-scored"><?php echo (int)$tbl['scored']; ?></span>
				/
				<span class="eval-overview-entries"><?php echo (int)$tbl['entries']; ?></span>
				<?php echo (isset($label_evals_submitted)) ? $label_evals_submitted : "Evaluations"; ?>
			</div>
		</div>
		<div class="panel-body">
			<div class="eval-overview-progress">
				<div class="progress">
					<div class="progress-bar <?php echo $bar_class; ?> eval-overview-bar" role="progressbar" aria-valuenow="<?php echo (int)$tbl['percent']; ?>" aria-valuemin="0" aria-valuemax="100" style="width: <?php echo (int)$tbl['percent']; ?>%;">
						<span class="eval-overview-percent"><?php echo (int)$tbl['percent']; ?>%</span>
					</div>
				</div>
			</div>
			<div class="eval-overview-issues"><?php echo eval_overview_render_issues_html($tbl['issues'], $issue_labels); ?></div>
			<div class="eval-overview-meta small text-muted">
				<span class="eval-overview-imported-count"><?php echo (int)$tbl['imported']; ?></span> <?php echo (isset($label_eval_overview_scores_imported)) ? $label_eval_overview_scores_imported : "scores already imported"; ?>
			</div>
			<div class="eval-overview-actions">
				<a class="btn btn-default btn-sm" href="<?php echo $detail_table_url; ?>"><i class="fa fa-list"></i> <?php echo (isset($label_eval_overview_view_details)) ? $label_eval_overview_view_details : "View Details"; ?></a>
				<?php if (!empty($tbl['import_ready'])) { ?>
				<button type="button" class="btn btn-success btn-sm import-scores-scoped-btn eval-overview-import-btn" data-toggle="modal" data-target="#eval-import-modal" data-scope="table" data-table-id="<?php echo $tbl['table_id']; ?>" data-table-label="<?php echo htmlspecialchars($tbl['table_label'], ENT_QUOTES, "UTF-8"); ?>"><i class="fa fa-file-import"></i> <?php echo $label_import_this_table; ?></button>
				<?php } else { ?>
				<button type="button" class="btn btn-default btn-sm eval-overview-import-btn" disabled title="<?php echo htmlspecialchars((isset($evaluation_info_142)) ? $evaluation_info_142 : "Import is available when the table is complete and has no issues.", ENT_QUOTES, "UTF-8"); ?>"><i class="fa fa-file-import"></i> <?php echo $label_import_this_table; ?></button>
				<?php } ?>
			</div>
		</div>
	</div>
<?php } ?>
</div>

<?php } ?>

<script type="text/javascript">
(function() {
	var ajax_url = <?php echo json_encode($ajax_url); ?>;
	var sessionId = <?php echo (int) $overview_session_id; ?>;
	var pollMs = 30000;
	var pollTimer = null;
	var pollingPaused = false;
	var requestInFlight = false;

	var statusLabels = <?php echo json_encode($status_labels); ?>;
	var issueLabels = <?php echo json_encode($issue_labels); ?>;
	var labelImport = <?php echo json_encode($label_import_this_table); ?>;
	var labelViewDetails = <?php echo json_encode((isset($label_eval_overview_view_details)) ? $label_eval_overview_view_details : "View Details"); ?>;
	var labelScoresImported = <?php echo json_encode((isset($label_eval_overview_scores_imported)) ? $label_eval_overview_scores_imported : "scores already imported"); ?>;
	var labelNoIssues = <?php echo json_encode((isset($label_eval_overview_no_issues)) ? $label_eval_overview_no_issues : "No issues"); ?>;
	var importGateTitle = <?php echo json_encode((isset($evaluation_info_142)) ? $evaluation_info_142 : "Import is available when the table is complete and has no issues."); ?>;
	var detailBaseUrl = <?php echo json_encode(html_entity_decode($detail_url, ENT_QUOTES, "UTF-8")); ?>;

	var evalImportSessionId = <?php echo ($overview_session_id > 0) ? json_encode((string)$overview_session_id) : "null"; ?>;
	var evalImportSessionLabel = <?php echo json_encode($overview_session_label); ?>;
	var evalImportScope = evalImportSessionId ? 'session' : 'all';
	var evalImportTableId = null;

	// Keep closing-tag sequences out of this inline script body.
	function escapeHtml(str) {
		return $("<div/>").text(str == null ? "" : String(str)).html();
	}

	function statusBadge(status) {
		var map = { in_progress: "label-default", issues: "label-warning", ready: "label-success", imported: "label-primary" };
		var cls = map[status] || "label-default";
		var label = statusLabels[status] || status;
		return '<span class="label ' + cls + ' eval-overview-status-badge">' + escapeHtml(label) + '</span>';
	}

	function barClass(status) {
		if (status === "issues") return "progress-bar-warning";
		if (status === "ready") return "progress-bar-success";
		if (status === "imported") return "progress-bar-primary";
		return "progress-bar-info";
	}

	function issuesHtml(issues) {
		var parts = [];
		if (issues.score_disparity) parts.push('<span class="label label-warning" title="' + escapeHtml(issueLabels.score_disparity) + '">' + issues.score_disparity + ' score range</span>');
		if (issues.single_eval) parts.push('<span class="label label-warning" title="' + escapeHtml(issueLabels.single_eval) + '">' + issues.single_eval + ' single eval</span>');
		if (issues.duplicate_judge_evals) parts.push('<span class="label label-warning" title="' + escapeHtml(issueLabels.duplicate_judge_evals) + '">' + issues.duplicate_judge_evals + ' duplicate eval</span>');
		if (issues.duplicate_places) parts.push('<span class="label label-danger" title="' + escapeHtml(issueLabels.duplicate_places) + '">duplicate places</span>');
		if (issues.mini_bos_mismatch) parts.push('<span class="label label-info" title="' + escapeHtml(issueLabels.mini_bos_mismatch) + '">' + issues.mini_bos_mismatch + ' Mini-BOS</span>');
		if (!parts.length) return '<span class="text-muted small">' + escapeHtml(labelNoIssues) + '</span>';
		return parts.join(" ");
	}

	function importButtonHtml(tbl) {
		if (parseInt(tbl.import_ready, 10) === 1) {
			return '<button type="button" class="btn btn-success btn-sm import-scores-scoped-btn eval-overview-import-btn" data-toggle="modal" data-target="#eval-import-modal" data-scope="table" data-table-id="' + tbl.table_id + '" data-table-label="' + escapeHtml(tbl.table_label) + '"><i class="fa fa-file-import"></i> ' + escapeHtml(labelImport) + '</button>';
		}
		return '<button type="button" class="btn btn-default btn-sm eval-overview-import-btn" disabled title="' + escapeHtml(importGateTitle) + '"><i class="fa fa-file-import"></i> ' + escapeHtml(labelImport) + '</button>';
	}

	function updateTableCard(tbl) {
		var $card = $("#eval-overview-table-" + tbl.table_id);
		if (!$card.length) return;

		$card.find(".eval-overview-status").html(statusBadge(tbl.status));
		$card.find(".eval-overview-scored").text(tbl.scored);
		$card.find(".eval-overview-entries").text(tbl.entries);
		$card.find(".eval-overview-imported-count").text(tbl.imported);
		$card.find(".eval-overview-issues").html(issuesHtml(tbl.issues));

		var $bar = $card.find(".eval-overview-bar");
		$bar.attr("class", "progress-bar " + barClass(tbl.status) + " eval-overview-bar");
		$bar.css("width", tbl.percent + "%").attr("aria-valuenow", tbl.percent);
		$card.find(".eval-overview-percent").text(tbl.percent + "%");

		var $actions = $card.find(".eval-overview-actions");
		var detailsHref = detailBaseUrl + "#table" + tbl.table_id;
		$actions.html(
			'<a class="btn btn-default btn-sm" href="' + detailsHref + '"><i class="fa fa-list"></i> ' + escapeHtml(labelViewDetails) + '</a> ' +
			importButtonHtml(tbl)
		);
	}

	function setSyncing(on) {
		var $icon = $("#eval-overview-sync-icon");
		if (on) $icon.addClass("fa-spin");
		else $icon.removeClass("fa-spin");
	}

	function refreshOverview(force) {
		if (!sessionId) return;
		if (requestInFlight) return;
		if (pollingPaused && !force) return;

		requestInFlight = true;
		setSyncing(true);

		$.getJSON(ajax_url + "eval_overview_status.ajax.php", { session_id: sessionId })
			.done(function(data) {
				if (!data || !data.success) return;
				if (data.updated) $("#eval-overview-updated").text(data.updated);
				if ($.isArray(data.tables)) {
					$.each(data.tables, function(i, tbl) { updateTableCard(tbl); });
				}
			})
			.always(function() {
				requestInFlight = false;
				setSyncing(false);
			});
	}

	function startPolling() {
		if (pollTimer) clearInterval(pollTimer);
		if (!sessionId) return;
		pollTimer = setInterval(function() { refreshOverview(false); }, pollMs);
	}

	$(document).ready(function() {
		$("#eval-overview-refresh-now").on("click", function(e) {
			e.preventDefault();
			refreshOverview(true);
		});

		$('#eval-import-modal').on('show.bs.modal', function(event) {
			pollingPaused = true;
			var $trigger = $(event.relatedTarget);
			var scopeText = <?php echo json_encode($evaluation_info_137); ?>;

			if ($trigger.data('scope') == 'table') {
				evalImportScope = 'table';
				evalImportTableId = $trigger.data('table-id');
				scopeText = <?php echo json_encode($evaluation_info_135); ?> + ' (' + $trigger.data('table-label') + ')';
			}
			else {
				evalImportTableId = null;
				if (evalImportSessionId) {
					evalImportScope = 'session';
					scopeText = <?php echo json_encode($evaluation_info_136); ?> + ' (' + evalImportSessionLabel + ')';
				}
				else evalImportScope = 'all';
			}

			$('#eval-import-modal-scope').text(scopeText);
		});

		$('#eval-import-modal').on('hidden.bs.modal', function() {
			pollingPaused = false;
		});

		$('#import-scores').off('click').on('click', function() {
			pollingPaused = true;
			var params = {};
			if ((evalImportScope == 'table') && (evalImportTableId)) params.table_id = evalImportTableId;
			else if ((evalImportScope == 'session') && (evalImportSessionId)) params.session_id = evalImportSessionId;

			$('#import-scores-status-icon').attr('class', 'fa fa-spin fa-spinner');
			$('#import-scores-status').text('');

			$.post(ajax_url + 'import_scores.ajax.php', params, function(data) {
				$('#import-scores-status-icon').attr('class', 'fa fa-check-circle');
				$('#import-scores-status').text('Imported ' + data.scores_imported_count + ' score(s). ' + data.flagged_count + ' flagged for mismatched consensus.');

				if (data.scored_places_discrepency_count > 0) {
					$('#import-status-discrepency-icon').attr('class', 'fa fa-exclamation-triangle');
					$('#import-status-discrepency').text(data.scored_places_discrepency_count + ' place discrepancy(ies) found.');
				}

				pollingPaused = false;
				refreshOverview(true);
			}, 'json').fail(function() {
				pollingPaused = false;
			});
		});

		startPolling();
	});
})();
</script>
