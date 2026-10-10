<?php
/**
 * Core helpers — escaping, URLs, dates, logging, flash, JSON (CSRF: security.php).
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

/**
 * Clean URL inside the app: url('teacher/attendance') → "/teacher/attendance".
 * Legacy forms are normalised too: "teacher/attendance.php" → "/teacher/attendance",
 * "teacher/index.php" → "/teacher", "index.php" → "/". A query string is kept.
 */
function url(string $path = ''): string
{
    [$path, $query] = array_pad(explode('?', $path, 2), 2, null);
    $path = trim($path, '/');
    $path = preg_replace('/\.php$/', '', $path);
    $path = preg_replace('#(^|/)index$#', '', $path);
    return base_path() . '/' . $path . ($query !== null && $query !== '' ? '?' . $query : '');
}

/** Alias kept for older call sites. */
function root_url(string $path = ''): string
{
    return url($path);
}

/** Cache-busted asset URL; $path is relative to public/ (e.g. "assets/css/base.css"). */
function asset_url(string $path): string
{
    $file = MK_PUBLIC . '/' . ltrim($path, '/');
    return base_path() . '/' . ltrim($path, '/') . '?v=' . (is_file($file) ? filemtime($file) : '1');
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

/**
 * Thai clock time from a stored "HH:MM" (or "HH:MM:SS"): "08:00 น." · empty → $empty.
 * All user-facing dates / times are Thai (Buddhist year, Thai month names, 24 h + "น.", Asia/Bangkok).
 */
function thai_hm(?string $hm, string $empty = ''): string
{
    return $hm ? substr($hm, 0, 5) . ' น.' : $empty;
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

function percent(int|float $part, int|float $total): int
{
    return $total > 0 ? (int) round($part * 100 / $total) : 0;
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
