<?php
/**
 * Readiness check: "ok" (200) when the database answers, "unavailable" (503) otherwise.
 * Reveals nothing else — details are in the server log / `php database/check.php`.
 * (/healthz stays database-free so a database outage never restarts the web container.)
 */
declare(strict_types=1);

header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: no-store');
try {
    $pdo = new PDO('mysql:host=' . mk_env('DB_HOST', '127.0.0.1') . ';port=' . mk_env('DB_PORT', '3306') . ';dbname=' . mk_env('DB_NAME', 'mykid') . ';charset=utf8mb4',
        mk_env('DB_USER', 'mykid'), mk_env('DB_PASS', ''), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 3]);
    $pdo->query('SELECT 1')->fetchColumn();
    echo 'ok';
} catch (PDOException $e) {
    error_log('[Mykid][Readyz] ERROR ' . $e->getMessage());
    http_response_code(503);
    echo 'unavailable';
}
