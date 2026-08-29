<?php
/*
 * Module:      process.inc.php
 * Description: This module does all the heavy lifting for any DB updates; new entries,
 *              new users, organization, etc.
 */

ob_start();
error_reporting(E_ALL ^ E_NOTICE);
ini_set('display_errors', '0');

require ('../paths.php');
require (INCLUDES.'url_variables.inc.php');
require (INCLUDES.'styles.inc.php');
include (INCLUDES.'scrubber.inc.php');
include (LIB.'common.lib.php');
include (LIB.'update.lib.php');
require (DB.'common.db.php');
include (LANG.'language.lang.php'); 
require (LIB.'process.lib.php');

$mail_use_smtp = FALSE;
if (HOSTED) $mail_use_smtp = TRUE;
elseif (isset($_SESSION['prefsEmailSMTP'])) { 
    if (($_SESSION['prefsEmailSMTP'] == 1) && (!empty($_SESSION['prefsEmailHost'])) && (!empty($_SESSION['prefsEmailFrom'])) && (!empty($_SESSION['prefsEmailUsername'])) && (!empty($_SESSION['prefsEmailPassword'])) && (!empty($_SESSION['prefsEmailPort']))) $mail_use_smtp = TRUE;
}

mysqli_select_db($connection,$database);

// Set timezone as Europe/London just in case
$timezone_raw = "0";

// Set up redirect var
$redirect_go_to = "";

// Track queries if debugging
if (DEBUG) include (DEBUGGING.'query_count_begin.debug.php');

// Check if setup is running, if so, check whether prefs have been established
// If so, get time zone setup by admin
if ($section == "setup") {

	if (check_setup($prefix."preferences",$database)) {

		if ($dbTable == $prefix."preferences") {
			$action = "edit";
		}

		else {
			$query_prefs_tz = sprintf("SELECT prefsTimeZone FROM %s WHERE id='1'", $prefix."preferences");
			$prefs_tz = mysqli_query($connection,$query_prefs_tz) or die (mysqli_error($connection));
			$row_prefs_tz = mysqli_fetch_assoc($prefs_tz);
			$totalRows_prefs_tz = mysqli_num_rows($prefs_tz);

			if ($totalRows_prefs_tz > 0) {
				$timezone_raw = $row_prefs_tz['prefsTimeZone'];
			}
		}	

	}

}

// If running normally, get time zone from cookie
// Set timezone globals for the site
else  $timezone_raw = $_SESSION['prefsTimeZone'];

// Establish time zone for all date-related functions
$timezone_prefs = get_timezone($timezone_raw);
date_default_timezone_set($timezone_prefs);
$tz = date_default_timezone_get();

// Check for Daylight Savings Time (DST) - if true, add one hour to the offset
$bool = date("I");
if ($bool == 1) $timezone_offset = number_format(($timezone_raw + 1.000),0);
else $timezone_offset = number_format($timezone_raw,0);

$process_allowed = FALSE;
if (isset($_SERVER['HTTP_REFERER'])) {
	$referrer = parse_url($_SERVER['HTTP_REFERER']);
	if ((($referrer['host'] == $_SERVER['SERVER_NAME']) && (isset($_SESSION['prefs'.$prefix_session]))) || ($setup_free_access)) $process_allowed = TRUE;
}

if ((isset($_SESSION['prefsSEF'])) && ($_SESSION['prefsSEF'] == "Y")) $sef = TRUE;

/**
 * Check for CSRF token.
 * If tokens match, continue with process.
 * If not, redirect to 403 (forbidden) error page.
 */

$request_method = strtoupper($_SERVER['REQUEST_METHOD']);
$bypass_token = array("login","logout","forgot","reset","paypal");

function process_issue_csrf_token() {
	if (function_exists('random_bytes')) return bin2hex(random_bytes(32));
	if (function_exists('mcrypt_create_iv')) return bin2hex(mcrypt_create_iv(32, MCRYPT_DEV_URANDOM));
	return bin2hex(openssl_random_pseudo_bytes(32));
}

/*
// Troubleshoot
echo "GET session ID: ".$_POST['session-id']."<br>";
echo "POST session ID: ".session_id()."<br>";
echo "Posted CSRF: ".$_POST['user_session_token']."<br>";
echo "Session CSRF: ".$_SESSION['user_session_token']."<br>";
$error_log = "CSRF validate on " . $_SERVER['REQUEST_METHOD'] . " " . $_SERVER['REQUEST_URI'] .
          " posted=" . ($_POST['user_session_token'] ?? '(missing)') .
          " session=" . ($_SESSION['user_session_token'] ?? '(missing)');
echo $error_log;
error_log($error_log);
exit();
*/

if (($request_method === "POST") && (!in_array($section,$bypass_token))) {

	$token_hash = FALSE;
	$posted = filter_input(INPUT_POST, 'user_session_token', FILTER_UNSAFE_RAW);
	$posted = is_string($posted) ? trim($posted) : '';
	$session = $_SESSION['user_session_token'] ?? '';
	$session_prev = $_SESSION['user_session_token_prev'] ?? '';
	$is_eval_process = (strpos($section, "process-eval") === 0);

	// Validate shape first (example: 64 hex chars for 32 bytes)
	$valid_shape = (bool) preg_match('/^[a-f0-9]{64}$/i', $posted);

	if (($valid_shape) && ($posted !== '')) {
		if (($session !== '') && (hash_equals($session, $posted))) $token_hash = TRUE;
		elseif (($is_eval_process) && ($session_prev !== '') && (hash_equals($session_prev, $posted))) $token_hash = TRUE;
	}

	if (($posted === '') || (!$token_hash) || (!$process_allowed)) {
		$session_has_auth = ((isset($_SESSION['loginUsername'])) && (isset($_SESSION['userLevel'])) && (isset($_SESSION['prefs'.$prefix_session])));
		$session_expired = FALSE;
		if (($session_has_auth) && (isset($_SESSION['last_action'])) && (is_numeric($_SESSION['last_action'])) && (is_numeric($session_expire_after))) {
			$session_expired = ((time() - (int) $_SESSION['last_action']) >= ((int) $session_expire_after * 60));
		}
		$stale_eval_token = (($is_eval_process) && ($posted !== '') && (!$token_hash) && ($process_allowed) && ($session_has_auth) && (!$session_expired));

		// Stale token with an otherwise-authenticated judge session (common in multi-tab use):
		// rotate token and send the user back to their scoresheet without forcing logout.
		if ($stale_eval_token) {
			$_SESSION['user_session_token_prev'] = $_SESSION['user_session_token'] ?? '';
			$_SESSION['user_session_token'] = process_issue_csrf_token();
			$redirect = $base_url."index.php?section=evaluation&go=scoresheet&action=add";

			if (($action == "edit") && (is_numeric($id)) && ((int) $id > 0)) $redirect = $base_url."index.php?section=evaluation&go=scoresheet&action=edit&id=".$id;
			elseif ((isset($_POST['eid'])) && (is_numeric($_POST['eid'])) && ((int) $_POST['eid'] > 0)) $redirect .= "&id=".sterilize($_POST['eid']);

			if ((isset($_POST['evalTable'])) && (!empty($_POST['evalTable'])) && (is_numeric($_POST['evalTable']))) $redirect .= "&filter=".sterilize($_POST['evalTable']);
			$redirect .= "&view=refresh";
			$redirect = prep_redirect_link($redirect);
			$redirect_go_to = sprintf("Location: %s", $redirect);
			header($redirect_go_to);
			exit();
		}

	    session_unset();
	    session_destroy();
	    session_write_close();
	    // Evaluation posts that fail CSRF/session checks are almost always an
	    // expired judging session. Send judges to login (not a 403/404 error page)
	    // so they can sign back in. Scoresheet drafts are preserved in localStorage
	    // until a successful save landing clears them.
	    if ($is_eval_process) {
	    	$redirect = $base_url."index.php?msg=99";
	    	if ((isset($_POST['eid'])) && (is_numeric($_POST['eid'])) && ((int) $_POST['eid'] > 0)) $redirect .= "&return_eid=".sterilize($_POST['eid']);
	    	if (($action == "edit") && (is_numeric($id)) && ((int) $id > 0)) $redirect .= "&return_eval_id=".sterilize($id);
	    }
	    else $redirect = $base_url."index.php?section=403";
	    $redirect = prep_redirect_link($redirect);
	    $redirect_go_to = sprintf("Location: %s", $redirect);
	    header($redirect_go_to);
	    exit();
	}

}

if (((isset($_SERVER['HTTP_REFERER'])) && ($referrer['host'] == $_SERVER['SERVER_NAME'])) && ((isset($_SESSION['prefs'.$prefix_session])) || ($setup_free_access))) {

	$archive_db_table = $prefix."archive";
	$brewer_db_table = $prefix."brewer";
	$brewing_db_table = $prefix."brewing";
	$contacts_db_table = $prefix."contacts";
	$coupon_codes_db_table = $prefix."coupon_codes";
	$coupon_redemptions_db_table = $prefix."coupon_redemptions";
	$coupon_entry_payments_db_table = $prefix."coupon_entry_payments";
	$contest_info_db_table = $prefix."contest_info";
	$drop_off_db_table = $prefix."drop_off";
	$judging_assignments_db_table = $prefix."judging_assignments";
	$judging_flights_db_table = $prefix."judging_flights";
	$judging_locations_db_table = $prefix."judging_locations";
	$judging_preferences_db_table = $prefix."judging_preferences";
	$judging_scores_db_table = $prefix."judging_scores";
	$judging_scores_bos_db_table = $prefix."judging_scores_bos";
	$judging_tables_db_table = $prefix."judging_tables";
	$mods_db_table = $prefix."mods";
	$preferences_db_table = $prefix."preferences";
	$special_best_data_db_table = $prefix."special_best_data";
	$special_best_info_db_table = $prefix."special_best_info";
	$sponsors_db_table = $prefix."sponsors";
	$staff_db_table = $prefix."staff";
	$styles_db_table = $prefix."styles";
	$style_types_db_table = $prefix."style_types";
	$system_db_table = $prefix."bcoem_sys";
	$themes_db_table = $prefix."themes";
	$users_db_table = $prefix."users";

	// --------------------------- // -------------------------------- //

	$insertGoTo = "";
	$updateGoTo = "";
	$massUpdateGoTo = "";
	$errorGoTo = "";
	$deleteGoTo = "";
	
	if (isset($_POST['relocate'])) {

		if (strpos($_POST['relocate'],"?") === false) {
			$insertGoTo .= $_POST['relocate']."?msg=1";
			$updateGoTo .= $_POST['relocate']."?msg=2";
			$errorGoTo .= $_POST['relocate']."?msg=3";
			$massUpdateGoTo .= $_POST['relocate']."?msg=9";
		}

		else {
			$insertGoTo .= $_POST['relocate']."&msg=1";
			$updateGoTo .= $_POST['relocate']."&msg=2";
			$errorGoTo .= $_POST['relocate']."&msg=3";
			$massUpdateGoTo .= $_POST['relocate']."&msg=9";
		}

	}

	if 		(strstr($_SERVER['HTTP_REFERER'], $base_url."list"))  		$deleteGoTo = $base_url."index.php?section=list&msg=5";
	elseif 	(strstr($_SERVER['HTTP_REFERER'], $base_url."rules")) 		$deleteGoTo = $base_url."index.php?section=rules&msg=5";
	elseif 	(strstr($_SERVER['HTTP_REFERER'], $base_url."volunteers")) 	$deleteGoTo = $base_url."index.php?section=volunteers&msg=5";
	elseif 	(strstr($_SERVER['HTTP_REFERER'], $base_url."sponsors")) 	$deleteGoTo = $base_url."index.php?section=sponsors&msg=5";
	elseif 	(strstr($_SERVER['HTTP_REFERER'], $base_url."pay")) 		$deleteGoTo = $base_url."index.php?section=pay&msg=5";
	else $deleteGoTo = clean_up_url($_SERVER['HTTP_REFERER'])."&msg=5";

	// --------------------------- Various Actions ------------------------------- //

	// Log in, log out, forgot password
	if ($action == "login") include (INCLUDES.'logincheck.inc.php');
	elseif ($action == "logout") include (INCLUDES.'logout.inc.php');
	elseif (($action == "forgot") || ($action == "reset")) include (PROCESS.'process_forgot_password.inc.php');

	// Delete
	elseif ($action == "delete") include (PROCESS.'process_delete.inc.php');

	// Create a practice judging session
	//elseif ($action == "practice_session") include (PROCESS.'process_judging_practice_session.inc.php');
	
	// Barcode check in
	elseif ($action == "barcode_check_in") include (PROCESS.'process_barcode_check_in.inc.php');

	// Updating judging flights
	elseif ($action == "update_judging_flights") include (PROCESS.'process_judging_flight_check.inc.php');
	
	// Delete scoresheets in user_docs folder
	elseif ($action == "delete_scoresheets") {

		rdelete(USER_DOCS,"");
		if ($filter == "admin-dashboard") $redirect_go_to = sprintf("Location: %s", $base_url."index.php?section=admin&msg=31");
		else $redirect_go_to = sprintf("Location: %s", $base_url."index.php?section=admin&go=upload_scoresheets&action=".$filter."&msg=31");

	}

	// Clear session vars
	elseif ($action == "clear_session") {

		unset($_SESSION['session_set_'.$prefix_session]);
		unset($_SESSION['prefs'.$prefix_session]);
		unset($_SESSION['user_info'.$prefix_session]);
		unset($_SESSION['contest_info_general'.$prefix_session]);

		if ($section == "update") $redirect_go_to = sprintf("Location: %s", $base_url."update.php");
		else $redirect_go_to = sprintf("Location: %s", $base_url."index.php?section=admin");

	}

	// Data clean up
	elseif (($action == "purge") || ($action == "cleanup")) include (INCLUDES.'data_cleanup.inc.php');

	// Regenerate judging numbers
	elseif ($action == "generate_judging_numbers") {

		generate_judging_numbers($prefix."brewing",$sort);

		if ($go == "hidden") $redirect_go_to =  sprintf("Location: %s", $base_url."index.php");
		elseif ($go == "entries") $redirect_go_to =  sprintf("Location: %s", $base_url."index.php?section=admin&go=entries&msg=14");
		else $redirect_go_to =  sprintf("Location: %s", $base_url."index.php?section=admin&msg=14");

	}

	// Check for any entry fee discounts
	elseif ($action == "check_discount") {

		$query_contest_info1 = sprintf("SELECT contestEntryFeePassword FROM %s WHERE id='1'",$prefix."contest_info");
		if (SINGLE) $query_contest_info1 .= sprintf(" WHERE comp_id='%s'",$_SESSION['comp_id']);
		$contest_info1 = mysqli_query($connection,$query_contest_info1) or die (mysqli_error($connection));
		$row_contest_info1 = mysqli_fetch_assoc($contest_info1);

		$secretKey = base64_encode(bin2hex($password));
		$nacl = base64_encode(bin2hex($server_root));
		$contestEntryFeePassword = simpleDecrypt($row_contest_info1['contestEntryFeePassword'], $secretKey, $nacl);

		if (sterilize($_POST['brewerDiscount']) == $contestEntryFeePassword) {
			$updateSQL = sprintf("UPDATE $brewer_db_table SET brewerDiscount='%s' WHERE uid='%s'", "Y", $id);
			mysqli_real_escape_string($connection,$updateSQL);
			$result = mysqli_query($connection,$updateSQL) or die (mysqli_error($connection));
			$redirect_go_to = sprintf("Location: %s", $base_url."index.php?section=list&bid=".$id."&msg=15");
		}

		else $redirect_go_to = sprintf("Location: %s", $base_url."index.php?section=list&bid=".$id."&msg=16");
	}

	// Redeem an admin-issued coupon code to the current user account
	elseif ($action == "redeem_coupon") {

		if (!session_pref_enabled('prefsCoupons', 1)) {
			$redirect = $base_url."index.php?section=list";
			$redirect = prep_redirect_link($redirect);
			$redirect_go_to = sprintf("Location: %s", $redirect);
		}

		else {

		$user_id = intval($_SESSION['user_id']);
		$coupon_input = "";
		if (isset($_POST['couponCode'])) $coupon_input = strtoupper(trim(sterilize($_POST['couponCode'])));
		$coupon_input = preg_replace('/\s+/', '', $coupon_input);

		if (empty($coupon_input)) {
			$redirect_go_to = sprintf("Location: %s", $base_url."index.php?section=list&msg=18");
		}

		else {

			$query_coupon = sprintf(
				"SELECT id, credits_granted, max_redemptions, redeemed_count, is_active, expires_at FROM %s WHERE code='%s' LIMIT 1",
				$coupon_codes_db_table,
				mysqli_real_escape_string($connection, $coupon_input)
			);
			$coupon = mysqli_query($connection,$query_coupon) or die (mysqli_error($connection));
			$row_coupon = mysqli_fetch_assoc($coupon);
			$totalRows_coupon = mysqli_num_rows($coupon);

			$is_valid_coupon = TRUE;
			if ($totalRows_coupon == 0) $is_valid_coupon = FALSE;
			if (($is_valid_coupon) && (intval($row_coupon['is_active']) !== 1)) $is_valid_coupon = FALSE;
			if (($is_valid_coupon) && (!empty($row_coupon['expires_at'])) && (strtotime($row_coupon['expires_at']) < time())) $is_valid_coupon = FALSE;
			if (($is_valid_coupon) && (!empty($row_coupon['max_redemptions'])) && (intval($row_coupon['redeemed_count']) >= intval($row_coupon['max_redemptions']))) $is_valid_coupon = FALSE;

			if (!$is_valid_coupon) {
				$redirect_go_to = sprintf("Location: %s", $base_url."index.php?section=list&msg=18");
			}

			else {

				$query_redeemed = sprintf("SELECT id FROM %s WHERE coupon_code_id='%s' AND user_id='%s' LIMIT 1", $coupon_redemptions_db_table, $row_coupon['id'], $user_id);
				$redeemed = mysqli_query($connection,$query_redeemed) or die (mysqli_error($connection));
				$totalRows_redeemed = mysqli_num_rows($redeemed);

				if ($totalRows_redeemed > 0) {
					$redirect_go_to = sprintf("Location: %s", $base_url."index.php?section=list&msg=19");
				}

				else {
					$credits_granted = intval($row_coupon['credits_granted']);
					if ($credits_granted < 1) $credits_granted = 1;

					$insert_redemption = sprintf(
						"INSERT INTO %s (coupon_code_id, user_id, credits_granted, redeemed_at) VALUES ('%s', '%s', '%s', NOW())",
						$coupon_redemptions_db_table,
						$row_coupon['id'],
						$user_id,
						$credits_granted
					);
					$insert_result = mysqli_query($connection,$insert_redemption);

					if (!$insert_result) {
						$redirect_go_to = sprintf("Location: %s", $base_url."index.php?section=list&msg=19");
					}

					else {
						$update_coupon = sprintf(
							"UPDATE %s SET redeemed_count = redeemed_count + 1, updated_at = NOW() WHERE id='%s' AND (max_redemptions IS NULL OR redeemed_count < max_redemptions)",
							$coupon_codes_db_table,
							$row_coupon['id']
						);
						$update_result = mysqli_query($connection,$update_coupon);

						if (($update_result) && (mysqli_affected_rows($connection) > 0)) {
							$redirect_go_to = sprintf("Location: %s", $base_url."index.php?section=list&msg=17");
						}

						else {
							$delete_redemption = sprintf("DELETE FROM %s WHERE user_id='%s' AND coupon_code_id='%s' ORDER BY id DESC LIMIT 1", $coupon_redemptions_db_table, $user_id, $row_coupon['id']);
							mysqli_query($connection,$delete_redemption);
							$redirect_go_to = sprintf("Location: %s", $base_url."index.php?section=list&msg=18");
						}
					}
				}
			}
		}

		}

	}

	// Apply one coupon credit to a single entry and mark it paid
	elseif ($action == "apply_coupon_to_entry") {

		if (!session_pref_enabled('prefsCoupons', 1)) {
			$redirect = $base_url."index.php?section=list";
			$redirect = prep_redirect_link($redirect);
			$redirect_go_to = sprintf("Location: %s", $redirect);
		}

		else {

		$user_id = intval($_SESSION['user_id']);
		$entry_id = intval($id);

		$query_entry = sprintf("SELECT id, brewPaid FROM %s WHERE id='%s' AND brewBrewerID='%s' LIMIT 1", $brewing_db_table, $entry_id, $user_id);
		$entry = mysqli_query($connection,$query_entry) or die (mysqli_error($connection));
		$row_entry = mysqli_fetch_assoc($entry);
		$totalRows_entry = mysqli_num_rows($entry);

		if (($totalRows_entry == 0) || (intval($row_entry['brewPaid']) === 1)) {
			$redirect_go_to = sprintf("Location: %s", $base_url."index.php?section=list&msg=22");
		}

		else {
			$coupon_credits = coupon_available_credits($user_id);
			if ($coupon_credits < 1) {
				$redirect_go_to = sprintf("Location: %s", $base_url."index.php?section=list&msg=21");
			}

			else {
				// Find the oldest redemption that still has credits available (FIFO)
				$query_find_redemption = sprintf(
					"SELECT r.id, r.credits_granted,
					        (SELECT COUNT(*) FROM %s ep WHERE ep.redemption_id = r.id) AS credits_used
					 FROM %s r
					 WHERE r.user_id = '%s'
					 HAVING credits_used < r.credits_granted
					 ORDER BY r.redeemed_at ASC
					 LIMIT 1",
					$coupon_entry_payments_db_table,
					$coupon_redemptions_db_table,
					$user_id
				);
				$find_redemption = mysqli_query($connection, $query_find_redemption);
				$row_find_redemption = ($find_redemption) ? mysqli_fetch_assoc($find_redemption) : null;
				$redemption_id_to_use = ($row_find_redemption) ? intval($row_find_redemption['id']) : "NULL";

				$insert_apply = sprintf(
					"INSERT INTO %s (entry_id, user_id, redemption_id, created_at) VALUES ('%s', '%s', %s, NOW())",
					$coupon_entry_payments_db_table,
					$entry_id,
					$user_id,
					$redemption_id_to_use
				);
				$insert_apply_result = mysqli_query($connection,$insert_apply);

				if (!$insert_apply_result) {
					$redirect_go_to = sprintf("Location: %s", $base_url."index.php?section=list&msg=22");
				}

				else {
					$update_entry_paid = sprintf("UPDATE %s SET brewPaid='1' WHERE id='%s' AND brewBrewerID='%s' AND (brewPaid IS NULL OR brewPaid='0')", $brewing_db_table, $entry_id, $user_id);
					$update_entry_result = mysqli_query($connection,$update_entry_paid);

					if (($update_entry_result) && (mysqli_affected_rows($connection) > 0)) {
						$redirect_go_to = sprintf("Location: %s", $base_url."index.php?section=list&msg=20");
					}

					else {
						$delete_apply = sprintf("DELETE FROM %s WHERE entry_id='%s' LIMIT 1", $coupon_entry_payments_db_table, $entry_id);
						mysqli_query($connection,$delete_apply);
						$redirect_go_to = sprintf("Location: %s", $base_url."index.php?section=list&msg=22");
					}
				}
			}
		}
		}

	}

	// Add a new admin-managed coupon code
	elseif (($action == "coupon_add") && ($_SESSION['userLevel'] == 0) && (session_pref_enabled('prefsCoupons', 1))) {

		$code = "";
		if (isset($_POST['couponCode'])) $code = strtoupper(trim(sterilize($_POST['couponCode'])));
		$code = preg_replace('/\s+/', '', $code);

		$credits_granted = 1;
		if (isset($_POST['creditsGranted'])) $credits_granted = intval($_POST['creditsGranted']);
		if ($credits_granted < 1) $credits_granted = 1;

		$max_redemptions_sql = "NULL";
		if ((isset($_POST['maxRedemptions'])) && ($_POST['maxRedemptions'] !== "")) {
			$max_redemptions = intval($_POST['maxRedemptions']);
			if ($max_redemptions > 0) $max_redemptions_sql = "'".$max_redemptions."'";
		}

		$expires_at_sql = "NULL";
		if ((isset($_POST['expiresAt'])) && (!empty($_POST['expiresAt']))) {
			$expires_at = sterilize($_POST['expiresAt']);
			$expires_at = str_replace("T", " ", $expires_at).":00";
			$expires_at_sql = "'".mysqli_real_escape_string($connection, $expires_at)."'";
		}

		if (empty($code)) {
			$redirect_go_to = sprintf("Location: %s", $base_url."index.php?section=admin&go=coupons&msg=3");
		}

		else {
			$insert_coupon = sprintf(
				"INSERT INTO %s (code, credits_granted, max_redemptions, redeemed_count, expires_at, is_active, created_at, updated_at) VALUES ('%s', '%s', %s, '0', %s, '1', NOW(), NOW())",
				$coupon_codes_db_table,
				mysqli_real_escape_string($connection, $code),
				$credits_granted,
				$max_redemptions_sql,
				$expires_at_sql
			);
			$result_coupon = mysqli_query($connection,$insert_coupon);
			if ($result_coupon) $redirect_go_to = sprintf("Location: %s", $base_url."index.php?section=admin&go=coupons&msg=1");
			else $redirect_go_to = sprintf("Location: %s", $base_url."index.php?section=admin&go=coupons&msg=3");
		}
	}

	// Enable or disable an existing coupon code
	elseif (($action == "coupon_toggle") && ($_SESSION['userLevel'] == 0) && (session_pref_enabled('prefsCoupons', 1))) {
		$coupon_id = intval($id);
		$is_active = 0;
		if ((isset($_GET['view'])) && ($_GET['view'] == "1")) $is_active = 1;
		$update_coupon = sprintf("UPDATE %s SET is_active='%s', updated_at=NOW() WHERE id='%s'", $coupon_codes_db_table, $is_active, $coupon_id);
		mysqli_query($connection,$update_coupon);
		$redirect_go_to = sprintf("Location: %s", $base_url."index.php?section=admin&go=coupons&msg=2");
	}

	// Import coupon codes from CSV file
	elseif (($action == "coupon_import") && ($_SESSION['userLevel'] == 0) && (session_pref_enabled('prefsCoupons', 1))) {
		$inserted_count = 0;
		$skipped_count = 0;

		$file_ok = FALSE;
		if ((isset($_FILES['couponImportFile'])) && (is_uploaded_file($_FILES['couponImportFile']['tmp_name']))) {
			$filename = $_FILES['couponImportFile']['name'];
			$extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
			if ($extension == "csv") $file_ok = TRUE;
		}

		if (!$file_ok) {
			$redirect_go_to = sprintf("Location: %s", $base_url."index.php?section=admin&go=coupons&msg=39");
		}

		else {
			$import_handle = fopen($_FILES['couponImportFile']['tmp_name'], 'r');
			if ($import_handle === FALSE) {
				$redirect_go_to = sprintf("Location: %s", $base_url."index.php?section=admin&go=coupons&msg=39");
			}

			else {
				$line_number = 0;
				while (($row_data = fgetcsv($import_handle, 1000, ",")) !== FALSE) {
					$line_number++;
					if (empty($row_data)) continue;

					$code_raw = "";
					if (isset($row_data[0])) $code_raw = trim($row_data[0]);
					$code = strtoupper($code_raw);
					$code = preg_replace('/\s+/', '', $code);

					// Skip header row.
					if (($line_number == 1) && (strtolower($code_raw) == "code")) continue;

					if (empty($code)) {
						$skipped_count++;
						continue;
					}

					$credits_granted = 1;
					if ((isset($row_data[1])) && ($row_data[1] !== "")) {
						$credits_granted = intval($row_data[1]);
						if ($credits_granted < 1) $credits_granted = 1;
					}

					$max_redemptions_sql = "NULL";
					if ((isset($row_data[2])) && ($row_data[2] !== "")) {
						$max_redemptions = intval($row_data[2]);
						if ($max_redemptions > 0) $max_redemptions_sql = "'".$max_redemptions."'";
					}

					$expires_at_sql = "NULL";
					if ((isset($row_data[3])) && ($row_data[3] !== "")) {
						$expires_timestamp = strtotime($row_data[3]);
						if ($expires_timestamp !== FALSE) {
							$expires_at_sql = "'".date('Y-m-d H:i:s', $expires_timestamp)."'";
						}
					}

					$is_active = 1;
					if ((isset($row_data[4])) && ($row_data[4] !== "")) {
						$is_active = intval($row_data[4]) ? 1 : 0;
					}

					$insert_coupon = sprintf(
						"INSERT INTO %s (code, credits_granted, max_redemptions, redeemed_count, expires_at, is_active, created_at, updated_at)
						VALUES ('%s', '%s', %s, '0', %s, '%s', NOW(), NOW())",
						$coupon_codes_db_table,
						mysqli_real_escape_string($connection, $code),
						$credits_granted,
						$max_redemptions_sql,
						$expires_at_sql,
						$is_active
					);

					$result_coupon = mysqli_query($connection,$insert_coupon);
					if ($result_coupon) $inserted_count++;
					else $skipped_count++;
				}

				fclose($import_handle);
				$redirect_go_to = sprintf("Location: %s", $base_url."index.php?section=admin&go=coupons&msg=38&inserted=".$inserted_count."&sort=".$skipped_count);
			}
		}
	}

	// Convert entries to selected BJCP version
	elseif ($action == "convert_bjcp") {

		include (LIB.'convert.lib.php');

		if ($_SESSION['prefsStyleSet'] == "BJCP2008") {

			include (INCLUDES.'convert/convert_bjcp_2015.inc.php');

			$updateSQL = sprintf("UPDATE %s SET prefsStyleSet='%s' WHERE id='%s'",$prefix."preferences","BJCP2015","1");
			mysqli_real_escape_string($connection,$updateSQL);
			$result = mysqli_query($connection,$updateSQL) or die (mysqli_error($connection));

		}

		if ($_SESSION['prefsStyleSet'] == "BJCP2015") {

			include (INCLUDES.'convert/convert_bjcp_2021.inc.php');

			$updateSQL = sprintf("UPDATE %s SET prefsStyleSet='%s' WHERE id='%s'",$prefix."preferences","BJCP2021","1");
			mysqli_real_escape_string($connection,$updateSQL);
			$result = mysqli_query($connection,$updateSQL) or die (mysqli_error($connection));

		}

		if ($_SESSION['prefsStyleSet'] == "BJCP2021") {

			include (INCLUDES.'convert/convert_bjcp_2025.inc.php');

			$updateSQL = sprintf("UPDATE %s SET prefsStyleSet='%s' WHERE id='%s'",$prefix."preferences","BJCP2025","1");
			mysqli_real_escape_string($connection,$updateSQL);
			$result = mysqli_query($connection,$updateSQL) or die (mysqli_error($connection));

		}
		
		if (session_status() === PHP_SESSION_NONE) {
			session_name($prefix_session);
			session_start();
		}
		
		unset($_SESSION['prefs'.$prefix_session]);

		$redirect_go_to = sprintf("Location: %s", $base_url."index.php?section=admin&go=entries&msg=25");

	}

	// Archive data
	elseif ($action == "archive") {

		if (HOSTED) include (PROCESS.'process_archive_hosted.inc.php');
		else include (PROCESS.'process_archive.inc.php');

	}

	/**
	 * Publish results - resets pertinent dates to the current timestamp -
	 * entry, acct registration, judge/steward registration, and judging deadlines.
	 * Marks all relevant dates in the past to trigger the winner display.
	 */ 
	elseif ($action == "publish") {

		$update = sprintf("UPDATE %s SET prefsDisplayWinners='%s', prefsWinnerDelay='%s' WHERE id='%s'",$prefix."preferences","Y",time(),"1");
		mysqli_real_escape_string($connection,$update);
		$result = mysqli_query($connection,$update) or die (mysqli_error($connection));

		if ($_SESSION['contestRegistrationDeadline'] > time()) {
			$update = sprintf("UPDATE %s SET contestRegistrationDeadline='%s' WHERE id='%s'",$prefix."contest_info",time(),"1");
			mysqli_real_escape_string($connection,$update);
			$result = mysqli_query($connection,$update) or die (mysqli_error($connection));
		}

		if ($_SESSION['contestEntryDeadline'] > time()) {
			$update = sprintf("UPDATE %s SET contestEntryDeadline='%s' WHERE id='%s'",$prefix."contest_info",time(),"1");
			mysqli_real_escape_string($connection,$update);
			$result = mysqli_query($connection,$update) or die (mysqli_error($connection));
		}

		if ($_SESSION['contestJudgeDeadline'] > time()) {
			$update = sprintf("UPDATE %s SET contestJudgeDeadline='%s' WHERE id='%s'",$prefix."contest_info",time(),"1");
			mysqli_real_escape_string($connection,$update);
			$result = mysqli_query($connection,$update) or die (mysqli_error($connection));
		}

		if ($_SESSION['jPrefsJudgingClosed'] > time()) {
			$update = sprintf("UPDATE %s SET jPrefsJudgingClosed='%s' WHERE id='%s'",$prefix."judging_preferences",time(),"1");
			mysqli_real_escape_string($connection,$update);
			$result = mysqli_query($connection,$update) or die (mysqli_error($connection));
		}

		$query_judging_locations = sprintf("SELECT id,judgingDate FROM %s",$prefix."judging_locations",time());
		$judging_locations = mysqli_query($connection,$query_judging_locations) or die (mysqli_error($connection));
		$row_judging_locations = mysqli_fetch_assoc($judging_locations);
		$totalRows_judging_locations = mysqli_num_rows($judging_locations);

		if ($totalRows_judging_locations > 0) {
			
			do {

				if ($row_judging_locations['judgingDate'] > time()) {

					$update = sprintf("UPDATE %s SET judgingDate='%s' WHERE id='%s'",$prefix."judging_locations",time(),$row_judging_locations['id']);
					mysqli_real_escape_string($connection,$update);
					$result = mysqli_query($connection,$update) or die (mysqli_error($connection));

				}

			} while($row_judging_locations = mysqli_fetch_assoc($judging_locations));
		
		}

		$update = sprintf("UPDATE %s SET judgingDateEnd='%s' WHERE judgingLocType='1'",$prefix."judging_locations",time());
		mysqli_real_escape_string($connection,$update);
		$result = mysqli_query($connection,$update) or die (mysqli_error($connection));

		if (session_status() === PHP_SESSION_NONE) {
			session_name($prefix_session);
			session_start();
		}
		unset($_SESSION['prefs'.$prefix_session]);

		$redirect_go_to = sprintf("Location: %s", $base_url."index.php?section=admin&msg=36");

	}

	/**
	 * Release personal scores and scoresheets without publishing winners.
	 */
	elseif ($action == "release_scores") {

		$update = sprintf("UPDATE %s SET prefsDisplayScores='%s' WHERE id='%s'",$prefix."preferences","Y","1");
		mysqli_real_escape_string($connection,$update);
		$result = mysqli_query($connection,$update) or die (mysqli_error($connection));

		if (session_status() === PHP_SESSION_NONE) {
			session_name($prefix_session);
			session_start();
		}
		unset($_SESSION['prefs'.$prefix_session]);

		$redirect_go_to = sprintf("Location: %s", $base_url."index.php?section=admin&msg=2");

	}

	// Email functions
	elseif (($action == "email") && ($dbTable == "default")) include (PROCESS.'process_email.inc.php');
	
	// Paypal IPN
	elseif (($action == "paypal") && ($dbTable == "default")) include (PROCESS.'process_paypal.inc.php');
	
	// Updates to associated entry, acct registration, judge/steward registration, and judging dates
	elseif (($action == "dates") && ($dbTable == "default")) include (PROCESS.'process_dates.inc.php');
	
	// Update to various DB Tables as called out in process URL
	else {

		if ($dbTable == $prefix."brewing") include (PROCESS.'process_brewing.inc.php');
		if ($dbTable == $prefix."users") include (PROCESS.'process_users.inc.php');
		if ($dbTable == $prefix."brewer") include (PROCESS.'process_brewer.inc.php');
		if ($dbTable == $prefix."contest_info") include (PROCESS.'process_comp_info.inc.php');
		if ($dbTable == $prefix."preferences") include (PROCESS.'process_prefs.inc.php');
		if ($dbTable == $prefix."sponsors") include (PROCESS.'process_sponsors.inc.php');
		if ($dbTable == $prefix."judging_locations") include (PROCESS.'process_judging_locations.inc.php');
		if ($dbTable == $prefix."drop_off") include (PROCESS.'process_drop_off.inc.php');
		if (($dbTable == $prefix."styles") || ($dbTable == "bcoem_shared_styles")) include (PROCESS.'process_styles.inc.php');
		if ($dbTable == $prefix."contacts") include (PROCESS.'process_contacts.inc.php');
		if ($dbTable == $prefix."judging_preferences") include (PROCESS.'process_judging_preferences.inc.php');
		if ($dbTable == $prefix."judging_tables") include (PROCESS.'process_judging_tables.inc.php');
		if ($dbTable == $prefix."judging_flights") include (PROCESS.'process_judging_flights.inc.php');
		if ($dbTable == $prefix."judging_assignments") include (PROCESS.'process_judging_assignments.inc.php');
		if ($dbTable == $prefix."judging_scores") include (PROCESS.'process_judging_scores.inc.php');
		if ($dbTable == $prefix."judging_scores_bos") include (PROCESS.'process_judging_scores_bos.inc.php');
		if ($dbTable == $prefix."style_types") include (PROCESS.'process_style_types.inc.php');
		if ($dbTable == $prefix."special_best_info") include (PROCESS.'process_special_best_info.inc.php');
		if ($dbTable == $prefix."special_best_data") include (PROCESS.'process_special_best_data.inc.php');
		if ($dbTable == $prefix."mods") include (PROCESS.'process_mods.inc.php');
		if ($dbTable == $prefix."evaluation") include (EVALS.'process.eval.php');

	}

	if (DEBUG) include (DEBUGGING.'query_count_end.debug.php');
	session_write_close();

	// Failsafe to convert &amp; to & and so on for use in header redirect.
	$redirect_go_to = html_entity_decode($redirect_go_to);
	header($redirect_go_to);

}

else {
	header(sprintf("Location: %s", $base_url."index.php?msg=98"));
}

exit();
?>