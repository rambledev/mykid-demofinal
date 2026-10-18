<?php
/**
 * Core CCTV — camera access control + stream configuration (Package A & B).
 *
 * Production architecture (see docs/cctv-architecture.md, docs/tapo-c200c-integration.md):
 *
 *     IP camera (e.g. TP-Link Tapo C200C) ──RTSP──▶ NVR / Media Server (e.g. MediaMTX)
 *                                                   │  pulls RTSP once, keeps credentials
 *                                                   ▼
 *                                         HLS (.m3u8) / WebRTC (WHEP)
 *                                                   │  validates a short-lived token
 *                                                   ▼
 *                                         Mykid web app ──▶ Browser
 *
 * Browsers cannot play RTSP, so Mykid NEVER hands an rtsp:// URL (or camera credentials) to
 * the browser. Mykid only decides WHO may watch WHICH camera, then returns a playback
 * descriptor (type + HLS/WHEP URL + token) from the permission-checked API (action
 * "camera_stream"). Mykid is not a video server and does no recording / download / playback.
 *
 * Every page, dashboard card and API call uses can_view_camera() / accessible_cameras(),
 * so the rules live in exactly one place:
 *   admin / executive  → every camera of their own school (executive is read only)
 *   teacher / parent   → same school + active camera + (own classroom camera OR common area)
 *                        + an explicit row in cameraPermissions for their role & classroom
 */
declare(strict_types=1);

const STREAM_TYPES = [
    'mock'   => 'ยังไม่เชื่อมต่อ (ยังไม่มี Stream URL)',
    'hls'    => 'HLS (.m3u8)',
    'webrtc' => 'WebRTC (WHEP)',
];

const CAMERA_STATUSES = [
    'online'      => ['label' => 'Online',      'emoji' => '🟢', 'tone' => 'good'],
    'offline'     => ['label' => 'Offline',     'emoji' => '🔴', 'tone' => 'critical'],
    'maintenance' => ['label' => 'Maintenance', 'emoji' => '🛠️', 'tone' => 'warning'],
];

const STREAM_TOKEN_TTL = 300; // seconds — media server should reject expired tokens

/** Does cameraPermissions grant this role (and classroom) access to the camera? */
function camera_policy_allows(array $camera, string $role, ?int $classroomId): bool
{
    foreach (store_rows('cameraPermissions') as $p) {
        if ($p['camera_id'] === $camera['id'] && $p['role'] === $role && !empty($p['can_view'])
            && ($p['classroom_id'] === null || $p['classroom_id'] === $classroomId)) {
            return true;
        }
    }
    return false;
}

/**
 * Classrooms a teacher / parent may watch cameras for, read from the database on every call
 * (never from the request, never cached across a write):
 *   teacher → the account's classroom
 *   parent  → the CURRENT classroom of EVERY child linked in parentStudents (users.student_id is
 *             only the fallback for accounts without links, as in current_user()); a child moved to
 *             another room changes this list at once. Only children / rooms of the user's own school count.
 */
function camera_viewer_classroom_ids(array $user): array
{
    $rooms = [];
    if ($user['role'] === 'teacher') {
        $account = find_row('users', (int) $user['id']);
        $rooms[] = $account['classroom_id'] ?? null;
    } elseif ($user['role'] === 'parent') {
        $children = array_column(where(store_rows('parentStudents'), 'user_id', (int) $user['id']), 'student_id');
        if (!$children) {
            $children = array_filter([find_row('users', (int) $user['id'])['student_id'] ?? null]);
        }
        foreach ($children as $studentId) {
            $student = find_row('students', (int) $studentId);
            if ($student !== null && $student['school_id'] === $user['school_id']) {
                $rooms[] = $student['classroom_id'];
            }
        }
    }
    $rooms = array_filter($rooms, fn ($id) => $id !== null && (find_row('classrooms', (int) $id)['school_id'] ?? null) === $user['school_id']);
    return array_values(array_unique($rooms));
}

/**
 * THE camera access rule — role + school + covered classrooms + camera permission.
 *   admin / executive → every camera of their own school (inactive too: the list shows its state;
 *                       camera_stream still refuses inactive / not-online cameras)
 *   teacher / parent  → own school + active camera + a cameraPermissions row for the role AND
 *     classroom camera: one of the viewer's classrooms is among the rooms it covers (camera_classrooms)
 *     common area     : the viewer has a classroom of this school; a common camera never opens to
 *                       every parent by itself — it needs the parent permission row (admin form)
 */
function can_view_camera(array $camera, array $user): bool
{
    if (!has_feature('cctv')) {
        return false;
    }
    // School isolation — scope comes from the session user, never the request.
    if (($camera['school_id'] ?? null) !== $user['school_id']) {
        return false;
    }
    if (in_array($user['role'], ['admin', 'executive'], true)) {
        return true;
    }
    if (!in_array($user['role'], ['teacher', 'parent'], true) || empty($camera['is_active'])) {
        return false;
    }

    $viewerRooms = camera_viewer_classroom_ids($user);
    if (!$viewerRooms) {
        return false; // no classroom (teacher without a room, parent without a current child) — nothing to watch
    }
    $covered = camera_classroom_ids($camera['id']);
    if ($covered) {
        foreach (array_intersect($viewerRooms, $covered) as $roomId) {
            if (camera_policy_allows($camera, $user['role'], $roomId)) {
                return true;
            }
        }
        return false; // only other classrooms' cameras — never
    }
    return camera_policy_allows($camera, $user['role'], null); // common area: needs a school-wide row for the role
}

/** Cameras the user may see, ordered by school then code. */
function accessible_cameras(?array $user = null): array
{
    $user ??= current_user();
    if (!$user) {
        return [];
    }
    $cameras = array_values(array_filter(store_rows('cameras'), fn ($c) => can_view_camera($c, $user)));
    usort($cameras, fn ($a, $b) => [$a['school_id'], $a['sort_order'] ?? 0, $a['code']] <=> [$b['school_id'], $b['sort_order'] ?? 0, $b['code']]);
    mk_log('CCTV', 'accessible_cameras', ['user' => $user['id'], 'role' => $user['role'], 'count' => count($cameras)]);
    return $cameras;
}

/** Look up one camera AND authorise it (use for anything driven by a camera id). */
function authorized_camera(int $cameraId, array $user): array
{
    $camera = find_row('cameras', $cameraId);
    authorize($camera !== null && can_view_camera($camera, $user), 'บัญชีนี้ไม่มีสิทธิ์ดูกล้องนี้',
        ['user' => $user['id'], 'role' => $user['role'], 'camera_id' => $cameraId]);
    return $camera;
}

/** Classroom ids a camera covers (camera_classrooms), in link order; [] = common area. */
function camera_classroom_ids(int $cameraId): array
{
    return array_column(where(store_rows('cameraClassrooms'), 'camera_id', $cameraId), 'classroom_id');
}

/**
 * Fields of the admin "ตั้งค่ากล้อง CCTV" form (app/pages/admin/cctv-settings.php, api action
 * save_camera_settings). No RTSP, no credentials — only what an admin needs to name and open a camera.
 */
function camera_settings_fields(): array
{
    return [
        'name'       => ['label' => 'ชื่อกล้อง', 'type' => 'text', 'required' => true, 'max' => 60, 'placeholder' => 'เช่น ห้องเรียน 1'],
        'view_url'   => ['label' => 'URL สำหรับเปิดดูภาพ', 'type' => 'text', 'required' => true, 'max' => 300, 'placeholder' => 'เช่น /cctv/cam01/'],
        'sort_order' => ['label' => 'ลำดับการแสดง', 'type' => 'number', 'min' => 0, 'max' => 9999],
        'is_active'  => ['label' => 'สถานะ', 'type' => 'select', 'required' => true, 'options' => ['1' => '✅ เปิดใช้งาน', '0' => '⏸️ ปิดใช้งาน']],
    ];
}

/**
 * The page URL an admin opens to watch a camera: a path on this site ("/cctv/cam01/") or a full
 * https:// URL. Anything else — http, rtsp, javascript:, "//host", "..", user:password@ — is rejected.
 */
function camera_view_url_valid(string $url): bool
{
    if ($url === '' || strlen($url) > 300 || preg_match('/[\s"\'<>\\\\]/', $url)) {
        return false;
    }
    if ($url[0] === '/') { // same-site path
        return !str_starts_with($url, '//') && !preg_match('#(^|/)\.\.(/|$)#', $url)
            && preg_match('#^/[A-Za-z0-9._~\-/]*(\?[A-Za-z0-9._~\-=&%]*)?$#', $url) === 1;
    }
    $parts = parse_url($url);
    return $parts !== false && filter_var($url, FILTER_VALIDATE_URL) !== false && strtolower($parts['scheme'] ?? '') === 'https'
        && ($parts['host'] ?? '') !== '' && !isset($parts['user']) && !isset($parts['pass']);
}

function can_manage_cameras(array $user): bool
{
    return has_feature('cctv') && can_write_table($user, 'cameras');
}

function camera_counts(array $cameras): array
{
    $counts = ['total' => count($cameras), 'online' => 0, 'offline' => 0, 'maintenance' => 0];
    foreach ($cameras as $c) {
        $counts[$c['status']] = ($counts[$c['status']] ?? 0) + 1;
    }
    return $counts;
}

/**
 * Browser-safe description of a camera (for data attributes / JSON).
 * Contains NO stream_url and NO rtsp_url — playback info comes from the API only.
 */
function camera_public(array $camera): array
{
    $status = CAMERA_STATUSES[$camera['status']] ?? CAMERA_STATUSES['offline'];
    $rooms = array_filter(array_map(fn ($id) => find_row('classrooms', $id)['name'] ?? null, camera_classroom_ids($camera['id'])));
    return [
        'id'          => tok('cameras', $camera['id']),
        'code'        => $camera['code'],
        'name'        => $camera['name'],
        'location'    => $camera['location'],
        'description' => $camera['description'] ?? '',
        'model'       => trim(($camera['vendor'] ?? '') . ' ' . ($camera['camera_model'] ?? '')),
        'status'      => $camera['status'],
        'statusLabel' => $status['emoji'] . ' ' . $status['label'],
        'streamType'  => $camera['stream_type'],
        'streamLabel' => STREAM_TYPES[$camera['stream_type']] ?? $camera['stream_type'],
        'area'        => $rooms ? 'ห้อง' . implode(', ', $rooms) : 'พื้นที่ส่วนกลาง',
        'school'      => find_row('schools', $camera['school_id'])['name'] ?? '',
        'active'      => !empty($camera['is_active']),
        'scene'       => $camera['scene'] ?? 'classroom',
    ];
}

/** Admin form record: editable config, RTSP replaced by a "configured" flag (write-only secret). */
function camera_admin_record(array $camera): array
{
    $record = $camera;
    unset($record['rtsp_url']);
    $record['rtsp_url'] = '';
    $record['rtsp_configured'] = !empty($camera['rtsp_url']);
    $record['is_active'] = !empty($camera['is_active']) ? '1' : '0';
    $rooms = camera_classroom_ids($camera['id']);
    $record['area_type'] = $rooms ? 'classroom' : 'common';
    $record['classroom_ids'] = array_map(fn ($id) => tok('classrooms', $id), $rooms); // tokens, like every id sent to the browser
    $record['allow_teacher'] = camera_policy_allows($camera, 'teacher', $camera['classroom_id']) ? '1' : '0';
    $record['allow_parent'] = camera_policy_allows($camera, 'parent', $camera['classroom_id']) ? '1' : '0';
    return $record;
}

/* ---------------------------------------------------------------------------
 * Media tokens + MediaMTX HTTP authentication (MediaMTX v1.21.2, authMethod: http)
 *
 * MediaMTX POSTs {user, password, token, ip, action, path, protocol, id, query, userAgent} to
 * /api/media-auth once per reader session (HLS: when the session starts on index.m3u8, later
 * playlists / segments ride on MediaMTX's own session secret; WebRTC: on the WHEP POST).
 * Any 2xx = allowed, anything else = denied. Where our token arrives:
 *   hls    → URL query "?token=…" → raw "query" field (authMethod http does NOT copy it to "token")
 *   webrtc → "Authorization: Bearer …" → "token" field (a token containing ":" would be read as
 *            user:pass, so tokens use base64url + "." only)
 * Token = base64url(JSON {v, c: camera id, u: user id, p: media path, t: stream type, e: expiry})
 *       . "." . base64url(HMAC-SHA256), key derived from MEDIA_TOKEN_SECRET — never APP_KEY / CSRF.
 * No secret (or a weak / reused one) → no tokens and every media-auth request is denied (fail closed).
 * ------------------------------------------------------------------------ */

/** Signing key from MEDIA_TOKEN_SECRET, or null (= media streaming disabled). */
function media_token_key(): ?string
{
    $secret = (string) mk_env('MEDIA_TOKEN_SECRET', '');
    if (strlen($secret) < 32 || hash_equals((string) mk_env('APP_KEY', ''), $secret)) {
        return null;
    }
    return hash_hkdf('sha256', $secret, 32, 'mykid-media-token-v1');
}

/** Teachers / parents get real streams only after MediaMTX auth is verified (CCTV_VIEWERS_ENABLED=1). */
function media_viewers_enabled(): bool
{
    return mk_env('CCTV_VIEWERS_ENABLED', '0') === '1';
}

/** First path segment of Mykid's own CCTV proxy (Apache: /cctv/cam01/ → MediaMTX /cam01/). */
const CCTV_PROXY_PREFIX = 'cctv';

/**
 * MediaMTX path of a camera from its stream URL — the "path" MediaMTX reports to /api/media-auth:
 *   https://media-host/cam01/index.m3u8 · …/cam01/whep            → "cam01"   (MediaMTX directly)
 *   https://mykid-host/cctv/cam01/index.m3u8 · /cctv/cam01/whep   → "cam01"   (through the /cctv/ proxy,
 *                                                                    which MediaMTX never sees)
 * A MediaMTX path that itself starts with "cctv/" is therefore not supported.
 */
function camera_media_path(array $camera): ?string
{
    $path = trim((string) parse_url((string) ($camera['stream_url'] ?? ''), PHP_URL_PATH), '/');
    $path = match ($camera['stream_type'] ?? '') {
        'hls'    => preg_replace('#/[^/]+\.m3u8$#', '', $path),
        'webrtc' => preg_replace('#/whep$#', '', $path),
        default  => '',
    };
    if ($path === CCTV_PROXY_PREFIX) {
        return null; // "/cctv/index.m3u8": the proxy prefix alone names no camera
    }
    if (str_starts_with((string) $path, CCTV_PROXY_PREFIX . '/')) {
        $path = substr($path, strlen(CCTV_PROXY_PREFIX) + 1);
    }
    $valid = preg_match('#^[A-Za-z0-9._~-]+(/[A-Za-z0-9._~-]+)*$#', (string) $path) === 1
        && !preg_match('#(^|/)\.{1,2}(/|$)#', (string) $path); // no "." / ".." segments
    return $valid ? $path : null;
}

function media_b64(string $raw): string
{
    return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
}

function media_token_issue(array $camera, array $user, string $path, string $type, ?int $expires = null): ?string
{
    $key = media_token_key();
    if ($key === null) {
        return null;
    }
    $body = media_b64(json_encode(['v' => 1, 'c' => $camera['id'], 'u' => $user['id'], 'p' => $path, 't' => $type, 'e' => $expires ?? time() + STREAM_TOKEN_TTL]));
    return $body . '.' . media_b64(hash_hmac('sha256', $body, $key, true));
}

/** Verified token claims, or null (bad format, bad signature, expired, missing secret). */
function media_token_verify(string $token): ?array
{
    $key = media_token_key();
    if ($key === null || strlen($token) > 1024 || !preg_match('/^([A-Za-z0-9_-]+)\.([A-Za-z0-9_-]{43})$/', $token, $m)) {
        return null;
    }
    if (!hash_equals(media_b64(hash_hmac('sha256', $m[1], $key, true)), $m[2])) {
        return null;
    }
    $claims = json_decode((string) base64_decode(strtr($m[1], '-_', '+/'), true), true);
    if (!is_array($claims) || ($claims['v'] ?? null) !== 1 || !is_int($claims['c'] ?? null) || !is_int($claims['u'] ?? null)
        || !is_string($claims['p'] ?? null) || !is_string($claims['t'] ?? null) || !is_int($claims['e'] ?? null)) {
        return null;
    }
    $now = time();
    return $claims['e'] >= $now && $claims['e'] <= $now + STREAM_TOKEN_TTL + 60 ? $claims : null;
}

/**
 * Decide one MediaMTX auth request. Returns [allowed, reason, context] — reason / context are for
 * the log only (never the token or the query). Every check must pass; anything unexpected = deny.
 */
function media_auth_decide(array $req): array
{
    $protocol = (string) ($req['protocol'] ?? '');
    $path = (string) ($req['path'] ?? '');
    $ctx = ['action' => (string) ($req['action'] ?? ''), 'protocol' => $protocol, 'path' => $path, 'ip' => (string) ($req['ip'] ?? '')];
    if (media_token_key() === null) {
        return [false, 'MEDIA_TOKEN_SECRET not configured', $ctx];
    }
    if (($req['action'] ?? '') !== 'read') {
        return [false, 'action not allowed', $ctx];
    }
    if (!in_array($protocol, ['hls', 'webrtc'], true)) {
        return [false, 'protocol not allowed', $ctx];
    }
    $token = is_string($req['token'] ?? null) ? $req['token'] : '';
    if ($token === '' && is_string($req['query'] ?? null)) { // HLS: ?token=… only arrives in the raw query
        parse_str($req['query'], $query);
        $token = is_string($query['token'] ?? null) ? $query['token'] : '';
    }
    $claims = $token === '' ? null : media_token_verify($token);
    if ($claims === null) {
        return [false, $token === '' ? 'no token' : 'invalid or expired token', $ctx];
    }
    $ctx += ['camera' => $claims['c'], 'user' => $claims['u']];
    if ($claims['t'] !== $protocol) {
        return [false, 'token issued for another protocol', $ctx];
    }
    $camera = find_row('cameras', $claims['c']);
    if ($camera === null) {
        return [false, 'camera not found', $ctx];
    }
    $current = camera_media_path($camera);
    if ($current === null || $claims['p'] !== $current || $path !== $current || ($camera['stream_type'] ?? '') !== $protocol) {
        return [false, 'path / camera mismatch', $ctx];
    }
    if (empty($camera['is_active']) || ($camera['status'] ?? '') !== 'online') {
        return [false, 'camera inactive or not online', $ctx];
    }
    $account = find_row('users', $claims['u']);
    if ($account === null) {
        return [false, 'user not found', $ctx];
    }
    $user = ['id' => $account['id'], 'role' => $account['role'], 'school_id' => $account['school_id'],
        'classroom_id' => $account['classroom_id'], 'student_id' => $account['student_id']];
    if (in_array($user['role'], ['teacher', 'parent'], true) && !media_viewers_enabled()) {
        return [false, 'teacher / parent streaming not enabled', $ctx];
    }
    if (!can_view_camera($camera, $user)) { // school + classroom + permission, re-checked now
        return [false, 'no permission for this camera', $ctx];
    }
    return [true, 'ok', $ctx];
}

/**
 * Playback descriptor for an authorised viewer: {type, url, token, expiresAt}. type "mock" = no
 * stream URL yet; "unavailable" (+ notice) = streaming switched off / not configured — fail closed,
 * no URL is handed out. MediaMTX re-checks the token through /api/media-auth.
 */
function camera_stream_descriptor(array $camera, array $user): array
{
    $type = (string) ($camera['stream_type'] ?? '');
    if (($camera['stream_url'] ?? '') === '' || !in_array($type, ['hls', 'webrtc'], true)) {
        return ['type' => 'mock', 'url' => '', 'token' => '', 'expiresAt' => null]; // "not connected" placeholder
    }
    $off = fn (string $notice) => ['type' => 'unavailable', 'url' => '', 'token' => '', 'expiresAt' => null, 'notice' => $notice];
    if (in_array($user['role'], ['teacher', 'parent'], true) && !media_viewers_enabled()) {
        return $off('ระบบดูกล้องสำหรับครูและผู้ปกครองกำลังเตรียมความพร้อม');
    }
    $path = camera_media_path($camera);
    $expires = time() + STREAM_TOKEN_TTL;
    $token = $path === null ? null : media_token_issue($camera, $user, $path, $type, $expires);
    if ($token === null) {
        mk_log('CCTV', 'stream unavailable', ['camera' => $camera['code'], 'reason' => $path === null ? 'stream URL has no media path' : 'MEDIA_TOKEN_SECRET not configured']);
        return $off('ระบบกล้องยังไม่พร้อมใช้งาน กรุณาติดต่อผู้ดูแลโรงเรียน');
    }
    $url = (string) $camera['stream_url'];
    if ($type === 'hls') {
        $url .= (str_contains($url, '?') ? '&' : '?') . 'token=' . $token;
    }
    return ['type' => $type, 'url' => $url, 'token' => $token, 'expiresAt' => date('c', $expires)];
}

/**
 * Rebuild the camera's permission rows from the admin form flags. One row per role with
 * classroom_id NULL = "this role, in the classrooms the camera covers" (camera_classrooms decides
 * which rooms; a permission row never widens the camera to other rooms).
 */
function camera_permission_rows(array $camera, bool $allowTeacher, bool $allowParent, int &$nextId): array
{
    $rows = [];
    foreach (['teacher' => $allowTeacher, 'parent' => $allowParent] as $role => $allowed) {
        if ($allowed) {
            $rows[] = ['id' => $nextId++, 'school_id' => $camera['school_id'], 'camera_id' => $camera['id'],
                'role' => $role, 'classroom_id' => null, 'can_view' => true];
        }
    }
    return $rows;
}
