<?php
/**
 * CCTV Phase 4 — stream tokens + POST /api/media-auth (MediaMTX v1.21.2 "authMethod: http").
 *
 * Requests below have the exact shape MediaMTX v1.21.2 sends (internal/auth/manager.go
 * authenticateHTTP): {ip, user, password, token, action, path, protocol, id, query, userAgent}.
 *   HLS    → our token only in "query" (token=…) — authMethod http does not copy it to "token"
 *   WebRTC → our token in "token" (Authorization: Bearer on the WHEP POST)
 * Main server (tests/run-local.sh): MEDIA_TOKEN_SECRET set, CCTV_VIEWERS_ENABLED=1.
 * Extra servers started here: same secret + viewers OFF, and NO secret at all.
 * No real MediaMTX is involved — this proves Mykid's side only.
 */
declare(strict_types=1);

require __DIR__ . '/lib.php';

$base = getenv('TEST_BASE_URL') ?: 'http://127.0.0.1:8099';
$f = seed_fixture();
$tok = fn (string $table, int $id) => tok($table, $id);
$secret = (string) getenv('MEDIA_TOKEN_SECRET');
if (strlen($secret) < 32) {
    T::ok('MEDIA_TOKEN_SECRET is set for the test run', false);
    T::done();
}

/** One MediaMTX auth request → HTTP status. */
$mtx = function (string $url, array $over = []): int {
    $body = $over + ['ip' => '100.64.0.10', 'user' => '', 'password' => '', 'token' => '', 'action' => 'read', 'path' => 'cam01',
        'protocol' => 'hls', 'id' => '7f8c3f0e-2b6e-4b8e-9a4b-0c1d2e3f4a5b', 'query' => '', 'userAgent' => 'Mozilla/5.0'];
    $ch = curl_init($url . '/api/media-auth');
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => is_string($over['__raw'] ?? null) ? $over['__raw'] : json_encode($body),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10]);
    curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return $status;
};
$hls = fn (string $token, string $path = 'cam01', array $over = []) => $over + ['protocol' => 'hls', 'path' => $path, 'query' => 'token=' . rawurlencode($token)];

// ---- fixture cameras (through the admin API) ----
$admin = new Client($base);
$admin->login('admin', '0810000001');
$adminB = new Client($base);
$adminB->login('admin', '0820000001');
$make = function (Client $c, string $code, string $type, string $url, array $rooms, bool $teacher, bool $parent, bool $active = true) use ($tok): void {
    $r = $c->api('save', ['table' => 'cameras', 'id' => '', 'data' => ['code' => $code, 'name' => $code, 'location' => 'จุด', 'stream_type' => $type,
        'stream_url' => $url, 'rtsp_url' => '', 'status' => 'online', 'is_active' => $active ? '1' : '0', 'allow_teacher' => $teacher ? '1' : '0',
        'allow_parent' => $parent ? '1' : '0', 'area_type' => $rooms ? 'classroom' : 'common', 'classroom_ids' => array_map(fn ($id) => $tok('classrooms', $id), $rooms)]]);
    if (!($r['ok'] ?? false)) {
        T::ok("setup $code", false, $r['message'] ?? '');
    }
};
$make($admin, 'MTX-01', 'hls', 'https://media.test/cam01/index.m3u8', [$f['roomA1']], true, true);
$make($admin, 'MTX-02', 'hls', 'https://media.test/cam02/index.m3u8', [$f['roomA2']], true, true);
$make($admin, 'MTX-03', 'webrtc', 'https://media.test/cam03/whep', [], true, false);
$make($admin, 'MTX-04', 'hls', 'https://media.test/cam04/index.m3u8', [$f['roomA1']], true, true, false);
$make($adminB, 'MTX-B1', 'hls', 'https://media.test/camb1/index.m3u8', [$f['roomB1']], true, true);
$cam = [];
foreach (fresh('cameras') as $c) {
    $cam[$c['code']] = $c;
}

$clients = [];
foreach (['admin' => '0810000001', 'teacher' => '0810000003', 'teacher2' => '0810000004', 'parent' => '0810000005', 'exec' => '0810000002'] as $who => $phone) {
    $clients[$who] = new Client($base);
    $clients[$who]->login(in_array($who, ['teacher2'], true) ? 'teacher' : ($who === 'exec' ? 'executive' : $who), $phone);
}
$stream = fn (string $who, string $code) => $clients[$who]->api('camera_stream', ['camera_id' => $tok('cameras', $cam[$code]['id'])])['stream'] ?? null;
$tokenOf = function (?array $s): string {
    if (!$s) {
        return '';
    }
    if ($s['token'] !== '') {
        return $s['token'];
    }
    parse_str((string) parse_url((string) $s['url'], PHP_URL_QUERY), $q);
    return (string) ($q['token'] ?? '');
};

T::section('1. tokens handed out by camera_stream');
$sAdmin = $stream('admin', 'MTX-01');
$tAdmin = $tokenOf($sAdmin);
T::ok('hls descriptor: URL carries ?token=, type hls', ($sAdmin['type'] ?? '') === 'hls' && str_starts_with($sAdmin['url'], 'https://media.test/cam01/index.m3u8?token='));
T::ok('token format: base64url "." signature, no ":" (MediaMTX would split user:pass)', preg_match('/^[A-Za-z0-9_-]+\.[A-Za-z0-9_-]{43}$/', $tAdmin) === 1);
T::ok('token is not derived from the CSRF token / APP_KEY (verifies only with MEDIA_TOKEN_SECRET)', media_token_verify($tAdmin) !== null);
$tParent = $tokenOf($stream('parent', 'MTX-01'));
$tTeacher = $tokenOf($stream('teacher', 'MTX-01'));
$sWebrtc = $stream('teacher', 'MTX-03');
$tWebrtc = $tokenOf($sWebrtc);
T::ok('webrtc descriptor: URL unchanged, token in the descriptor (sent as Bearer)', ($sWebrtc['type'] ?? '') === 'webrtc' && $sWebrtc['url'] === 'https://media.test/cam03/whep' && $tWebrtc !== '');
T::ok('no token for a camera the user may not see (parent → MTX-02 is fine, teacher A1 → MTX-02 is not)', $stream('teacher', 'MTX-02') === null);

T::section('2. valid MediaMTX requests are allowed');
T::ok('admin, HLS (token in query) → 200', $mtx($base, $hls($tAdmin)) === 200);
T::ok('HLS query with extra parameters → 200', $mtx($base, $hls($tAdmin, 'cam01', ['query' => 'foo=1&token=' . rawurlencode($tAdmin)])) === 200);
T::ok('teacher A1, HLS → 200', $mtx($base, $hls($tTeacher)) === 200);
T::ok('parent (child in A1), HLS → 200', $mtx($base, $hls($tParent)) === 200);
T::ok('teacher, WebRTC (token field = Bearer) → 200', $mtx($base, ['protocol' => 'webrtc', 'path' => 'cam03', 'token' => $tWebrtc]) === 200);

T::section('3. forged / wrong tokens are denied');
$flip = substr($tAdmin, 0, -2) . (substr($tAdmin, -2, 1) === 'A' ? 'B' : 'A') . substr($tAdmin, -1);
[$body] = explode('.', $tAdmin);
$claims = json_decode(base64_decode(strtr($body, '-_', '+/')), true);
$tamper = rtrim(strtr(base64_encode(json_encode(['u' => $f['parentA']] + $claims)), '+/', '-_'), '=') . '.' . explode('.', $tAdmin)[1];
$otherKey = rtrim(strtr(base64_encode(hash_hmac('sha256', $body, 'some-other-key', true)), '+/', '-_'), '=');
$cases = [
    'no token at all'                        => ['query' => ''],
    'garbage token'                          => $hls('not-a-token'),
    'signature changed'                      => $hls($flip),
    'claims changed (user swapped)'          => $hls($tamper),
    'signed with another key'                => $hls($body . '.' . $otherKey),
    'old CSRF-style hex token'               => $hls(hash_hmac('sha256', 'x', 'y')),
    'token for cam01 used on path cam02'     => $hls($tAdmin, 'cam02'),
    'path not matching any camera'           => $hls($tAdmin, 'cam99'),
    'HLS token used over WebRTC'             => ['protocol' => 'webrtc', 'path' => 'cam01', 'token' => $tAdmin],
    'WebRTC token used over HLS'             => $hls($tWebrtc, 'cam03'),
    'action publish'                         => $hls($tAdmin, 'cam01', ['action' => 'publish']),
    'action playback'                        => $hls($tAdmin, 'cam01', ['action' => 'playback']),
    'action api'                             => $hls($tAdmin, 'cam01', ['action' => 'api']),
    'protocol rtsp'                          => $hls($tAdmin, 'cam01', ['protocol' => 'rtsp']),
    'two token values in the query'          => ['query' => 'token=' . rawurlencode($tAdmin) . '&token[]=x'],
    'body is not JSON'                       => ['__raw' => 'token=' . $tAdmin],
];
foreach ($cases as $what => $req) {
    $status = $mtx($base, $req);
    T::ok("$what → denied", $status >= 400, "status $status");
}

T::section('4. expired / too-long tokens');
$expired = media_token_issue($cam['MTX-01'], ['id' => $f['adminA']], 'cam01', 'hls', time() - 1);
T::ok('expired 1 s ago → denied', $mtx($base, $hls($expired)) === 401);
$tooLong = media_token_issue($cam['MTX-01'], ['id' => $f['adminA']], 'cam01', 'hls', time() + 86400);
T::ok('expiry far beyond the TTL → denied', $mtx($base, $hls($tooLong)) === 401);
$fresh = media_token_issue($cam['MTX-01'], ['id' => $f['adminA']], 'cam01', 'hls');
T::ok('freshly issued in-process token → allowed (same secret as the server)', $mtx($base, $hls($fresh)) === 200);

T::section('5. user / permission / school');
$mint = fn (string $code, int $userId, string $path, string $type = 'hls') => media_token_issue($cam[$code], ['id' => $userId], $path, $type);
T::ok('parent on a teacher-only camera (MTX-03) → denied', $mtx($base, ['protocol' => 'webrtc', 'path' => 'cam03', 'token' => $mint('MTX-03', $f['parentA'], 'cam03', 'webrtc')]) === 401);
T::ok('teacher A2 on room A1 camera → denied', $mtx($base, $hls($mint('MTX-01', $f['teacherA2'], 'cam01'))) === 401);
T::ok('admin A on school B camera → denied', $mtx($base, $hls($mint('MTX-B1', $f['adminA'], 'camb1'), 'camb1')) === 401);
T::ok('admin B on its own camera → allowed', $mtx($base, $hls($mint('MTX-B1', $f['adminB'], 'camb1'), 'camb1')) === 200);
T::ok('user that does not exist → denied', $mtx($base, $hls($mint('MTX-01', 999999, 'cam01'))) === 401);
T::ok('executive (read-only role) may watch own school → allowed', $mtx($base, $hls($tokenOf($stream('exec', 'MTX-01')))) === 200);

T::section('6. state changes after the token was issued (re-checked on every auth call)');
$set = function (string $code, array $changes) use ($cam): void {
    store_mutate(function (array &$s) use ($cam, $code, $changes) {
        foreach ($s['tables']['cameras'] as &$c) {
            if ($c['id'] === $cam[$code]['id']) {
                $c = array_merge($c, $changes);
            }
        }
    });
};
$set('MTX-01', ['is_active' => false]);
T::ok('camera deactivated → parent token denied', $mtx($base, $hls($tParent)) === 401);
T::ok('camera deactivated → admin token denied too', $mtx($base, $hls($tAdmin)) === 401);
$set('MTX-01', ['is_active' => true, 'status' => 'offline']);
T::ok('camera offline → denied', $mtx($base, $hls($tAdmin)) === 401);
$set('MTX-01', ['status' => 'online', 'stream_url' => 'https://media.test/cam01b/index.m3u8']);
T::ok('stream URL moved to another path → old token denied on both paths', $mtx($base, $hls($tAdmin)) === 401 && $mtx($base, $hls($tAdmin, 'cam01b')) === 401);
$set('MTX-01', ['stream_url' => 'https://media.test/cam01/index.m3u8']);
T::ok('restored → allowed again within the TTL', $mtx($base, $hls($tParent)) === 200);
store_mutate(function (array &$s) use ($f) {
    foreach ($s['tables']['students'] as &$st) {
        if ($st['id'] === $f['kidA1']) {
            $st['classroom_id'] = $f['roomA3'];
        }
    }
});
T::ok('child moved out of the room → parent token denied', $mtx($base, $hls($tParent)) === 401);
store_mutate(function (array &$s) use ($f) {
    foreach ($s['tables']['students'] as &$st) {
        if ($st['id'] === $f['kidA1']) {
            $st['classroom_id'] = $f['roomA1'];
        }
    }
});
$admin->api('save', ['table' => 'cameras', 'id' => $tok('cameras', $cam['MTX-01']['id']), 'data' => ['allow_teacher' => '1', 'allow_parent' => '0']]);
T::ok('parent permission removed → parent token denied', $mtx($base, $hls($tParent)) === 401);
T::ok('…teacher token still allowed', $mtx($base, $hls($tTeacher)) === 200);

T::section('7. fail closed: secret missing / weak / reused, viewers switched off');
$spawn = function (int $port, array $env): array {
    $log = tempnam(sys_get_temp_dir(), 'mk-test-extra-');
    $proc = proc_open([PHP_BINARY, '-S', "127.0.0.1:$port", '-t', 'public', 'public/index.php'], [1 => ['file', $log, 'w'], 2 => ['file', $log, 'w']],
        $pipes, dirname(__DIR__), $env + getenv());
    usleep(800000);
    return [$proc, $log, "http://127.0.0.1:$port"];
};
[$pNoSecret, $logNoSecret, $noSecret] = $spawn(8096, ['MEDIA_TOKEN_SECRET' => '', 'CCTV_VIEWERS_ENABLED' => '0']);
[$pNoViewers, $logNoViewers, $noViewers] = $spawn(8095, ['CCTV_VIEWERS_ENABLED' => '0']);
try {
    T::ok('no secret: a token valid elsewhere → denied', $mtx($noSecret, $hls($tTeacher)) === 401);
    $c = new Client($noSecret);
    $c->login('admin', '0810000001');
    $s = $c->api('camera_stream', ['camera_id' => $tok('cameras', $cam['MTX-01']['id'])])['stream'] ?? null;
    T::ok('no secret: camera_stream gives "unavailable", no URL, no token', ($s['type'] ?? '') === 'unavailable' && $s['url'] === '' && $s['token'] === '' && !empty($s['notice']));
    $p = new Client($noViewers);
    $p->login('teacher', '0810000003');
    $s = $p->api('camera_stream', ['camera_id' => $tok('cameras', $cam['MTX-01']['id'])])['stream'] ?? null;
    T::ok('viewers off: teacher gets "unavailable", no URL / token', ($s['type'] ?? '') === 'unavailable' && $s['url'] === '' && $s['token'] === '');
    T::ok('viewers off: teacher token (issued while on) → denied', $mtx($noViewers, $hls($tTeacher)) === 401);
    T::ok('viewers off: admin token → still allowed (admin can test)', $mtx($noViewers, $hls($mint('MTX-01', $f['adminA'], 'cam01'))) === 200);
} finally {
    foreach ([$pNoSecret, $pNoViewers] as $proc) {
        proc_terminate($proc);
        proc_close($proc);
    }
}
$was = getenv('MEDIA_TOKEN_SECRET');
putenv('MEDIA_TOKEN_SECRET=too-short');
T::ok('secret shorter than 32 chars → no key, no tokens', media_token_key() === null && media_token_issue($cam['MTX-01'], ['id' => 1], 'cam01', 'hls') === null);
putenv('MEDIA_TOKEN_SECRET=' . mk_env('APP_KEY'));
T::ok('secret equal to APP_KEY → refused', media_token_key() === null);
putenv('MEDIA_TOKEN_SECRET=' . $was);
T::ok('decision helper denies with a reason when the secret is gone', (function () use ($was, $tAdmin) {
    putenv('MEDIA_TOKEN_SECRET=');
    [$allowed, $reason] = media_auth_decide(['action' => 'read', 'protocol' => 'hls', 'path' => 'cam01', 'query' => 'token=' . $tAdmin]);
    putenv('MEDIA_TOKEN_SECRET=' . $was);
    return !$allowed && str_contains($reason, 'MEDIA_TOKEN_SECRET');
})());

T::section('8. secrets never reach the logs or the browser');
$admin->api('save', ['table' => 'cameras', 'id' => $tok('cameras', $cam['MTX-02']['id']), 'data' => ['rtsp_url' => 'rtsp://10.20.30.40:554/stream2-leakcheck']]);
$r = $admin->api('save', ['table' => 'cameras', 'id' => $tok('cameras', $cam['MTX-02']['id']), 'data' => ['rtsp_url' => 'rtsp://viewer:Sup3rS3cretPw@10.20.30.40/stream2']]);
T::ok('RTSP URL with user:password rejected', !($r['ok'] ?? true));
$r = $clients['parent']->api('change_pin', ['current' => '000111', 'new' => '135792', 'confirm' => '135792']);
T::ok('wrong current PIN rejected (validation ERROR path)', !($r['ok'] ?? true));
[, $html] = $admin->get('/admin/cctv');
T::ok('admin CCTV page has no RTSP URL', !str_contains($html, 'leakcheck') && !str_contains($html, '10.20.30.40'));
usleep(300000);
$logs = (string) @file_get_contents((string) getenv('TEST_SERVER_LOG')) . (string) @file_get_contents($logNoSecret) . (string) @file_get_contents($logNoViewers);
T::ok('server logs were read', strlen($logs) > 1000);
T::ok('no RTSP URL in the logs', !str_contains($logs, 'leakcheck') && !str_contains($logs, '10.20.30.40'));
T::ok('no RTSP password in the logs', !str_contains($logs, 'Sup3rS3cretPw'));
T::ok('no PIN typed in a failed change_pin in the logs', !str_contains($logs, '000111') && !str_contains($logs, '135792'));
$leaked = array_filter([$tAdmin, $tParent, $tTeacher, $tWebrtc, $expired, $fresh], fn ($t) => $t !== '' && str_contains($logs, $t));
T::ok('no stream token in the logs', !$leaked && !str_contains($logs, explode('.', $tAdmin)[1]));
T::ok('no MEDIA_TOKEN_SECRET in the logs', !str_contains($logs, $secret));
T::ok('media-auth decisions are logged (ALLOW / DENY with a reason)', str_contains($logs, '[MediaAuth] ALLOW') && str_contains($logs, '[MediaAuth] DENY {"reason":"invalid or expired token"'));

@unlink($logNoSecret);
@unlink($logNoViewers);

T::section('9. stream URL through the Mykid /cctv/ proxy (Apache → MediaMTX path without "cctv/")');
$paths = [ // [stream_type, stream_url, expected MediaMTX path]
    ['hls', 'http://100.116.192.95:8888/cam01/index.m3u8', 'cam01'],           // unchanged: MediaMTX directly
    ['hls', 'https://media.test/cam01/index.m3u8', 'cam01'],
    ['hls', 'https://media.test/school1/cam01/index.m3u8', 'school1/cam01'],
    ['hls', 'https://media.test/cam01/index.m3u8?foo=1', 'cam01'],
    ['webrtc', 'https://media.test/cam03/whep', 'cam03'],
    ['hls', 'https://mykid.test/cctv/cam01/index.m3u8', 'cam01'],             // new: through the proxy
    ['hls', '/cctv/cam01/index.m3u8', 'cam01'],
    ['hls', 'https://mykid.test/cctv/school1/cam01/index.m3u8', 'school1/cam01'],
    ['webrtc', 'https://mykid.test/cctv/cam03/whep', 'cam03'],
    ['hls', 'https://mykid.test/cctvx/cam01/index.m3u8', 'cctvx/cam01'],       // only the exact "cctv" segment is a prefix
    ['hls', 'https://media.test/cam01/cctv/index.m3u8', 'cam01/cctv'],         // "cctv" not first → kept
    ['hls', 'https://mykid.test/cctv/index.m3u8', null],                       // prefix alone → no camera
    ['hls', 'https://mykid.test/cctv/', null],
    ['hls', 'https://mykid.test/cctv/../cam01/index.m3u8', null],              // dot segments never form a path
    ['hls', 'https://media.test/../cam01/index.m3u8', null],
    ['hls', 'https://media.test/./cam01/index.m3u8', null],
    ['hls', 'https://media.test/cam.01/index.m3u8', 'cam.01'],                 // dots inside a name are fine
    ['hls', '', null],
    ['mock', 'https://mykid.test/cctv/cam01/index.m3u8', null],
];
foreach ($paths as [$type, $url, $want]) {
    $got = camera_media_path(['stream_type' => $type, 'stream_url' => $url]);
    T::ok("camera_media_path($type, " . ($url === '' ? "''" : $url) . ') → ' . var_export($want, true), $got === $want, 'got ' . var_export($got, true));
}
$make($admin, 'MTX-05', 'hls', 'https://mykid.test/cctv/cam05/index.m3u8', [$f['roomA2']], true, true);
$cam5 = array_values(array_filter(fresh('cameras'), fn ($c) => $c['code'] === 'MTX-05'))[0] ?? null;
T::ok('proxy URL camera saved (stream_url https://mykid.test/cctv/cam05/index.m3u8)', $cam5 !== null);
$stream5 = fn (string $who) => $clients[$who]->api('camera_stream', ['camera_id' => $tok('cameras', $cam5['id'] ?? 0)])['stream'] ?? null;
$s5 = $stream5('admin');
$t5 = $tokenOf($s5);
T::ok('proxy URL: descriptor keeps the proxy URL + ?token=', str_starts_with((string) ($s5['url'] ?? ''), 'https://mykid.test/cctv/cam05/index.m3u8?token='));
T::ok('proxy URL: token carries the MediaMTX path "cam05"', (media_token_verify($t5)['p'] ?? null) === 'cam05');
T::ok('proxy URL: MediaMTX reports path cam05 → allowed', $mtx($base, $hls($t5, 'cam05')) === 200);
T::ok('proxy URL: path "cctv/cam05" (not what MediaMTX sends) → denied', $mtx($base, $hls($t5, 'cctv/cam05')) === 401);
T::ok('proxy URL: token for cam05 on cam01 → denied', $mtx($base, $hls($t5, 'cam01')) === 401);
T::ok('proxy URL: parent (child in A2) → allowed', $mtx($base, $hls($tokenOf($stream5('parent')), 'cam05')) === 200);
T::ok('proxy URL: teacher A1 gets no token (room A2 camera)', $stream5('teacher') === null);

T::done();
