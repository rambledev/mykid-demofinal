<?php
/**
 * POST /api/media-auth — MediaMTX "authMethod: http" callback (server to server, no session / CSRF).
 *
 * MediaMTX v1.21.2 sends JSON {user, password, token, ip, action, path, protocol, id, query, userAgent};
 * 2xx = allow, anything else = deny. The decision is media_auth_decide() (app/lib/cctv.php): signed
 * short-lived token + path + camera + user + permission, all re-checked here. Fail closed: a missing
 * secret, a bad body, an exception (→ 500) or any failed check is a deny. The log never gets the token,
 * the query string or credentials.
 */
declare(strict_types=1);

$deny = function (string $reason, array $context = []): never {
    mk_log('MediaAuth', 'DENY', ['reason' => $reason] + $context);
    http_response_code(401);
    header('Content-Type: text/plain; charset=UTF-8');
    header('Cache-Control: no-store');
    echo 'unauthorized';
    exit;
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $deny('method not allowed');
}
$raw = (string) file_get_contents('php://input', false, null, 0, 8192);
$req = json_decode($raw, true);
if (!is_array($req)) {
    $deny('body is not JSON');
}

[$allowed, $reason, $context] = media_auth_decide($req);
if (!$allowed) {
    $deny($reason, $context);
}
mk_log('MediaAuth', 'ALLOW', $context);
http_response_code(200);
header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: no-store');
echo 'ok';
