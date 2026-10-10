<?php
/**
 * Router — maps clean URLs (/teacher/attendance, /media/{token}) to app/pages/*.php.
 *
 * The route table (app/routes.php) is an explicit allow-list: unknown paths get a friendly
 * 404, a known path with the wrong HTTP method gets 405. Dynamic {segments} are matched
 * against a safe character set and read with route_param().
 */
declare(strict_types=1);

/** Base path of the app ('' when served at the domain root). */
function base_path(): string
{
    static $base = null;
    if ($base === null) {
        $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
        $base = rtrim($dir === '.' ? '' : $dir, '/');
    }
    return $base;
}

/** Path of the current request relative to the base path, e.g. "/teacher/attendance". */
function request_path(): string
{
    $path = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
    $base = base_path();
    if ($base !== '' && str_starts_with($path, $base)) {
        $path = substr($path, strlen($base));
    }
    $path = '/' . trim($path, '/');
    return $path;
}

/** Read / set a dynamic route segment ({token}, {school}, ...). */
function route_param(string $name, ?array $set = null): ?string
{
    static $params = [];
    if ($set !== null) {
        $params = $set;
        return null;
    }
    return $params[$name] ?? null;
}

/** Compile the route table into [method[], regex, page, options]. */
function routes_compiled(): array
{
    $table = require MK_APP . '/routes.php';
    $routes = [];
    foreach ($table['routes'] as $r) {
        $routes[] = [explode('|', $r[0]), route_regex($r[1]), $r[2], $r[3] ?? []];
    }
    foreach ($table['sections'] as $section => $def) {
        foreach ($def['pages'] as $name => $page) {
            $path = '/' . $section . ($name === 'index' ? '' : '/' . $name);
            $routes[] = [['GET'], route_regex($path), $page, ['role' => $def['role']]];
        }
    }
    return $routes;
}

function route_regex(string $pattern): string
{
    $regex = preg_replace_callback('/\\\\\{([a-z_]+)\\\\\}/', fn ($m) => '(?P<' . $m[1] . '>[A-Za-z0-9_-]{1,64})', preg_quote($pattern, '#'));
    return '#^' . $regex . '$#';
}

/** Dispatch the current request (called by public/index.php). */
function mk_dispatch(): void
{
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    $method = $method === 'HEAD' ? 'GET' : $method;
    $path = request_path();
    mk_log('Router', 'dispatch START', ['method' => $method, 'path' => $path]);

    $allowed = [];
    foreach (routes_compiled() as [$methods, $regex, $page, $options]) {
        if (!preg_match($regex, $path, $m)) {
            continue;
        }
        if (!in_array($method, $methods, true)) {
            $allowed = array_merge($allowed, $methods);
            continue;
        }
        route_param('', array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY));
        send_security_headers();
        if (empty($options['stateless'])) {
            if (!store_rows('schools')) { // fresh database: nothing to show until `php database/install.php --school=...`
                mk_log('Router', 'not set up (no school)', ['path' => $path]);
                error_page(503, 'ระบบยังไม่พร้อมใช้งาน', 'ผู้ดูแลระบบกำลังตั้งค่าข้อมูลโรงเรียน กรุณาลองใหม่อีกครั้งภายหลัง');
            }
            mk_session_start();
        }
        mk_render($page, $options['role'] ?? null);
        mk_log('Router', 'dispatch END', ['page' => $page]);
        return;
    }

    if ($allowed) {
        header('Allow: ' . implode(', ', array_unique($allowed)));
        mk_log('Router', 'method not allowed', ['method' => $method, 'path' => $path]);
        error_page(405, 'ไม่สามารถดำเนินการได้', 'กรุณากลับไปหน้าก่อนหน้าแล้วลองใหม่อีกครั้ง');
    }
    mk_log('Router', 'not found', ['method' => $method, 'path' => $path]);
    error_page(404, 'ไม่พบหน้าที่ต้องการ', 'ลิงก์อาจไม่ถูกต้อง หรือหน้านี้ถูกย้ายไปแล้ว');
}

/** Friendly error page (never shows technical details). */
function error_page(int $status, string $title, string $text): never
{
    http_response_code($status);
    send_security_headers();
    if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['ok' => false, 'message' => $title], JSON_UNESCAPED_UNICODE);
        exit;
    }
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html lang="th"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<meta name="robots" content="noindex"><title>' . e($title) . ' | Mykid</title>'
        . '<link rel="icon" type="image/svg+xml" href="' . e(asset_url('favicon.svg')) . '">'
        . '<link rel="stylesheet" href="' . e(asset_url('assets/css/base.css')) . '"></head>'
        . '<body class="layout-landing theme-landing"><main class="page page--landing" style="display:grid;place-items:center;min-height:80vh;text-align:center">'
        . '<div><p style="font-size:3rem;margin:0">☀️</p><h1>' . e($title) . '</h1><p>' . e($text) . '</p>'
        . '<p><a class="btn btn--primary" href="' . e(url('')) . '">กลับหน้าแรก</a></p></div></main></body></html>';
    exit;
}
