<?php
/**
 * Local scenario tests for the auto-averaged consensus flow.
 *
 * Usage:
 *   C:\xampp\php\php.exe sql\test_reconcile_scenarios.php
 *
 * Optional env overrides: DB_HOST DB_USER DB_PASSWORD DB_NAME DB_PREFIX
 *
 * Creates temporary mock evaluations for two (then three) judges on one
 * shared entry, exercises the consensus averaging math (mirrors
 * recompute_eval_consensus() in lib/common.lib.php) and the simplified
 * check_partner_eval.ajax.php payload shape, then cleans up those mock
 * rows (does not wipe other seeded data).
 *
 * This script talks to the DB directly rather than bootstrapping the app,
 * so the averaging/rounding math below is a mirror of
 * recompute_eval_consensus() - if that function's math changes, update
 * recompute_consensus_sql() here to match.
 */

$hostname = getenv('DB_HOST') !== false ? getenv('DB_HOST') : 'localhost';
$username = getenv('DB_USER') !== false ? getenv('DB_USER') : 'root';
$password = getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : '';
$database = getenv('DB_NAME') !== false ? getenv('DB_NAME') : 'mbc_local';
$p = getenv('DB_PREFIX') !== false ? getenv('DB_PREFIX') : 'baseline_';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = new mysqli($hostname, $username, $password, $database);
$db->set_charset('utf8mb4');

$pass = 0;
$fail = 0;
$failures = array();

function assert_true($label, $condition, $detail = '') {
	global $pass, $fail, $failures;
	if ($condition) {
		$pass++;
		echo "[PASS] {$label}\n";
		return;
	}
	$fail++;
	$msg = $detail !== '' ? "{$label} :: {$detail}" : $label;
	$failures[] = $msg;
	echo "[FAIL] {$msg}\n";
}

function column_exists($db, $table, $column) {
	$safe_table = mysqli_real_escape_string($db, $table);
	$safe_col = mysqli_real_escape_string($db, $column);
	$res = $db->query("SHOW COLUMNS FROM `{$safe_table}` LIKE '{$safe_col}'");
	return ($res && $res->num_rows > 0);
}

echo "=== Reconcile scenario tests ({$database}) ===\n";

// ---------------------------------------------------------------------------
// Schema prerequisites
// ---------------------------------------------------------------------------
assert_true(
	"evalFinalScore is float",
	column_exists($db, "{$p}evaluation", "evalFinalScore") &&
	(($row = $db->query("SHOW COLUMNS FROM {$p}evaluation LIKE 'evalFinalScore'")->fetch_assoc()) && stripos($row['Type'], 'float') !== false),
	isset($row['Type']) ? "got {$row['Type']}" : "missing"
);

if (!column_exists($db, "{$p}evaluation", "evalDraft")) {
	$db->query("ALTER TABLE `{$p}evaluation` ADD `evalDraft` TINYINT(1) NULL DEFAULT 0 COMMENT '1=in-progress autosave draft; 0=finalized evaluation' AFTER `evalFinalScore`");
	echo "[INFO] Added missing evalDraft column for local parity.\n";
}
assert_true("evalDraft exists", column_exists($db, "{$p}evaluation", "evalDraft"));

// ---------------------------------------------------------------------------
// Pick shared flight entry + two assigned judges (table 14: James + Priya)
// ---------------------------------------------------------------------------
$judge_a = 53; // James Carter
$judge_b = 54; // Priya Shah
$judge_c = 55; // third judge, used for the multi-judge averaging scenario
$table_id = 14;

$entry = $db->query("SELECT b.id AS eid, b.brewJudgingNumber, b.brewBrewerID AS brewer_uid
	FROM {$p}judging_flights f
	JOIN {$p}brewing b ON b.id = f.flightEntryID
	WHERE f.flightTable = {$table_id}
	ORDER BY b.id ASC
	LIMIT 1")->fetch_assoc();

assert_true("Found shared flight entry on table {$table_id}", !empty($entry));
if (empty($entry)) {
	echo "Cannot continue without a shared entry.\n";
	exit(1);
}

$eid = (int)$entry['eid'];
$brewer_uid = (int)$entry['brewer_uid'];
echo "[INFO] Using entry eid={$eid} judging#={$entry['brewJudgingNumber']} table={$table_id}\n";
echo "[INFO] Judges: A={$judge_a} (James), B={$judge_b} (Priya), C={$judge_c} (mock)\n";

// Clean any prior mock rows for this entry/judges so the test is idempotent
$db->query("DELETE FROM {$p}evaluation WHERE eid={$eid} AND evalJudgeInfo IN ({$judge_a},{$judge_b},{$judge_c})");

$now = time();
$style = $db->query("SELECT id FROM {$p}styles WHERE brewStyleVersion IN ('BJCP2021','BJCP2025') ORDER BY id ASC LIMIT 1")->fetch_assoc();
$style_id = !empty($style['id']) ? (int)$style['id'] : 1;

function insert_mock_eval($db, $p, $eid, $brewer_uid, $judge_id, $table_id, $style_id, $scores, $now) {
	$token = 'mock-reconcile-' . $judge_id . '-' . $eid;
	$sql = sprintf(
		"INSERT INTO {$p}evaluation
			(eid, uid, evalToken, evalJudgeInfo, evalScoresheet, evalStyle,
			 evalAromaScore, evalAppearanceScore, evalFlavorScore, evalMouthfeelScore, evalOverallScore,
			 evalOverallComments, evalInitialDate, evalUpdatedDate, evalTable, evalFinalScore, evalDraft, evalWaiveConsensus, evalMiniBOS)
		 VALUES
			(%d, %d, '%s', %d, 1, %d,
			 %d, %d, %d, %d, %d,
			 'Mock reconcile scenario scoresheet.', %d, %d, %d, NULL, 0, NULL, 0)",
		$eid,
		$brewer_uid,
		mysqli_real_escape_string($db, $token),
		$judge_id,
		$style_id,
		$scores['aroma'],
		$scores['appearance'],
		$scores['flavor'],
		$scores['mouthfeel'],
		$scores['overall'],
		$now,
		$now,
		$table_id
	);
	$db->query($sql);
	return (int)$db->insert_id;
}

// Mirrors recompute_eval_consensus() in lib/common.lib.php: average every
// non-draft judge's total for the entry, round to the nearest 0.5, and
// write that same value to every non-draft row for the entry.
function recompute_consensus_sql($db, $p, $eid, $draft_filter) {
	$rows = $db->query("SELECT evalAromaScore, evalAppearanceScore, evalFlavorScore, evalMouthfeelScore, evalOverallScore FROM {$p}evaluation WHERE eid={$eid}{$draft_filter}");
	$total = 0;
	$count = 0;
	while ($row = $rows->fetch_assoc()) {
		$total += $row['evalAromaScore'] + $row['evalAppearanceScore'] + $row['evalFlavorScore'] + $row['evalMouthfeelScore'] + $row['evalOverallScore'];
		$count++;
	}
	if ($count === 0) return null;
	$consensus = round(($total / $count) * 2) / 2;
	$db->query("UPDATE {$p}evaluation SET evalFinalScore={$consensus} WHERE eid={$eid}{$draft_filter}");
	return $consensus;
}

function poll_judges($db, $p, $eid, $draft_filter) {
	$judges_res = $db->query("SELECT a.id, a.evalJudgeInfo, a.evalAromaScore, a.evalAppearanceScore, a.evalFlavorScore, a.evalMouthfeelScore, a.evalOverallScore, a.evalFinalScore, b.brewerFirstName, b.brewerLastName
		FROM {$p}evaluation a
		LEFT JOIN {$p}brewer b ON a.evalJudgeInfo = b.uid
		WHERE a.eid={$eid}{$draft_filter}
		ORDER BY a.id ASC");
	$judges = array();
	while ($row = $judges_res->fetch_assoc()) {
		$judges[] = array(
			'id' => (int)$row['id'],
			'judge_id' => (int)$row['evalJudgeInfo'],
			'name' => trim($row['brewerFirstName'].' '.mb_substr($row['brewerLastName'], 0, 1).'.'),
			'total' => (float)$row['evalAromaScore'] + (float)$row['evalAppearanceScore'] + (float)$row['evalFlavorScore'] + (float)$row['evalMouthfeelScore'] + (float)$row['evalOverallScore'],
			'consensus' => (($row['evalFinalScore'] !== null) && ($row['evalFinalScore'] !== '')) ? (float)$row['evalFinalScore'] : null,
		);
	}
	return $judges;
}

$draft_filter = " AND (evalDraft <> '1' OR evalDraft IS NULL)";

// ---------------------------------------------------------------------------
// Scenario: two judges, no consensus until both have scored
// ---------------------------------------------------------------------------
$id_a = insert_mock_eval($db, $p, $eid, $brewer_uid, $judge_a, $table_id, $style_id, array(
	'aroma' => 8, 'appearance' => 3, 'flavor' => 10, 'mouthfeel' => 3, 'overall' => 3
), $now);

$total_a = $db->query("SELECT (evalAromaScore+evalAppearanceScore+evalFlavorScore+evalMouthfeelScore+evalOverallScore) AS t FROM {$p}evaluation WHERE id={$id_a}")->fetch_assoc();
assert_true("Judge A total is 27", (int)$total_a['t'] === 27, "got {$total_a['t']}");

recompute_consensus_sql($db, $p, $eid, $draft_filter);
$solo_consensus = $db->query("SELECT evalFinalScore FROM {$p}evaluation WHERE id={$id_a}")->fetch_assoc();
assert_true("Consensus with 1 judge equals that judge's own total (27)", (float)$solo_consensus['evalFinalScore'] === 27.0, "got {$solo_consensus['evalFinalScore']}");

$id_b = insert_mock_eval($db, $p, $eid, $brewer_uid, $judge_b, $table_id, $style_id, array(
	'aroma' => 7, 'appearance' => 3, 'flavor' => 10, 'mouthfeel' => 3, 'overall' => 3
), $now + 30);

$total_b = $db->query("SELECT (evalAromaScore+evalAppearanceScore+evalFlavorScore+evalMouthfeelScore+evalOverallScore) AS t FROM {$p}evaluation WHERE id={$id_b}")->fetch_assoc();
assert_true("Judge B total is 26", (int)$total_b['t'] === 26, "got {$total_b['t']}");

$consensus = recompute_consensus_sql($db, $p, $eid, $draft_filter);
assert_true("Average of 27 and 26 rounds to 26.5", $consensus === 26.5, "got {$consensus}");

$judges = poll_judges($db, $p, $eid, $draft_filter);
assert_true("Poll judges array has 2 rows", count($judges) === 2, "got ".count($judges));
assert_true("Poll judges include both totals 27 and 26", (
	(($judges[0]['total'] == 27 && $judges[1]['total'] == 26) || ($judges[0]['total'] == 26 && $judges[1]['total'] == 27))
));
assert_true("Poll judge names are populated", $judges[0]['name'] !== '.' && $judges[1]['name'] !== '.');
assert_true("Both judges' rows carry the same 26.5 consensus", ($judges[0]['consensus'] === 26.5) && ($judges[1]['consensus'] === 26.5), json_encode($judges));

// ---------------------------------------------------------------------------
// Scenario: partner edits their sheet total; consensus should recompute
// ---------------------------------------------------------------------------
$db->query("UPDATE {$p}evaluation SET evalOverallScore=4, evalUpdatedDate=".($now+60)." WHERE id={$id_b}"); // 26 -> 27
$new_total_b = $db->query("SELECT (evalAromaScore+evalAppearanceScore+evalFlavorScore+evalMouthfeelScore+evalOverallScore) AS t FROM {$p}evaluation WHERE id={$id_b}")->fetch_assoc();
assert_true("Partner edit changes judge B total to 27", (int)$new_total_b['t'] === 27, "got {$new_total_b['t']}");

$consensus = recompute_consensus_sql($db, $p, $eid, $draft_filter);
assert_true("Consensus recomputes to 27 once both totals are 27", $consensus === 27.0, "got {$consensus}");

// ---------------------------------------------------------------------------
// Scenario: a third judge joins - consensus should average all three
// ---------------------------------------------------------------------------
$id_c = insert_mock_eval($db, $p, $eid, $brewer_uid, $judge_c, $table_id, $style_id, array(
	'aroma' => 8, 'appearance' => 4, 'flavor' => 12, 'mouthfeel' => 4, 'overall' => 4
), $now + 90);
$total_c = $db->query("SELECT (evalAromaScore+evalAppearanceScore+evalFlavorScore+evalMouthfeelScore+evalOverallScore) AS t FROM {$p}evaluation WHERE id={$id_c}")->fetch_assoc();
assert_true("Judge C total is 32", (int)$total_c['t'] === 32, "got {$total_c['t']}");

$consensus = recompute_consensus_sql($db, $p, $eid, $draft_filter);
// (27 + 27 + 32) / 3 = 28.666..., rounds to nearest 0.5 -> 28.5
assert_true("Three-judge average (27,27,32) rounds to 28.5", $consensus === 28.5, "got {$consensus}");

$judges = poll_judges($db, $p, $eid, $draft_filter);
assert_true("Poll judges array has 3 rows after third judge joins", count($judges) === 3, "got ".count($judges));
foreach ($judges as $j) {
	assert_true("Judge id={$j['judge_id']} carries the shared 28.5 consensus", $j['consensus'] === 28.5, json_encode($j));
}

// ---------------------------------------------------------------------------
// Scenario: removing a judge's evaluation recomputes the remaining average
// ---------------------------------------------------------------------------
$db->query("DELETE FROM {$p}evaluation WHERE id={$id_c}");
$consensus = recompute_consensus_sql($db, $p, $eid, $draft_filter);
assert_true("Removing judge C's eval drops consensus back to 27 (27,27 avg)", $consensus === 27.0, "got {$consensus}");

// ---------------------------------------------------------------------------
// Scenario: waiting-state transition (1 judge -> 2 judges)
// ---------------------------------------------------------------------------
$db->query("DELETE FROM {$p}evaluation WHERE id={$id_b}");
recompute_consensus_sql($db, $p, $eid, $draft_filter);
$wait_count = $db->query("SELECT COUNT(*) AS c FROM {$p}evaluation WHERE eid={$eid}{$draft_filter}")->fetch_assoc();
assert_true("Waiting state: only 1 judge scored", (int)$wait_count['c'] === 1);

// Re-add partner
$id_b = insert_mock_eval($db, $p, $eid, $brewer_uid, $judge_b, $table_id, $style_id, array(
	'aroma' => 7, 'appearance' => 3, 'flavor' => 10, 'mouthfeel' => 3, 'overall' => 3
), $now + 120);
recompute_consensus_sql($db, $p, $eid, $draft_filter);
$ready_count = $db->query("SELECT COUNT(*) AS c FROM {$p}evaluation WHERE eid={$eid}{$draft_filter}")->fetch_assoc();
assert_true("Waiting screen would resolve: partner arrival makes judge_count > 1", (int)$ready_count['c'] === 2);

// Draft rows must not count toward partner readiness or the average
if (column_exists($db, "{$p}evaluation", "evalDraft")) {
	$db->query("INSERT INTO {$p}evaluation
		(eid, uid, evalToken, evalJudgeInfo, evalScoresheet, evalStyle,
		 evalAromaScore, evalAppearanceScore, evalFlavorScore, evalMouthfeelScore, evalOverallScore,
		 evalOverallComments, evalInitialDate, evalUpdatedDate, evalTable, evalDraft)
		VALUES
		({$eid}, {$brewer_uid}, 'mock-draft-{$judge_c}-{$eid}', {$judge_c}, 1, {$style_id},
		 1, 1, 1, 1, 1,
		 'draft', {$now}, {$now}, {$table_id}, 1)");
	$draft_id = (int)$db->insert_id;
	$count_no_draft = $db->query("SELECT COUNT(*) AS c FROM {$p}evaluation WHERE eid={$eid}{$draft_filter}")->fetch_assoc();
	$count_with_draft = $db->query("SELECT COUNT(*) AS c FROM {$p}evaluation WHERE eid={$eid}")->fetch_assoc();
	assert_true("Draft rows excluded from partner poll count", (int)$count_no_draft['c'] === 2, "filtered={$count_no_draft['c']} raw={$count_with_draft['c']}");
	$consensus_with_draft_excluded = recompute_consensus_sql($db, $p, $eid, $draft_filter);
	// Judge A (27) + re-added Judge B (26, its original score) = 26.5; the
	// draft row's score of 5 must not pull that average down.
	assert_true("Draft rows excluded from the consensus average", $consensus_with_draft_excluded === 26.5, "got {$consensus_with_draft_excluded}");
	$db->query("DELETE FROM {$p}evaluation WHERE id={$draft_id}");
}

// ---------------------------------------------------------------------------
// Optional HTTP smoke (best-effort; does not fail suite if Apache/auth unavailable)
// ---------------------------------------------------------------------------
$http_base = getenv('HTTP_BASE') !== false ? getenv('HTTP_BASE') : 'http://localhost/mbc/';
echo "\n[INFO] Optional HTTP smoke against {$http_base}\n";

function http_get($url) {
	$ch = curl_init($url);
	curl_setopt_array($ch, array(
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_TIMEOUT => 8,
		CURLOPT_HEADER => true,
	));
	$body = curl_exec($ch);
	$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	$err = curl_error($ch);
	curl_close($ch);
	return array($code, $body, $err);
}

if (function_exists('curl_init')) {
	list($code, $body, $err) = http_get($http_base . 'index.php?section=evaluation&go=reconcile&id=' . $eid);
	if ($err) {
		echo "[SKIP] HTTP reconcile page unreachable: {$err}\n";
	} else {
		// Unauthenticated should redirect to login / not 500
		assert_true("HTTP reconcile endpoint responds (not 500)", $code > 0 && $code < 500, "HTTP {$code}");
		echo "[INFO] Unauthenticated reconcile HTTP status: {$code}\n";
	}

	list($code2, $body2, $err2) = http_get($http_base . 'ajax/check_partner_eval.ajax.php?eid=' . $eid);
	if ($err2) {
		echo "[SKIP] HTTP poll endpoint unreachable: {$err2}\n";
	} else {
		assert_true("HTTP poll endpoint responds (not 500)", $code2 > 0 && $code2 < 500, "HTTP {$code2}");
		$json_start = strpos($body2, '{');
		if ($json_start !== false) {
			$payload = json_decode(substr($body2, $json_start), true);
			assert_true("Unauthenticated poll returns status 9", is_array($payload) && (int)$payload['status'] === 9, json_encode($payload));
		}
	}
} else {
	echo "[SKIP] curl extension unavailable for HTTP smoke\n";
}

// ---------------------------------------------------------------------------
// Cleanup mock rows created by this script (unless KEEP_MOCKS=1 for browser QA)
// ---------------------------------------------------------------------------
$keep_mocks = (getenv('KEEP_MOCKS') === '1' || getenv('KEEP_MOCKS') === 'true');
if ($keep_mocks) {
	echo "[INFO] KEEP_MOCKS=1 set — leaving mock rows in place for browser testing.\n";
	echo "[INFO] Paired entry eid={$eid} (judges {$judge_a}/{$judge_b}).\n";
	echo "[INFO] Reconcile URL: index.php?section=evaluation&go=reconcile&id={$eid}\n";
} else {
	$db->query("DELETE FROM {$p}evaluation WHERE eid={$eid} AND evalJudgeInfo IN ({$judge_a},{$judge_b},{$judge_c}) AND (evalToken LIKE 'mock-reconcile-%' OR evalToken LIKE 'mock-draft-%' OR evalOverallComments='Mock reconcile scenario scoresheet.')");
	echo "[INFO] Cleaned up mock evaluation rows for this test run.\n";
}

echo "\n=== Results: {$pass} passed, {$fail} failed ===\n";
if ($fail > 0) {
	echo "Failures:\n - " . implode("\n - ", $failures) . "\n";
	exit(1);
}

echo "All reconcile scenarios passed.\n";
echo "\nManual browser checks (password for seeded judges: test123):\n";
echo "  1) Run: set KEEP_MOCKS=1 && C:\\xampp\\php\\php.exe sql\\test_reconcile_scenarios.php\n";
echo "  2) Login as james.judge@example.com / test123\n";
echo "  3) Open Judging Dashboard -> View Consensus for the test entry\n";
echo "  4) In another browser/profile login as priya.judge@example.com / test123 and edit/re-submit their scoresheet\n";
echo "  5) Confirm James' waiting/consensus page updates within ~4s without refresh\n";

exit(0);
