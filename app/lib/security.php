<?php
/**
 * Security baseline (OWASP ASVS L1-style) — sessions, headers, CSRF, credentials, login throttling.
 *
 * - Session: strict mode, HttpOnly + SameSite=Lax cookie (Secure on HTTPS), idle + absolute timeout,
 *   ID regenerated on login (auth.php) and periodically.
 * - Headers: CSP with a per-request nonce for inline <script>, frame-ancestors 'none', nosniff,
 *   Referrer-Policy, Permissions-Policy, HSTS on HTTPS, no-store for app pages.
 * - CSRF: one random token per session, checked on every state-changing request.
 * - Credentials: PINs are stored as password_hash() (Argon2id when available, bcrypt otherwise).
 * - Login throttling: failed attempts per (phone) and per (IP) in the login_attempts table.
 */
declare(strict_types=1);

const SESSION_IDLE_SECONDS = 2 * 60 * 60;        // log out after 2 h without activity
const SESSION_ABSOLUTE_SECONDS = 12 * 60 * 60;   // and at the latest after 12 h
const SESSION_ROTATE_SECONDS = 15 * 60;          // new session id every 15 min
const LOGIN_MAX_FAILS = 5;                       // per phone (or per IP x4) inside the window
const LOGIN_WINDOW_SECONDS = 15 * 60;

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'; // behind Coolify / Traefik
}

/** Start the hardened session (web requests only). */
function mk_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    session_name(pkg()['session']);
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => base_path() . '/',
        'secure'   => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();

    $now = time();
    $meta = $_SESSION['_meta'] ?? null;
    if (is_array($meta) && (($now - $meta['last']) > SESSION_IDLE_SECONDS || ($now - $meta['created']) > SESSION_ABSOLUTE_SECONDS)) {
        mk_log('Security', 'session expired', ['idle' => $now - $meta['last'], 'age' => $now - $meta['created']]);
        $_SESSION = [];
        session_regenerate_id(true);
        $_SESSION['flash']['login_error'] = 'หมดเวลาการใช้งาน กรุณาเข้าสู่ระบบอีกครั้ง';
        $meta = null;
    }
    if (!is_array($meta)) {
        $meta = ['created' => $now, 'last' => $now, 'rotated' => $now];
    }
    if ($now - $meta['rotated'] > SESSION_ROTATE_SECONDS) {
        session_regenerate_id(true);
        $meta['rotated'] = $now;
    }
    $meta['last'] = $now;
    $_SESSION['_meta'] = $meta;
}

/** Fresh session id + timestamps (call right after a successful login / logout). */
function session_renew(): void
{
    session_regenerate_id(true);
    $now = time();
    $_SESSION['_meta'] = ['created' => $now, 'last' => $now, 'rotated' => $now];
}

/** Per-request nonce for inline <script> (CSP). */
function csp_nonce(): string
{
    static $nonce = null;
    return $nonce ??= base64_encode(random_bytes(16));
}

function send_security_headers(): void
{
    static $sent = false;
    if ($sent || headers_sent()) {
        return;
    }
    $sent = true;
    $csp = implode('; ', [
        "default-src 'self'",
        "script-src 'self' 'nonce-" . csp_nonce() . "' https://cdn.jsdelivr.net", // hls.js (CCTV) is lazy-loaded from jsDelivr
        "style-src 'self' 'unsafe-inline'",   // style="" attributes (charts, colours)
        "img-src 'self' data: blob:",
        "media-src 'self' blob: https:",       // CCTV streams from the media server
        "connect-src 'self' https:",           // HLS segments / WebRTC WHEP on the media server
        "font-src 'self' data:",
        "object-src 'none'",
        "base-uri 'self'",
        "form-action 'self'",
        "frame-ancestors 'none'",
    ]);
    header('Content-Security-Policy: ' . $csp);
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(self), microphone=(), geolocation=(), payment=(), usb=()');
    header('Cross-Origin-Opener-Policy: same-origin');
    header('Cache-Control: no-store, max-age=0');
    if (is_https()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
    header_remove('X-Powered-By');
}

/* ---------------------------------------------------------------------------
 * CSRF
 * ------------------------------------------------------------------------ */

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function verify_csrf(?string $token): bool
{
    return is_string($token) && $token !== '' && hash_equals(csrf_token(), $token);
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

/* ---------------------------------------------------------------------------
 * Credentials (PIN 6 digits) — hashed at rest
 * ------------------------------------------------------------------------ */

function pin_algo(): string|int
{
    return defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
}

function pin_hash(string $pin): string
{
    return password_hash($pin, pin_algo());
}

/** Easy-to-guess PINs: all the same digit, or a straight run (123456, 654321, ...). */
function pin_is_weak(string $pin): bool
{
    if (preg_match('/^(\d)\1{5}$/', $pin)) {
        return true;
    }
    return str_contains('0123456789', $pin) || str_contains('9876543210', $pin);
}

/** Constant-time check; also accepts a legacy plain PIN (rehashed by the caller). */
function pin_verify(string $pin, string $stored): bool
{
    if (str_starts_with($stored, '$')) {
        return password_verify($pin, $stored);
    }
    return $stored !== '' && hash_equals($stored, $pin);
}

/* ---------------------------------------------------------------------------
 * Login throttling (brute force protection)
 * ------------------------------------------------------------------------ */

/**
 * Client IP for login throttling. Behind a reverse proxy (Coolify / Traefik, TRUST_PROXY=1) the
 * real address is the first entry of X-Forwarded-For; otherwise REMOTE_ADDR (never trusted headers).
 */
function client_ip(): string
{
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    if (mk_env('TRUST_PROXY', '0') === '1' && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $first = trim(explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
        if (filter_var($first, FILTER_VALIDATE_IP)) {
            $ip = $first;
        }
    }
    return substr($ip, 0, 45);
}

/** Seconds the caller must still wait (0 = may try). */
function login_lock_seconds(string $phone): int
{
    $pdo = mk_db();
    $since = date('Y-m-d H:i:s', time() - LOGIN_WINDOW_SECONDS);
    $wait = 0;
    foreach (['phone' => [$phone, LOGIN_MAX_FAILS], 'ip' => [client_ip(), LOGIN_MAX_FAILS * 4]] as $column => [$value, $limit]) {
        $q = $pdo->prepare("SELECT COUNT(*) n, MIN(attempted_at) first FROM login_attempts WHERE success = 0 AND attempted_at >= ? AND $column = ?");
        $q->execute([$since, $value]);
        $row = $q->fetch();
        if ($row && (int) $row['n'] >= $limit) {
            $wait = max($wait, strtotime((string) $row['first']) + LOGIN_WINDOW_SECONDS - time());
        }
    }
    return max(0, $wait);
}

function login_record_attempt(string $phone, bool $success): void
{
    $pdo = mk_db();
    $pdo->prepare('INSERT INTO login_attempts (phone, ip, success, attempted_at) VALUES (?, ?, ?, NOW())')
        ->execute([substr($phone, 0, 20), client_ip(), $success ? 1 : 0]);
    if ($success) { // a successful login clears the phone's failures
        $pdo->prepare('DELETE FROM login_attempts WHERE phone = ? AND success = 0')->execute([$phone]);
    }
    if (random_int(1, 50) === 1) { // housekeeping
        $pdo->exec('DELETE FROM login_attempts WHERE attempted_at < NOW() - INTERVAL 1 DAY');
    }
}
