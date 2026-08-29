<?php

/**
 * Admin: Entrant Placement Tracker
 * Search-and-select entrants and monitor placement status with live AJAX updates.
 */

if ((!isset($_SESSION['userLevel'])) || ($_SESSION['userLevel'] > 1) || (!isset($_SESSION['prefsEval'])) || ($_SESSION['prefsEval'] != 1) || (!session_pref_enabled('prefsEvalAdminTools', 1))) {
	echo "<div class=\"alert alert-danger\"><p><strong>Access denied.</strong></p></div>";
	return;
}

include (LIB.'eval_entrant_tracker.lib.php');

$uids_raw = "";
if (isset($_GET['uids'])) $uids_raw = sterilize($_GET['uids']);
$selected_uids = eval_entrant_tracker_normalize_uids($uids_raw);
$cids_raw = "";
if (isset($_GET['cids'])) $cids_raw = sterilize($_GET['cids']);
$selected_cids = eval_entrant_tracker_normalize_category_ids($cids_raw);
$sids_raw = "";
if (isset($_GET['sids'])) $sids_raw = sterilize($_GET['sids']);
$selected_sids = eval_entrant_tracker_normalize_session_ids($sids_raw);
$selected_mode = "entrant";
if (isset($_GET['mode'])) {
	$selected_mode = strtolower(trim((string)$_GET['mode']));
	if (($selected_mode !== "entrant") && ($selected_mode !== "category") && ($selected_mode !== "session")) $selected_mode = "entrant";
}

$status_labels = array(
	"clear" => (isset($label_eval_tracker_status_clear)) ? $label_eval_tracker_status_clear : "Clear",
	"pending" => (isset($label_eval_tracker_status_pending)) ? $label_eval_tracker_status_pending : "Judging Incomplete",
	"has_place" => (isset($label_eval_tracker_status_has_place)) ? $label_eval_tracker_status_has_place : "Placed",
	"bos_pull" => (isset($label_eval_tracker_status_bos_pull)) ? $label_eval_tracker_status_bos_pull : "BoS Pull",
	"gold" => (isset($label_eval_tracker_status_gold)) ? $label_eval_tracker_status_gold : "Gold Conflict",
	"in_progress" => (isset($label_eval_overview_in_progress)) ? $label_eval_overview_in_progress : "In Progress",
	"issues" => (isset($label_eval_overview_issues)) ? $label_eval_overview_issues : "Issues",
	"ready" => (isset($label_eval_overview_ready)) ? $label_eval_overview_ready : "Ready to Import",
	"imported" => (isset($label_eval_overview_imported)) ? $label_eval_overview_imported : "Imported"
);

$progress_labels = array(
	"not_started" => (isset($label_eval_tracker_progress_not_started)) ? $label_eval_tracker_progress_not_started : "Not started",
	"in_progress" => (isset($label_eval_tracker_progress_in_progress)) ? $label_eval_tracker_progress_in_progress : "In progress",
	"awaiting_place" => (isset($label_eval_tracker_progress_awaiting_place)) ? $label_eval_tracker_progress_awaiting_place : "Awaiting place",
	"placed_pending" => (isset($label_eval_tracker_progress_placed_pending)) ? $label_eval_tracker_progress_placed_pending : "Place pending import",
	"complete_no_place" => (isset($label_eval_tracker_progress_complete_no_place)) ? $label_eval_tracker_progress_complete_no_place : "Complete - no place",
	"placed_official" => (isset($label_eval_tracker_progress_placed_official)) ? $label_eval_tracker_progress_placed_official : "Official place",
	"in_progress_session" => (isset($label_eval_overview_in_progress)) ? $label_eval_overview_in_progress : "In Progress",
	"issues_session" => (isset($label_eval_overview_issues)) ? $label_eval_overview_issues : "Issues",
	"ready_session" => (isset($label_eval_overview_ready)) ? $label_eval_overview_ready : "Ready to Import",
	"imported_session" => (isset($label_eval_overview_imported)) ? $label_eval_overview_imported : "Imported"
);

?>

<style>
.eval-tracker-search-wrap { position: relative; }
.eval-tracker-search-results {
	position: absolute;
	z-index: 1000;
	top: 100%;
	left: 0;
	right: 0;
	background: #fff;
	border: 1px solid #ccc;
	border-top: 0;
	border-radius: 0 0 4px 4px;
	box-shadow: 0 3px 8px rgba(0, 0, 0, 0.08);
	max-height: 280px;
	overflow-y: auto;
	display: none;
}
.eval-tracker-search-result {
	display: block;
	padding: 9px 12px;
	border-bottom: 1px solid #eee;
	color: #333;
	text-decoration: none;
}
.eval-tracker-search-result:hover,
.eval-tracker-search-result:focus {
	background: #f9f9f9;
	text-decoration: none;
}
.eval-tracker-search-result:last-child { border-bottom: 0; }
.eval-tracker-search-result .small { margin-top: 2px; }
.eval-tracker-mode-group { display: block; margin-bottom: 18px; }
.eval-tracker-search-label { display: block; margin-top: 4px; margin-bottom: 5px; font-weight: 600; }
.eval-tracker-selected-wrap { margin-top: 5px; margin-bottom: 0; }
.eval-tracker-chip {
	display: inline-block;
	margin: 0 6px 6px 0;
	padding: 4px 8px;
	font-size: 11px;
	font-weight: normal;
}
.eval-tracker-chip .remove-chip {
	margin-left: 6px;
	color: #777;
	cursor: pointer;
	text-decoration: none;
}
.eval-tracker-chip .remove-chip:hover { color: #333; }
.eval-tracker-panel { margin-bottom: 12px; }
.eval-tracker-panel .panel-heading { display: flex; align-items: center; justify-content: space-between; gap: 10px; }
.eval-tracker-panel-title { display: flex; align-items: center; gap: 8px; min-width: 0; }
.eval-tracker-panel-controls { display: flex; align-items: center; gap: 8px; flex-shrink: 0; }
.eval-tracker-drag-handle {
	cursor: grab;
	color: #999;
	padding: 2px 4px;
	line-height: 1;
}
.eval-tracker-drag-handle:hover,
.eval-tracker-drag-handle:focus { color: #555; outline: none; }
.eval-tracker-drag-handle:active { cursor: grabbing; }
.eval-tracker-reorder-btns { display: inline-flex; gap: 2px; }
.eval-tracker-reorder-btns .btn {
	padding: 1px 5px;
	line-height: 1.2;
	color: #777;
}
.eval-tracker-reorder-btns .btn[disabled] { opacity: 0.35; }
.eval-tracker-panel.is-dragging { opacity: 0.45; }
.eval-tracker-panel.drag-over-before {
	box-shadow: inset 0 3px 0 0 #5bc0de;
}
.eval-tracker-panel.drag-over-after {
	box-shadow: inset 0 -3px 0 0 #5bc0de;
}
.eval-tracker-reorder-hint { margin: 0 0 10px 0; }
.eval-tracker-table th, .eval-tracker-table td { vertical-align: middle !important; }
.eval-tracker-source { white-space: nowrap; }
.eval-tracker-empty { margin-top: 15px; }
.eval-tracker-actions .btn { margin-right: 8px; margin-bottom: 8px; }
.eval-tracker-summary { margin: 0; line-height: 1.6; }
.eval-tracker-status-text { font-size: 12px; white-space: nowrap; }
.eval-tracker-expand-btn {
	padding: 0;
	border: 0;
	background: transparent;
	color: #555;
	cursor: pointer;
}
.eval-tracker-expand-btn:hover,
.eval-tracker-expand-btn:focus { color: #222; outline: none; }
.eval-tracker-issue-detail td {
	background: #f9f9f9;
	border-top: 0 !important;
	padding-top: 8px;
	padding-bottom: 12px;
}
.eval-tracker-issue-detail .label { margin: 0 4px 4px 0; display: inline-block; }
.eval-tracker-issue-actions { margin-top: 8px; }
.eval-tracker-issue-actions .btn { margin-right: 6px; margin-bottom: 4px; }
.eval-tracker-ready-badge {
	display: inline-block;
	margin-left: 6px;
	font-weight: normal;
}
.eval-tracker-session-row.is-ready td { background-color: #f3faf3; }
#eval-tracker-import-assets > .bcoem-admin-element > .btn-group { display: none; }
#eval-tracker-import-assets #import-scores-status-div {
	display: none;
	margin-top: 10px;
	margin-bottom: 0;
}
#eval-tracker-import-assets #import-scores-status-div.is-visible { display: block; }
#eval-tracker-import-assets #score-import-status { display: none; }
#eval-entrant-tracker-sync-icon.hidden { display: none !important; }
</style>

<div class="bcoem-admin-element hidden-print eval-tracker-actions">
	<a role="button" class="btn btn-default" href="<?php echo $base_url; ?>index.php?section=admin&amp;go=judging_scores_bos"><span class="fa fa-chevron-circle-left"></span> <?php echo (isset($label_manage_bos_entries_places)) ? $label_manage_bos_entries_places : "Manage BOS Entries and Places"; ?></a>
	<button type="button" class="btn btn-default" id="eval-entrant-tracker-refresh"><i class="fa fa-refresh" id="eval-entrant-tracker-sync-icon"></i> <?php echo (isset($label_eval_tracker_update_now)) ? $label_eval_tracker_update_now : "Update Now"; ?></button>
</div>

<div id="eval-tracker-import-assets">
<?php include (EVALS.'import_scores.eval.php'); ?>
</div>

<div class="bcoem-admin-element">
	<div class="panel panel-default">
		<div class="panel-body">
			<div class="row">
				<div class="col-sm-6">
					<div class="btn-group btn-group-sm eval-tracker-mode-group" role="group" aria-label="Tracker mode">
						<button type="button" class="btn btn-default eval-tracker-mode-btn" data-mode="entrant"><?php echo (isset($label_entrant)) ? $label_entrant : "Entrants"; ?></button>
						<button type="button" class="btn btn-default eval-tracker-mode-btn" data-mode="category"><?php echo (isset($label_category)) ? $label_category : "Categories"; ?></button>
						<button type="button" class="btn btn-default eval-tracker-mode-btn" data-mode="session"><?php echo (isset($label_session)) ? $label_session : "Sessions"; ?></button>
					</div>
				</div>
				<div class="col-sm-6 text-right">
					<p class="small text-muted eval-tracker-summary">
						<span id="eval-tracker-summary-selected-label"><?php echo (isset($label_selected)) ? $label_selected : "Selected"; ?></span>: <strong id="eval-tracker-selected-count">0</strong>
						&nbsp;|&nbsp;
						<span id="eval-tracker-summary-clear-label"><?php echo (isset($label_eval_tracker_status_clear)) ? $label_eval_tracker_status_clear : "Clear"; ?></span>: <strong id="eval-tracker-clear-count">0</strong>
						&nbsp;|&nbsp;
						<span id="eval-tracker-summary-pending-label"><?php echo (isset($label_eval_tracker_status_pending)) ? $label_eval_tracker_status_pending : "Judging Incomplete"; ?></span>: <strong id="eval-tracker-pending-count">0</strong>
						&nbsp;|&nbsp;
						<span id="eval-tracker-summary-bos-label"><?php echo (isset($label_eval_tracker_status_bos_pull)) ? $label_eval_tracker_status_bos_pull : "BoS Pull"; ?></span>: <strong id="eval-tracker-bos-pull-count">0</strong>
						&nbsp;|&nbsp;
						<span id="eval-tracker-summary-gold-label"><?php echo (isset($label_eval_tracker_status_gold)) ? $label_eval_tracker_status_gold : "Gold Conflict"; ?></span>: <strong id="eval-tracker-gold-count">0</strong>
					</p>
				</div>
			</div>
			<label for="eval-entrant-search" class="eval-tracker-search-label" id="eval-tracker-search-label"><?php echo (isset($label_eval_tracker_search_label)) ? $label_eval_tracker_search_label : "Search Entrants"; ?></label>
			<div class="eval-tracker-search-wrap">
				<input type="text" id="eval-entrant-search" class="form-control" autocomplete="off" placeholder="<?php echo (isset($label_eval_tracker_search_placeholder)) ? $label_eval_tracker_search_placeholder : "Type at least 2 letters to search entrants..."; ?>">
				<div id="eval-entrant-search-results" class="eval-tracker-search-results"></div>
			</div>
			<p class="help-block" id="eval-tracker-help-text"><?php echo (isset($evaluation_info_144)) ? $evaluation_info_144 : "Select entrants to track; results update every 30 seconds."; ?></p>
			<hr style="margin:10px 0;">
			<div class="eval-tracker-selected-wrap">
				<div id="eval-tracker-selected-chips"></div>
				<button type="button" class="btn btn-link btn-sm" id="eval-tracker-clear-all" style="padding-left:0;"><?php echo (isset($label_clear)) ? $label_clear : "Clear"; ?> <?php echo (isset($label_all)) ? $label_all : "All"; ?></button>
			</div>
		</div>
	</div>
</div>

<p class="text-muted small">
	<span><?php echo (isset($label_eval_overview_updated)) ? $label_eval_overview_updated : "Updated"; ?>:</span>
	<span id="eval-tracker-updated"><?php echo getTimeZoneDateTime($_SESSION['prefsTimeZone'], time(), $_SESSION['prefsDateFormat'], $_SESSION['prefsTimeFormat'], "short", "date-time"); ?></span>
	<span class="text-muted"> (<?php echo (isset($evaluation_info_139)) ? $evaluation_info_139 : "Table status refreshes automatically every 30 seconds."; ?>)</span>
</p>

<div id="eval-tracker-empty-state" class="alert alert-info eval-tracker-empty">
	<p><i class="fa fa-info-circle"></i> <span id="eval-tracker-empty-text"><?php echo (isset($evaluation_info_145)) ? $evaluation_info_145 : "Search and add entrants to start tracking placements."; ?></span></p>
</div>

<div id="eval-tracker-entrants"></div>

<script type="text/javascript">
(function() {
	var ajaxUrl = <?php echo json_encode($ajax_url); ?>;
	var baseUrl = <?php echo json_encode($base_url); ?>;
	var statusLabels = <?php echo json_encode($status_labels); ?>;
	var progressLabels = <?php echo json_encode($progress_labels); ?>;
	var issueLabels = <?php echo json_encode(array(
		"single_eval" => (isset($evaluation_info_019)) ? $evaluation_info_019 : "Entries with only one evaluation submitted.",
		"score_disparity" => (isset($evaluation_info_036)) ? $evaluation_info_036 : "Judge scores are outside the acceptable range.",
		"duplicate_judge_evals" => (isset($evaluation_info_032)) ? $evaluation_info_032 : "Multiple evaluations for a single entry were submitted by a judge.",
		"duplicate_places" => (isset($evaluation_info_029)) ? $evaluation_info_029 : "Duplicate places have been awarded.",
		"mini_bos_mismatch" => (isset($evaluation_info_105)) ? $evaluation_info_105 : "Entries have mismatched Mini-BOS indications from judges."
	)); ?>;
	var selectedEntrantsFromUrl = <?php echo json_encode($selected_uids); ?>;
	var selectedCategoriesFromUrl = <?php echo json_encode($selected_cids); ?>;
	var selectedSessionsFromUrl = <?php echo json_encode($selected_sids); ?>;
	var selectedModeFromUrl = <?php echo json_encode($selected_mode); ?>;
	var selectedByMode = { entrant: [], category: [], session: [] };
	var selectedMetaByMode = { entrant: {}, category: {}, session: {} };
	var expandedSessionTables = {};
	var lastEntrants = [];
	var lastCounts = {};
	var currentMode = "entrant";
	var pollMs = 30000;
	var pollTimer = null;
	var searchTimer = null;
	var requestInFlight = false;
	var searchInFlight = false;
	var searchRequestId = 0;
	var panelDragAllowed = false;
	var panelDragId = null;
	var pollingPaused = false;
	var evalImportScope = "all";
	var evalImportTableId = null;
	var evalImportSessionId = null;
	var evalImportSessionLabel = "";
	var storageKeys = {
		entrant: "evalEntrantTrackerSelectedEntrants",
		category: "evalEntrantTrackerSelectedCategories",
		session: "evalEntrantTrackerSelectedSessions"
	};

	var sourceLabels = {
		official: <?php echo json_encode((isset($label_eval_tracker_source_official)) ? $label_eval_tracker_source_official : "Official"); ?>,
		eval: <?php echo json_encode((isset($label_eval_tracker_source_eval)) ? $label_eval_tracker_source_eval : "Pending Eval"); ?>,
		none: <?php echo json_encode((isset($label_none)) ? $label_none : "None"); ?>
	};

	var uiLabels = <?php echo json_encode(array(
		"no_place" => (isset($label_eval_tracker_no_place)) ? $label_eval_tracker_no_place : "No place",
		"awaiting_place" => (isset($label_eval_tracker_awaiting_place)) ? $label_eval_tracker_awaiting_place : "Awaiting place",
		"in_progress" => (isset($label_eval_tracker_in_progress)) ? $label_eval_tracker_in_progress : "In progress",
		"not_started" => (isset($label_eval_tracker_not_started)) ? $label_eval_tracker_not_started : "Not started",
		"judge" => (isset($label_judge)) ? $label_judge : "Judge",
		"entries" => (isset($label_entries)) ? $label_entries : "Entries",
		"judging_number" => (isset($label_judging_number)) ? $label_judging_number : "Judging #",
		"entry_name" => (isset($label_entry_name)) ? $label_entry_name : "Entry Name",
		"style" => (isset($label_style)) ? $label_style : "Style",
		"table" => (isset($label_table)) ? $label_table : "Table",
		"progress" => (isset($label_eval_tracker_progress)) ? $label_eval_tracker_progress : "Progress",
		"evals" => (isset($label_evals)) ? $label_evals : "Evals",
		"score" => (isset($label_score)) ? $label_score : "Score",
		"place" => (isset($label_place_awarded)) ? $label_place_awarded : "Place",
		"source" => (isset($label_source)) ? $label_source : "Source",
		"entrant_col" => (isset($label_entrant)) ? $label_entrant : "Entrant",
		"mode_entrant" => (isset($label_entrant)) ? $label_entrant : "Entrants",
		"mode_category" => (isset($label_category)) ? $label_category : "Categories",
		"mode_session" => (isset($label_session)) ? $label_session : "Sessions",
		"search_entrant_label" => (isset($label_eval_tracker_search_label)) ? $label_eval_tracker_search_label : "Search Entrants",
		"search_category_label" => "Search Categories",
		"search_session_label" => "Search Sessions",
		"search_entrant_placeholder" => (isset($label_eval_tracker_search_placeholder)) ? $label_eval_tracker_search_placeholder : "Type at least 2 letters to search entrants...",
		"search_category_placeholder" => "Type at least 2 letters to search categories...",
		"search_session_placeholder" => "Type at least 2 letters to search sessions...",
		"help_entrant" => (isset($evaluation_info_144)) ? $evaluation_info_144 : "Select entrants to track; results update every 30 seconds.",
		"help_category" => "Select categories to track; results update every 30 seconds.",
		"help_session" => "Select sessions to track table completion and issues.",
		"empty_entrant" => (isset($evaluation_info_145)) ? $evaluation_info_145 : "Search and add entrants to start tracking placements.",
		"empty_category" => "Search and add categories to start tracking placements.",
		"empty_session" => "Search and add sessions to monitor table completion status.",
		"summary_clear_default" => (isset($label_eval_tracker_status_clear)) ? $label_eval_tracker_status_clear : "Clear",
		"summary_pending_default" => (isset($label_eval_tracker_status_pending)) ? $label_eval_tracker_status_pending : "Judging Incomplete",
		"summary_bos_default" => (isset($label_eval_tracker_status_bos_pull)) ? $label_eval_tracker_status_bos_pull : "BoS Pull",
		"summary_gold_default" => (isset($label_eval_tracker_status_gold)) ? $label_eval_tracker_status_gold : "Gold Conflict",
		"summary_clear_session" => (isset($label_eval_overview_imported)) ? $label_eval_overview_imported : "Imported",
		"summary_pending_session" => (isset($label_eval_overview_in_progress)) ? $label_eval_overview_in_progress : "In Progress",
		"summary_bos_session" => (isset($label_eval_overview_issues)) ? $label_eval_overview_issues : "Issues",
		"summary_gold_session" => (isset($label_eval_overview_ready)) ? $label_eval_overview_ready : "Ready to Import",
		"table_status" => "Table Status",
		"table_progress" => "Progress",
		"table_scored" => "Scored / Entries",
		"table_imported" => (isset($label_eval_overview_imported)) ? $label_eval_overview_imported : "Imported",
		"table_issues" => (isset($label_eval_overview_issues)) ? $label_eval_overview_issues : "Issues",
		"no_issues" => (isset($label_eval_overview_no_issues)) ? $label_eval_overview_no_issues : "No issues",
		"view_details" => (isset($label_eval_overview_view_details)) ? $label_eval_overview_view_details : "View Details",
		"view_overview" => (isset($label_eval_overview)) ? $label_eval_overview : "Overview",
		"import_table" => (isset($label_import_this_table)) ? $label_import_this_table : "Import This Table",
		"ready_to_import" => (isset($label_eval_overview_ready)) ? $label_eval_overview_ready : "Ready to Import",
		"expand_issues" => "Show issues",
		"collapse_issues" => "Hide issues",
		"issue_score_range" => "score range",
		"issue_single_eval" => "single eval",
		"issue_duplicate_eval" => "duplicate eval",
		"issue_duplicate_places" => "duplicate places",
		"issue_mini_bos" => "Mini-BOS",
		"reorder_hint" => "Drag the grip, or use the arrows, to rearrange tracked items.",
		"move_up" => "Move up",
		"move_down" => "Move down",
		"drag_to_reorder" => "Drag to reorder",
		"none" => (isset($label_none)) ? $label_none : "None",
		"consensus" => (isset($label_consensus)) ? $label_consensus : "consensus",
		"scoresheet" => (isset($label_scoresheet)) ? $label_scoresheet : "Scoresheet",
		"view_scoresheets" => (isset($brewer_entries_text_025)) ? html_entity_decode($brewer_entries_text_025, ENT_QUOTES, "UTF-8") : "View or Print judges' scoresheets",
		"no_entries" => (isset($evaluation_info_146)) ? $evaluation_info_146 : "No received entries found for this entrant."
	)); ?>;

	var importScopeLabels = {
		table: <?php echo json_encode((isset($evaluation_info_135)) ? $evaluation_info_135 : "This will import scores for this table only."); ?>,
		session: <?php echo json_encode((isset($evaluation_info_136)) ? $evaluation_info_136 : "This will import scores for this judging session only."); ?>,
		all: <?php echo json_encode((isset($evaluation_info_137)) ? $evaluation_info_137 : "This will import scores for all tables and sessions."); ?>
	};

	// Keep closing-tag sequences out of this inline script body.
	function escapeHtml(str) {
		return $("<div/>").text(str == null ? "" : String(str)).html();
	}

	function entryIsComplete(entry) {
		var progress = entry.progress || "";
		if (progress === "complete_no_place" || progress === "placed_official") return true;
		if (parseInt(entry.has_official_score, 10) === 1) return true;
		return false;
	}

	function scoresheetLinkHtml(entry) {
		var eid = parseInt(entry.eid, 10) || 0;
		if (!eid) return "";
		if (!entryIsComplete(entry)) return "";
		if ((parseInt(entry.eval_count, 10) || 0) <= 0) return "";
		var href = baseUrl + "includes/output.inc.php?section=evaluation&go=default&view=all&id=" + eid;
		return '<a data-fancybox data-type="iframe" class="modal-window-link hide-loader" href="' + href + '" title="' + escapeHtml(uiLabels.view_scoresheets) + '"><i class="fa fa-lg fa-file-text"><\/i><\/a>';
	}

	function sessionTableKey(sessionId, tableId) {
		return String(sessionId) + ":" + String(tableId);
	}

	function tableDetailUrl(sessionId, tableId) {
		var url = baseUrl + "index.php?section=admin&go=evaluation&filter=default&view=admin&session=" + encodeURIComponent(String(sessionId));
		if (tableId) url += "#table" + String(tableId);
		return url;
	}

	function tableOverviewUrl(sessionId) {
		return baseUrl + "index.php?section=admin&go=evaluation&action=overview&view=admin&session=" + encodeURIComponent(String(sessionId));
	}

	function sessionTableIsReady(entry) {
		if (parseInt(entry.table_import_ready, 10) === 1) return true;
		return (entry.progress === "ready");
	}

	function sessionIssuesHtml(entry) {
		var issues = entry.table_issues || {};
		var parts = [];
		var scoreDisp = parseInt(issues.score_disparity, 10) || 0;
		var singleEval = parseInt(issues.single_eval, 10) || 0;
		var dupEval = parseInt(issues.duplicate_judge_evals, 10) || 0;
		var dupPlaces = parseInt(issues.duplicate_places, 10) || 0;
		var miniBos = parseInt(issues.mini_bos_mismatch, 10) || 0;
		if (scoreDisp > 0) parts.push('<span class="label label-warning" title="' + escapeHtml(issueLabels.score_disparity) + '">' + scoreDisp + ' ' + escapeHtml(uiLabels.issue_score_range) + '<\/span>');
		if (singleEval > 0) parts.push('<span class="label label-warning" title="' + escapeHtml(issueLabels.single_eval) + '">' + singleEval + ' ' + escapeHtml(uiLabels.issue_single_eval) + '<\/span>');
		if (dupEval > 0) parts.push('<span class="label label-warning" title="' + escapeHtml(issueLabels.duplicate_judge_evals) + '">' + dupEval + ' ' + escapeHtml(uiLabels.issue_duplicate_eval) + '<\/span>');
		if (dupPlaces > 0) parts.push('<span class="label label-danger" title="' + escapeHtml(issueLabels.duplicate_places) + '">' + escapeHtml(uiLabels.issue_duplicate_places) + '<\/span>');
		if (miniBos > 0) parts.push('<span class="label label-info" title="' + escapeHtml(issueLabels.mini_bos_mismatch) + '">' + miniBos + ' ' + escapeHtml(uiLabels.issue_mini_bos) + '<\/span>');
		if (!parts.length) return '<span class="text-muted small">' + escapeHtml(uiLabels.no_issues) + '<\/span>';
		return parts.join(" ");
	}

	function sessionImportButtonHtml(entry) {
		if (!sessionTableIsReady(entry)) return "";
		var tableId = parseInt(entry.eid, 10) || 0;
		if (!tableId) return "";
		return '<button type="button" class="btn btn-success btn-xs import-scores-scoped-btn" data-toggle="modal" data-target="#eval-import-modal" data-scope="table" data-table-id="' + tableId + '" data-table-label="' + escapeHtml(entry.table_label || "") + '"><i class="fa fa-file-import"><\/i> ' + escapeHtml(uiLabels.import_table) + '<\/button>';
	}

	function sessionIssueDetailHtml(sessionId, entry) {
		var tableId = parseInt(entry.eid, 10) || 0;
		var html = '<div class="eval-tracker-issue-breakdown">' + sessionIssuesHtml(entry) + '<\/div>';
		html += '<div class="eval-tracker-issue-actions">';
		html += sessionImportButtonHtml(entry);
		html += '<a class="btn btn-default btn-xs" href="' + tableDetailUrl(sessionId, tableId) + '"><i class="fa fa-list"><\/i> ' + escapeHtml(uiLabels.view_details) + '<\/a>';
		html += '<a class="btn btn-default btn-xs" href="' + tableOverviewUrl(sessionId) + '"><i class="fa fa-dashboard"><\/i> ' + escapeHtml(uiLabels.view_overview) + '<\/a>';
		html += '<\/div>';
		return html;
	}

	function normalizeIdArray(raw) {
		var out = [];
		var seen = {};
		if (!$.isArray(raw)) return out;
		$.each(raw, function(i, value) {
			var token = $.trim(String(value || ""));
			if (!token) return;
			if (!/^[A-Za-z0-9_-]+$/.test(token)) return;
			if (seen[token]) return;
			seen[token] = true;
			out.push(token);
		});
		return out;
	}

	function selectedIds() {
		return selectedByMode[currentMode] || [];
	}

	function selectedMeta() {
		return selectedMetaByMode[currentMode] || {};
	}

	function orderEntrantsBySelection(entrants) {
		var byId = {};
		var ordered = [];
		var seen = {};
		$.each($.isArray(entrants) ? entrants : [], function(i, entrant) {
			byId[String(entrant.uid)] = entrant;
		});
		$.each(selectedIds(), function(i, id) {
			id = String(id);
			if (!byId[id] || seen[id]) return;
			seen[id] = true;
			ordered.push(byId[id]);
		});
		$.each(entrants, function(i, entrant) {
			var id = String(entrant.uid);
			if (seen[id]) return;
			seen[id] = true;
			ordered.push(entrant);
		});
		return ordered;
	}

	function applySelectedOrder(ids) {
		selectedByMode[currentMode] = normalizeIdArray(ids);
		persistSelected();
		lastEntrants = orderEntrantsBySelection(lastEntrants);
		renderSelectedChips();
		renderEntrants(lastEntrants, lastCounts);
	}

	function moveSelectedId(id, direction) {
		id = String(id || "");
		var ids = selectedIds().slice();
		var idx = -1;
		$.each(ids, function(i, value) {
			if (String(value) === id) idx = i;
		});
		if (idx < 0) return;
		var swap = idx + direction;
		if (swap < 0 || swap >= ids.length) return;
		var tmp = ids[idx];
		ids[idx] = ids[swap];
		ids[swap] = tmp;
		applySelectedOrder(ids);
	}

	function moveSelectedIdBefore(fromId, beforeId) {
		fromId = String(fromId || "");
		beforeId = String(beforeId || "");
		if (!fromId || !beforeId || fromId === beforeId) return;
		var ids = [];
		var fromFound = false;
		$.each(selectedIds(), function(i, value) {
			value = String(value);
			if (value === fromId) {
				fromFound = true;
				return;
			}
			ids.push(value);
		});
		if (!fromFound) return;
		var insertAt = ids.length;
		$.each(ids, function(i, value) {
			if (String(value) === beforeId) {
				insertAt = i;
				return false;
			}
		});
		ids.splice(insertAt, 0, fromId);
		applySelectedOrder(ids);
	}

	function moveSelectedIdAfter(fromId, afterId) {
		fromId = String(fromId || "");
		afterId = String(afterId || "");
		if (!fromId || !afterId || fromId === afterId) return;
		var ids = [];
		var fromFound = false;
		$.each(selectedIds(), function(i, value) {
			value = String(value);
			if (value === fromId) {
				fromFound = true;
				return;
			}
			ids.push(value);
		});
		if (!fromFound) return;
		var insertAt = ids.length;
		$.each(ids, function(i, value) {
			if (String(value) === afterId) {
				insertAt = i + 1;
				return false;
			}
		});
		ids.splice(insertAt, 0, fromId);
		applySelectedOrder(ids);
	}

	function loadSelectedForMode(mode, fromUrl) {
		if (fromUrl && fromUrl.length) {
			selectedByMode[mode] = normalizeIdArray(fromUrl);
			return;
		}
		try {
			var stored = window.localStorage.getItem(storageKeys[mode]);
			if (!stored) return;
			selectedByMode[mode] = normalizeIdArray(JSON.parse(stored));
		}
		catch (e) {}
	}

	function loadSelected() {
		loadSelectedForMode("entrant", selectedEntrantsFromUrl);
		loadSelectedForMode("category", selectedCategoriesFromUrl);
		loadSelectedForMode("session", selectedSessionsFromUrl);
		if ((selectedModeFromUrl === "entrant") || (selectedModeFromUrl === "category") || (selectedModeFromUrl === "session")) currentMode = selectedModeFromUrl;
	}

	function persistSelected() {
		try { window.localStorage.setItem(storageKeys.entrant, JSON.stringify(selectedByMode.entrant)); } catch (e) {}
		try { window.localStorage.setItem(storageKeys.category, JSON.stringify(selectedByMode.category)); } catch (e) {}
		try { window.localStorage.setItem(storageKeys.session, JSON.stringify(selectedByMode.session)); } catch (e) {}
		try {
			if (window.history && window.history.replaceState && typeof window.URL === "function") {
				var url = new URL(window.location.href);
				if (selectedByMode.entrant.length) url.searchParams.set("uids", selectedByMode.entrant.join(","));
				else url.searchParams.delete("uids");
				if (selectedByMode.category.length) url.searchParams.set("cids", selectedByMode.category.join(","));
				else url.searchParams.delete("cids");
				if (selectedByMode.session.length) url.searchParams.set("sids", selectedByMode.session.join(","));
				else url.searchParams.delete("sids");
				url.searchParams.set("mode", currentMode);
				window.history.replaceState({}, "", url.toString());
			}
		}
		catch (e) {}
	}

	function updateModeUi() {
		$(".eval-tracker-mode-btn").removeClass("btn-primary").addClass("btn-default");
		$('.eval-tracker-mode-btn[data-mode="' + currentMode + '"]').removeClass("btn-default").addClass("btn-primary");
		if (currentMode === "category") {
			$("#eval-tracker-search-label").text(uiLabels.search_category_label);
			$("#eval-entrant-search").attr("placeholder", uiLabels.search_category_placeholder);
			$("#eval-tracker-help-text").text(uiLabels.help_category);
			$("#eval-tracker-empty-text").text(uiLabels.empty_category);
			$("#eval-tracker-summary-clear-label").text(uiLabels.summary_clear_default);
			$("#eval-tracker-summary-pending-label").text(uiLabels.summary_pending_default);
			$("#eval-tracker-summary-bos-label").text(uiLabels.summary_bos_default);
			$("#eval-tracker-summary-gold-label").text(uiLabels.summary_gold_default);
		}
		else if (currentMode === "session") {
			$("#eval-tracker-search-label").text(uiLabels.search_session_label);
			$("#eval-entrant-search").attr("placeholder", uiLabels.search_session_placeholder);
			$("#eval-tracker-help-text").text(uiLabels.help_session);
			$("#eval-tracker-empty-text").text(uiLabels.empty_session);
			$("#eval-tracker-summary-clear-label").text(uiLabels.summary_clear_session);
			$("#eval-tracker-summary-pending-label").text(uiLabels.summary_pending_session);
			$("#eval-tracker-summary-bos-label").text(uiLabels.summary_bos_session);
			$("#eval-tracker-summary-gold-label").text(uiLabels.summary_gold_session);
		}
		else {
			$("#eval-tracker-search-label").text(uiLabels.search_entrant_label);
			$("#eval-entrant-search").attr("placeholder", uiLabels.search_entrant_placeholder);
			$("#eval-tracker-help-text").text(uiLabels.help_entrant);
			$("#eval-tracker-empty-text").text(uiLabels.empty_entrant);
			$("#eval-tracker-summary-clear-label").text(uiLabels.summary_clear_default);
			$("#eval-tracker-summary-pending-label").text(uiLabels.summary_pending_default);
			$("#eval-tracker-summary-bos-label").text(uiLabels.summary_bos_default);
			$("#eval-tracker-summary-gold-label").text(uiLabels.summary_gold_default);
		}
	}

	function statusTextClass(status) {
		if (status === "gold") return "text-danger";
		if (status === "bos_pull") return "text-warning";
		if (status === "pending") return "text-info";
		if (status === "has_place") return "text-primary";
		if (status === "issues") return "text-warning";
		if (status === "in_progress") return "text-info";
		if (status === "ready") return "text-success";
		if (status === "imported") return "text-success";
		return "text-success";
	}

	function placeCellHtml(entry) {
		if (entry.place_display) return entry.place_display;
		if (entry.progress === "complete_no_place") {
			return '<span class="text-success">' + escapeHtml(uiLabels.no_place) + '<\/span>';
		}
		if (entry.progress === "awaiting_place") {
			return '<span class="text-warning">' + escapeHtml(uiLabels.awaiting_place) + '<\/span>';
		}
		if (entry.progress === "in_progress") {
			return '<span class="text-info">' + escapeHtml(uiLabels.in_progress) + '<\/span>';
		}
		return '<span class="text-muted">' + escapeHtml(uiLabels.not_started) + '<\/span>';
	}

	function renderSelectedChips() {
		var html = "";
		var ids = selectedIds();
		var meta = selectedMeta();
		$.each(ids, function(i, id) {
			var fallback = "Entrant #" + id;
			if (currentMode === "category") fallback = "Category " + id;
			else if (currentMode === "session") fallback = "Session " + id;
			var label = meta[id] ? meta[id] : fallback;
			html += '<span class="btn btn-default btn-xs eval-tracker-chip" data-id="' + escapeHtml(id) + '">' +
				escapeHtml(label) +
				' <a href="#" class="remove-chip" data-remove-id="' + escapeHtml(id) + '" title="Remove"><span class="fa fa-times"><\/span><\/a><\/span>';
		});
		$("#eval-tracker-selected-chips").html(html);
		$("#eval-tracker-clear-all").toggle(ids.length > 0);
	}

	function placeSourceLabel(source) {
		if (source === "official") return sourceLabels.official;
		if (source === "eval") return sourceLabels.eval;
		return sourceLabels.none;
	}

	function renderEntrants(entrants, counts) {
		var ids = selectedIds();
		var isCategoryMode = (currentMode === "category");
		var isSessionMode = (currentMode === "session");
		var canReorder = ids.length > 1;
		entrants = orderEntrantsBySelection(entrants);
		$("#eval-tracker-selected-count").text(ids.length || 0);
		$("#eval-tracker-clear-count").text(counts.clear || 0);
		$("#eval-tracker-pending-count").text(counts.pending || 0);
		$("#eval-tracker-bos-pull-count").text(counts.bos_pull || 0);
		$("#eval-tracker-gold-count").text(counts.gold || 0);

		if (!ids.length) {
			$("#eval-tracker-empty-state").show();
			$("#eval-tracker-entrants").html("");
			return;
		}

		$("#eval-tracker-empty-state").hide();

		var html = "";
		if (canReorder) {
			html += '<p class="small text-muted eval-tracker-reorder-hint"><i class="fa fa-arrows-v"><\/i> ' + escapeHtml(uiLabels.reorder_hint) + '<\/p>';
		}
		$.each(entrants, function(i, entrant) {
			var entrantId = String(entrant.uid);
			selectedMetaByMode[currentMode][entrantId] = entrant.name;
			var club = entrant.club ? (' <small class="text-muted">(' + escapeHtml(entrant.club) + ')<\/small>') : "";
			var judgeFlag = entrant.is_judge ? (' <small class="text-muted">(' + escapeHtml(uiLabels.judge) + ')<\/small>') : "";
			if (isCategoryMode) {
				club = "";
				judgeFlag = "";
			}
			if (isSessionMode) {
				judgeFlag = "";
			}
			html += '<div class="panel panel-default eval-tracker-panel" id="eval-tracker-entrant-' + escapeHtml(entrantId) + '" data-id="' + escapeHtml(entrantId) + '"' + (canReorder ? ' draggable="true"' : '') + '>';
			html += '<div class="panel-heading">';
			html += '<div class="eval-tracker-panel-title">';
			if (canReorder) {
				html += '<span class="eval-tracker-drag-handle" title="' + escapeHtml(uiLabels.drag_to_reorder) + '" aria-label="' + escapeHtml(uiLabels.drag_to_reorder) + '"><i class="fa fa-bars"><\/i><\/span>';
			}
			html += '<div><strong>' + escapeHtml(entrant.name) + '<\/strong>' + club + judgeFlag + '<\/div>';
			html += '<\/div>';
			html += '<div class="eval-tracker-panel-controls">';
			if (canReorder) {
				html += '<span class="eval-tracker-reorder-btns">';
				html += '<button type="button" class="btn btn-default btn-xs eval-tracker-move-up" data-id="' + escapeHtml(entrantId) + '" title="' + escapeHtml(uiLabels.move_up) + '"' + (i === 0 ? ' disabled' : '') + '><i class="fa fa-chevron-up"><\/i><\/button>';
				html += '<button type="button" class="btn btn-default btn-xs eval-tracker-move-down" data-id="' + escapeHtml(entrantId) + '" title="' + escapeHtml(uiLabels.move_down) + '"' + (i === entrants.length - 1 ? ' disabled' : '') + '><i class="fa fa-chevron-down"><\/i><\/button>';
				html += '<\/span>';
			}
			html += '<small class="eval-tracker-status-text ' + statusTextClass(entrant.status) + '">' + escapeHtml(statusLabels[entrant.status] || entrant.status) + '<\/small>';
			html += '<\/div><\/div>';
			html += '<div class="panel-body">';
			if (isSessionMode) {
				html += '<p class="small text-muted">' + escapeHtml(String(entrant.entry_count || 0)) + ' tables in session' +
					((entrant.table_status_counts && (parseInt(entrant.table_status_counts.in_progress, 10) > 0)) ? (' \u2014 ' + parseInt(entrant.table_status_counts.in_progress, 10) + ' in progress') : '') +
					'.<\/p>';
			}
			else {
				html += '<p class="small text-muted">' +
					entrant.placed_count + ' / ' + entrant.entry_count + ' ' + escapeHtml(uiLabels.entries) + ' have places' +
					((parseInt(entrant.pending_count, 10) > 0) ? (' \u2014 ' + entrant.pending_count + ' still judging') : '') +
					'.<\/p>';
			}
			if (!entrant.entries || !entrant.entries.length) {
				html += '<p class="text-muted">' + escapeHtml(uiLabels.no_entries) + '<\/p>';
			}
			else {
				html += '<div class="table-responsive"><table class="table table-condensed table-striped eval-tracker-table">';
				html += '<thead><tr>';
				if (isSessionMode) {
					html += '<th style="width:28px;"><\/th>';
					html += '<th>' + escapeHtml(uiLabels.table) + '<\/th>';
					html += '<th>' + escapeHtml(uiLabels.table_status) + '<\/th>';
					html += '<th>' + escapeHtml(uiLabels.table_progress) + '<\/th>';
					html += '<th>' + escapeHtml(uiLabels.table_scored) + '<\/th>';
					html += '<th>' + escapeHtml(uiLabels.table_imported) + '<\/th>';
					html += '<th>' + escapeHtml(uiLabels.table_issues) + '<\/th>';
				}
				else {
					html += '<th>' + escapeHtml(uiLabels.judging_number) + '<\/th>';
					if (isCategoryMode) html += '<th>' + escapeHtml(uiLabels.entrant_col) + '<\/th>';
					html += '<th>' + escapeHtml(uiLabels.entry_name) + '<\/th>';
					html += '<th>' + escapeHtml(uiLabels.style) + '<\/th>';
					html += '<th>' + escapeHtml(uiLabels.table) + '<\/th>';
					html += '<th>' + escapeHtml(uiLabels.progress) + '<\/th>';
					html += '<th>' + escapeHtml(uiLabels.evals) + '<\/th>';
					html += '<th>' + escapeHtml(uiLabels.score) + '<\/th>';
					html += '<th>' + escapeHtml(uiLabels.place) + '<\/th>';
					html += '<th>' + escapeHtml(uiLabels.source) + '<\/th>';
					html += '<th>' + escapeHtml(uiLabels.scoresheet) + '<\/th>';
				}
				html += '<\/tr><\/thead><tbody>';
				$.each(entrant.entries, function(j, entry) {
					if (isSessionMode) {
						var tStatus = entry.progress || "in_progress";
						var tStatusLabel = statusLabels[tStatus] || tStatus;
						var tScored = (parseInt(entry.table_scored_total, 10) || 0);
						var tEntries = (parseInt(entry.table_entries_total, 10) || 0);
						var tImported = (parseInt(entry.table_imported_total, 10) || 0);
						var tPercent = (parseInt(entry.table_percent, 10) || 0);
						var tIssues = (parseInt(entry.table_issue_total, 10) || 0);
						var tableId = parseInt(entry.eid, 10) || 0;
						var expandKey = sessionTableKey(entrant.uid, tableId);
						var isExpanded = !!expandedSessionTables[expandKey];
						var isReady = sessionTableIsReady(entry);
						var expandTitle = isExpanded ? uiLabels.collapse_issues : uiLabels.expand_issues;
						var chevron = isExpanded ? "fa-chevron-down" : "fa-chevron-right";
						var issuesCell = tIssues;
						if (tIssues > 0) {
							issuesCell = '<a href="#" class="eval-tracker-expand-link">' + tIssues + '<\/a>';
						}
						html += '<tr class="eval-tracker-session-row' + (isReady ? ' is-ready' : '') + '" data-expand-key="' + escapeHtml(expandKey) + '" data-session-id="' + escapeHtml(String(entrant.uid)) + '" data-table-id="' + escapeHtml(String(tableId)) + '">';
						html += '<td><button type="button" class="eval-tracker-expand-btn" title="' + escapeHtml(expandTitle) + '" aria-expanded="' + (isExpanded ? "true" : "false") + '"><i class="fa ' + chevron + '"><\/i><\/button><\/td>';
						html += '<td>' + escapeHtml(entry.table_label || "");
						if (isReady) html += ' <span class="label label-success eval-tracker-ready-badge"><i class="fa fa-file-import"><\/i> ' + escapeHtml(uiLabels.ready_to_import) + '<\/span>';
						html += '<\/td>';
						html += '<td><small class="' + statusTextClass(tStatus) + '">' + escapeHtml(tStatusLabel) + '<\/small><\/td>';
						html += '<td>' + tPercent + '%<\/td>';
						html += '<td>' + tScored + ' / ' + tEntries + '<\/td>';
						html += '<td>' + tImported + '<\/td>';
						html += '<td>' + issuesCell + '<\/td>';
						html += '<\/tr>';
						if (isExpanded) {
							html += '<tr class="eval-tracker-issue-detail" data-expand-key="' + escapeHtml(expandKey) + '"><td colspan="7">' + sessionIssueDetailHtml(entrant.uid, entry) + '<\/td><\/tr>';
						}
					}
					else {
						var styleLabel = entry.style ? entry.style : (entry.category + entry.subcategory);
						var progressKey = entry.progress || "not_started";
						var progressLabel = progressLabels[progressKey] || progressKey;
						var scoreHtml = '<span class="text-muted">' + escapeHtml(uiLabels.none) + '<\/span>';
						if (entry.score !== null && entry.score !== undefined && entry.score !== "") {
							scoreHtml = escapeHtml(String(entry.score));
							if (entry.score_source === "eval") scoreHtml += ' <small class="text-muted">(' + escapeHtml(uiLabels.consensus) + ')<\/small>';
						}
						html += '<tr>';
						html += '<td>' + escapeHtml(entry.judging_number || "") + '<\/td>';
						if (isCategoryMode) html += '<td>' + escapeHtml(entry.entrant_name || "") + '<\/td>';
						html += '<td>' + escapeHtml(entry.entry_name || "") + '<\/td>';
						html += '<td>' + escapeHtml(styleLabel) + '<\/td>';
						html += '<td>' + escapeHtml(entry.table_label || "") + '<\/td>';
						html += '<td><small class="text-muted">' + escapeHtml(progressLabel) + '<\/small><\/td>';
						html += '<td>' + (parseInt(entry.eval_count, 10) || 0) + '<\/td>';
						html += '<td>' + scoreHtml + '<\/td>';
						html += '<td>' + placeCellHtml(entry) + '<\/td>';
						html += '<td class="eval-tracker-source">' + escapeHtml(placeSourceLabel(entry.place_source)) + '<\/td>';
						html += '<td>' + (scoresheetLinkHtml(entry) || '<span class="text-muted">\u2014<\/span>') + '<\/td>';
						html += '<\/tr>';
					}
				});
				html += '<\/tbody><\/table><\/div>';
			}
			html += '<\/div><\/div>';
		});
		$("#eval-tracker-entrants").html(html);
		renderSelectedChips();
	}

	function setSyncing(on) {
		var $icon = $("#eval-entrant-tracker-sync-icon");
		if (on) $icon.addClass("fa-spin");
		else $icon.removeClass("fa-spin");
	}

	function refreshStatus(force) {
		var ids = selectedIds();
		if (!ids.length) {
			lastEntrants = [];
			lastCounts = { selected: 0, clear: 0, pending: 0, has_place: 0, bos_pull: 0, gold: 0 };
			renderEntrants([], lastCounts);
			return;
		}
		if (pollingPaused && !force) return;
		if (requestInFlight && !force) return;
		requestInFlight = true;
		setSyncing(true);

		var params = { mode: currentMode };
		if (currentMode === "category") params.cids = ids.join(",");
		else if (currentMode === "session") params.sids = ids.join(",");
		else params.uids = ids.join(",");
		$.getJSON(ajaxUrl + "eval_entrant_tracker_status.ajax.php", params)
			.done(function(data) {
				if (!data || !data.success) return;
				if (data.updated) $("#eval-tracker-updated").text(data.updated);
				lastEntrants = $.isArray(data.entrants) ? data.entrants : [];
				lastCounts = data.counts || {};
				renderEntrants(lastEntrants, lastCounts);
			})
			.always(function() {
				requestInFlight = false;
				setSyncing(false);
			});
	}

	function showImportStatus(message, isError) {
		var $status = $("#eval-tracker-import-assets #import-scores-status-div");
		var $icon = $("#eval-tracker-import-assets #import-scores-status-icon");
		var $text = $("#eval-tracker-import-assets #import-scores-status");
		$status.addClass("is-visible");
		if (isError) {
			$icon.attr("class", "fa fa-exclamation-circle");
			$status.removeClass("alert-grey alert-success").addClass("alert-danger");
		}
		else {
			$icon.attr("class", "fa fa-check-circle");
			$status.removeClass("alert-grey alert-danger").addClass("alert-success");
		}
		$text.text(message || "");
	}

	function startPolling() {
		if (pollTimer) clearInterval(pollTimer);
		pollTimer = setInterval(function() {
			refreshStatus(false);
		}, pollMs);
	}

	function showSearchResults(results) {
		var html = "";
		if (!results || !results.length) {
			$("#eval-entrant-search-results").hide().empty();
			return;
		}
		$.each(results, function(i, row) {
			var id = String((row.id !== undefined) ? row.id : row.uid);
			if (selectedIds().indexOf(id) !== -1) return;
			var metaText = row.meta ? String(row.meta) : (row.club ? String(row.club) : "");
			var countText = parseInt(row.entry_count, 10) || 0;
			html += '<a href="#" class="eval-tracker-search-result" data-id="' + escapeHtml(id) + '">' +
				'<strong>' + escapeHtml(row.name) + '<\/strong>' +
				' <span class="text-muted">(' + countText + ')<\/span>';
			if (metaText) html += '<div class="small text-muted">' + escapeHtml(metaText) + '<\/div>';
			html += '<\/a>';
		});
		if (!html) {
			$("#eval-entrant-search-results").hide().empty();
			return;
		}
		$("#eval-entrant-search-results").html(html).show();
	}

	function runSearch() {
		var q = $.trim($("#eval-entrant-search").val());
		if (q.length < 2) {
			$("#eval-entrant-search-results").hide().empty();
			return;
		}
		if (searchInFlight) return;

		searchInFlight = true;
		searchRequestId += 1;
		var requestId = searchRequestId;
		var requestedQuery = q;

		$.getJSON(ajaxUrl + "eval_entrant_search.ajax.php", { mode: currentMode, q: requestedQuery, limit: 20 })
			.done(function(data) {
				if (requestId !== searchRequestId) return;
				if (!data || !data.success) {
					$("#eval-entrant-search-results").hide().empty();
					return;
				}
				// Only render if the input still matches what we searched.
				if ($.trim($("#eval-entrant-search").val()) !== requestedQuery) return;
				showSearchResults(data.results || []);
			})
			.fail(function() {
				if (requestId !== searchRequestId) return;
				$("#eval-entrant-search-results").hide().empty();
			})
			.always(function() {
				if (requestId !== searchRequestId) return;
				searchInFlight = false;
				var latest = $.trim($("#eval-entrant-search").val());
				if (latest !== requestedQuery && latest.length >= 2) runSearch();
			});
	}

	function addId(id, label) {
		id = $.trim(String(id || ""));
		if (!id) return;
		if (!/^[A-Za-z0-9_-]+$/.test(id)) return;
		if (selectedIds().indexOf(id) !== -1) return;
		selectedByMode[currentMode].push(id);
		if (label) selectedMetaByMode[currentMode][id] = label;
		persistSelected();
		renderSelectedChips();
		refreshStatus(true);
	}

	function removeId(id) {
		id = $.trim(String(id || ""));
		selectedByMode[currentMode] = $.grep(selectedByMode[currentMode], function(v) { return String(v) !== id; });
		delete selectedMetaByMode[currentMode][id];
		persistSelected();
		renderSelectedChips();
		refreshStatus(true);
	}

	$(document).ready(function() {
		loadSelected();
		renderSelectedChips();
		refreshStatus(true);
		startPolling();
		updateModeUi();

		$("#eval-entrant-tracker-refresh").on("click", function(e) {
			e.preventDefault();
			refreshStatus(true);
		});

		$(document).on("click", ".eval-tracker-mode-btn", function(e) {
			e.preventDefault();
			var mode = String($(this).data("mode") || "");
			if ((mode !== "entrant") && (mode !== "category") && (mode !== "session")) return;
			if (mode === currentMode) return;
			currentMode = mode;
			persistSelected();
			updateModeUi();
			$("#eval-entrant-search").val("");
			$("#eval-entrant-search-results").hide().empty();
			renderSelectedChips();
			refreshStatus(true);
		});

		$("#eval-entrant-search").on("input", function() {
			if (searchTimer) clearTimeout(searchTimer);
			searchTimer = setTimeout(runSearch, 300);
		});

		$(document).on("click", ".eval-tracker-search-result", function(e) {
			e.preventDefault();
			var id = $(this).data("id");
			var name = $(this).find("strong").text();
			addId(id, name);
			$("#eval-entrant-search").val("");
			$("#eval-entrant-search-results").hide().empty();
		});

		$(document).on("click", ".remove-chip", function(e) {
			e.preventDefault();
			removeId($(this).data("remove-id"));
		});

		$(document).on("click", ".eval-tracker-move-up", function(e) {
			e.preventDefault();
			moveSelectedId($(this).data("id"), -1);
		});

		$(document).on("click", ".eval-tracker-move-down", function(e) {
			e.preventDefault();
			moveSelectedId($(this).data("id"), 1);
		});

		$(document).on("mousedown", ".eval-tracker-drag-handle", function() {
			panelDragAllowed = true;
		});

		$(document).on("mouseup", function() {
			panelDragAllowed = false;
		});

		$(document).on("dragstart", ".eval-tracker-panel", function(e) {
			if (!panelDragAllowed) {
				e.preventDefault();
				return false;
			}
			panelDragId = String($(this).data("id") || "");
			$(this).addClass("is-dragging");
			if (e.originalEvent && e.originalEvent.dataTransfer) {
				e.originalEvent.dataTransfer.effectAllowed = "move";
				e.originalEvent.dataTransfer.setData("text/plain", panelDragId);
			}
		});

		$(document).on("dragend", ".eval-tracker-panel", function() {
			panelDragAllowed = false;
			panelDragId = null;
			$(".eval-tracker-panel").removeClass("is-dragging drag-over drag-over-before drag-over-after");
		});

		$(document).on("dragover", ".eval-tracker-panel", function(e) {
			e.preventDefault();
			if (!panelDragId) return;
			var targetId = String($(this).data("id") || "");
			if (!targetId || targetId === panelDragId) return;
			$(".eval-tracker-panel").removeClass("drag-over drag-over-before drag-over-after");
			var rect = this.getBoundingClientRect();
			var before = true;
			if (e.originalEvent && typeof e.originalEvent.clientY === "number") {
				before = (e.originalEvent.clientY < (rect.top + (rect.height / 2)));
			}
			$(this).addClass("drag-over").addClass(before ? "drag-over-before" : "drag-over-after").data("drop-before", before ? 1 : 0);
			if (e.originalEvent && e.originalEvent.dataTransfer) {
				e.originalEvent.dataTransfer.dropEffect = "move";
			}
		});

		$(document).on("dragleave", ".eval-tracker-panel", function() {
			$(this).removeClass("drag-over drag-over-before drag-over-after");
		});

		$(document).on("drop", ".eval-tracker-panel", function(e) {
			e.preventDefault();
			var $target = $(this);
			var targetId = String($target.data("id") || "");
			var fromId = panelDragId;
			var dropBefore = parseInt($target.data("drop-before"), 10) !== 0;
			if ((!fromId) && e.originalEvent && e.originalEvent.dataTransfer) {
				fromId = String(e.originalEvent.dataTransfer.getData("text/plain") || "");
			}
			$(".eval-tracker-panel").removeClass("is-dragging drag-over drag-over-before drag-over-after");
			panelDragAllowed = false;
			panelDragId = null;
			if (!fromId || !targetId || fromId === targetId) return;
			if (dropBefore) moveSelectedIdBefore(fromId, targetId);
			else moveSelectedIdAfter(fromId, targetId);
		});

		$(document).on("click", ".eval-tracker-expand-btn, .eval-tracker-expand-link", function(e) {
			e.preventDefault();
			e.stopPropagation();
			var $row = $(this).closest("tr.eval-tracker-session-row");
			var key = String($row.data("expand-key") || "");
			if (!key) return;
			if (expandedSessionTables[key]) delete expandedSessionTables[key];
			else expandedSessionTables[key] = true;
			renderEntrants(lastEntrants, lastCounts);
		});

		$("#eval-import-modal").on("show.bs.modal", function(event) {
			pollingPaused = true;
			var $trigger = $(event.relatedTarget);
			var scopeText = importScopeLabels.all;
			evalImportSessionId = null;
			evalImportSessionLabel = "";
			evalImportTableId = null;
			evalImportScope = "all";

			if ($trigger && $trigger.data("scope") === "table") {
				evalImportScope = "table";
				evalImportTableId = $trigger.data("table-id");
				scopeText = importScopeLabels.table + " (" + ($trigger.data("table-label") || "") + ")";
				var $row = $trigger.closest("tr").prevAll("tr.eval-tracker-session-row").first();
				if (!$row.length) $row = $trigger.closest("tr.eval-tracker-session-row");
				var sessionId = String($row.data("session-id") || "");
				if (sessionId) {
					evalImportSessionId = sessionId;
					evalImportSessionLabel = selectedMetaByMode.session[sessionId] || "";
				}
			}

			$("#eval-import-modal-scope").text(scopeText);
		});

		$("#eval-import-modal").on("hidden.bs.modal", function() {
			pollingPaused = false;
		});

		$("#import-scores").off("click").on("click", function() {
			pollingPaused = true;
			var params = {};
			if ((evalImportScope === "table") && evalImportTableId) params.table_id = evalImportTableId;
			else if ((evalImportScope === "session") && evalImportSessionId) params.session_id = evalImportSessionId;

			$("#eval-tracker-import-assets #import-scores-status-div").addClass("is-visible").removeClass("alert-danger alert-success").addClass("alert-grey");
			$("#eval-tracker-import-assets #import-scores-status-icon").attr("class", "fa fa-spin fa-spinner");
			$("#eval-tracker-import-assets #import-scores-status").text("");

			$.post(ajaxUrl + "import_scores.ajax.php", params, function(data) {
				var message = "Imported " + data.scores_imported_count + " score(s). " + data.flagged_count + " flagged for mismatched consensus.";
				if (data.scored_places_discrepency_count > 0) {
					message += " " + data.scored_places_discrepency_count + " place discrepancy(ies) found.";
				}
				showImportStatus(message, false);
				pollingPaused = false;
				refreshStatus(true);
			}, "json").fail(function() {
				showImportStatus("Import failed. Please try again.", true);
				pollingPaused = false;
			});
		});

		$("#eval-tracker-clear-all").on("click", function(e) {
			e.preventDefault();
			selectedByMode[currentMode] = [];
			selectedMetaByMode[currentMode] = {};
			persistSelected();
			renderSelectedChips();
			refreshStatus(true);
		}).toggle(selectedIds().length > 0);

		$(document).on("click", function(e) {
			if (!$(e.target).closest(".eval-tracker-search-wrap").length) {
				$("#eval-entrant-search-results").hide();
			}
		});
	});
})();
</script>
