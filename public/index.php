<?php
/**
 * Front controller — every request that is not a static file in public/ comes here.
 *
 * Apache: DocumentRoot public/ + FallbackResource /index.php (docker/apache/mykid.conf).
 * Local:  php -S 127.0.0.1:8081 -t public public/index.php
 */
declare(strict_types=1);

if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    if ($file !== __DIR__ . '/' && is_file($file) && !str_ends_with($file, '.php')) {
        return false; // let the built-in server send static assets
    }
    $_SERVER['SCRIPT_NAME'] = '/index.php';
}

require dirname(__DIR__) . '/app/bootstrap.php';

try {
    mk_dispatch();
} catch (Throwable $e) {
    // Details go to the server log only; the user sees a calm, non-technical message.
    error_log('[Mykid][FrontController][dispatch] ERROR ' . get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine()
        . ' ' . json_encode(['method' => $_SERVER['REQUEST_METHOD'] ?? '', 'uri' => $_SERVER['REQUEST_URI'] ?? '']));
    error_page(500, 'ระบบกำลังดำเนินการ', 'กรุณาลองใหม่อีกครั้งในอีกสักครู่');
}
