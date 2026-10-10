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
 *   super_admin        → every camera of every school
 *   admin / executive  → every camera of their own school (executive is read only)
 *   teacher / parent   → same school + active camera + (own classroom camera OR common area)
 *                        + an explicit row in cameraPermissions for their role & classroom
 */
declare(strict_types=1);

const STREAM_TYPES = [
    'mock'   => 'Mock (Demo)',
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

/** THE camera access rule — role + school + classroom/student scope + camera permission. */
function can_view_camera(array $camera, array $user): bool
{
    if (!has_feature('cctv')) {
        return false;
    }
    if ($user['role'] === 'super_admin') {
        return true;
    }
    // School isolation — scope comes from the session user, never the request.
    if (($camera['school_id'] ?? null) !== $user['school_id']) {
        return false;
    }
    if (in_array($user['role'], ['admin', 'executive'], true)) {
        return true;
    }
    if (empty($camera['is_active'])) {
        return false;
    }

    // Teacher: own classroom. Parent: the child's classroom (re-derived from student_id).
    $classroomId = $user['role'] === 'parent'
        ? (find_row('students', (int) $user['student_id'])['classroom_id'] ?? null)
        : $user['classroom_id'];

    $isOwnRoom = $camera['classroom_id'] !== null && $camera['classroom_id'] === $classroomId;
    $isCommon = $camera['classroom_id'] === null;
    if (!$isOwnRoom && !$isCommon) {
        return false; // another classroom's camera — never
    }
    return camera_policy_allows($camera, $user['role'], $classroomId);
}

/** Cameras the user may see, ordered by school then code. */
function accessible_cameras(?array $user = null): array
{
    $user ??= current_user();
    if (!$user) {
        return [];
    }
    $cameras = array_values(array_filter(store_rows('cameras'), fn ($c) => can_view_camera($c, $user)));
    usort($cameras, fn ($a, $b) => [$a['school_id'], $a['code']] <=> [$b['school_id'], $b['code']]);
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
    $room = $camera['classroom_id'] ? find_row('classrooms', $camera['classroom_id']) : null;
    return [
        'id'          => $camera['id'],
        'code'        => $camera['code'],
        'name'        => $camera['name'],
        'location'    => $camera['location'],
        'description' => $camera['description'] ?? '',
        'model'       => trim(($camera['vendor'] ?? '') . ' ' . ($camera['camera_model'] ?? '')),
        'status'      => $camera['status'],
        'statusLabel' => $status['emoji'] . ' ' . $status['label'],
        'streamType'  => $camera['stream_type'],
        'streamLabel' => STREAM_TYPES[$camera['stream_type']] ?? $camera['stream_type'],
        'area'        => $room ? 'ห้อง' . $room['name'] : 'พื้นที่ส่วนกลาง',
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
    $record['allow_teacher'] = camera_policy_allows($camera, 'teacher', $camera['classroom_id']) ? '1' : '0';
    $record['allow_parent'] = camera_policy_allows($camera, 'parent', $camera['classroom_id']) ? '1' : '0';
    return $record;
}

/**
 * Playback descriptor for an authorised viewer. In production the media server validates
 * `token` (e.g. MediaMTX external HTTP auth calling back to Mykid) before serving the stream.
 */
function camera_stream_descriptor(array $camera, array $user): array
{
    $expires = time() + STREAM_TOKEN_TTL;
    $token = hash_hmac('sha256', $camera['id'] . '|' . $user['id'] . '|' . $expires, csrf_token());
    $url = (string) ($camera['stream_url'] ?? '');
    $type = $url === '' ? 'mock' : $camera['stream_type']; // no URL yet → demo player, never an error

    if ($type === 'hls' && $url !== '') {
        $url .= (str_contains($url, '?') ? '&' : '?') . 'token=' . $token;
    }
    return ['type' => $type, 'url' => $type === 'mock' ? '' : $url, 'token' => $token, 'expiresAt' => date('c', $expires)];
}

/** Rebuild the camera's permission rows from the admin form flags. */
function camera_permission_rows(array $camera, bool $allowTeacher, bool $allowParent, int &$nextId): array
{
    $rows = [];
    foreach (['teacher' => $allowTeacher, 'parent' => $allowParent] as $role => $allowed) {
        if ($allowed) {
            $rows[] = ['id' => $nextId++, 'school_id' => $camera['school_id'], 'camera_id' => $camera['id'],
                'role' => $role, 'classroom_id' => $camera['classroom_id'], 'can_view' => true];
        }
    }
    return $rows;
}
