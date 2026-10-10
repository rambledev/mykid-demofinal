<?php
/**
 * CLI: test the database connection with the current settings (env vars or .env).
 *
 *   php database/check.php
 *
 * Prints where it connects (never the password), the server version, which tables exist and
 * how many rows they hold. Exit code 0 = connected, 1 = failed. Read-only: creates nothing.
 * On Coolify: Application → Terminal → `php database/check.php`.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/app/bootstrap.php';

$cfg = [
    'host' => mk_env('DB_HOST', '127.0.0.1'),
    'port' => mk_env('DB_PORT', '3306'),
    'db'   => mk_env('DB_NAME', 'mykid'),
    'user' => mk_env('DB_USER', 'mykid'),
];
$pass = (string) mk_env('DB_PASS', '');

echo "[Check] Mykid database connection\n";
printf("  host     %s\n  port     %s\n  database %s\n  user     %s\n  password %s\n",
    $cfg['host'], $cfg['port'], $cfg['db'], $cfg['user'], $pass === '' ? '(empty!)' : '(set, ' . strlen($pass) . ' chars)');
printf("  APP_KEY  %s\n  DEMO_MODE %s · MYKID_ENV %s\n\n",
    strlen((string) mk_env('APP_KEY', '')) >= 32 ? 'OK' : 'MISSING / too short (need 32+ chars)', mk_env('DEMO_MODE', '0'), MYKID_ENV);

$started = microtime(true);
try {
    $pdo = new PDO("mysql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['db']};charset=utf8mb4", $cfg['user'], $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 5,
    ]);
} catch (PDOException $e) {
    echo "❌ เชื่อมต่อไม่สำเร็จ: " . $e->getMessage() . "\n\n";
    $hint = match (true) {
        str_contains($e->getMessage(), '2002'), str_contains($e->getMessage(), 'getaddrinfo') => 'DB_HOST / DB_PORT ไม่ถูกต้อง หรือแอปกับฐานข้อมูลไม่ได้อยู่ใน network เดียวกัน (Coolify: ใช้ชื่อ host จาก "MariaDB URL (internal)", port 3306)',
        str_contains($e->getMessage(), '1045') => 'DB_USER / DB_PASS ไม่ถูกต้อง (Coolify: Normal User / Normal User Password ของ MariaDB)',
        str_contains($e->getMessage(), '1044'), str_contains($e->getMessage(), '1049') => 'DB_NAME ไม่ถูกต้อง หรือ user ไม่มีสิทธิ์บนฐานข้อมูลนี้ (Coolify: Initial Database เช่น "default")',
        default => 'ตรวจสอบค่า DB_* อีกครั้ง',
    };
    echo "   แนะนำ: $hint\n";
    exit(1);
}

$ms = (int) round((microtime(true) - $started) * 1000);
echo "✅ เชื่อมต่อสำเร็จ ({$ms} ms)\n";
echo '  server   ' . $pdo->query('SELECT VERSION()')->fetchColumn() . "\n";
echo '  charset  ' . $pdo->query('SELECT @@character_set_database')->fetchColumn() . ' · time zone ' . $pdo->query('SELECT @@time_zone')->fetchColumn() . "\n";
$grants = $pdo->query('SHOW GRANTS')->fetchAll(PDO::FETCH_COLUMN);
echo '  grants   ' . (preg_match('/ALL PRIVILEGES|CREATE/i', implode(' ', $grants)) ? 'can create tables ✔' : 'NO CREATE privilege — tables cannot be created!') . "\n\n";

preg_match_all('/CREATE TABLE IF NOT EXISTS\s+`?([a-z_]+)`?/i', (string) file_get_contents(MK_ROOT . '/database/schema.sql'), $m);
$existing = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
$missing = array_diff($m[1], $existing);
printf("  tables   %d / %d in schema.sql\n", count($m[1]) - count($missing), count($m[1]));
if ($missing) {
    echo '  missing  ' . implode(', ', $missing) . "\n";
    echo "  → จะถูกสร้างอัตโนมัติเมื่อเปิดเว็บครั้งแรก หรือรัน: php database/install.php\n";
} else {
    $meta = $pdo->query('SELECT k, v FROM app_meta')->fetchAll(PDO::FETCH_KEY_PAIR);
    printf("  data     seedDate %s · version %s · schema %s\n", $meta['seedDate'] ?? '-', $meta['version'] ?? '-', $meta['schema'] ?? '-');
    foreach (['schools', 'users', 'classrooms', 'students', 'attendance', 'media_files'] as $t) {
        printf("           %-12s %5d rows\n", $t, (int) $pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn());
    }
}
exit(0);
