<?php
/**
 * CLI: set up the database — tables (database/schema.sql) + the school + the first admin account.
 * No demo data: everything else (rooms, teachers, students, accounts) is entered by the admin.
 *
 *   php database/install.php                       create missing tables, show the current state
 *   php database/install.php --school="ศูนย์พัฒนาเด็กเล็กบ้านโพนสูง" --short="ศพด.บ้านโพนสูง" \
 *       --province="อุดรธานี" --admin-name="ชื่อผู้ดูแล" --admin-phone=0812345678 --admin-pin=246810
 *                                                  first setup (only when no school exists yet)
 *   php database/install.php --reset ...           DROP every table first (all data is lost!) then set up
 *
 * Connection settings: env vars or .env (DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS).
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
require $root . '/app/bootstrap.php';

$opt = getopt('', ['reset', 'school:', 'short:', 'province:', 'admin-name:', 'admin-phone:', 'admin-pin:']);
$fail = function (string $message): never {
    fwrite(STDERR, "❌ $message\n");
    exit(1);
};

echo "[Install] START " . mk_env('DB_USER') . '@' . mk_env('DB_HOST') . ':' . mk_env('DB_PORT') . '/' . mk_env('DB_NAME') . "\n";
$pdo = mk_db(); // creates missing tables / applies schema.sql changes

if (isset($opt['reset'])) {
    echo "[Install] --reset: dropping every table\n";
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
        $pdo->exec('DROP TABLE ' . mk_db_quote($table));
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    mk_db_run_sql($pdo, (string) file_get_contents($root . '/database/schema.sql'));
    $pdo->prepare("REPLACE INTO app_meta (k, v) VALUES ('ddl', ?)")->execute([md5_file($root . '/database/schema.sql')]);
}

if (isset($opt['school'])) {
    if (store_rows('schools')) {
        $fail('มีข้อมูลโรงเรียนอยู่แล้ว — ใช้ --reset ถ้าต้องการเริ่มใหม่ทั้งหมด (ข้อมูลเดิมจะหายทั้งหมด)');
    }
    $name = trim((string) $opt['school']);
    $phone = normalize_phone((string) ($opt['admin-phone'] ?? ''));
    $pin = (string) ($opt['admin-pin'] ?? '');
    $adminName = trim((string) ($opt['admin-name'] ?? 'ผู้ดูแลโรงเรียน'));
    if ($name === '') {
        $fail('กรุณาระบุชื่อโรงเรียน --school="..."');
    }
    if (!preg_match('/^0\d{9}$/', $phone)) {
        $fail('กรุณาระบุเบอร์โทร Admin 10 หลัก --admin-phone=08xxxxxxxx');
    }
    if (!preg_match('/^\d{6}$/', $pin) || preg_match('/^(\d)\1{5}$/', $pin) || in_array($pin, ['123456', '654321', '012345'], true)) {
        $fail('กรุณาระบุ PIN 6 หลักที่เดายาก --admin-pin=xxxxxx (ห้ามใช้ 123456 / เลขซ้ำกันทั้งหมด)');
    }
    store_mutate(function (array &$state) use ($name, $opt, $phone, $pin, $adminName) {
        $schoolId = $state['nextId']++;
        $state['tables']['schools'][] = ['id' => $schoolId, 'name' => $name, 'shortName' => trim((string) ($opt['short'] ?? $name)),
            'emoji' => '🏫', 'color' => '#FFF4CC', 'province' => trim((string) ($opt['province'] ?? '')), 'status' => 'active'];
        $state['tables']['settings'][] = ['id' => $state['nextId']++, 'school_id' => $schoolId, 'phone' => '', 'address' => '',
            'openTime' => '07:30', 'closeTime' => '16:30', 'motto' => ''];
        $state['tables']['users'][] = ['id' => $state['nextId']++, 'name' => $adminName, 'phone' => $phone, 'pin' => pin_hash($pin),
            'role' => 'admin', 'school_id' => $schoolId, 'classroom_id' => null, 'student_id' => null, 'teacher_id' => null];
    });
    echo "[Install] ✅ created school \"$name\" + admin $phone\n";
}

$schools = store_rows('schools');
echo "[Install] END\n";
if (!$schools) {
    echo "  ยังไม่ได้ตั้งค่าโรงเรียน — รันอีกครั้งพร้อม --school / --admin-phone / --admin-pin (ดูตัวอย่างด้านบนของไฟล์นี้)\n";
    exit(0);
}
printf("  school   %s\n", $schools[0]['name']);
foreach (store_tables() as $table) {
    $n = count(store_rows($table));
    if ($n) {
        printf("  %-20s %5d rows\n", mk_db_table($table), $n);
    }
}
