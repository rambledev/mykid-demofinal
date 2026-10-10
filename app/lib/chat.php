<?php
/**
 * Chat — parent ↔ the child's classroom teachers, one thread per student.
 *
 * Row (messages): school_id, classroom_id, student_id (= the thread), from ('teacher' | 'parent'),
 * sender, text, at, read_at (when the OTHER side read it; NULL = unread).
 *
 * Near-realtime: chat pages poll the API "chat_poll" every ~2.5 s for messages newer than the last
 * one shown (ids travel as tokens). Sending a message notifies the other side in the same write
 * (in-app notification, one unread notification per thread is kept up to date instead of piling up).
 */
declare(strict_types=1);

/** Which side of a thread the user is on. */
function chat_side(array $user): string
{
    return $user['role'] === 'parent' ? 'parent' : 'teacher';
}

/** Student ids whose thread the user may read / write (teacher: own room · parent: own children). */
function chat_thread_ids(array $user): array
{
    return match ($user['role']) {
        'teacher' => array_column(classroom_students((int) $user['classroom_id']), 'id'),
        'parent'  => array_map('intval', $user['student_ids'] ?? [$user['student_id']]),
        default   => [],
    };
}

function chat_can_access(array $user, int $studentId): bool
{
    return in_array($studentId, chat_thread_ids($user), true);
}

/** Unread messages per thread for this user: [studentId => count] (messages from the other side). */
function chat_unread_counts(array $user): array
{
    if (!has_feature('chat')) {
        return [];
    }
    $threads = array_flip(chat_thread_ids($user));
    $side = chat_side($user);
    $counts = [];
    foreach (store_rows('messages') as $m) {
        if (isset($threads[$m['student_id']]) && $m['from'] !== $side && ($m['read_at'] ?? null) === null) {
            $counts[$m['student_id']] = ($counts[$m['student_id']] ?? 0) + 1;
        }
    }
    return $counts;
}

function chat_unread_total(?array $user): int
{
    return $user && in_array($user['role'], ['teacher', 'parent'], true) ? array_sum(chat_unread_counts($user)) : 0;
}

/** Browser-safe message (token id, Thai time, mine / read flags). */
function chat_message_public(array $m, array $user): array
{
    $mine = $m['from'] === chat_side($user);
    return [
        'id'     => tok('messages', $m['id']),
        'sender' => $m['sender'],
        'text'   => $m['text'],
        'time'   => thai_time($m['at']),
        'mine'   => $mine,
        'read'   => $mine && ($m['read_at'] ?? null) !== null,
    ];
}

/** Messages of one thread (oldest first), optionally only those newer than $afterId. */
function chat_thread(int $studentId, int $afterId = 0): array
{
    $rows = array_values(array_filter(store_rows('messages'), fn ($m) => $m['student_id'] === $studentId && $m['id'] > $afterId));
    usort($rows, fn ($a, $b) => [$a['at'], $a['id']] <=> [$b['at'], $b['id']]);
    return $rows;
}

/** Recipients on the other side: the room's teachers, or the child's parents. */
function chat_recipients(array $tables, array $user, array $student): array
{
    if (chat_side($user) === 'parent') {
        return classroom_teacher_users($tables['users'], $student['classroom_id']);
    }
    $ids = array_flip(array_column(array_filter($tables['parentStudents'] ?? [], fn ($l) => $l['student_id'] === $student['id']), 'user_id'));
    return array_values(array_filter($tables['users'], fn ($u) => $u['role'] === 'parent' && (isset($ids[$u['id']]) || $u['student_id'] === $student['id'])));
}

/** Send a message + notify the other side in ONE write. Returns the new message row. */
function chat_send(array $user, int $studentId, string $text): array
{
    mk_log('Chat', 'chat_send START', ['user' => $user['id'], 'student' => $studentId]);
    authorize(chat_can_access($user, $studentId), 'ไม่มีสิทธิ์ส่งข้อความในห้องแชทนี้', ['user' => $user['id'], 'student' => $studentId]);
    $student = find_row('students', $studentId);
    $sender = $user['role'] === 'parent' ? 'คุณ' . ($user['relation'] ?? 'แม่') . 'ของ' . $student['nickname'] : $user['name'];
    $text = mb_substr($text, 0, 300);

    $row = store_mutate(function (array &$state) use ($user, $student, $sender, $text) {
        $now = date('Y-m-d H:i:s');
        $row = ['id' => $state['nextId']++, 'school_id' => $student['school_id'], 'classroom_id' => $student['classroom_id'],
            'student_id' => $student['id'], 'from' => chat_side($user), 'sender' => $sender, 'text' => $text, 'at' => $now, 'read_at' => null];
        $state['tables']['messages'][] = $row;

        // One unread "chat" notification per recipient + thread: refresh it, or add a new one.
        $title = '💬 ข้อความใหม่จาก' . (chat_side($user) === 'parent' ? 'ผู้ปกครองของ' . $student['nickname'] : $user['name']);
        $preview = mb_strimwidth($text, 0, 80, '…');
        foreach (chat_recipients($state['tables'], $user, $student) as $recipient) {
            $found = false;
            foreach ($state['tables']['userNotifications'] as &$n) {
                if ($n['user_id'] === $recipient['id'] && $n['reference_type'] === 'chat' && $n['reference_id'] === $student['id'] && $n['read_at'] === null) {
                    $n['title'] = $title;
                    $n['message'] = $preview;
                    $n['created_at'] = $now;
                    $found = true;
                }
            }
            unset($n);
            if (!$found) {
                $state['tables']['userNotifications'][] = notification_row($state['nextId']++, $recipient, 'chat', $title, $preview, 'chat', $student['id'], $now);
            }
        }
        return $row;
    })['result'];
    mk_log('Chat', 'chat_send END', ['id' => $row['id']]);
    return $row;
}

/** Mark the other side's messages of a thread + the user's chat notification for it as read. Returns how many. */
function chat_mark_read(array $user, int $studentId): int
{
    $side = chat_side($user);
    $pending = array_filter(chat_thread($studentId), fn ($m) => $m['from'] !== $side && ($m['read_at'] ?? null) === null);
    $pendingNotes = array_filter(store_rows('userNotifications'), fn ($n) => $n['user_id'] === $user['id'] && $n['reference_type'] === 'chat'
        && $n['reference_id'] === $studentId && $n['read_at'] === null);
    if (!$pending && !$pendingNotes) {
        return 0; // nothing to write — polling stays read-only
    }
    $count = store_mutate(function (array &$state) use ($user, $studentId, $side) {
        $now = date('Y-m-d H:i:s');
        $n = 0;
        foreach ($state['tables']['messages'] as &$m) {
            if ($m['student_id'] === $studentId && $m['from'] !== $side && ($m['read_at'] ?? null) === null) {
                $m['read_at'] = $now;
                $n++;
            }
        }
        unset($m);
        foreach ($state['tables']['userNotifications'] as &$note) {
            if ($note['user_id'] === $user['id'] && $note['reference_type'] === 'chat' && $note['reference_id'] === $studentId && $note['read_at'] === null) {
                $note['read_at'] = $now;
            }
        }
        unset($note);
        return $n;
    })['result'];
    mk_log('Chat', 'chat_mark_read', ['user' => $user['id'], 'student' => $studentId, 'count' => $count]);
    return $count;
}

/** Red badge for the "แชท" menu item (updated by core.js from every API response). */
function chat_badge(): string
{
    static $count = null;
    $count ??= chat_unread_total(current_user());
    $text = $count > 99 ? '99+' : (string) $count;
    return '<span class="notif-badge" data-chat-badge' . ($count ? '' : ' hidden') . ' aria-label="ข้อความยังไม่ได้อ่าน ' . $count . ' ข้อความ">' . $text . '</span>';
}
