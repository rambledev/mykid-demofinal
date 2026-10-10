<?php
/**
 * Core helpers — escaping, URLs, dates, logging, CSRF, flash, JSON.
 */
declare(strict_types=1);

/** Dev log to the PHP error log. Format: [Mykid][Scope] message {context} */
function mk_log(string $scope, string $message, array $context = []): void
{
    if (!MYKID_DEBUG) {
        return;
    }
    $ctx = $context ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
    error_log('[Mykid][' . $scope . '] ' . $message . $ctx);
}

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/** Relative prefix ('', '../', '../../') from the running script's folder to $targetDir. */
function rel_prefix(string $targetDir): string
{
    $script = realpath($_SERVER['SCRIPT_FILENAME'] ?? '') ?: $targetDir . '/index.php';
    $from = str_replace('\\', '/', dirname($script));
    $to = str_replace('\\', '/', $targetDir);
    $rel = trim(substr($from, strlen($to)), '/');
    return $rel === '' ? '' : str_repeat('../', substr_count($rel, '/') + 1);
}

/** URL inside the app (project root). */
function url(string $path = ''): string
{
    return rel_prefix(MK_PKG_ROOT) . ltrim($path, '/');
}

/** URL relative to the project root (shared assets). */
function root_url(string $path = ''): string
{
    return rel_prefix(MK_ROOT) . ltrim($path, '/');
}

/** Cache-busted asset URL; $path is relative to the project root. */
function asset_url(string $path): string
{
    $file = MK_ROOT . '/' . $path;
    return root_url($path) . '?v=' . (is_file($file) ? filemtime($file) : '1');
}

function redirect(string $path): never
{
    mk_log('Helpers', 'redirect', ['to' => $path]);
    header('Location: ' . url($path));
    exit;
}

function today(): string
{
    return date('Y-m-d');
}

const THAI_MONTHS = ['', 'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน',
    'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
const THAI_MONTHS_SHORT = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
const THAI_DAYS = ['อาทิตย์', 'จันทร์', 'อังคาร', 'พุธ', 'พฤหัสบดี', 'ศุกร์', 'เสาร์'];
const THAI_DAYS_SHORT = ['อา.', 'จ.', 'อ.', 'พ.', 'พฤ.', 'ศ.', 'ส.'];

function thai_date(?int $ts = null): string
{
    $ts ??= time();
    return 'วัน' . THAI_DAYS[(int) date('w', $ts)] . 'ที่ ' . date('j', $ts) . ' '
        . THAI_MONTHS[(int) date('n', $ts)] . ' ' . ((int) date('Y', $ts) + 543);
}

/** "5 ต.ค." from Y-m-d. */
function thai_short_date(string $date): string
{
    $ts = strtotime($date) ?: time();
    return date('j', $ts) . ' ' . THAI_MONTHS_SHORT[(int) date('n', $ts)];
}

/** "10:30 น." — prefixed with a short date when it is not today. */
function thai_time(?string $datetime): string
{
    if (!$datetime) {
        return '-';
    }
    $ts = strtotime($datetime) ?: time();
    $time = date('H:i', $ts) . ' น.';
    return date('Y-m-d', $ts) === today() ? $time : thai_short_date(date('Y-m-d', $ts)) . ' · ' . $time;
}

function is_today(?string $datetime): bool
{
    return $datetime !== null && date('Y-m-d', strtotime($datetime) ?: 0) === today();
}

/** Minutes between two "HH:MM" strings. */
function minutes_between(string $from, string $to): int
{
    [$h1, $m1] = array_map('intval', explode(':', $from));
    [$h2, $m2] = array_map('intval', explode(':', $to));
    return ($h2 * 60 + $m2) - ($h1 * 60 + $m1);
}

function thai_duration(int $minutes): string
{
    $h = intdiv($minutes, 60);
    $m = $minutes % 60;
    return trim(($h ? $h . ' ชั่วโมง ' : '') . ($m ? $m . ' นาที' : ''));
}

/** Deterministic pseudo-random int for mock data. */
function mk_rand(int|string ...$parts): int
{
    return crc32(implode('|', $parts)) & 0x7fffffff;
}

function percent(int|float $part, int|float $total): int
{
    return $total > 0 ? (int) round($part * 100 / $total) : 0;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function verify_csrf(?string $token): bool
{
    return is_string($token) && hash_equals(csrf_token(), $token);
}

/** Set (2 args) or read-once (1 arg) a flash value; toasts may be ['message' => ..., 'type' => ...]. */
function flash(string $key, string|array|null $value = null): string|array|null
{
    if ($value !== null) {
        $_SESSION['flash'][$key] = $value;
        return null;
    }
    $stored = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $stored;
}

function json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function capture(callable $render): string
{
    ob_start();
    $render();
    return (string) ob_get_clean();
}

function json_attr(mixed $value): string
{
    return e(json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}
