<?php
/**
 * CLI: create the tables (database/schema.sql) and seed the demo data.
 *
 *   php database/install.php           create missing tables, seed if the database is empty
 *   php database/install.php --reset   DROP every app table first, then create + seed again
 *
 * Connection settings: env vars or .env (DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS).
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
$_SERVER['SCRIPT_FILENAME'] = $root . '/index.php';
require $root . '/includes/bootstrap.php';

echo "[Install] START\n";
$pdo = mk_db(); // creates missing tables

if (in_array('--reset', $argv, true)) {
    echo "[Install] --reset: dropping app tables\n";
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
        $pdo->exec('DROP TABLE ' . mk_db_quote($table));
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    mk_db_run_sql($pdo, (string) file_get_contents($root . '/database/schema.sql'));
}

$meta = store_state(); // seeds an empty database / rolls an old one forward to today
echo '[Install] END ' . json_encode($meta) . "\n";
foreach (store_tables() as $table) {
    printf("  %-20s %5d rows\n", mk_db_table($table), count(store_rows($table)));
}
