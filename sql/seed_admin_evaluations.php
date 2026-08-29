<?php
/**
 * Give the baseline admin account Session 2 scoresheets so the judging
 * dashboard stats panel appears for that login.
 *
 *   C:\xampp\php\php.exe sql\seed_admin_evaluations.php
 *
 * Optional env overrides: DB_HOST DB_USER DB_PASSWORD DB_NAME DB_PREFIX
 */

$hostname = getenv('DB_HOST') !== false ? getenv('DB_HOST') : 'localhost';
$username = getenv('DB_USER') !== false ? getenv('DB_USER') : 'root';
$password = getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : '';
$database = getenv('DB_NAME') !== false ? getenv('DB_NAME') : 'mbc_local';
$p = getenv('DB_PREFIX') !== false ? getenv('DB_PREFIX') : 'baseline_';

$db = new mysqli($hostname, $username, $password, $database);
$db->set_charset('utf8mb4');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$judge_uid = 1;
$now = time();
$ts = $now - 3600;

$sets = array(
	array(
		'aroma' => 'Pleasant malt aroma with light hop notes.',
		'appearance' => 'Clear amber with persistent foam.',
		'flavor' => 'Balanced malt and hop with a clean finish.',
		'mouthfeel' => 'Medium body and carbonation.',
		'overall' => 'A solid, enjoyable example of the style.',
	),
	array(
		'aroma' => 'Complex malt bouquet with caramel and biscuit notes plus a hint of floral hop character that opens nicely as the beer warms.',
		'appearance' => 'Bright copper color with excellent clarity and a dense off-white head that leaves nice lacing.',
		'flavor' => 'Caramel malt leads with moderate bitterness and a dry finish. Fermentation is clean with no off flavors detected.',
		'mouthfeel' => 'Medium body with lively carbonation and a smooth palate that supports drinkability.',
		'overall' => 'Well made and stylistically accurate. Competitive in this flight with only minor suggestions for refinement.',
	),
	array(
		'aroma' => 'Rich malt aroma featuring toffee, toast, and light dark fruit. Hop character is restrained and appropriate. No diacetyl or DMS noted.',
		'appearance' => 'Deep amber to light brown with ruby highlights. Head retention is good and foam quality is attractive.',
		'flavor' => 'Malt complexity carries through the palate with supporting bitterness. Finish is dry and clean, inviting another sip. Strength is well integrated.',
		'mouthfeel' => 'Medium to medium-full body with appropriate carbonation. Smooth with no astringency. Alcohol warmth is subtle.',
		'overall' => 'An enjoyable and well crafted entry. Strengths include malt expression and clean fermentation. A pleasure to judge.',
	),
);
$durs = array(510, 660, 780, 900, 720);

// Ensure admin is a judge with Session 2 willingness
$db->query("UPDATE {$p}brewer SET
	brewerJudge='Y',
	brewerJudgeLocation='Y-5,Y-6,Y-7',
	brewerJudgeWaiver='Y'
	WHERE uid={$judge_uid}");

$chk = $db->query("SELECT id FROM {$p}staff WHERE uid={$judge_uid}");
if ($chk->num_rows == 0) {
	$db->query("INSERT INTO {$p}staff (uid, staff_judge, staff_judge_bos, staff_steward, staff_organizer, staff_staff)
		VALUES ({$judge_uid}, 1, 0, 0, 1, 0)");
} else {
	$db->query("UPDATE {$p}staff SET staff_judge=1 WHERE uid={$judge_uid}");
}

// Prefer existing Session 2 assignment; otherwise assign to British & Irish
$assign = $db->query("SELECT a.assignTable
	FROM {$p}judging_assignments a
	JOIN {$p}judging_tables t ON t.id=a.assignTable
	JOIN {$p}judging_locations l ON l.id=t.tableLocation
	WHERE a.bid={$judge_uid} AND a.assignment='J' AND l.judgingLocName LIKE 'Session 2%'
	LIMIT 1");

if ($row = $assign->fetch_assoc()) {
	$tid = (int)$row['assignTable'];
} else {
	$t = $db->query("SELECT t.id, t.tableLocation
		FROM {$p}judging_tables t
		JOIN {$p}judging_locations l ON l.id=t.tableLocation
		WHERE l.judgingLocName LIKE 'Session 2%'
		ORDER BY t.tableNumber
		LIMIT 1")->fetch_assoc();
	$tid = (int)$t['id'];
	$loc = (int)$t['tableLocation'];
	$db->query("INSERT INTO {$p}judging_assignments
		(bid, assignment, assignTable, assignFlight, assignRound, assignLocation, assignRoles, assignPlanning)
		VALUES ({$judge_uid}, 'J', {$tid}, 1, 1, {$loc}, 'HJ', 0)");
}

$db->query("UPDATE {$p}judging_assignments
	SET assignRoles='HJ', assignPlanning=0, assignFlight=1
	WHERE bid={$judge_uid} AND assignTable={$tid}");

$db->query("UPDATE {$p}judging_preferences SET jPrefsJudgeStats='Y' WHERE id=1");
$db->query("DELETE FROM {$p}evaluation WHERE evalJudgeInfo={$judge_uid} AND evalTable={$tid}");

function resolve_style_id($db, $p, $sort, $sub) {
	$candidates = array_unique(array(
		(string)$sort,
		ltrim((string)$sort, '0'),
		str_pad((string)((int)$sort), 2, '0', STR_PAD_LEFT),
	));
	foreach ($candidates as $g) {
		if ($g === '' || $g === false) continue;
		$g_esc = $db->real_escape_string($g);
		$sub_esc = $db->real_escape_string($sub);
		$res = $db->query("SELECT id FROM {$p}styles WHERE brewStyleGroup='{$g_esc}' AND brewStyleNum='{$sub_esc}' AND brewStyleActive='Y' LIMIT 1");
		if ($row = $res->fetch_assoc()) return (int)$row['id'];
	}
	return 0;
}

$eq = $db->query("SELECT f.flightEntryID AS eid, b.brewBrewerID AS owner, b.brewCategorySort, b.brewSubCategory
	FROM {$p}judging_flights f
	JOIN {$p}brewing b ON b.id=f.flightEntryID
	WHERE f.flightTable={$tid} AND f.flightNumber=1
	ORDER BY f.id");

$entries = array();
while ($er = $eq->fetch_assoc()) {
	$entries[] = array(
		'eid' => (int)$er['eid'],
		'owner' => (int)$er['owner'],
		'style_id' => resolve_style_id($db, $p, $er['brewCategorySort'], $er['brewSubCategory']),
	);
}

$to_score = array_slice($entries, 0, max(3, count($entries) - 1));
$n = 0;

foreach ($to_score as $si => $e) {
	$c = $sets[$si % 3];
	$base = 32 + ($si % 6);
	$a = max(4, min(12, $base + rand(-1, 1)));
	$ap = max(1, min(3, (int)round($base / 10) + rand(0, 1)));
	$f = max(8, min(20, $base + rand(-2, 2)));
	$m = max(2, min(5, (int)round($base / 8) + rand(0, 1)));
	$o = max(4, min(10, (int)round($base / 4) + rand(-1, 1)));
	$total = $a + $ap + $f + $m + $o;
	$dur = $durs[$si % count($durs)];
	$token = $db->real_escape_string(substr(md5('admin-' . $e['eid'] . '-' . $ts), 0, 16));
	$ts += 150;
	$style_sql = $e['style_id'] > 0 ? (string)$e['style_id'] : 'NULL';

	$db->query("INSERT INTO {$p}evaluation (
		eid, uid, evalToken, evalJudgeInfo, evalScoresheet, evalStyle,
		evalAromaScore, evalAromaComments, evalAppearanceScore, evalAppearanceComments,
		evalFlavorScore, evalFlavorComments, evalMouthfeelScore, evalMouthfeelComments,
		evalOverallScore, evalOverallComments, evalStyleAccuracy, evalTechMerit, evalIntangibles,
		evalInitialDate, evalUpdatedDate, evalDurationSec, evalTable, evalFinalScore, evalMiniBOS
	) VALUES (
		{$e['eid']}, {$e['owner']}, '{$token}', {$judge_uid}, 3, {$style_sql},
		{$a}, '".$db->real_escape_string($c['aroma'])."',
		{$ap}, '".$db->real_escape_string($c['appearance'])."',
		{$f}, '".$db->real_escape_string($c['flavor'])."',
		{$m}, '".$db->real_escape_string($c['mouthfeel'])."',
		{$o}, '".$db->real_escape_string($c['overall'])."',
		3, 3, 3, {$ts}, {$ts}, {$dur}, {$tid}, {$total}, 0
	)");
	$n++;
}

$table = $db->query("SELECT tableName FROM {$p}judging_tables WHERE id={$tid}")->fetch_assoc();
echo "Admin ready on {$table['tableName']} with {$n} scoresheets.\n";
echo "Refresh the judging dashboard (log out/in if stats still missing).\n";
echo "Login: user.baseline@brewingcompetitions.com / bcoem\n";
