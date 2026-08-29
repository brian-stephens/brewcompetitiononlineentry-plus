<?php

// -----------------------------------------------------------
// Alter Table: judging_locations
//   Add row to allow a judging session to be hidden from all
//   front end (public) views while remaining usable in the
//   admin backend (e.g. for table/judge assignment).
// -----------------------------------------------------------

if (!check_update("judgingLocHidden", $prefix."judging_locations")) {
	$updateSQL = "ALTER TABLE `".$prefix."judging_locations` ADD `judgingLocHidden` CHAR(1) NULL DEFAULT NULL COMMENT '1=true - hidden from front end views' AFTER `judgingLocNotes`;";
	mysqli_real_escape_string($connection,$updateSQL);
	$result = mysqli_query($connection,$updateSQL) or die (mysqli_error($connection));
	$output .= "<li>Judging Locations table updated with judgingLocHidden row.</li>";
}

// -----------------------------------------------------------
// Alter Table: judging_locations
//   Add row so admins can mark a judging session complete and
//   hide it from judge dashboard views while keeping it in admin.
// -----------------------------------------------------------

if (!check_update("judgingLocComplete", $prefix."judging_locations")) {
	$updateSQL = "ALTER TABLE `".$prefix."judging_locations` ADD `judgingLocComplete` CHAR(1) NULL DEFAULT NULL COMMENT '1=true - complete; hide from judge dashboards' AFTER `judgingLocHidden`;";
	mysqli_real_escape_string($connection,$updateSQL);
	$result = mysqli_query($connection,$updateSQL) or die (mysqli_error($connection));
	$output .= "<li>Judging Locations table updated with judgingLocComplete row.</li>";
}

// -----------------------------------------------------------
// Alter Table: evaluation
//   Change evalFinalScore from smallint to float so judges can
//   record half-point consensus scores (e.g. 39.5), matching
//   the judging_scores.scoreEntry column it is later imported into.
// -----------------------------------------------------------

if (check_setup($prefix."evaluation",$database)) {
	$updateSQL = "ALTER TABLE `".$prefix."evaluation` MODIFY `evalFinalScore` float DEFAULT NULL COMMENT 'final, agreed upon score';";
	mysqli_real_escape_string($connection,$updateSQL);
	$result = mysqli_query($connection,$updateSQL) or die (mysqli_error($connection));
	$output .= "<li>Evaluation table updated so evalFinalScore supports half-point consensus scores.</li>";
}

// -----------------------------------------------------------
// Alter Table: evaluation
//   Add evalWaiveConsensus so a judge can flag that they
//   continued past the reconcile/waiting screen without another
//   judge's evaluation being submitted (e.g. odd-one-out entries),
//   for head judge/admin follow up.
// -----------------------------------------------------------

if (check_setup($prefix."evaluation",$database) && !check_update("evalWaiveConsensus", $prefix."evaluation")) {
	$updateSQL = "ALTER TABLE `".$prefix."evaluation` ADD `evalWaiveConsensus` CHAR(1) NULL DEFAULT NULL COMMENT '1=true - judge continued without waiting for another judge''s evaluation' AFTER `evalFinalScore`;";
	mysqli_real_escape_string($connection,$updateSQL);
	$result = mysqli_query($connection,$updateSQL) or die (mysqli_error($connection));
	$output .= "<li>Evaluation table updated with evalWaiveConsensus row.</li>";
}

// -----------------------------------------------------------
// Alter Table: evaluation
//   Add evalDraft to support periodic autosave rows that
//   should not be counted as fully submitted evaluations.
// -----------------------------------------------------------

if (check_setup($prefix."evaluation",$database) && !check_update("evalDraft", $prefix."evaluation")) {
	$updateSQL = "ALTER TABLE `".$prefix."evaluation` ADD `evalDraft` TINYINT(1) NULL DEFAULT 0 COMMENT '1=in-progress autosave draft; 0=finalized evaluation' AFTER `evalFinalScore`;";
	mysqli_real_escape_string($connection,$updateSQL);
	$result = mysqli_query($connection,$updateSQL) or die (mysqli_error($connection));
	$output .= "<li>Evaluation table updated with evalDraft row.</li>";
}

if (!check_update("prefsCoupons", $prefix."preferences")) {
	$updateSQL = "ALTER TABLE `".$prefix."preferences` ADD `prefsCoupons` TINYINT(1) NULL DEFAULT 0 COMMENT 'Enable voucher/coupon features' AFTER `prefsStyleLimits`;";
	mysqli_real_escape_string($connection,$updateSQL);
	$result = mysqli_query($connection,$updateSQL) or die (mysqli_error($connection));
	$output .= "<li>Preferences table updated with prefsCoupons row.</li>";
}

if (!check_update("prefsEvalAdminTools", $prefix."preferences")) {
	$updateSQL = "ALTER TABLE `".$prefix."preferences` ADD `prefsEvalAdminTools` TINYINT(1) NULL DEFAULT 0 COMMENT 'Enable eval progress/tracker/judge-view admin tools' AFTER `prefsCoupons`;";
	mysqli_real_escape_string($connection,$updateSQL);
	$result = mysqli_query($connection,$updateSQL) or die (mysqli_error($connection));
	$output .= "<li>Preferences table updated with prefsEvalAdminTools row.</li>";
}

if ((check_update("contestMichiganAck", $prefix."contest_info")) && (!check_update("contestExtraAck", $prefix."contest_info"))) {
	$updateSQL = "ALTER TABLE `".$prefix."contest_info` CHANGE `contestMichiganAck` `contestExtraAck` TINYINT(1) NULL DEFAULT 0 COMMENT 'Require extra registration acknowledgments';";
	mysqli_real_escape_string($connection,$updateSQL);
	$result = mysqli_query($connection,$updateSQL) or die (mysqli_error($connection));
	$output .= "<li>Contest info table renamed contestMichiganAck to contestExtraAck.</li>";
}

if (!check_update("contestExtraAck", $prefix."contest_info")) {
	$updateSQL = "ALTER TABLE `".$prefix."contest_info` ADD `contestExtraAck` TINYINT(1) NULL DEFAULT 0 COMMENT 'Require extra registration acknowledgments' AFTER `contestWinnerLink`;";
	mysqli_real_escape_string($connection,$updateSQL);
	$result = mysqli_query($connection,$updateSQL) or die (mysqli_error($connection));
	$output .= "<li>Contest info table updated with contestExtraAck row.</li>";
}

if (!check_update("contestExtraAcks", $prefix."contest_info")) {
	$updateSQL = "ALTER TABLE `".$prefix."contest_info` ADD `contestExtraAcks` MEDIUMTEXT NULL DEFAULT NULL AFTER `contestExtraAck`;";
	mysqli_real_escape_string($connection,$updateSQL);
	$result = mysqli_query($connection,$updateSQL) or die (mysqli_error($connection));
	$output .= "<li>Contest info table updated with contestExtraAcks row.</li>";

	if (check_update("contestMichiganBannerTitle", $prefix."contest_info")) {
		$query_legacy_ack = sprintf("SELECT contestMichiganBannerTitle, contestMichiganBannerText, contestMichiganCheckboxText FROM %s LIMIT 1", $prefix."contest_info");
		$legacy_ack = mysqli_query($connection,$query_legacy_ack);
		if ($legacy_ack) {
			$row_legacy_ack = mysqli_fetch_assoc($legacy_ack);
			$legacy_title = "";
			$legacy_text = "";
			$legacy_checkbox = "";
			if (!empty($row_legacy_ack['contestMichiganBannerTitle'])) $legacy_title = trim($row_legacy_ack['contestMichiganBannerTitle']);
			if (!empty($row_legacy_ack['contestMichiganBannerText'])) $legacy_text = trim($row_legacy_ack['contestMichiganBannerText']);
			if (!empty($row_legacy_ack['contestMichiganCheckboxText'])) $legacy_checkbox = trim($row_legacy_ack['contestMichiganCheckboxText']);
			if (($legacy_title !== "") || ($legacy_text !== "") || ($legacy_checkbox !== "")) {
				$legacy_json = json_encode(array(array(
					'title' => $legacy_title,
					'text' => $legacy_text,
					'checkbox' => $legacy_checkbox
				)));
				$updateSQL = "UPDATE `".$prefix."contest_info` SET `contestExtraAcks` = '".mysqli_real_escape_string($connection,$legacy_json)."'";
				mysqli_query($connection,$updateSQL) or die (mysqli_error($connection));
				$output .= "<li>Migrated existing acknowledgment copy into extra acknowledgments.</li>";
			}
		}
	}
}

?>
