<?php
/**
 * รับ-ส่ง (student dismissal) — Package A.
 *
 *   Parent  : "กำลังไปรับลูก" (one tap, no time / ETA)   → pending
 *   Teacher : "เตรียมกลับบ้าน"                            → preparing
 *   Teacher : "ถึงจุดรับส่งแล้ว"                          → ready_for_pickup
 *   Teacher : "ส่งมอบนักเรียนแล้ว"                        → completed (+ completed_at / completed_by)
 *
 * One request per CHILD (siblings are never merged). Every step creates in-app notifications
 * (core/notifications.php): parent → teachers of the child's classroom, teacher → the child's parents.
 * Parents are authorised through parentStudents (several children), teachers through their classroom.
 * No GPS / location / time input of any kind.
 */
declare(strict_types=1);

/** Allowed transitions: current status → the only next status (teacher side). */
const PICKUP_FLOW = ['pending' => 'preparing', 'preparing' => 'ready_for_pickup', 'ready_for_pickup' => 'completed'];

/** Teacher button for moving a request out of its current status. */
const PICKUP_ACTIONS = [
    'pending'          => ['icon' => '🎒', 'label' => 'เตรียมกลับบ้าน'],
    'preparing'        => ['icon' => '📍', 'label' => 'ถึงจุดรับส่งแล้ว'],
    'ready_for_pickup' => ['icon' => '🤝', 'label' => 'ส่งมอบนักเรียนแล้ว'],
];

/** Column holding the time each status was reached. */
const PICKUP_TIME_COLUMN = ['pending' => 'requested_at', 'preparing' => 'preparing_at', 'ready_for_pickup' => 'ready_at', 'completed' => 'completed_at'];

function pickup_status_meta(string $status): array
{
    return cat_find('pickupStatus', $status) ?? ['code' => $status, 'label' => $status, 'emoji' => '•', 'tone' => 'neutral', 'parent' => '', 'teacher' => '', 'explain' => ''];
}

/** Children of the logged-in parent (parentStudents relation, same school), ordered by name. */
function parent_children(array $user): array
{
    $ids = array_flip($user['student_ids'] ?? []);
    $kids = array_values(array_filter(store_rows('students'), fn ($s) => isset($ids[$s['id']]) && $s['school_id'] === $user['school_id']));
    usort($kids, fn ($a, $b) => $a['id'] <=> $b['id']);
    return $kids;
}

/** Is this child one of the parent's children? (relation check — never the request alone) */
function parent_has_child(array $user, int $studentId): bool
{
    return $user['role'] === 'parent' && in_array($studentId, $user['student_ids'] ?? [], true);
}

/**
 * Today's pickups the session user may see (newest first).
 * Teacher / admin: scoped() (classroom / school). Parent: every linked child (parentStudents).
 */
function pickup_today_rows(): array
{
    $user = current_user();
    $source = $user['role'] === 'parent'
        ? array_filter(store_rows('pickups'), fn ($p) => parent_has_child($user, $p['student_id']) && $p['school_id'] === $user['school_id'])
        : scoped('pickups');
    $rows = array_values(array_filter($source, fn ($p) => ($p['date'] ?? '') === today()));
    usort($rows, fn ($a, $b) => strcmp($b['requested_at'], $a['requested_at']));
    return $rows;
}

/** Latest pickup of one child today (null = not requested yet). */
function pickup_for_student(int $studentId): ?array
{
    foreach (pickup_today_rows() as $p) {
        if ($p['student_id'] === $studentId) {
            return $p;
        }
    }
    return null;
}

/** Plain data sent to the browser by pickup_status. */
function pickup_public(array $p): array
{
    $meta = pickup_status_meta($p['status']);
    return [
        'id' => $p['id'], 'student_id' => $p['student_id'], 'status' => $p['status'],
        'label' => $meta['emoji'] . ' ' . $meta['label'], 'message' => $meta['parent'],
        'requested_at' => pickup_hm($p['requested_at']), 'completed_at' => pickup_hm($p['completed_at'] ?? null),
    ];
}

/** Changes whenever a visible pickup is added or moves to another status. */
function pickup_signature(array $rows): string
{
    return md5(json_encode(array_map(fn ($p) => [$p['id'], $p['status']], $rows)));
}

function pickup_hm(?string $datetime): string
{
    return $datetime ? date('H:i', strtotime($datetime)) : '';
}

/** Parent: "กำลังไปรับลูก" for ONE of their children. Returns the new row. */
function pickup_create(array $user, int $studentId): array
{
    mk_log('Pickup', 'pickup_create START', ['user' => $user['id'], 'student_id' => $studentId]);
    authorize($user['role'] === 'parent' && can_write_table($user, 'pickups'), 'บัญชีนี้ไม่สามารถแจ้งมารับได้', ['user' => $user['id'], 'role' => $user['role']]);
    $student = find_row('students', $studentId);
    authorize($student !== null && parent_has_child($user, $studentId) && $student['school_id'] === $user['school_id'],
        'ไม่มีสิทธิ์แจ้งมารับนักเรียนคนนี้', ['user' => $user['id'], 'student_id' => $studentId]);

    $row = [
        'school_id' => $student['school_id'], 'classroom_id' => $student['classroom_id'], 'student_id' => $student['id'],
        'date' => today(), 'status' => 'pending',
        'parent_id' => $user['id'], 'parent_name' => 'คุณ' . ($user['relation'] ?? 'แม่'),
        'requested_at' => date('Y-m-d H:i:s'), 'preparing_at' => null, 'ready_at' => null,
        'completed_at' => null, 'completed_by' => null, 'completed_by_name' => null,
    ];
    $created = store_mutate(function (array &$state) use ($row, $student) {
        foreach ($state['tables']['pickups'] ?? [] as $p) {
            if ($p['student_id'] === $row['student_id'] && $p['date'] === $row['date']) {
                throw new DemoValidationException($p['status'] === 'completed' ? 'วันนี้ส่งมอบนักเรียนเรียบร้อยแล้ว' : 'แจ้งครูไว้แล้ว กรุณารอการอัปเดตจากครู');
            }
        }
        $row = ['id' => $state['nextId']++] + $row;
        $state['tables']['pickups'][] = $row;
        notify_users($state, classroom_teacher_users($state['tables']['users'], $student['classroom_id']), 'pickup',
            '🔵 ผู้ปกครองกำลังเดินทางมารับ' . $student['nickname'], 'กรุณาเตรียม' . $student['nickname'] . 'กลับบ้าน', 'pickup', $row['id']);
        return $row;
    })['result'];
    mk_log('Pickup', 'pickup_create END', ['id' => $created['id'], 'student_id' => $created['student_id']]);
    return $created;
}

/** Teacher: move a request of their own classroom to the next status. Returns the updated row. */
function pickup_advance(array $user, int $id, string $to): array
{
    mk_log('Pickup', 'pickup_advance START', ['user' => $user['id'], 'id' => $id, 'to' => $to]);
    authorize($user['role'] === 'teacher' && can_write_table($user, 'pickups'), 'บัญชีนี้ไม่สามารถอัปเดตการรับ-ส่งได้', ['user' => $user['id'], 'role' => $user['role']]);
    $updated = store_mutate(function (array &$state) use ($user, $id, $to) {
        $index = store_find($state, 'pickups', $id);
        $row = $index === null ? null : $state['tables']['pickups'][$index];
        authorize($row !== null && row_in_scope($user, 'pickups', $row), 'ไม่มีสิทธิ์จัดการการรับ-ส่งของนักเรียนคนนี้', ['user' => $user['id'], 'id' => $id]);
        if ((PICKUP_FLOW[$row['status']] ?? null) !== $to) {
            throw new DemoValidationException('สถานะมีการเปลี่ยนแปลงแล้ว กรุณาลองใหม่อีกครั้ง');
        }
        $row['status'] = $to;
        $row[PICKUP_TIME_COLUMN[$to]] = date('Y-m-d H:i:s');
        if ($to === 'completed') {
            $row['completed_by'] = $user['id'];
            $row['completed_by_name'] = $user['name'];
        }
        $state['tables']['pickups'][$index] = $row;

        $student = find_in_state($state, 'students', $row['student_id']);
        $meta = pickup_status_meta($to);
        $message = match ($to) {
            'preparing'        => 'ครูกำลังเตรียม' . $student['nickname'] . 'และพาไปยังจุดรับ-ส่ง',
            'ready_for_pickup' => $student['nickname'] . 'มาถึงจุดรับ-ส่งแล้ว ผู้ปกครองสามารถมารับได้',
            default            => 'ครูส่งมอบ' . $student['nickname'] . 'ให้ผู้ปกครองเรียบร้อยแล้ว',
        };
        notify_users($state, student_parent_users($state['tables'], $row['student_id']), 'pickup',
            $meta['emoji'] . ' ' . $meta['label'] . ' · ' . $student['nickname'], $message, 'pickup', $row['id']);
        return $row;
    })['result'];
    mk_log('Pickup', 'pickup_advance END', ['id' => $id, 'status' => $updated['status']]);
    return $updated;
}

/** Row lookup inside an open mutation (store_rows() would read the pre-mutation cache). */
function find_in_state(array $state, string $table, int $id): ?array
{
    $index = store_find($state, $table, $id);
    return $index === null ? null : $state['tables'][$table][$index];
}
