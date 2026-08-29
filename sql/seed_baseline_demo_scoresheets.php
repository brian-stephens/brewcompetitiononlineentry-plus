<?php
/**
 * Demo seed for user.baseline screenshot/video capture.
 *
 * Sets up British & Irish so:
 *  - user.baseline (uid=1) is HJ with exactly one partner judge
 *  - flight 1 has 5 beers
 *  - partner has already submitted all 5 scoresheets (no consensus yet)
 *  - baseline has no scoresheets yet
 *  - judging window / session times are open now
 *
 * Usage:
 *   C:\xampp\php\php.exe sql\seed_baseline_demo_scoresheets.php
 */

$hostname = getenv('DB_HOST') !== false ? getenv('DB_HOST') : 'localhost';
$username = getenv('DB_USER') !== false ? getenv('DB_USER') : 'root';
$password = getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : '';
$database = getenv('DB_NAME') !== false ? getenv('DB_NAME') : 'mbc_local';
$p = getenv('DB_PREFIX') !== false ? getenv('DB_PREFIX') : 'baseline_';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = new mysqli($hostname, $username, $password, $database);
$db->set_charset('utf8mb4');

$baseline_uid = 1;
$partner_email = 'priya.judge@example.com';
$now = time();

function resolve_style_id($db, $p, $sort, $sub) {
	$candidates = array_unique(array(
		(string)$sort,
		ltrim((string)$sort, '0'),
		str_pad((string)((int)$sort), 2, '0', STR_PAD_LEFT),
	));
	foreach ($candidates as $g) {
		if ($g === '' || $g === false) continue;
		$g_esc = $db->real_escape_string($g);
		$sub_esc = $db->real_escape_string((string)$sub);
		$res = $db->query("SELECT id FROM {$p}styles WHERE brewStyleGroup='{$g_esc}' AND brewStyleNum='{$sub_esc}' AND brewStyleActive='Y' LIMIT 1");
		if ($row = $res->fetch_assoc()) return (int)$row['id'];
	}
	return 0;
}

$partner = $db->query("SELECT id FROM {$p}users WHERE user_name='" . $db->real_escape_string($partner_email) . "' LIMIT 1")->fetch_assoc();
if (!$partner) {
	echo "Partner judge {$partner_email} not found. Run sql/seed_judging_test_data.php first.\n";
	exit(1);
}
$partner_uid = (int)$partner['id'];

$table = $db->query("SELECT t.id, t.tableName, t.tableLocation, t.tableStyles
	FROM {$p}judging_tables t
	WHERE t.tableName='British & Irish'
	LIMIT 1")->fetch_assoc();
if (!$table) {
	echo "British & Irish table not found. Run sql/seed_judging_test_data.php first.\n";
	exit(1);
}
$tid = (int)$table['id'];
$loc = (int)$table['tableLocation'];

echo "Preparing demo on {$table['tableName']} (table id={$tid})\n";
echo "Baseline uid={$baseline_uid}, partner={$partner_email} (uid={$partner_uid})\n";

// Re-open judging windows so Add Score is available
$session_end = $now + (6 * 3600);
$db->query("UPDATE {$p}judging_preferences SET
	jPrefsJudgingOpen=" . ($now - 3600) . ",
	jPrefsJudgingClosed=" . ($now + (36 * 3600)) . ",
	jPrefsJudgeStats='Y',
	jPrefsQueued='N'
	WHERE id=1");
$db->query("UPDATE {$p}judging_locations SET
	judgingDate=" . ($now - 1800) . ",
	judgingDateEnd={$session_end}
	WHERE id={$loc}");

// Ensure baseline is a judge
$db->query("UPDATE {$p}brewer SET
	brewerJudge='Y',
	brewerJudgeWaiver='Y',
	brewerJudgeLocation=CONCAT('Y-', {$loc})
	WHERE uid={$baseline_uid}");

$chk = $db->query("SELECT id FROM {$p}staff WHERE uid={$baseline_uid}");
if ($chk->num_rows == 0) {
	$db->query("INSERT INTO {$p}staff (uid, staff_judge, staff_judge_bos, staff_steward, staff_organizer, staff_staff)
		VALUES ({$baseline_uid}, 1, 0, 0, 1, 0)");
} else {
	$db->query("UPDATE {$p}staff SET staff_judge=1 WHERE uid={$baseline_uid}");
}

// Trim this table to exactly baseline + partner as judges
$db->query("DELETE FROM {$p}judging_assignments
	WHERE assignTable={$tid} AND assignment='J' AND bid NOT IN ({$baseline_uid}, {$partner_uid})");

$has_baseline = $db->query("SELECT id FROM {$p}judging_assignments WHERE assignTable={$tid} AND bid={$baseline_uid} AND assignment='J'")->num_rows;
if ($has_baseline == 0) {
	$db->query("INSERT INTO {$p}judging_assignments
		(bid, assignment, assignTable, assignFlight, assignRound, assignLocation, assignRoles, assignPlanning)
		VALUES ({$baseline_uid}, 'J', {$tid}, 1, 1, {$loc}, 'HJ', 0)");
} else {
	$db->query("UPDATE {$p}judging_assignments
		SET assignRoles='HJ', assignPlanning=0, assignFlight=1, assignRound=1, assignLocation={$loc}
		WHERE bid={$baseline_uid} AND assignTable={$tid}");
}

$has_partner = $db->query("SELECT id FROM {$p}judging_assignments WHERE assignTable={$tid} AND bid={$partner_uid} AND assignment='J'")->num_rows;
if ($has_partner == 0) {
	$db->query("INSERT INTO {$p}judging_assignments
		(bid, assignment, assignTable, assignFlight, assignRound, assignLocation, assignRoles, assignPlanning)
		VALUES ({$partner_uid}, 'J', {$tid}, 1, 1, {$loc}, '', 0)");
} else {
	$db->query("UPDATE {$p}judging_assignments
		SET assignRoles='', assignPlanning=0, assignFlight=1, assignRound=1, assignLocation={$loc}
		WHERE bid={$partner_uid} AND assignTable={$tid}");
}

// Ensure flight 1 has exactly 5 entries (promote from flight 2 if needed)
$f1 = $db->query("SELECT flightEntryID AS eid FROM {$p}judging_flights
	WHERE flightTable={$tid} AND flightNumber=1 ORDER BY id");
$flight1 = array();
while ($row = $f1->fetch_assoc()) $flight1[] = (int)$row['eid'];

while (count($flight1) < 5) {
	$next = $db->query("SELECT id, flightEntryID FROM {$p}judging_flights
		WHERE flightTable={$tid} AND flightNumber=2
		ORDER BY id LIMIT 1")->fetch_assoc();
	if (!$next) break;
	$db->query("UPDATE {$p}judging_flights SET flightNumber=1 WHERE id=" . (int)$next['id']);
	$flight1[] = (int)$next['flightEntryID'];
}

if (count($flight1) < 5) {
	// Create a 5th beer if still short
	$style_ids = array_filter(array_map('intval', explode(',', $table['tableStyles'])));
	$style_id = $style_ids ? $style_ids[0] : 0;
	$st = $style_id ? $db->query("SELECT * FROM {$p}styles WHERE id={$style_id}")->fetch_assoc() : null;
	$owner = $db->query("SELECT uid, brewerFirstName, brewerLastName FROM {$p}brewer WHERE uid <> 1 ORDER BY uid LIMIT 1")->fetch_assoc();
	$max_j = $db->query("SELECT MAX(CAST(brewJudgingNumber AS UNSIGNED)) AS m FROM {$p}brewing")->fetch_assoc();
	$jnum = str_pad((string)(((int)$max_j['m']) + 1), 6, '0', STR_PAD_LEFT);
	$group = $st ? $st['brewStyleGroup'] : '11';
	$sub = $st ? $st['brewStyleNum'] : 'A';
	$cat = (string)((int)$group);
	$sort = ((int)$group < 10) ? sprintf('%02d', (int)$group) : (string)((int)$group);
	$bname = 'Demo Flight Bitter';
	$style_name = $st ? $st['brewStyle'] : 'Ordinary Bitter';

	$db->query("INSERT INTO {$p}brewing (
		brewName, brewStyle, brewCategory, brewCategorySort, brewSubCategory, brewInfo,
		brewBrewerID, brewBrewerFirstName, brewBrewerLastName, brewPaid, brewABV,
		brewReceived, brewConfirmed, brewJudgingNumber, brewUpdated, brewStyleType, brewPackaging, brewBoxNum
	) VALUES (
		'" . $db->real_escape_string($bname) . "',
		'" . $db->real_escape_string($style_name) . "',
		'" . $db->real_escape_string($cat) . "',
		'" . $db->real_escape_string($sort) . "',
		'" . $db->real_escape_string($sub) . "',
		'Demo entry for scoresheet capture.',
		" . (int)$owner['uid'] . ",
		'" . $db->real_escape_string($owner['brewerFirstName']) . "',
		'" . $db->real_escape_string($owner['brewerLastName']) . "',
		1, 4.8, 1, 1, '{$jnum}', NOW(), 1, 'Bottle', 'B6'
	)");
	$eid = (int)$db->insert_id;
	$db->query("INSERT INTO {$p}judging_flights
		(flightTable, flightNumber, flightEntryID, flightRound, flightPlanning)
		VALUES ({$tid}, 1, '{$eid}', 1, 0)");
	$flight1[] = $eid;
}

// Cap flight 1 at 5 for a focused demo (demote extras back to flight 2)
$f1 = $db->query("SELECT id, flightEntryID AS eid FROM {$p}judging_flights
	WHERE flightTable={$tid} AND flightNumber=1 ORDER BY id");
$keep = array();
$i = 0;
while ($row = $f1->fetch_assoc()) {
	$i++;
	if ($i <= 5) $keep[] = (int)$row['eid'];
	else $db->query("UPDATE {$p}judging_flights SET flightNumber=2 WHERE id=" . (int)$row['id']);
}
$flight1 = $keep;

// Clear existing evals for these entries from baseline + partner (and any leftover judges on this table)
$eids_sql = implode(',', $flight1);
$db->query("DELETE FROM {$p}evaluation WHERE eid IN ({$eids_sql})");
$db->query("DELETE FROM {$p}evaluation WHERE evalJudgeInfo={$baseline_uid} AND evalTable={$tid}");

$scoresheet = (int)$db->query("SELECT jPrefsScoresheet FROM {$p}judging_preferences WHERE id=1")->fetch_assoc()['jPrefsScoresheet'];
if ($scoresheet < 1) $scoresheet = 1;

$comment_sets = array(
	array(
		'aroma' => 'Pleasant malt and light hop aroma with a hint of caramel. Clean fermentation character.',
		'appearance' => 'Clear amber with persistent off-white foam and good lacing.',
		'flavor' => 'Balanced malt and hop bitterness with a dry finish. No off flavors noted.',
		'mouthfeel' => 'Medium-light body with appropriate carbonation for the style.',
		'overall' => 'A solid, enjoyable example. Competitive in this flight with only minor refinements needed.',
	),
	array(
		'aroma' => 'Bready malt leads with moderate earthy hop notes. Aroma intensity is appropriate as the beer warms.',
		'appearance' => 'Bright copper color, excellent clarity, dense head retention.',
		'flavor' => 'Caramel malt with supportive bitterness and a clean, dry finish. Fermentation is neutral.',
		'mouthfeel' => 'Medium body, smooth palate, no astringency.',
		'overall' => 'Well made and stylistically accurate. A pleasure to judge.',
	),
	array(
		'aroma' => 'Toasty malt with light floral hop character. Clean and expressive.',
		'appearance' => 'Deep amber with ruby highlights and attractive foam quality.',
		'flavor' => 'Malt complexity carries through with balanced bitterness. Finish invites another sip.',
		'mouthfeel' => 'Medium body with lively carbonation and a smooth finish.',
		'overall' => 'Enjoyable entry with clean fermentation and good drinkability.',
	),
);

$ts = $now - 3600;
$inserted = 0;

foreach ($flight1 as $si => $eid) {
	$entry = $db->query("SELECT id, brewBrewerID, brewCategorySort, brewSubCategory, brewName, brewJudgingNumber
		FROM {$p}brewing WHERE id={$eid}")->fetch_assoc();
	if (!$entry) continue;

	$style_id = resolve_style_id($db, $p, $entry['brewCategorySort'], $entry['brewSubCategory']);
	$style_sql = $style_id > 0 ? (string)$style_id : 'NULL';
	$c = $comment_sets[$si % count($comment_sets)];

	// Partner scores vary a bit per beer; no consensus score yet (set on reconcile)
	$base = 30 + ($si % 6);
	$a = max(4, min(12, $base + (($si % 3) - 1)));
	$ap = max(1, min(3, (int)round($base / 12) + 1));
	$f = max(8, min(20, $base + (($si % 2))));
	$m = max(2, min(5, (int)round($base / 9) + 1));
	$o = max(4, min(10, (int)round($base / 4)));
	$dur = 480 + ($si * 90);
	$token = $db->real_escape_string(substr(md5('partner-' . $partner_uid . '-' . $eid . '-' . $ts), 0, 16));
	$ts += 180;

	$db->query("INSERT INTO {$p}evaluation (
		eid, uid, evalToken, evalJudgeInfo, evalScoresheet, evalStyle,
		evalAromaScore, evalAromaComments, evalAppearanceScore, evalAppearanceComments,
		evalFlavorScore, evalFlavorComments, evalMouthfeelScore, evalMouthfeelComments,
		evalOverallScore, evalOverallComments, evalStyleAccuracy, evalTechMerit, evalIntangibles,
		evalInitialDate, evalUpdatedDate, evalDurationSec, evalTable, evalFinalScore, evalDraft, evalMiniBOS, evalPosition
	) VALUES (
		{$eid}, " . (int)$entry['brewBrewerID'] . ", '{$token}', {$partner_uid}, {$scoresheet}, {$style_sql},
		{$a}, '" . $db->real_escape_string($c['aroma']) . "',
		{$ap}, '" . $db->real_escape_string($c['appearance']) . "',
		{$f}, '" . $db->real_escape_string($c['flavor']) . "',
		{$m}, '" . $db->real_escape_string($c['mouthfeel']) . "',
		{$o}, '" . $db->real_escape_string($c['overall']) . "',
		3, 3, 3, {$ts}, {$ts}, {$dur}, {$tid}, NULL, 0, 0, '" . ($si + 1) . ",5'
	)");
	$inserted++;

	echo "  Partner scored #{$entry['brewJudgingNumber']} {$entry['brewName']} (total components ~" . ($a+$ap+$f+$m+$o) . ")\n";
}

echo "\nDone. Inserted {$inserted} partner scoresheets; baseline has none.\n";
echo "Judging window open for ~36 hours; session ends in ~6 hours.\n\n";
echo "Login: user.baseline@brewingcompetitions.com / bcoem\n";
echo "Dashboard: http://localhost/mbc/index.php?section=evaluation\n";
echo "Table: British & Irish — Flight 1 (5 beers)\n";
echo "Partner already submitted: {$partner_email}\n";
echo "Log out/in if the session still shows judging closed.\n";
