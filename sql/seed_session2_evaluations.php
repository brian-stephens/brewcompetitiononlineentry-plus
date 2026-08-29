<?php
/**
 * Add sample scoresheets for Session 2 judges so the dashboard stats panel has data.
 * Leaves 1–2 unscored flight-1 entries per judge for Add Score testing.
 *
 *   C:\xampp\php\php.exe sql\seed_session2_evaluations.php
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
$now = time();

$sets = array(
	array(
		'aroma' => 'Light malt and roast aroma.',
		'appearance' => 'Dark brown with tan foam.',
		'flavor' => 'Roasty with moderate bitterness.',
		'mouthfeel' => 'Medium body, smooth.',
		'overall' => 'Solid example of the style.',
	),
	array(
		'aroma' => 'Pleasant roast and chocolate notes with a clean fermentation character and faint coffee accent.',
		'appearance' => 'Deep brown to black with persistent tan head and good clarity for the style.',
		'flavor' => 'Chocolate and roast malt flavors lead with balanced bitterness and a dry finish that stays clean.',
		'mouthfeel' => 'Medium body with moderate carbonation and a slightly creamy texture throughout.',
		'overall' => 'A well made entry that fits the style well. Minor tweaks to roast intensity could elevate it.',
	),
	array(
		'aroma' => 'Complex roast bouquet featuring dark chocolate, espresso, and light caramel over a clean malt foundation. No diacetyl or DMS detected. Aroma intensity is appropriate and remains expressive as the beer warms.',
		'appearance' => 'Near black with ruby highlights when held to light. Dense tan head retains well and leaves attractive lacing on the glass.',
		'flavor' => 'Roast and chocolate flavors echo the aroma with supportive bitterness and a dry finish. Fermentation is neutral with no off flavors. Finish highlights roast character without harsh astringency.',
		'mouthfeel' => 'Medium to full body with lively carbonation. Smooth palate with no astringency. Alcohol warmth is restrained for the strength. Mouthfeel supports drinkability very well.',
		'overall' => 'An enjoyable and stylistically accurate entry. Strengths include aroma intensity and clean fermentation. Competitive in most flights and a pleasure to judge today.',
	),
);

$q = $db->query("SELECT t.id FROM {$p}judging_tables t
	JOIN {$p}judging_locations l ON l.id=t.tableLocation
	WHERE l.judgingLocName LIKE 'Session 2%'");
$table_ids = array();
while ($r = $q->fetch_assoc()) $table_ids[] = (int)$r['id'];

if (empty($table_ids)) {
	echo "No Session 2 tables found.\n";
	exit(1);
}

$in = implode(',', $table_ids);
$db->query("DELETE FROM {$p}evaluation WHERE evalTable IN ({$in})");

function resolve_style_id($db, $p, $sort, $sub) {
	$sort = (string)$sort;
	$sub = (string)$sub;
	$candidates = array_unique(array(
		$sort,
		ltrim($sort, '0'),
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

$eval_count = 0;
$ts = $now - 7200;
$durs = array(420, 540, 660, 780, 900, 1020, 480, 720);

foreach ($table_ids as $tid) {
	$jq = $db->query("SELECT bid FROM {$p}judging_assignments WHERE assignTable={$tid} AND assignment='J' AND bid <> 1");
	$judge_uids = array();
	while ($jr = $jq->fetch_assoc()) $judge_uids[] = (int)$jr['bid'];

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

	foreach ($judge_uids as $ji => $judge_uid) {
		$to_score = array_slice($entries, 0, max(2, count($entries) - 2));
		if ($ji === 0) $to_score = array_slice($entries, 0, max(3, count($entries) - 1));

		foreach ($to_score as $si => $e) {
			$c = $sets[($ji + $si) % 3];
			$base = 30 + (($ji + $si) % 8);
			$a = max(4, min(12, $base + rand(-1, 1)));
			$ap = max(1, min(3, (int)round($base / 10) + rand(0, 1)));
			$f = max(8, min(20, $base + rand(-2, 2)));
			$m = max(2, min(5, (int)round($base / 8) + rand(0, 1)));
			$o = max(4, min(10, (int)round($base / 4) + rand(-1, 1)));
			$total = $a + $ap + $f + $m + $o;
			$dur = $durs[($ji + $si) % count($durs)];
			$token = $db->real_escape_string(substr(md5($judge_uid . '-' . $e['eid'] . '-' . $ts), 0, 16));
			$ts += 120;
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
			$eval_count++;
		}
	}
}

echo "Inserted {$eval_count} Session 2 evaluations.\n";
$r = $db->query("SELECT u.user_name, COUNT(*) AS c
	FROM {$p}evaluation e
	JOIN {$p}users u ON u.id=e.evalJudgeInfo
	WHERE e.evalTable IN ({$in})
	GROUP BY e.evalJudgeInfo
	ORDER BY c DESC");
while ($row = $r->fetch_assoc()) {
	echo "  {$row['user_name']}: {$row['c']} sheets\n";
}
echo "\nRefresh the judging dashboard (log out/in if stats still missing).\n";
echo "Best login: tom.judge@example.com / test123\n";
