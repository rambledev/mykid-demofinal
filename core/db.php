<?php
/**
 * Core database — MariaDB connection (PDO), table-name mapping and column metadata.
 *
 * Connection settings come from environment variables (Coolify / Docker) or, for local
 * development, from the project's .env file: DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS.
 * The schema (database/schema.sql) is applied automatically when app_meta is missing.
 */
declare(strict_types=1);

/** Environment value: real env var first, then .env (local development). */
function mk_env(string $key, ?string $default = null): ?string
{
    static $file = null;
    $value = getenv($key);
    if ($value !== false && $value !== '') {
        return $value;
    }
    if ($file === null) {
        $file = [];
        $path = MK_ROOT . '/.env';
        foreach (is_file($path) ? file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [] as $line) {
            if (preg_match('/^\s*([A-Z0-9_]+)\s*=\s*(.*?)\s*$/', $line, $m)) {
                $file[$m[1]] = trim($m[2], "\"'");
            }
        }
    }
    return $file[$key] ?? $default;
}

/** Shared PDO connection (one per request). */
function mk_db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }
    $host = mk_env('DB_HOST', '127.0.0.1');
    $port = mk_env('DB_PORT', '3306');
    $name = mk_env('DB_NAME', 'mykid');
    mk_log('DB', 'connect START', ['host' => $host, 'port' => $port, 'db' => $name]);
    try {
        $pdo = new PDO("mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4", mk_env('DB_USER', 'mykid'), mk_env('DB_PASS', ''), [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        $pdo->exec("SET time_zone = '+07:00', sql_mode = 'STRICT_ALL_TABLES,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION'");
    } catch (PDOException $e) {
        error_log('[Mykid][DB][connect] ERROR ' . $e->getMessage() . ' ' . json_encode(['host' => $host, 'port' => $port, 'db' => $name]));
        mk_db_unavailable();
    }
    mk_db_install_if_missing($pdo);
    mk_log('DB', 'connect END');
    return $pdo;
}

/** Friendly "try again" response when the database cannot be reached (details stay in the log). */
function mk_db_unavailable(): never
{
    http_response_code(503);
    $message = 'ระบบกำลังดำเนินการ กรุณาลองใหม่อีกครั้งในอีกสักครู่';
    if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') || basename($_SERVER['SCRIPT_NAME'] ?? '') === 'api.php') {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['ok' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
    } else {
        header('Content-Type: text/html; charset=UTF-8');
        echo '<!DOCTYPE html><html lang="th"><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>Mykid</title><body style="font-family:sans-serif;display:grid;place-items:center;min-height:90vh;color:#5A3A26;background:#FFF6E9">'
            . '<p style="text-align:center">☀️<br>' . $message . '</p></body></html>';
    }
    exit;
}

/** Create the tables from database/schema.sql when the database is still empty. */
function mk_db_install_if_missing(PDO $pdo): void
{
    if ($pdo->query("SHOW TABLES LIKE 'app_meta'")->fetchColumn() !== false) {
        return;
    }
    mk_log('DB', 'install START (app_meta missing)');
    mk_db_run_sql($pdo, (string) file_get_contents(MK_ROOT . '/database/schema.sql'));
    mk_log('DB', 'install END');
}

/** Run a .sql file's statements one by one (statements end with ";" at the end of a line). */
function mk_db_run_sql(PDO $pdo, string $sql): void
{
    $sql = preg_replace('/^\s*--.*$/m', '', $sql);
    foreach (preg_split('/;\s*$/m', $sql) as $statement) {
        if (trim($statement) !== '') {
            $pdo->exec($statement);
        }
    }
}

/** App table name (camelCase, core/schema.php) → database table name (snake_case). */
function mk_db_table(string $table): string
{
    return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $table));
}

/** Backtick-quote an identifier (column names include reserved words: by, from, condition, ...). */
function mk_db_quote(string $name): string
{
    return '`' . str_replace('`', '``', $name) . '`';
}

/**
 * Column metadata of every table: [dbTable => [column => kind]], kind = int | float | bool | json | date | datetime | string.
 * JSON columns are recognised by MariaDB's json_valid() check constraint.
 */
function mk_db_columns(): array
{
    static $columns = null;
    if ($columns !== null) {
        return $columns;
    }
    $pdo = mk_db();
    $json = [];
    foreach ($pdo->query("SELECT TABLE_NAME, CONSTRAINT_NAME FROM information_schema.CHECK_CONSTRAINTS
                          WHERE CONSTRAINT_SCHEMA = DATABASE() AND CHECK_CLAUSE LIKE 'json_valid(%'") as $c) {
        $json[$c['TABLE_NAME']][$c['CONSTRAINT_NAME']] = true;
    }
    $columns = [];
    foreach ($pdo->query("SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE, COLUMN_TYPE FROM information_schema.COLUMNS
                          WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME, ORDINAL_POSITION") as $c) {
        $columns[$c['TABLE_NAME']][$c['COLUMN_NAME']] = match (true) {
            isset($json[$c['TABLE_NAME']][$c['COLUMN_NAME']]) => 'json',
            $c['COLUMN_TYPE'] === 'tinyint(1)'                 => 'bool',
            in_array($c['DATA_TYPE'], ['tinyint', 'smallint', 'mediumint', 'int', 'bigint'], true) => 'int',
            in_array($c['DATA_TYPE'], ['double', 'float', 'decimal'], true) => 'float',
            $c['DATA_TYPE'] === 'date'                         => 'date',
            in_array($c['DATA_TYPE'], ['datetime', 'timestamp'], true) => 'datetime',
            default                                            => 'string',
        };
    }
    return $columns;
}
