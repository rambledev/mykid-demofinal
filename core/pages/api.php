<?php
/**
 * Package JSON endpoint (A / B) used by core/assets/js/core.js.
 *
 * Request:  POST JSON { action, ... } + header X-CSRF-Token
 * Response: { ok, message, version }   (the page then refreshes its [data-live] regions)
 *
 * Every write = ROLE check (permission matrix) + SCOPE check (permissions.php) against
 * the session user. Scope ids in the request are only accepted when they sit inside the
 * user's scope; otherwise the request is rejected with 403.
 */
declare(strict_types=1);

// JSON body, or multipart/form-data when images are attached (fields: action, data=JSON, images[]).
if (isset($_POST['action'])) {
    $input = $_POST;
    $input['data'] = json_decode((string) ($_POST['data'] ?? '{}'), true) ?: [];
} else {
    $input = json_decode((string) file_get_contents('php://input'), true) ?: [];
}
$action = (string) ($input['action'] ?? '');
mk_log('Api', 'request START', ['action' => $action, 'input' => $input]);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'message' => 'ไม่สามารถดำเนินการได้'], 405);
}
$user = current_user();
if (!$user) {
    json_response(['ok' => false, 'message' => 'กรุณาเข้าสู่ระบบอีกครั้ง', 'redirect' => url('login.php')], 401);
}
if (!verify_csrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
    json_response(['ok' => false, 'message' => 'กรุณารีเฟรชหน้าแล้วลองใหม่อีกครั้ง'], 419);
}

/** Validate submitted values against the table schema. Partial updates only check what is sent. */
function validate_fields(string $table, array $data, bool $isNew): array
{
    $clean = [];
    foreach (table_def($table)['fields'] as $key => $field) {
        if ($field['type'] === 'image') {
            continue; // files are handled by core/media.php
        }
        $present = array_key_exists($key, $data);
        $value = $present ? $data[$key] : null;
        $empty = $value === null || (is_string($value) && trim($value) === '');

        if ($empty) {
            if (!empty($field['required']) && ($isNew || $present)) {
                throw new DemoValidationException('กรุณากรอก “' . $field['label'] . '”');
            }
            if ($present) {
                $clean[$key] = in_array($field['type'], ['classroom', 'student', 'school', 'number'], true) ? null : '';
            }
            continue;
        }

        $value = is_string($value) ? trim($value) : $value;
        $clean[$key] = match ($field['type']) {
            'classroom', 'student', 'school' => (int) $value,
            'number' => (function () use ($value, $field) {
                if (!is_numeric($value) || $value < ($field['min'] ?? PHP_INT_MIN) || $value > ($field['max'] ?? PHP_INT_MAX)) {
                    throw new DemoValidationException('“' . $field['label'] . '” ต้องอยู่ระหว่าง ' . $field['min'] . '–' . $field['max']);
                }
                return str_contains((string) ($field['step'] ?? '1'), '.') ? round((float) $value, 1) : (int) $value;
            })(),
            'time' => preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', (string) $value) ? $value : throw new DemoValidationException('กรุณาระบุเวลาให้ถูกต้อง'),
            'date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $value) ? $value : throw new DemoValidationException('กรุณาระบุวันที่ให้ถูกต้อง'),
            'phone' => preg_match('/^0\d{9}$/', normalize_phone((string) $value)) ? normalize_phone((string) $value) : throw new DemoValidationException('กรุณากรอกเบอร์โทรศัพท์ 10 หลัก'),
            'pin' => preg_match('/^\d{6}$/', (string) $value) ? (string) $value : throw new DemoValidationException('PIN ต้องเป็นตัวเลข 6 หลัก'),
            'url' => preg_match('#^https?://[^\s@]+$#i', (string) $value) ? mb_substr((string) $value, 0, 300) : throw new DemoValidationException('“' . $field['label'] . '” ต้องเป็น URL แบบ https:// และห้ามมีรหัสผ่าน'),
            'secret' => mb_substr((string) $value, 0, 300),
            'select' => array_key_exists((string) $value, field_options($field)) ? (string) $value : throw new DemoValidationException('กรุณาเลือก “' . $field['label'] . '” จากรายการ'),
            'emoji' => in_array($value, mk_catalog()[$field['options']], true) ? $value : '⭐',
            default => mb_substr((string) $value, 0, (int) ($field['max'] ?? 200)),
        };
    }
    return $clean;
}

/** Fields the server fills in on insert (never taken from the request). */
function insert_defaults(string $table, array $row, array $user): array
{
    $now = date('Y-m-d H:i:s');
    return match ($table) {
        'students' => $row + [
            'code' => 'MK' . random_int(200, 999), 'classroom' => find_row('classrooms', $row['classroom_id'])['name'] ?? '',
            'parentRelation' => str_starts_with($row['parentName'] ?? '', 'นาย') ? 'พ่อ' : 'แม่',
            'avatar' => ['skin' => '#FFDBC0', 'hair' => '#3B2A20', 'bg' => '#BFE7FF', 'shirt' => '#7CC6F2',
                         'style' => ($row['gender'] ?? 'm') === 'f' ? 'pigtails' : 'short'],
        ],
        'teachers'   => $row + ['position' => 'ครูประจำชั้น', 'emoji' => '👩‍🏫'],
        'classrooms' => $row + ['emoji' => '🐣', 'color' => '#FFF4CC', 'capacity' => 20],
        'schools'    => $row + ['emoji' => '🏫', 'color' => '#FFF4CC', 'status' => 'active'],
        'users'      => $row + ['classroom_id' => null, 'student_id' => null, 'teacher_id' => null],
        'stars'      => $row + ['date' => today(), 'by' => $user['teacher_id']],
        'portfolio'  => ['date' => ($row['date'] ?? '') !== '' ? $row['date'] : today()] + $row + ['art' => random_int(0, 5)],
        'photos'     => $row + ['date' => today(), 'art' => random_int(0, 5), 'emoji' => '📷'],
        'notifications' => $row + ['at' => $now, 'student_id' => null],
        'studentStatuses' => $row + ['date' => today(), 'at' => $now, 'by' => $user['name'], 'by_user' => $user['id']],
        'attendance', 'healthRecords', 'sleepRecords', 'activities' => $row + ['date' => today()],
        'cameras' => $row + [
            'code' => sprintf('CAM-%02d', count(where(store_rows('cameras'), 'school_id', $row['school_id'])) + 1),
            'scene' => $row['classroom_id'] ? 'classroom' : 'playground', 'vendor' => '', 'camera_model' => 'IP Camera',
            'stream_url' => '', 'rtsp_url' => '', 'description' => '',
        ],
        default => $row,
    };
}

/**
 * CCTV config rules: RTSP is write-only and must not carry credentials (they belong in the
 * media server), HLS must point at a playlist. Returns [cleanData, permissionFlags|null].
 */
function prepare_camera_data(array $data): array
{
    $flags = array_key_exists('allow_teacher', $data) || array_key_exists('allow_parent', $data)
        ? [($data['allow_teacher'] ?? '0') === '1', ($data['allow_parent'] ?? '0') === '1'] : null;
    unset($data['allow_teacher'], $data['allow_parent']);

    if (array_key_exists('rtsp_url', $data)) {
        if ($data['rtsp_url'] === '') {
            unset($data['rtsp_url']); // empty = keep the stored value (never echoed back to the browser)
        } elseif (!preg_match('#^rtsps?://[^\s]+$#i', $data['rtsp_url'])) {
            throw new DemoValidationException('RTSP URL ต้องขึ้นต้นด้วย rtsp://');
        } elseif (preg_match('#^rtsps?://[^/]*@#i', $data['rtsp_url'])) { // user:pass@host
            throw new DemoValidationException('ห้ามใส่ Username/Password ใน RTSP URL — ให้ตั้งค่า Credential ที่ Media Server');
        }
    }
    if (isset($data['is_active'])) {
        $data['is_active'] = $data['is_active'] === '1';
    }
    if (($data['stream_type'] ?? '') === 'hls' && !empty($data['stream_url']) && !str_contains($data['stream_url'], '.m3u8')) {
        throw new DemoValidationException('HLS Stream URL ต้องเป็นไฟล์ .m3u8 จาก Media Server');
    }
    return [$data, $flags];
}

/** Replace a camera's cameraPermissions rows (admin form flags). */
function sync_camera_permissions(int $cameraId, bool $allowTeacher, bool $allowParent): void
{
    store_mutate(function (array &$state) use ($cameraId, $allowTeacher, $allowParent) {
        $index = store_find($state, 'cameras', $cameraId);
        $camera = $state['tables']['cameras'][$index];
        $state['tables']['cameraPermissions'] = array_values(array_filter($state['tables']['cameraPermissions'], fn ($p) => $p['camera_id'] !== $cameraId));
        array_push($state['tables']['cameraPermissions'], ...camera_permission_rows($camera, $allowTeacher, $allowParent, $state['nextId']));
    });
    mk_log('CCTV', 'permissions synced', compact('cameraId', 'allowTeacher', 'allowParent'));
}

try {
    $message = '';
    switch ($action) {
        case 'version':
            // also the unread in-app notification count (red badge) — polled by every page
            json_response(['ok' => true, 'version' => store_state()['version'], 'unread' => notifications_unread_count($user)]);

        case 'save':
            $table = (string) ($input['table'] ?? '');
            table_def($table);
            authorize(can_write_table($user, $table), 'บัญชีนี้ไม่มีสิทธิ์แก้ไขข้อมูลนี้', ['user' => $user['id'], 'role' => $user['role'], 'table' => $table]);
            authorize($table !== 'pickups', 'กรุณาใช้เมนูรับ-ส่ง', ['table' => $table]); // status flow: pickup_* actions only
            $id = (int) ($input['id'] ?? 0);
            $data = validate_fields($table, (array) ($input['data'] ?? []), $id === 0);
            if ($id === 0) {
                // New rows always carry every schema column (optional ones empty), so views never miss a key.
                foreach (table_def($table)['fields'] as $key => $field) {
                    if ($field['type'] !== 'image') {
                        $data += [$key => in_array($field['type'], ['classroom', 'student', 'school', 'number'], true) ? null : ''];
                    }
                }
            }
            if ($table === 'portfolio' && ($pending = uploaded_files())) {
                if (count($pending) > MEDIA_MAX_FILES) {
                    throw new DemoValidationException('อัปโหลดได้ครั้งละไม่เกิน ' . MEDIA_MAX_FILES . ' รูป');
                }
                array_map('validate_image_upload', $pending); // reject bad files BEFORE saving the work
            }
            $cameraFlags = null;
            if ($table === 'cameras') {
                [$data, $cameraFlags] = prepare_camera_data($data);
            }

            if ($table === 'users') {
                if ($id) {
                    $target = find_row('users', $id);
                    authorize(in_array($target['role'] ?? '', ['admin', 'executive'], true), 'แก้ไขได้เฉพาะบัญชีผู้ดูแลโรงเรียนและผู้บริหาร', ['id' => $id]);
                }
                foreach (store_rows('users') as $u) {
                    if ($u['phone'] === ($data['phone'] ?? null) && $u['id'] !== $id) {
                        throw new DemoValidationException('เบอร์โทรศัพท์นี้มีผู้ใช้งานแล้ว');
                    }
                }
            }

            if ($id) {
                $existing = find_row($table, $id);
                authorize($existing !== null && row_in_scope($user, $table, $existing), 'ไม่มีสิทธิ์แก้ไขข้อมูลนอกขอบเขตของบัญชีนี้', ['table' => $table, 'id' => $id]);
                $row = apply_write_scope($user, $table, array_merge($existing, $data));
                authorize(row_in_scope($user, $table, $row), 'ไม่มีสิทธิ์ย้ายข้อมูลออกนอกขอบเขตของบัญชีนี้', ['table' => $table, 'id' => $id]);
                if ($table === 'students' && isset($row['classroom_id'])) {
                    $row['classroom'] = find_row('classrooms', $row['classroom_id'])['name'] ?? '';
                }
                unset($row['id']);
                store_update($table, $id, $row, fn ($old) => authorize(row_in_scope($user, $table, $old), 'ไม่มีสิทธิ์แก้ไขข้อมูลนี้'));
                if ($cameraFlags) {
                    sync_camera_permissions($id, ...$cameraFlags);
                }
                $savedId = $id;
                $message = 'บันทึกการแก้ไขเรียบร้อยแล้ว';
            } else {
                $row = insert_defaults($table, apply_write_scope($user, $table, $data), $user);
                authorize(row_in_scope($user, $table, $row), 'ไม่มีสิทธิ์เพิ่มข้อมูลนอกขอบเขตของบัญชีนี้', ['table' => $table]);
                $newId = store_insert($table, $row);
                if ($cameraFlags) {
                    sync_camera_permissions($newId, ...$cameraFlags);
                }
                $savedId = $newId;
                $message = 'เพิ่ม' . table_def($table)['label'] . 'เรียบร้อยแล้ว';
            }
            if ($table === 'portfolio' && ($files = uploaded_files())) {
                // Portfolio images: the work was just saved inside the user's scope (apply_write_scope).
                authorize(has_feature('media') && can_write_table($user, 'mediaFiles'), 'บัญชีนี้ไม่มีสิทธิ์อัปโหลดรูปภาพ', ['role' => $user['role']]);
                attach_uploaded_images('portfolio', find_row('portfolio', $savedId), $user, $files);
                $message .= ' พร้อมรูปภาพ ' . count($files) . ' รูป';
            }
            break;

        case 'upload_images':
            // Activity / food images. Owner row + scope are re-checked on the server.
            $ownerType = (string) ($input['owner_type'] ?? '');
            $ownerTable = MEDIA_OWNERS[$ownerType] ?? null;
            authorize(has_feature('media') && $ownerTable !== null && $ownerType !== 'portfolio', 'ไม่สามารถอัปโหลดรูปภาพนี้ได้', ['owner' => $ownerType]);
            $owner = find_row($ownerTable, (int) ($input['owner_id'] ?? 0));
            authorize($owner !== null && can_write_table($user, $ownerTable) && can_write_table($user, 'mediaFiles') && row_in_scope($user, $ownerTable, $owner),
                'ไม่มีสิทธิ์อัปโหลดรูปภาพให้รายการนี้', ['user' => $user['id'], 'owner' => $ownerType, 'owner_id' => $input['owner_id'] ?? null]);
            $files = uploaded_files();
            if (!$files) {
                throw new DemoValidationException('กรุณาเลือกรูปภาพอย่างน้อย 1 รูป');
            }
            $meal = $ownerType === 'food' && ($input['meal'] ?? '') !== '' ? (string) $input['meal'] : null;
            if ($meal !== null && !cat_find('meals', $meal)) {
                throw new DemoValidationException('ไม่พบมื้ออาหารนี้');
            }
            $ids = attach_uploaded_images($ownerType, $owner, $user, $files, $meal);
            $message = 'อัปโหลดรูปภาพ ' . count($ids) . ' รูปเรียบร้อยแล้ว';
            break;

        case 'cleanup_media':
            // Admin only: delete expired PHYSICAL files in the own school; metadata is kept.
            authorize($user['role'] === 'admin' && can_write_table($user, 'mediaFiles'), 'เฉพาะผู้ดูแลโรงเรียนเท่านั้น', ['role' => $user['role']]);
            $done = cleanup_expired_media($user);
            $message = $done['files'] ? 'ล้างไฟล์ภาพที่หมดอายุ ' . number_format($done['files']) . ' ไฟล์ (' . human_bytes($done['bytes']) . ') เรียบร้อยแล้ว' : 'ไม่มีไฟล์ภาพที่หมดอายุ';
            break;

        case 'delete':
            $table = (string) ($input['table'] ?? '');
            $id = (int) ($input['id'] ?? 0);
            table_def($table);
            authorize(can_write_table($user, $table), 'บัญชีนี้ไม่มีสิทธิ์ลบข้อมูลนี้', ['table' => $table, 'id' => $id]);
            authorize($table !== 'pickups', 'กรุณาใช้เมนูรับ-ส่ง', ['table' => $table]);
            $existing = find_row($table, $id);
            authorize($existing !== null && row_in_scope($user, $table, $existing), 'ไม่มีสิทธิ์ลบข้อมูลนอกขอบเขตของบัญชีนี้', ['table' => $table, 'id' => $id]);
            if ($table === 'classrooms' && where(store_rows('students'), 'classroom_id', $id)) {
                throw new DemoValidationException('ห้องนี้ยังมีนักเรียนอยู่ กรุณาย้ายนักเรียนออกก่อน');
            }
            if ($table === 'schools' && where(store_rows('students'), 'school_id', $id)) {
                throw new DemoValidationException('โรงเรียนนี้ยังมีข้อมูลนักเรียนอยู่ จึงยังลบไม่ได้');
            }
            if ($table === 'users' && $id === $user['id']) {
                throw new DemoValidationException('ไม่สามารถลบบัญชีที่กำลังใช้งานอยู่');
            }
            store_delete($table, $id, fn ($old) => authorize(row_in_scope($user, $table, $old), 'ไม่มีสิทธิ์ลบข้อมูลนี้'));
            if ($table === 'cameras') {
                store_mutate(function (array &$state) use ($id) {
                    $state['tables']['cameraPermissions'] = array_values(array_filter($state['tables']['cameraPermissions'], fn ($p) => $p['camera_id'] !== $id));
                });
            }
            $message = 'ลบข้อมูลเรียบร้อยแล้ว';
            break;

        case 'camera_stream':
            // Playback descriptor for ONE camera — re-checks role + school + classroom + camera permission.
            $camera = authorized_camera((int) ($input['camera_id'] ?? 0), $user);
            if (empty($camera['is_active'])) {
                throw new DemoValidationException('กล้องนี้ถูกปิดใช้งานอยู่');
            }
            $online = $camera['status'] === 'online';
            mk_log('CCTV', 'stream requested', ['user' => $user['id'], 'role' => $user['role'], 'camera' => $camera['code'], 'school' => $camera['school_id'], 'online' => $online]);
            json_response(['ok' => true, 'camera' => camera_public($camera), 'stream' => $online ? camera_stream_descriptor($camera, $user) : null]);

        /* ---- รับ-ส่ง (core/pickup.php): get status · create request · update status ---- */
        case 'pickup_status':
            authorize(has_feature('pickup') && can_read_table($user, 'pickups'), 'บัญชีนี้ไม่มีสิทธิ์ดูข้อมูลรับ-ส่ง', ['role' => $user['role']]);
            $rows = pickup_today_rows();
            mk_log('Pickup', 'pickup_status', ['user' => $user['id'], 'rows' => count($rows)]);
            json_response(['ok' => true, 'signature' => pickup_signature($rows), 'rows' => array_map('pickup_public', $rows),
                'version' => store_state()['version'], 'unread' => notifications_unread_count($user)]);

        case 'pickup_create':
            authorize(has_feature('pickup'), 'ไม่สามารถดำเนินการได้');
            $row = pickup_create($user, (int) ($input['student_id'] ?? 0));
            $message = 'แจ้งครูแล้ว ครูจะเตรียม' . (find_row('students', $row['student_id'])['nickname'] ?? 'นักเรียน') . 'กลับบ้าน';
            break;

        case 'pickup_update':
            authorize(has_feature('pickup'), 'ไม่สามารถดำเนินการได้');
            $row = pickup_advance($user, (int) ($input['id'] ?? 0), (string) ($input['status'] ?? ''));
            $message = pickup_status_meta($row['status'])['teacher'];
            break;

        /* ---- In-app notifications (core/notifications.php) — own rows only ---- */
        case 'notification_read':
            authorize(has_feature('inAppNotifications'), 'ไม่สามารถดำเนินการได้');
            $row = notification_mark_read($user, (int) ($input['id'] ?? 0));
            $link = notification_link($user, $row);
            // link is relative to the package root (where api.php lives) — the browser resolves it against apiUrl
            json_response(['ok' => true, 'unread' => notifications_unread_count($user), 'link' => $link, 'version' => store_state()['version']]);

        case 'notification_read_all':
            authorize(has_feature('inAppNotifications'), 'ไม่สามารถดำเนินการได้');
            $marked = notification_mark_all_read($user);
            $message = $marked ? 'ทำเครื่องหมายอ่านแล้ว ' . $marked . ' รายการ' : 'ไม่มีการแจ้งเตือนที่ยังไม่อ่าน';
            break;

        case 'set_status':
            authorize(can_write_table($user, 'statuses'), 'บัญชีนี้ไม่มีสิทธิ์เปลี่ยนสถานะห้องเรียน', ['role' => $user['role']]);
            $data = validate_fields('statuses', ['code' => $input['code'] ?? '', 'note' => $input['note'] ?? ''], true);
            $row = apply_write_scope($user, 'statuses', $data + ['classroom_id' => $input['classroom_id'] ?? null]);
            store_mutate(function (array &$state) use ($row, $user) {
                array_unshift($state['tables']['statuses'], ['id' => $state['nextId']++] + $row + ['at' => date('Y-m-d H:i:s'), 'by' => $user['teacher_id']]);
            });
            $message = 'อัปเดตสถานะเรียบร้อยแล้ว';
            break;

        case 'save_meal':
            authorize(can_write_table($user, 'foodMenus'), 'บัญชีนี้ไม่มีสิทธิ์แก้ไขเมนูอาหาร', ['role' => $user['role']]);
            $meal = (string) ($input['meal'] ?? '');
            if (!cat_find('meals', $meal)) {
                throw new DemoValidationException('ไม่พบมื้ออาหารนี้');
            }
            $rawItems = $input['items'] ?? [];
            if (is_string($rawItems)) { // multipart (photos attached): items arrive as a JSON string
                $rawItems = json_decode($rawItems, true) ?: [];
            }
            $items = array_values(array_filter(array_map(fn ($i) => mb_substr(trim((string) $i), 0, 60), (array) $rawItems), fn ($i) => $i !== ''));
            if (!$items) {
                throw new DemoValidationException('กรุณากรอกรายการอาหารอย่างน้อย 1 รายการ');
            }
            // Food photos taken / picked in the meal form: check permission + every file BEFORE saving the menu.
            $mealPhotos = uploaded_files();
            if ($mealPhotos) {
                authorize(has_feature('media') && can_write_table($user, 'mediaFiles'), 'บัญชีนี้ไม่มีสิทธิ์อัปโหลดรูปภาพ', ['role' => $user['role']]);
                if (count($mealPhotos) > MEDIA_MAX_FILES) {
                    throw new DemoValidationException('อัปโหลดได้ครั้งละไม่เกิน ' . MEDIA_MAX_FILES . ' รูป');
                }
                array_map('validate_image_upload', $mealPhotos);
            }
            $scope = apply_write_scope($user, 'foodMenus', ['classroom_id' => $input['classroom_id'] ?? null]);
            store_mutate(function (array &$state) use ($scope, $meal, $items) {
                foreach ($state['tables']['foodMenus'] as &$menu) {
                    if ($menu['classroom_id'] === $scope['classroom_id'] && ($menu['date'] ?? '') === today()) {
                        $menu[$meal] = array_slice($items, 0, 6);
                        return;
                    }
                }
                unset($menu);
                $state['tables']['foodMenus'][] = ['id' => $state['nextId']++, 'date' => today()] + $scope + [$meal => array_slice($items, 0, 6)];
            });
            $message = 'บันทึกเมนูอาหารเรียบร้อยแล้ว';
            if ($mealPhotos) {
                $menu = null;
                foreach (store_rows('foodMenus') as $row) {
                    if ($row['classroom_id'] === $scope['classroom_id'] && ($row['date'] ?? '') === today()) {
                        $menu = $row;
                    }
                }
                authorize($menu !== null && row_in_scope($user, 'foodMenus', $menu), 'ไม่มีสิทธิ์อัปโหลดรูปภาพให้เมนูนี้', ['classroom_id' => $scope['classroom_id']]);
                attach_uploaded_images('food', $menu, $user, $mealPhotos, $meal);
                $message .= ' พร้อมรูปอาหาร ' . count($mealPhotos) . ' รูป';
            }
            break;

        case 'save_intake':
            authorize(can_write_table($user, 'foodIntake'), 'บัญชีนี้ไม่มีสิทธิ์บันทึกการรับประทานอาหาร', ['role' => $user['role']]);
            $id = (int) ($input['id'] ?? 0);
            $meal = (string) ($input['meal'] ?? '');
            $level = (string) ($input['level'] ?? '');
            if (!cat_find('meals', $meal) || !cat_find('intake', $level)) {
                throw new DemoValidationException('กรุณาเลือกระดับการรับประทานจากรายการ');
            }
            $existing = find_row('foodIntake', $id);
            authorize($existing !== null && row_in_scope($user, 'foodIntake', $existing), 'ไม่มีสิทธิ์บันทึกข้อมูลของเด็กคนนี้', ['id' => $id]);
            store_update('foodIntake', $id, ['levels' => [$meal => $level] + $existing['levels']], fn ($old) => authorize(row_in_scope($user, 'foodIntake', $old), 'ไม่มีสิทธิ์แก้ไขข้อมูลนี้'));
            $message = 'บันทึกการรับประทานอาหารแล้ว';
            break;

        case 'send_message':
            authorize(can_write_table($user, 'messages'), 'บัญชีนี้ไม่มีสิทธิ์ส่งข้อความ', ['role' => $user['role']]);
            $text = trim((string) ($input['text'] ?? ''));
            if ($text === '') {
                throw new DemoValidationException('กรุณาพิมพ์ข้อความ');
            }
            $row = apply_write_scope($user, 'messages', ['student_id' => (int) ($input['student_id'] ?? 0)]);
            $sender = $user['role'] === 'parent' ? 'คุณ' . ($user['relation'] ?? 'แม่') : $user['name'];
            store_insert('messages', $row + ['from' => $user['role'], 'sender' => $sender, 'text' => mb_substr($text, 0, 300), 'at' => date('Y-m-d H:i:s')]);
            $message = 'ส่งข้อความแล้ว';
            break;

        case 'reset_demo':
            authorize(in_array($user['role'], ['super_admin', 'admin', 'teacher'], true), 'บัญชีนี้ไม่มีสิทธิ์รีเซ็ตข้อมูล', ['role' => $user['role']]);
            store_reset_scope(function (string $table, array $row) use ($user) {
                if ($user['role'] === 'teacher' && !can_write_table($user, $table)) {
                    return false;
                }
                if ($user['role'] === 'admin' && in_array($table, ['users', 'schools'], true)) {
                    return false;
                }
                return row_in_scope($user, $table, $row);
            });
            $message = 'รีเซ็ตข้อมูล Demo ในขอบเขตของบัญชีนี้เรียบร้อยแล้ว';
            break;

        default:
            json_response(['ok' => false, 'message' => 'ไม่สามารถดำเนินการได้ กรุณาลองใหม่อีกครั้ง'], 400);
    }

    mk_log('Api', 'request END', ['action' => $action, 'user' => $user['id'], 'version' => store_state()['version']]);
    json_response(['ok' => true, 'message' => $message, 'version' => store_state()['version'], 'unread' => notifications_unread_count($user)]);
} catch (PermissionDeniedException $ex) {
    mk_log('Api', 'permission ERROR', ['action' => $action, 'user' => $user['id'], 'role' => $user['role'], 'error' => $ex->getMessage(), 'input' => $input]);
    json_response(['ok' => false, 'message' => $ex->getMessage()], 403);
} catch (DemoValidationException $ex) {
    mk_log('Api', 'validation ERROR', ['action' => $action, 'error' => $ex->getMessage(), 'input' => $input]);
    json_response(['ok' => false, 'message' => $ex->getMessage()], 422);
} catch (Throwable $ex) {
    mk_log('Api', 'unexpected ERROR', ['action' => $action, 'error' => $ex->getMessage(), 'file' => $ex->getFile() . ':' . $ex->getLine(), 'input' => $input]);
    json_response(['ok' => false, 'message' => 'ระบบกำลังดำเนินการ กรุณาลองใหม่อีกครั้ง'], 500);
}
