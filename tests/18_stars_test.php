<?php
/**
 * ธนาคารความดี + ขับขี่ปลอดภัย: every star is credited to the student, in category good (ความดี) or
 * safe_driving (ขับขี่ปลอดภัย); presets per category fill reason + points; old parent stars migrate.
 *
 *   tests/run-local.sh
 */
declare(strict_types=1);

require __DIR__ . '/lib.php';

$base = getenv('TEST_BASE_URL') ?: 'http://127.0.0.1:8099';
$f = seed_fixture();
$tok = fn (string $table, int $id) => tok($table, $id);

// a student with NO parent account (room A1)
$f['kidNoParent'] = store_mutate(function (array &$s) use ($f): int {
    $id = $s['nextId']++;
    $s['tables']['students'][] = ['id' => $id, 'school_id' => $f['schoolA'], 'classroom_id' => $f['roomA1'], 'name' => 'kidNoParent', 'nickname' => 'kidNoParent',
        'classroom' => 'A1', 'gender' => 'm', 'avatar' => ['skin' => '#FFDBC0', 'hair' => '#3B2A20', 'bg' => '#BFE7FF', 'shirt' => '#7CC6F2', 'style' => 'short']];
    return $id;
})['result'];

$teacher = new Client($base);
T::ok('teacher A1 logs in', $teacher->login('teacher', '0810000003'));
$give = fn (Client $c, array $data) => $c->api('save', ['table' => 'stars', 'id' => '', 'data' => $data]);
$lastStar = function (): array {
    $rows = fresh('stars');
    usort($rows, fn ($a, $b) => $b['id'] <=> $a['id']);
    return $rows[0];
};

T::section('1. giving stars in both categories');
$r = $give($teacher, ['category' => 'good', 'student_id' => $tok('students', $f['kidA1']), 'points' => '5', 'reason' => 'ตั้งใจทำกิจกรรม']);
$s1 = $lastStar();
T::ok('good star saved', ($r['ok'] ?? false) && $s1['category'] === 'good' && $s1['student_id'] === $f['kidA1'] && $s1['points'] === 5 && $s1['parent_id'] === null, $r['message'] ?? '');
$r = $give($teacher, ['category' => 'safe_driving', 'student_id' => $tok('students', $f['kidA1']), 'points' => '3', 'reason' => 'สวมหมวกกันน็อก']);
$s2 = $lastStar();
T::ok('safe_driving star credited to the student, parent account recorded', ($r['ok'] ?? false) && $s2['category'] === 'safe_driving'
    && $s2['student_id'] === $f['kidA1'] && $s2['recipient'] === 'student' && $s2['parent_id'] === $f['parentA'], $r['message'] ?? '');
$r = $give($teacher, ['category' => 'safe_driving', 'student_id' => $tok('students', $f['kidNoParent']), 'points' => '2', 'reason' => 'มารับ-ส่งตรงเวลา']);
$s3 = $lastStar();
T::ok('safe_driving star works without a parent account (no error, parent_id null)', ($r['ok'] ?? false) && $s3['student_id'] === $f['kidNoParent'] && $s3['parent_id'] === null, $r['message'] ?? '');
$r = $give($teacher, ['category' => 'bogus', 'student_id' => $tok('students', $f['kidA1']), 'points' => '1', 'reason' => 'x']);
T::ok('unknown category rejected', !($r['ok'] ?? true));
$r = $give($teacher, ['category' => 'good', 'student_id' => $tok('students', $f['kidA1']), 'points' => '1', 'reason' => 'x', 'preset' => 'injected', 'recipient' => 'parent']);
$s4 = $lastStar();
T::ok('"preset" never stored, "recipient" from the browser ignored', ($r['ok'] ?? false) && !array_key_exists('preset', $s4) && $s4['recipient'] === 'student');
$admin = new Client($base);
$admin->login('admin', '0810000001');
$r = $give($admin, ['category' => 'good', 'student_id' => $tok('students', $f['kidA1']), 'points' => '1', 'reason' => 'x']);
T::ok('admin cannot give stars (teachers only, as before)', !($r['ok'] ?? true));

T::section('2. one bank per student, split by category');
$bankOf = fn (int $studentId) => where(star_rows(null, fresh('stars')), 'student_id', $studentId); // student_stars() needs a session
$bank = $bankOf($f['kidA1']);
$split = stars_by_category($bank);
T::ok('kid A1 bank = good 5+1 + safe_driving 3 = 9', stars_total($bank) === 9 && $split === ['good' => 6, 'safe_driving' => 3], json_encode($split));
$rank = star_ranking(stars_in_month(star_rows(null, fresh('stars')), date('Y-m')), 'student_id', [$f['kidA1'], $f['kidA2'], $f['kidNoParent']]);
T::ok('single ranking on the combined total (A1 first with 9)', $rank[0]['id'] === $f['kidA1'] && $rank[0]['total'] === 9);

T::section('3. old parent stars migrate to safe_driving');
$legacy = store_mutate(function (array &$s) use ($f): int {
    $id = $s['nextId']++;
    $s['tables']['stars'][] = ['id' => $id, 'school_id' => $f['schoolA'], 'classroom_id' => $f['roomA2'], 'student_id' => $f['kidA2'], 'recipient' => 'parent',
        'parent_id' => $f['parentA'], 'category' => 'good', 'date' => today(), 'points' => 4, 'reason' => 'จอดรถในจุดรับ-ส่ง'];
    return $id;
})['result'];
T::ok('legacy row is read as safe_driving even before the migration', star_category(find_row('stars', $legacy)) === 'safe_driving');
$pdo = mk_db();
$pdo->exec("DELETE FROM app_meta WHERE k = 'ddl'");
mk_db_run_sql($pdo, (string) file_get_contents(dirname(__DIR__) . '/database/schema.sql'));
$pdo->prepare("REPLACE INTO app_meta (k, v) VALUES ('ddl', ?)")->execute([md5_file(dirname(__DIR__) . '/database/schema.sql')]);
fresh('stars');
T::ok('schema.sql migration sets category = safe_driving on it', find_row('stars', $legacy)['category'] === 'safe_driving');
T::ok('migration re-run leaves good stars alone', find_row('stars', $s1['id'])['category'] === 'good');
T::ok('legacy star counts in kid A2 bank', stars_by_category($bankOf($f['kidA2']))['safe_driving'] === 4);

T::section('4. pages');
[$status, $html] = $teacher->get('/teacher/stars');
T::ok('teacher page 200, one ranking with category split', $status === 200 && str_contains($html, 'ขับขี่ปลอดภัย') && !str_contains($html, 'starbank:parents'));
T::ok('give form: category select + preset chips per category that fill reason/points',
    str_contains($html, 'name="category"') && str_contains($html, 'data-show-if="category" data-show-value="safe_driving"')
    && str_contains($html, 'data-action="fill-preset"') && preg_match('/data-reason="[^"]+" data-points="\d+"/', $html) === 1);
$parent = new Client($base);
$parent->login('parent', '0810000005');
[$status, $html] = $parent->get('/parent/stars');
T::ok('parent page 200: child total includes safe driving (9 ⭐) + split', $status === 200 && str_contains($html, '⭐ 9 ดาว') && str_contains($html, 'ดาวสะสมแยกตามหมวด'));
foreach ([[$admin, '/admin/stars'], [$admin, '/admin/settings']] as [$c, $path]) {
    [$st] = $c->get($path);
    T::ok("$path → 200", $st === 200);
}
$exec = new Client($base);
$exec->login('executive', '0810000002');
foreach (['/executive/stars', '/executive/reports', '/executive'] as $path) {
    [$st, $h] = $exec->get($path);
    T::ok("$path → 200", $st === 200);
}
[, $rep] = $exec->get('/executive/reports');
T::ok('executive report lists ดาวสะสม + both categories', str_contains($rep, 'ดาวสะสม') && str_contains($rep, '🛵 ขับขี่ปลอดภัย'));

T::done();
