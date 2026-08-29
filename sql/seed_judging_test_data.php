<?php
/**
 * Local day-of-judging seed data.
 *
 * Sets the competition to the morning judging begins: sessions today,
 * tables built, flights assigned, judges/stewards seated, all entries
 * checked in — and no scoresheets submitted yet.
 *
 * Usage:
 *   C:\xampp\php\php.exe sql\seed_judging_test_data.php
 *
 * Optional env overrides (Docker / demo):
 *   DB_HOST DB_USER DB_PASSWORD DB_NAME DB_PREFIX
 *
 * Safe to re-run (keeps baseline admin id=1).
 */

require_once dirname(__DIR__) . '/classes/phpass/PasswordHash.php';

$hostname = getenv('DB_HOST') !== false ? getenv('DB_HOST') : 'localhost';
$username = getenv('DB_USER') !== false ? getenv('DB_USER') : 'root';
$password = getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : '';
$database = getenv('DB_NAME') !== false ? getenv('DB_NAME') : 'bcoem_local';
$prefix = getenv('DB_PREFIX') !== false ? getenv('DB_PREFIX') : 'baseline_';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = new mysqli($hostname, $username, $password, $database);
$db->set_charset('utf8mb4');
$p = $prefix;

$hasher = new PasswordHash(8, false);
$password_hash = $hasher->HashPassword(md5('test123'));
$answer_hash = $hasher->HashPassword('pabst');
$question = 'What is your favorite all-time beer to drink?';

// Anchor calendar dates to "today" in UTC.
// Session clocks are relative to NOW so scoring stays open whenever you seed
// (dashboard disables Add Score after judgingDateEnd).
$tz = new DateTimeZone('UTC');
$today = new DateTime('now', $tz);
$today->setTime(0, 0, 0);
$now = time();

// Active "morning" session already underway; afternoon starts soon; side room parallel
$session_am_start = $now - (30 * 60);          // began 30 minutes ago
$session_am_end   = $now + (3 * 3600);         // ends in 3 hours
$session_pm_start = $now + (3 * 3600) + 1800;  // starts in 3.5 hours
$session_pm_end   = $now + (7 * 3600);         // ends in 7 hours
$session2_start   = $now - (15 * 60);          // side room began 15 min ago
$session2_end     = $now + (6 * 3600);         // ends in 6 hours

$reg_open   = (clone $today)->modify('-60 days')->setTime(8, 0)->getTimestamp();
$reg_close  = (clone $today)->modify('-14 days')->setTime(23, 59)->getTimestamp();
$entry_open = (clone $today)->modify('-60 days')->setTime(8, 0)->getTimestamp();
$entry_close= (clone $today)->modify('-7 days')->setTime(23, 59)->getTimestamp();
$drop_open  = (clone $today)->modify('-30 days')->setTime(8, 0)->getTimestamp();
$drop_close = (clone $today)->modify('-7 days')->setTime(18, 0)->getTimestamp();
$judge_reg_open  = (clone $today)->modify('-45 days')->setTime(8, 0)->getTimestamp();
$judge_reg_close = (clone $today)->modify('-3 days')->setTime(23, 59)->getTimestamp();
$awards_time = (clone $today)->modify('+1 day')->setTime(18, 0)->getTimestamp();

// Judging window open now through tomorrow evening
$jprefs_open  = $now - 3600;
$jprefs_close = $now + (36 * 3600);

function esc($db, $v) {
	if ($v === null) return 'NULL';
	return "'" . mysqli_real_escape_string($db, (string)$v) . "'";
}

function cat_sort($group) {
	$g = (int)$group;
	return ($g < 10) ? sprintf('%02d', $g) : (string)$g;
}

echo "Seeding day-of-judging data into {$database}...\n";
echo "Judging day: " . $today->format('Y-m-d') . " (UTC)\n";

// Wipe prior seed data (keep baseline admin)
$db->query("DELETE FROM {$p}evaluation");
$db->query("DELETE FROM {$p}judging_scores");
$db->query("DELETE FROM {$p}judging_flights");
$db->query("DELETE FROM {$p}judging_assignments");
$db->query("DELETE FROM {$p}judging_tables");
$db->query("DELETE FROM {$p}judging_locations");
$db->query("DELETE FROM {$p}brewing");
$db->query("DELETE FROM {$p}staff WHERE uid <> 1");
$db->query("DELETE FROM {$p}brewer WHERE uid <> 1");
$db->query("DELETE FROM {$p}users WHERE id <> 1");

// --- Competition calendar: entry closed, judging begins today ---
$db->query("UPDATE {$p}contest_info SET
	contestName='Local Test Competition',
	contestHost='Homebrew Club',
	contestHostLocation='Hometown, USA',
	contestRegistrationOpen=" . esc($db, (string)$reg_open) . ",
	contestRegistrationDeadline=" . esc($db, (string)$reg_close) . ",
	contestEntryOpen=" . esc($db, (string)$entry_open) . ",
	contestEntryDeadline=" . esc($db, (string)$entry_close) . ",
	contestEntryEditDeadline=" . esc($db, (string)$entry_close) . ",
	contestJudgeOpen=" . esc($db, (string)$judge_reg_open) . ",
	contestJudgeDeadline=" . esc($db, (string)$judge_reg_close) . ",
	contestDropoffOpen=" . esc($db, (string)$drop_open) . ",
	contestDropoffDeadline=" . esc($db, (string)$drop_close) . ",
	contestShippingOpen=" . esc($db, (string)$drop_open) . ",
	contestShippingDeadline=" . esc($db, (string)$drop_close) . ",
	contestAwardsLocName='Main Hall Awards',
	contestAwardsLocation='123 Brewery Rd',
	contestAwardsLocDate=" . esc($db, (string)$awards_time) . ",
	contestAwardsLocTime=" . esc($db, (string)$awards_time) . "
	WHERE id=1");

$db->query("UPDATE {$p}preferences SET
	prefsEval='1',
	prefsStyleSet='BJCP2021',
	prefsDisplaySpecial='J',
	prefsTimeZone='0'
	WHERE id=1");

// Non-queued flights, planning closed, stats on, window open today
$db->query("UPDATE {$p}judging_preferences SET
	jPrefsQueued='N',
	jPrefsFlightEntries=12,
	jPrefsRounds=2,
	jPrefsBottleNum=2,
	jPrefsTablePlanning=0,
	jPrefsJudgeStats='Y',
	jPrefsScoresheet=3,
	jPrefsMinWords=10,
	jPrefsScoreDispMax=7,
	jPrefsJudgingOpen=" . esc($db, (string)$jprefs_open) . ",
	jPrefsJudgingClosed=" . esc($db, (string)$jprefs_close) . "
	WHERE id=1");

// --- Judging sessions (locations) ---
$sessions = array(
	array('name' => 'Session 1 - Main Hall (AM)', 'loc' => '123 Brewery Rd - Main Hall', 'start' => $session_am_start, 'end' => $session_am_end, 'rounds' => 2, 'notes' => 'Active now. Check-in complete; judging underway.'),
	array('name' => 'Session 2 - Main Hall (PM)', 'loc' => '123 Brewery Rd - Main Hall', 'start' => $session_pm_start, 'end' => $session_pm_end, 'rounds' => 2, 'notes' => 'Afternoon session. BOS follows if time allows.'),
	array('name' => 'Session 1 - Side Room', 'loc' => '123 Brewery Rd - Side Room', 'start' => $session2_start, 'end' => $session2_end, 'rounds' => 2, 'notes' => 'Overflow / specialty tables. Active in parallel with AM.'),
);

$session_ids = array();
foreach ($sessions as $s) {
	$db->query("INSERT INTO {$p}judging_locations
		(judgingLocType, judgingDate, judgingDateEnd, judgingLocName, judgingLocation, judgingRounds, judgingLocNotes)
		VALUES (0, " . esc($db, (string)$s['start']) . ", " . esc($db, (string)$s['end']) . ",
			" . esc($db, $s['name']) . ", " . esc($db, $s['loc']) . ", {$s['rounds']}, " . esc($db, $s['notes']) . ")");
	$session_ids[] = (int)$db->insert_id;
}
$sess_am = $session_ids[0];
$sess_pm = $session_ids[1];
$sess_side = $session_ids[2];

// --- People ---
$people = array(
	// Judges
	array('email' => 'sarah.judge@example.com', 'first' => 'Sarah', 'last' => 'Miller', 'role' => 'judge', 'rank' => 'Certified', 'city' => 'Hometown'),
	array('email' => 'tom.judge@example.com', 'first' => 'Tom', 'last' => 'Nguyen', 'role' => 'judge', 'rank' => 'National', 'city' => 'Grand Rapids'),
	array('email' => 'maria.judge@example.com', 'first' => 'Maria', 'last' => 'Lopez', 'role' => 'judge', 'rank' => 'Recognized', 'city' => 'Springfield'),
	array('email' => 'james.judge@example.com', 'first' => 'James', 'last' => 'Carter', 'role' => 'judge', 'rank' => 'Non-BJCP', 'city' => 'Lansing'),
	array('email' => 'priya.judge@example.com', 'first' => 'Priya', 'last' => 'Shah', 'role' => 'judge', 'rank' => 'Certified', 'city' => 'Kalamazoo'),
	array('email' => 'owen.judge@example.com', 'first' => 'Owen', 'last' => 'Blake', 'role' => 'judge', 'rank' => 'Apprentice', 'city' => 'Royal Oak'),
	array('email' => 'nina.judge@example.com', 'first' => 'Nina', 'last' => 'Park', 'role' => 'judge', 'rank' => 'Certified', 'city' => 'Ferndale'),
	array('email' => 'leo.judge@example.com', 'first' => 'Leo', 'last' => 'Grant', 'role' => 'judge', 'rank' => 'National', 'city' => 'Hometown'),
	array('email' => 'hannah.judge@example.com', 'first' => 'Hannah', 'last' => 'Cole', 'role' => 'judge', 'rank' => 'Recognized', 'city' => 'Ypsilanti'),
	array('email' => 'derek.judge@example.com', 'first' => 'Derek', 'last' => 'Moss', 'role' => 'judge', 'rank' => 'Certified', 'city' => 'Canton'),
	array('email' => 'iris.judge@example.com', 'first' => 'Iris', 'last' => 'Vance', 'role' => 'judge', 'rank' => 'Non-BJCP', 'city' => 'Plymouth'),
	array('email' => 'felix.judge@example.com', 'first' => 'Felix', 'last' => 'Ortega', 'role' => 'judge', 'rank' => 'Apprentice', 'city' => 'Troy'),
	// Stewards
	array('email' => 'alex.steward@example.com', 'first' => 'Alex', 'last' => 'Brooks', 'role' => 'steward', 'rank' => '', 'city' => 'Hometown'),
	array('email' => 'jamie.steward@example.com', 'first' => 'Jamie', 'last' => 'Patel', 'role' => 'steward', 'rank' => '', 'city' => 'Ypsilanti'),
	array('email' => 'chris.steward@example.com', 'first' => 'Chris', 'last' => 'Evans', 'role' => 'steward', 'rank' => '', 'city' => 'Dearborn'),
	array('email' => 'sam.steward@example.com', 'first' => 'Sam', 'last' => 'Rivera', 'role' => 'steward', 'rank' => '', 'city' => 'Livonia'),
	array('email' => 'kelly.steward@example.com', 'first' => 'Kelly', 'last' => 'Nguyen', 'role' => 'steward', 'rank' => '', 'city' => 'Novi'),
	array('email' => 'pat.steward@example.com', 'first' => 'Pat', 'last' => 'Hughes', 'role' => 'steward', 'rank' => '', 'city' => 'Warren'),
	// Entrants
	array('email' => 'casey.brewer@example.com', 'first' => 'Casey', 'last' => 'Reed', 'role' => 'entrant', 'rank' => '', 'city' => 'Royal Oak'),
	array('email' => 'jordan.brewer@example.com', 'first' => 'Jordan', 'last' => 'Hayes', 'role' => 'entrant', 'rank' => '', 'city' => 'Ferndale'),
	array('email' => 'taylor.brewer@example.com', 'first' => 'Taylor', 'last' => 'Kim', 'role' => 'entrant', 'rank' => '', 'city' => 'Troy'),
	array('email' => 'morgan.brewer@example.com', 'first' => 'Morgan', 'last' => 'Diaz', 'role' => 'entrant', 'rank' => '', 'city' => 'Livonia'),
	array('email' => 'riley.brewer@example.com', 'first' => 'Riley', 'last' => 'Chen', 'role' => 'entrant', 'rank' => '', 'city' => 'Canton'),
	array('email' => 'avery.brewer@example.com', 'first' => 'Avery', 'last' => 'Sullivan', 'role' => 'entrant', 'rank' => '', 'city' => 'Novi'),
	array('email' => 'quinn.brewer@example.com', 'first' => 'Quinn', 'last' => 'Foster', 'role' => 'entrant', 'rank' => '', 'city' => 'Plymouth'),
	array('email' => 'blake.brewer@example.com', 'first' => 'Blake', 'last' => 'Ortiz', 'role' => 'entrant', 'rank' => '', 'city' => 'Warren'),
	array('email' => 'cameron.brewer@example.com', 'first' => 'Cameron', 'last' => 'West', 'role' => 'entrant', 'rank' => '', 'city' => 'Hometown'),
	array('email' => 'drew.brewer@example.com', 'first' => 'Drew', 'last' => 'Banerjee', 'role' => 'entrant', 'rank' => '', 'city' => 'Hometown'),
	array('email' => 'emery.brewer@example.com', 'first' => 'Emery', 'last' => 'Glass', 'role' => 'entrant', 'rank' => '', 'city' => 'Saline'),
	array('email' => 'finley.brewer@example.com', 'first' => 'Finley', 'last' => 'Brooks', 'role' => 'entrant', 'rank' => '', 'city' => 'Chelsea'),
	// Dual: judge who also entered (not assigned against own styles ideally — still useful)
	array('email' => 'dana.dual@example.com', 'first' => 'Dana', 'last' => 'Walsh', 'role' => 'judge_entrant', 'rank' => 'Apprentice', 'city' => 'Hometown'),
);

$uids = array();
$judges = array();
$stewards = array();
$entrants = array();
$people_by_email = array();

foreach ($people as $person) {
	$people_by_email[$person['email']] = $person;

	$db->query("INSERT INTO {$p}users
		(user_name, password, userLevel, userQuestion, userQuestionAnswer, userCreated, userFailedLogins, userAdminObfuscate)
		VALUES (
			" . esc($db, $person['email']) . ",
			" . esc($db, $password_hash) . ",
			'2',
			" . esc($db, $question) . ",
			" . esc($db, $answer_hash) . ",
			NOW(), 0, 1
		)");
	$uid = (int)$db->insert_id;
	$uids[$person['email']] = $uid;

	$is_judge = in_array($person['role'], array('judge', 'judge_entrant'), true);
	$is_steward = ($person['role'] === 'steward');
	$is_entrant = in_array($person['role'], array('entrant', 'judge_entrant'), true);

	// Willing for all three sessions
	$judge_loc = $is_judge ? "Y-{$sess_am},Y-{$sess_pm},Y-{$sess_side}" : "N-{$sess_am},N-{$sess_pm},N-{$sess_side}";
	$steward_loc = $is_steward ? "Y-{$sess_am},Y-{$sess_pm},Y-{$sess_side}" : "N-{$sess_am},N-{$sess_pm},N-{$sess_side}";

	$db->query("INSERT INTO {$p}brewer (
		uid, brewerFirstName, brewerLastName, brewerAddress, brewerCity, brewerState, brewerZip, brewerCountry,
		brewerPhone1, brewerClubs, brewerEmail, brewerStaff, brewerSteward, brewerJudge, brewerJudgeID,
		brewerJudgeMead, brewerJudgeCider, brewerJudgeRank, brewerJudgeLocation, brewerStewardLocation,
		brewerJudgeWaiver, brewerDiscount, brewerProAm, brewerDropOff
	) VALUES (
		{$uid},
		" . esc($db, $person['first']) . ",
		" . esc($db, $person['last']) . ",
		'100 Main St',
		" . esc($db, $person['city']) . ",
		'ST', '00000', 'United States',
		'555-555-0100', 'Homebrew Club',
		" . esc($db, $person['email']) . ",
		'N',
		" . esc($db, $is_steward ? 'Y' : 'N') . ",
		" . esc($db, $is_judge ? 'Y' : 'N') . ",
		" . esc($db, $is_judge ? ('A' . str_pad((string)$uid, 6, '0', STR_PAD_LEFT)) : '') . ",
		'N', 'N',
		" . esc($db, $person['rank']) . ",
		" . esc($db, $judge_loc) . ",
		" . esc($db, $steward_loc) . ",
		'Y', 'N', 0, 1
	)");

	if ($is_judge || $is_steward) {
		$db->query("INSERT INTO {$p}staff (uid, staff_judge, staff_judge_bos, staff_steward, staff_organizer, staff_staff)
			VALUES ({$uid}, " . ($is_judge ? 1 : 0) . ", 0, " . ($is_steward ? 1 : 0) . ", 0, 0)");
	}

	if ($is_judge) $judges[] = $uid;
	if ($is_steward) $stewards[] = $uid;
	if ($is_entrant) $entrants[] = $uid;
}

// --- Tables: assigned to sessions, styles locked, planning closed ---
// style ids from BJCP2021 baseline_styles
$tables_def = array(
	// AM Main Hall
	array('name' => 'Pale Lager & Pils', 'number' => 1, 'styles' => '453,454,457,470', 'session' => $sess_am, 'flight_size' => 6),
	array('name' => 'Pale Ale', 'number' => 2, 'styles' => '508,509', 'session' => $sess_am, 'flight_size' => 6),
	array('name' => 'IPA', 'number' => 3, 'styles' => '516,517,525', 'session' => $sess_am, 'flight_size' => 6),
	array('name' => 'Amber & Brown', 'number' => 4, 'styles' => '510,511,512', 'session' => $sess_am, 'flight_size' => 5),
	// PM Main Hall
	array('name' => 'Porter & Stout', 'number' => 5, 'styles' => '513,514,515', 'session' => $sess_pm, 'flight_size' => 6),
	array('name' => 'British & Irish', 'number' => 6, 'styles' => '484,485,486,496,497', 'session' => $sess_pm, 'flight_size' => 5),
	array('name' => 'Strong Ale', 'number' => 7, 'styles' => '526,527,528', 'session' => $sess_pm, 'flight_size' => 4),
	// Side room
	array('name' => 'Belgian & Wit', 'number' => 8, 'styles' => '537,538,540', 'session' => $sess_side, 'flight_size' => 5),
	array('name' => 'Wheat & Sour', 'number' => 9, 'styles' => '481,530,536', 'session' => $sess_side, 'flight_size' => 5),
);

$table_ids = array();
$table_meta = array();
foreach ($tables_def as $ti => $t) {
	$db->query("INSERT INTO {$p}judging_tables (tableName, tableStyles, tableNumber, tableLocation)
		VALUES (" . esc($db, $t['name']) . ", " . esc($db, $t['styles']) . ", {$t['number']}, {$t['session']})");
	$tid = (int)$db->insert_id;
	$table_ids[] = $tid;
	$table_meta[$tid] = $t;
	$table_meta[$tid]['id'] = $tid;
}

// Style lookup for entry creation
$style_rows = array();
$res = $db->query("SELECT id, brewStyleGroup, brewStyleNum, brewStyle FROM {$p}styles WHERE brewStyleActive='Y'");
while ($row = $res->fetch_assoc()) {
	$style_rows[(int)$row['id']] = $row;
}

// Build entry list from table styles (enough for flights)
$beer_names = array(
	'Hop Bomb', 'Cascade Crusher', 'Citra Storm', 'Pale Rider', 'Summit Pale', 'Hazy Morning',
	'Juice Box', 'Rye Not', 'Midnight Porter', 'Coffee Track', 'Black River', 'Roast House',
	'Imperial Night', 'Amber Waves', 'Copper Line', 'Steam City', 'Nutty Professor', 'Brown Bag',
	'Trail Marker', 'Fog Machine', 'Oatmeal Alley', 'Maple Amber', 'Session Summit', 'Smoked Dock',
	'Pils Path', 'Lager Line', 'Kolsch Corner', 'Weiss Walk', 'Gose Garden', 'Wit Wonder',
	'Bitter Truth', 'Irish Mile', 'Double Down', 'Barley Bound', 'Blond Ambition', 'Saison Street',
	'Munich Morning', 'Vienna Valley', 'Dunkel Door', 'Stout Street', 'Porter Path', 'IPA Island',
	'Helles House', 'Export Express', 'Common Ground', 'Brownstone', 'Red River', 'Golden Gate',
);

$entries_by_table = array(); // table_id => [eid,...]
$all_entries = array();
$judging_num = 101;
$name_i = 0;
$entrant_count = count($entrants);

foreach ($table_meta as $tid => $t) {
	$style_ids = array_map('intval', explode(',', $t['styles']));
	// Aim for ~flight_size * 1.5 entries so 2 flights are useful; keep day-of manageable
	$target = max(8, (int)$t['flight_size'] + 4);
	$entries_by_table[$tid] = array();

	for ($i = 0; $i < $target; $i++) {
		$style_id = $style_ids[$i % count($style_ids)];
		if (!isset($style_rows[$style_id])) continue;
		$st = $style_rows[$style_id];
		$group = $st['brewStyleGroup'];
		$num = $st['brewStyleNum'];
		// Specialty IPA substyles like B1 — store as B for matching used by score_style_data often uses brewStyleNum as-is
		$sub = $num;
		$cat = (string)((int)$group);
		$sort = cat_sort($group);
		$owner = $entrants[$name_i % $entrant_count];
		$owner_email = array_search($owner, $uids, true);
		$owner_person = $people_by_email[$owner_email];
		$bname = $beer_names[$name_i % count($beer_names)] . ' ' . substr($st['brewStyle'], 0, 18);
		$jnum = str_pad((string)$judging_num, 6, '0', STR_PAD_LEFT);
		$abv = round(4.5 + (($i * 0.3) % 5), 1);
		$judging_num++;
		$name_i++;

		$db->query("INSERT INTO {$p}brewing (
			brewName, brewStyle, brewCategory, brewCategorySort, brewSubCategory, brewInfo,
			brewBrewerID, brewBrewerFirstName, brewBrewerLastName, brewPaid, brewABV,
			brewReceived, brewConfirmed, brewJudgingNumber, brewUpdated, brewStyleType, brewPackaging, brewBoxNum
		) VALUES (
			" . esc($db, $bname) . ",
			" . esc($db, $st['brewStyle']) . ",
			" . esc($db, $cat) . ",
			" . esc($db, $sort) . ",
			" . esc($db, $sub) . ",
			'Checked in and ready for judging.',
			" . esc($db, (string)$owner) . ",
			" . esc($db, $owner_person['first']) . ",
			" . esc($db, $owner_person['last']) . ",
			1, {$abv}, 1, 1,
			" . esc($db, $jnum) . ",
			NOW(), 1, 'Bottle',
			" . esc($db, 'B' . (string)(($tid % 9) + 1)) . "
		)");
		$eid = (int)$db->insert_id;
		$entries_by_table[$tid][] = $eid;
		$all_entries[$eid] = array('table_id' => $tid, 'owner' => $owner, 'style_id' => $style_id, 'jnum' => $jnum);
	}
}

// Flights: split each table into flight 1 and flight 2 (round 1)
foreach ($entries_by_table as $tid => $eids) {
	$flight_size = max(4, (int)$table_meta[$tid]['flight_size']);
	foreach ($eids as $idx => $eid) {
		$flight_num = ($idx < $flight_size) ? 1 : 2;
		$db->query("INSERT INTO {$p}judging_flights
			(flightTable, flightNumber, flightEntryID, flightRound, flightPlanning)
			VALUES ({$tid}, {$flight_num}, " . esc($db, (string)$eid) . ", 1, 0)");
	}
}

// --- Judge & steward assignments (day-of seating chart) ---
// 3 judges + 1 steward per table; head judge marked HJ; flight 1 for AM start
$seating = array(
	// table index => [HJ, J2, J3, steward_index]
	0 => array(0, 1, 2, 0),   // Pale Lager — Sarah HJ, Tom, Maria / Alex
	1 => array(3, 4, 5, 1),   // Pale Ale — James HJ, Priya, Owen / Jamie
	2 => array(6, 7, 0, 2),   // IPA — Nina HJ, Leo, Sarah / Chris
	3 => array(8, 9, 10, 3),  // Amber — Hannah HJ, Derek, Iris / Sam
	4 => array(1, 2, 11, 4),  // Porter PM — Tom HJ, Maria, Felix / Kelly
	5 => array(4, 5, 3, 5),   // British PM — Priya HJ, Owen, James / Pat
	6 => array(7, 6, 8, 0),   // Strong PM — Leo HJ, Nina, Hannah / Alex
	7 => array(9, 10, 11, 1), // Belgian Side — Derek HJ, Iris, Felix / Jamie
	8 => array(2, 0, 4, 2),   // Wheat Side — Maria HJ, Sarah, Priya / Chris
);

foreach ($seating as $t_index => $seat) {
	$tid = $table_ids[$t_index];
	$session = $table_meta[$tid]['session'];
	$roles = array('HJ', '', '');
	for ($i = 0; $i < 3; $i++) {
		$judge_uid = $judges[$seat[$i]];
		$db->query("INSERT INTO {$p}judging_assignments
			(bid, assignment, assignTable, assignFlight, assignRound, assignLocation, assignRoles, assignPlanning)
			VALUES ({$judge_uid}, 'J', {$tid}, 1, 1, {$session}, " . esc($db, $roles[$i]) . ", 0)");
	}
	$steward_uid = $stewards[$seat[3] % count($stewards)];
	$db->query("INSERT INTO {$p}judging_assignments
		(bid, assignment, assignTable, assignFlight, assignRound, assignLocation, assignRoles, assignPlanning)
		VALUES ({$steward_uid}, 'S', {$tid}, 1, 1, {$session}, '', 0)");
}

// Day judging begins: no scoresheets yet (evaluations already wiped)

// Summary
$counts = array();
foreach (array('users','brewer','staff','brewing','judging_locations','judging_tables','judging_assignments','judging_flights','evaluation') as $t) {
	$res = $db->query("SELECT COUNT(*) AS c FROM {$p}{$t}");
	$row = $res->fetch_assoc();
	$counts[$t] = (int)$row['c'];
}

echo "Done.\n";
echo "Counts: " . json_encode($counts) . "\n\n";
echo "State: day judging begins — entries checked in, tables/flights set, judges seated, 0 scoresheets.\n\n";
echo "Login password (all seeded users): test123\n";
echo "Security answer: pabst\n\n";
echo "Try these judges:\n";
echo "  sarah.judge@example.com  — HJ Pale Lager (AM); also IPA + Wheat tables\n";
echo "  tom.judge@example.com    — Pale Lager (AM); HJ Porter/Stout (PM)\n";
echo "  nina.judge@example.com   — HJ IPA (AM)\n";
echo "  maria.judge@example.com  — HJ Wheat/Sour (Side); also Porter PM\n\n";
echo "Admin: user.baseline@brewingcompetitions.com / bcoem\n";
echo "Judge dashboard: http://localhost/mbc/index.php?section=evaluation\n";
echo "Log out/in so session prefs reload.\n";
