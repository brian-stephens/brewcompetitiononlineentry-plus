<?php

/**
 * Admin: Judge Evaluations View
 * Select a judge and review all electronic evaluations they submitted.
 */

if ((!isset($_SESSION['userLevel'])) || ($_SESSION['userLevel'] > 1) || (!isset($_SESSION['prefsEval'])) || ($_SESSION['prefsEval'] != 1) || (!session_pref_enabled('prefsEvalAdminTools', 1))) {
	echo "<div class=\"alert alert-danger\"><p><strong>Access denied.</strong></p></div>";
	return;
}

include (LIB.'eval_judge_view.lib.php');

$judge_view_judges = get_eval_judge_view_judges();
$judge_count = count($judge_view_judges);
$selected_judge_uid = 0;
$selected_judge_label = "";
$judge_view_evals = array();

if (($bid != "default") && (is_numeric($bid))) {
	$selected_judge_uid = (int) $bid;
}

foreach ($judge_view_judges as $j) {
	if ((int) $j['uid'] == $selected_judge_uid) {
		$selected_judge_label = trim($j['first_name']." ".$j['last_name']);
		if (!empty($j['judge_id'])) $selected_judge_label .= " (".strtoupper($j['judge_id']).")";
		break;
	}
}

if (($selected_judge_uid > 0) && (!empty($selected_judge_label))) {
	$judge_view_evals = get_eval_judge_view_evaluations($selected_judge_uid);
}

$manage_url = $base_url."index.php?section=admin&amp;go=evaluation&amp;filter=default&amp;view=admin";
$detail_number_pref = (isset($_SESSION['prefsDisplaySpecial']) && ($_SESSION['prefsDisplaySpecial'] == "J")) ? "J" : "E";

?>

<style>
.eval-judge-view-table th,
.eval-judge-view-table td { vertical-align: middle !important; }
.eval-judge-view-actions a { margin-right: 4px; }
.eval-judge-view-meta { margin-top: 5px; margin-bottom: 15px; }
</style>

<div class="bcoem-admin-element hidden-print">
	<div class="btn-group" role="group">
		<a role="button" class="btn btn-default" href="<?php echo $base_url; ?>index.php?section=admin&amp;go=judging_scores"><span class="fa fa-chevron-circle-left"></span> Manage Scores</a>
		<a role="button" class="btn btn-default" href="<?php echo $manage_url; ?>"><i class="fa fa-list"></i> <?php echo (isset($label_eval_overview_manage_details)) ? $label_eval_overview_manage_details : "Manage Entry Details"; ?></a>
	</div>
</div>

<?php if ($judge_count > 0) { ?>
<div class="bcoem-admin-element hidden-print" style="margin-bottom:15px;">
	<form class="form-inline" method="get" action="<?php echo $base_url; ?>index.php">
		<input type="hidden" name="section" value="admin">
		<input type="hidden" name="go" value="evaluation">
		<input type="hidden" name="action" value="judge_view">
		<input type="hidden" name="view" value="admin">
		<div class="form-group">
			<label for="judge-view-filter" style="margin-right:8px;"><?php echo (isset($label_eval_judge_view_filter)) ? $label_eval_judge_view_filter : ((isset($label_judge)) ? $label_judge : "Judge"); ?></label>
			<select id="judge-view-filter" name="bid" class="form-control" onchange="this.form.submit();">
				<option value="default"<?php if ($selected_judge_uid == 0) echo " selected"; ?>><?php echo (isset($label_eval_judge_view_select)) ? $label_eval_judge_view_select : "Select a judge..."; ?></option>
				<?php foreach ($judge_view_judges as $j) {
					$opt_label = trim($j['first_name']." ".$j['last_name']);
					if (!empty($j['judge_id'])) $opt_label .= " (".strtoupper($j['judge_id']).")";
					$selected = ((int)$j['uid'] == $selected_judge_uid) ? " selected" : "";
					echo "<option value=\"".$j['uid']."\"".$selected.">".htmlspecialchars($opt_label, ENT_QUOTES, "UTF-8")."</option>";
				} ?>
			</select>
		</div>
	</form>
</div>
<?php } ?>

<?php if ($judge_count == 0) { ?>
	<div class="alert alert-warning">
		<p><i class="fa fa-exclamation-circle"></i> <?php echo (isset($evaluation_info_147)) ? $evaluation_info_147 : "No electronic evaluations have been submitted yet."; ?></p>
	</div>
<?php } elseif ($selected_judge_uid == 0) { ?>
	<div class="alert alert-info">
		<p><i class="fa fa-info-circle"></i> <?php echo (isset($evaluation_info_148)) ? $evaluation_info_148 : "Select a judge to view their submitted evaluations."; ?></p>
	</div>
<?php } elseif (empty($selected_judge_label)) { ?>
	<div class="alert alert-warning">
		<p><i class="fa fa-exclamation-circle"></i> <?php echo (isset($evaluation_info_149)) ? $evaluation_info_149 : "The selected judge was not found or has no electronic evaluations."; ?></p>
	</div>
<?php } elseif (empty($judge_view_evals)) { ?>
	<div class="alert alert-warning">
		<p><i class="fa fa-exclamation-circle"></i> <?php echo (isset($evaluation_info_149)) ? $evaluation_info_149 : "The selected judge was not found or has no electronic evaluations."; ?></p>
	</div>
<?php } else { ?>

<p class="text-muted small eval-judge-view-meta">
	<strong><?php echo htmlspecialchars($selected_judge_label, ENT_QUOTES, "UTF-8"); ?></strong>
	&mdash;
	<?php echo count($judge_view_evals); ?>
	<?php echo (isset($label_evals_submitted)) ? $label_evals_submitted : ((isset($label_evals)) ? $label_evals : "Evaluations"); ?>
</p>

<div class="table-responsive">
	<table class="table table-striped table-bordered eval-judge-view-table">
		<thead>
			<tr>
				<th><?php echo ($detail_number_pref == "J") ? $label_judging_number : $label_entry; ?></th>
				<th><?php echo $label_category; ?></th>
				<th><?php echo $label_style; ?></th>
				<th><?php echo (isset($label_eval_judge_view_judge_score)) ? $label_eval_judge_view_judge_score : "Judge Score"; ?></th>
				<th><?php echo (isset($label_eval_judge_view_consensus)) ? $label_eval_judge_view_consensus : "Consensus"; ?></th>
				<th><?php echo $label_submitted; ?></th>
				<th class="hidden-print"><?php echo $label_actions; ?></th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ($judge_view_evals as $row) {
			if ($detail_number_pref == "J") $number = sprintf("%06s", $row['brewJudgingNumber']);
			else $number = sprintf("%06s", $row['eid']);

			$style_num = style_number_const($row['brewCategorySort'], $row['brewSubCategory'], $_SESSION['style_set_display_separator'], 0);
			$submitted = "";
			if (!empty($row['date_added'])) {
				$submitted = getTimeZoneDateTime($_SESSION['prefsTimeZone'], $row['date_added'], $_SESSION['prefsDateFormat'], $_SESSION['prefsTimeFormat'], "short", "date-time");
			}

			$view_link = $base_url."includes/output.inc.php?section=evaluation&amp;go=default&amp;id=".$row['id']."&amp;tb=1";
			$print_link = $base_url."includes/output.inc.php?section=evaluation&amp;go=default&amp;id=".$row['id'];
			$judge_name_esc = htmlspecialchars($selected_judge_label, ENT_QUOTES, "UTF-8");
		?>
			<tr>
				<td><?php echo htmlspecialchars($number, ENT_QUOTES, "UTF-8"); ?></td>
				<td><?php echo htmlspecialchars($style_num, ENT_QUOTES, "UTF-8"); ?></td>
				<td><?php echo htmlspecialchars($row['brewStyle'], ENT_QUOTES, "UTF-8"); ?></td>
				<td><?php echo htmlspecialchars((string) $row['judge_score'], ENT_QUOTES, "UTF-8"); ?></td>
				<td><?php echo (!empty($row['consensus_score']) || $row['consensus_score'] === "0" || $row['consensus_score'] === 0) ? htmlspecialchars((string) $row['consensus_score'], ENT_QUOTES, "UTF-8") : "&mdash;"; ?></td>
				<td><small class="text-muted"><?php echo htmlspecialchars($submitted, ENT_QUOTES, "UTF-8"); ?></small></td>
				<td class="hidden-print eval-judge-view-actions">
					<a data-fancybox data-type="iframe" class="modal-window-link hide-loader" href="<?php echo $view_link; ?>" data-toggle="tooltip" title="View the generated scoresheet from the evaluation completed by <?php echo $judge_name_esc; ?>."><span class="fa-stack"><i class="fa fa-fw fa-square fa-stack-2x"></i><i class="fa fa-stack-1x fa-file-text fa-inverse"></i></span></a>
					<a data-fancybox data-type="iframe" class="modal-window-link hide-loader" href="<?php echo $print_link; ?>" data-toggle="tooltip" title="Print the generated scoresheet from the evaluation completed by <?php echo $judge_name_esc; ?>."><i class="fa fa-fw fa-lg fa-file-text"></i></a>
				</td>
			</tr>
		<?php } ?>
		</tbody>
	</table>
</div>

<?php } ?>
