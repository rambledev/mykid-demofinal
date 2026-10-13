<?php
/**
 * CLI: set up the database — tables (database/schema.sql) + the school + the first admin account.
 * No demo data: everything else (rooms, teachers, students, accounts) is entered by the admin.
 *
 *   php database/install.php                       create missing tables, show the current state
 *   php database/install.php --school="ศพด.โพนสูง" [--short=... --province=...]
 *                                                  first setup (no school yet): school + default admin
 *                                                  0820000000 / PIN 123456 (set own PIN at the first login)
 *   php database/install.php --reset --school=...  DROP every table first (all data is lost!) then set up
 *   --admin-phone=08xxxxxxxx [--admin-pin=xxxxxx] [--admin-name="..."]
 *                                                  another admin phone / a fixed PIN (no first-login change);
 *                                                  without --school: add that admin, or reset its PIN to 123456
 *   --allow-weak-pin                               accept an easy fixed --admin-pin on purpose
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

const DEFAULT_ADMIN_PHONE = '0820000000'; // default admin (PIN DEFAULT_PIN = 123456, set at first login)

$opt = getopt('', ['reset', 'allow-weak-pin', 'school:', 'short:', 'province:', 'admin-name:', 'admin-phone:', 'admin-pin:']);
$fail = function (string $message): never {
    fwrite(STDERR, "❌ $message\n");
    exit(1);
};

// 1. Validate EVERY argument before touching the database (a typo must never cost data).
$setup = null;
$adminOnly = !isset($opt['school']) && !isset($opt['reset']) && (isset($opt['admin-phone']) || isset($opt['admin-pin']));
if (isset($opt['school']) || isset($opt['reset']) || $adminOnly) {
    $name = $adminOnly ? '-' : trim((string) ($opt['school'] ?? ''));
    $phone = normalize_phone((string) ($opt['admin-phone'] ?? DEFAULT_ADMIN_PHONE));
    $pin = (string) ($opt['admin-pin'] ?? DEFAULT_PIN);
    $mustChange = !isset($opt['admin-pin']); // default PIN → the admin sets their own at the first login
    if ($name === '') {
        $fail('กรุณาระบุชื่อโรงเรียน --school="..."' . (isset($opt['reset']) ? ' (--reset ต้องตั้งค่าโรงเรียนและ Admin ใหม่ในคำสั่งเดียวกัน)' : ''));
    }
    if (!preg_match('/^0\d{9}$/', $phone)) {
        $fail('กรุณาระบุเบอร์โทร Admin 10 หลัก --admin-phone=08xxxxxxxx');
    }
    if (!preg_match('/^\d{6}$/', $pin)) {
        $fail('กรุณาระบุ PIN ตัวเลข 6 หลัก --admin-pin=xxxxxx');
    }
    $weak = pin_is_weak($pin) && !$mustChange;
    if ($weak && !isset($opt['allow-weak-pin'])) {
        $fail('PIN นี้เดาง่าย (เช่น 123456 / เลขซ้ำกันทั้งหมด) — ใช้ PIN อื่น หรือเพิ่ม --allow-weak-pin ถ้าตั้งใจ แล้วเปลี่ยนหลังเข้าระบบครั้งแรก');
    }
    $setup = ['name' => $name, 'short' => trim((string) ($opt['short'] ?? $name)), 'province' => trim((string) ($opt['province'] ?? '')),
        'phone' => $phone, 'pin' => $pin, 'adminName' => trim((string) ($opt['admin-name'] ?? 'ผู้ดูแลระบบ')), 'weak' => $weak, 'mustChange' => $mustChange];
}

echo "[Install] START " . mk_env('DB_USER') . '@' . mk_env('DB_HOST') . ':' . mk_env('DB_PORT') . '/' . mk_env('DB_NAME') . "\n";
$pdo = mk_db(); // creates missing tables / applies schema.sql changes

// Admin only (school exists): create the admin, or reset an existing admin's PIN. Nothing else changes.
if ($adminOnly) {
    $school = store_rows('schools')[0] ?? $fail('ยังไม่มีข้อมูลโรงเรียน — ตั้งค่าครั้งแรกด้วย --school=... ก่อน');
    $existing = array_values(array_filter(store_rows('users'), fn ($u) => $u['phone'] === $setup['phone']))[0] ?? null;
    if ($existing && $existing['role'] !== 'admin') {
        $fail('เบอร์ ' . $setup['phone'] . ' เป็นบัญชี ' . role_meta($existing['role'])['th'] . ' อยู่แล้ว — ใช้เบอร์อื่นสำหรับ Admin');
    }
    if ($setup['weak']) {
        echo "⚠️  PIN เดาง่าย — ควรเปลี่ยนทันทีหลังเข้าระบบครั้งแรก (หน้า บัญชี → เปลี่ยนรหัส PIN)\n";
    }
    store_mutate(function (array &$state) use ($setup, $school, $existing) {
        if ($existing) {
            foreach ($state['tables']['users'] as &$u) {
                if ($u['id'] === $existing['id']) {
                    $u['pin'] = pin_hash($setup['pin']);
                    $u['must_change_pin'] = $setup['mustChange'];
                    $u['name'] = isset($GLOBALS['opt']['admin-name']) ? $setup['adminName'] : $u['name'];
                }
            }
            unset($u);
            return;
        }
        $state['tables']['users'][] = ['id' => $state['nextId']++, 'name' => $setup['adminName'], 'phone' => $setup['phone'],
            'pin' => pin_hash($setup['pin']), 'must_change_pin' => $setup['mustChange'], 'role' => 'admin', 'school_id' => $school['id'],
            'classroom_id' => null, 'student_id' => null, 'teacher_id' => null];
    });
    mk_db()->prepare('DELETE FROM login_attempts WHERE phone = ?')->execute([$setup['phone']]); // unlock after a reset
    echo '[Install] ✅ ' . ($existing ? 'reset PIN of admin ' : 'created admin ') . $setup['phone'] . "\n";
    $setup = null;
}

if ($setup && !isset($opt['reset']) && store_rows('schools')) {
    $fail('มีข้อมูลโรงเรียนอยู่แล้ว — ใช้ --reset ถ้าต้องการเริ่มใหม่ทั้งหมด (ข้อมูลเดิมจะหายทั้งหมด)');
}

// 2. --reset: drop everything, recreate the tables.
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

// 3. The school + its settings + the first admin account.
if ($setup) {
    if ($setup['weak']) {
        echo "⚠️  PIN เดาง่าย — ควรเปลี่ยนทันทีหลังเข้าระบบครั้งแรก (หน้า บัญชี → เปลี่ยนรหัส PIN)\n";
    }
    store_mutate(function (array &$state) use ($setup) {
        $schoolId = $state['nextId']++;
        $state['tables']['schools'][] = ['id' => $schoolId, 'name' => $setup['name'], 'shortName' => $setup['short'],
            'emoji' => '🏫', 'color' => '#FFF4CC', 'province' => $setup['province'], 'status' => 'active'];
        $state['tables']['settings'][] = ['id' => $state['nextId']++, 'school_id' => $schoolId, 'phone' => '', 'address' => '',
            'openTime' => '07:30', 'closeTime' => '16:30', 'motto' => ''];
        $state['tables']['users'][] = ['id' => $state['nextId']++, 'name' => $setup['adminName'], 'phone' => $setup['phone'],
            'pin' => pin_hash($setup['pin']), 'must_change_pin' => $setup['mustChange'], 'role' => 'admin', 'school_id' => $schoolId,
            'classroom_id' => null, 'student_id' => null, 'teacher_id' => null];
    });
    echo "[Install] ✅ created school \"{$setup['name']}\" + admin {$setup['phone']}" . ($setup['mustChange'] ? ' (PIN ' . DEFAULT_PIN . ' — set own PIN at first login)' : '') . "\n";
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
