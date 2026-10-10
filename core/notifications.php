<?php
/**
 * In-app notifications (Package A) — the ONLY place that creates / reads / marks notifications.
 *
 * One row per recipient in userNotifications:
 *   id, school_id, user_id, type, title, message, reference_type, reference_id, created_at, read_at
 *
 * In-app only: no push, no external provider. The unread count is shown as a red badge on the
 * "แจ้งเตือน" menu and refreshed by the page polling (api "version" / "pickup_status").
 * A user can only ever see / mark their OWN notifications (user_id from the session).
 */
declare(strict_types=1);

const NOTIFICATION_TYPES = [
    'pickup'       => ['emoji' => '🔔', 'label' => 'รับ-ส่ง'],
    'announcement' => ['emoji' => '📢', 'label' => 'ประกาศ'],
    'activity'     => ['emoji' => '🎨', 'label' => 'กิจกรรม'],
    'status'       => ['emoji' => '📝', 'label' => 'สถานะนักเรียน'],
];

/** Build one notification row (used inside store_mutate() callers and by the seed). */
function notification_row(int $id, array $user, string $type, string $title, string $message, ?string $refType = null, ?int $refId = null, ?string $at = null, ?string $readAt = null): array
{
    return [
        'id' => $id, 'school_id' => $user['school_id'], 'user_id' => $user['id'], 'type' => $type,
        'title' => mb_substr($title, 0, 120), 'message' => mb_substr($message, 0, 300),
        'reference_type' => $refType, 'reference_id' => $refId,
        'created_at' => $at ?? date('Y-m-d H:i:s'), 'read_at' => $readAt,
    ];
}

/**
 * createNotification(): add a notification for each recipient INSIDE an open store_mutate() (same write
 * as the event that caused it). $recipients = user rows. Returns the number created.
 */
function notify_users(array &$state, array $recipients, string $type, string $title, string $message, ?string $refType = null, ?int $refId = null): int
{
    $state['tables']['userNotifications'] ??= [];
    foreach ($recipients as $user) {
        $state['tables']['userNotifications'][] = notification_row($state['nextId']++, $user, $type, $title, $message, $refType, $refId);
    }
    mk_log('Notification', 'notify_users', ['type' => $type, 'recipients' => array_column($recipients, 'id'), 'ref' => [$refType, $refId]]);
    return count($recipients);
}

/** createNotification() for callers outside a mutation. */
function notification_create(array $recipients, string $type, string $title, string $message, ?string $refType = null, ?int $refId = null): int
{
    return store_mutate(fn (array &$state) => notify_users($state, $recipients, $type, $title, $message, $refType, $refId))['result'];
}

/** getNotifications(): newest first, own rows only. */
function notifications_for(array $user, int $limit = 50): array
{
    $rows = array_values(array_filter(store_rows('userNotifications'), fn ($n) => $n['user_id'] === $user['id']));
    usort($rows, fn ($a, $b) => [$b['created_at'], $b['id']] <=> [$a['created_at'], $a['id']]);
    return array_slice($rows, 0, $limit);
}

/** getUnreadCount() */
function notifications_unread_count(?array $user): int
{
    if (!$user || !has_feature('inAppNotifications')) {
        return 0;
    }
    return count(array_filter(store_rows('userNotifications'), fn ($n) => $n['user_id'] === $user['id'] && $n['read_at'] === null));
}

/** markAsRead(): only the owner can mark; someone else's id behaves like "not found". Returns the row. */
function notification_mark_read(array $user, int $id): array
{
    mk_log('Notification', 'notification_mark_read START', ['user' => $user['id'], 'id' => $id]);
    $row = store_mutate(function (array &$state) use ($user, $id) {
        $index = store_find($state, 'userNotifications', $id);
        $row = $index === null ? null : $state['tables']['userNotifications'][$index];
        authorize($row !== null && $row['user_id'] === $user['id'], 'ไม่พบการแจ้งเตือนนี้', ['user' => $user['id'], 'id' => $id]);
        if ($row['read_at'] === null) {
            $row['read_at'] = date('Y-m-d H:i:s');
            $state['tables']['userNotifications'][$index] = $row;
        }
        return $row;
    })['result'];
    mk_log('Notification', 'notification_mark_read END', ['id' => $id]);
    return $row;
}

/** markAllAsRead(): own unread rows only. Returns how many were marked. */
function notification_mark_all_read(array $user): int
{
    $count = store_mutate(function (array &$state) use ($user) {
        $n = 0;
        foreach ($state['tables']['userNotifications'] ?? [] as $i => $row) {
            if ($row['user_id'] === $user['id'] && $row['read_at'] === null) {
                $state['tables']['userNotifications'][$i]['read_at'] = date('Y-m-d H:i:s');
                $n++;
            }
        }
        return $n;
    })['result'];
    mk_log('Notification', 'notification_mark_all_read', ['user' => $user['id'], 'count' => $count]);
    return $count;
}

/** Where a notification leads when opened (relative to the role directory). */
function notification_link(array $user, array $n): ?string
{
    return match ($n['reference_type']) {
        'pickup'    => $user['meta']['dir'] . '/pickup.php',
        'portfolio' => $user['meta']['dir'] . '/works.php',
        default     => null,
    };
}

/** Teachers responsible for a classroom (recipients of parent → teacher notifications). */
function classroom_teacher_users(array $users, int $classroomId): array
{
    return array_values(array_filter($users, fn ($u) => $u['role'] === 'teacher' && $u['classroom_id'] === $classroomId));
}

/** Parent users linked to a student through parentStudents. */
function student_parent_users(array $tables, int $studentId): array
{
    $ids = [];
    foreach ($tables['parentStudents'] ?? [] as $link) {
        if ($link['student_id'] === $studentId) {
            $ids[$link['user_id']] = true;
        }
    }
    return array_values(array_filter($tables['users'], fn ($u) => isset($ids[$u['id']])));
}
