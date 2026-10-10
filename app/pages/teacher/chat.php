<?php
/**
 * Teacher — chats with parents of children in the own classroom.
 * Threads with unread messages are marked (badge), newest activity first. /teacher/chat/{student token}
 * opens one thread directly (from a notification). New messages arrive by polling (core.js Chat).
 */
declare(strict_types=1);

$user = current_user();
$cid = $user['classroom_id'];
$students = classroom_students($cid);
$messages = group_by(scoped('messages'), 'student_id');
$unread = chat_unread_counts($user);
$open = untok('students', route_param('student'));
$open = $open !== null && chat_can_access($user, $open) ? $open : null;

// Threads with messages first, newest activity on top.
$lastAt = function (int $studentId) use ($messages): string {
    $thread = $messages[$studentId] ?? [];
    return $thread ? max(array_column($thread, 'at')) : '';
};
usort($students, fn ($a, $b) => strcmp($lastAt($b['id']), $lastAt($a['id'])));
$active = $open ?? ($students[0]['id'] ?? null);

mk_header(['id' => 'teacher-chat', 'title' => 'แชทกับผู้ปกครอง', 'nav' => 'chat']);
page_title('💬', 'แชทกับผู้ปกครอง', 'เฉพาะผู้ปกครองห้อง' . $user['classroom']['name']);
?>
<div class="chat-layout<?= $open ? ' is-thread-open' : '' ?>" data-chat-layout data-chat-open="<?= e($open ? tok('students', $open) : '') ?>">
    <ul class="thread-list" aria-label="รายชื่อแชท">
        <?php foreach ($students as $s): ?>
            <?php $thread = $messages[$s['id']] ?? []; usort($thread, fn ($a, $b) => strcmp($a['at'], $b['at'])); $last = end($thread) ?: null; $n = $unread[$s['id']] ?? 0; ?>
            <li>
                <button type="button" class="thread<?= $s['id'] === $active ? ' is-active' : '' ?><?= $n ? ' has-unread' : '' ?>" data-action="open-thread" data-thread="<?= e(tok('students', $s['id'])) ?>">
                    <?= student_avatar($s, 'sm') ?>
                    <span class="thread__text"><strong>ผู้ปกครอง<?= e($s['nickname']) ?></strong><small data-thread-preview><?= e($last['text'] ?? 'ยังไม่มีข้อความ') ?></small></span>
                    <span class="thread__meta">
                        <?php if ($last): ?><time><?= e(thai_time($last['at'])) ?></time><?php endif; ?>
                        <span class="thread__unread" data-thread-unread<?= $n ? '' : ' hidden' ?>><?= $n ?></span>
                    </span>
                </button>
            </li>
        <?php endforeach; ?>
    </ul>
    <div class="chat-panels">
        <?php foreach ($students as $s): ?>
            <section class="chat-panel" data-thread-panel="<?= e(tok('students', $s['id'])) ?>"<?= $s['id'] === $active ? '' : ' hidden' ?>>
                <header class="chat-panel__head">
                    <button type="button" class="icon-btn chat-panel__back" data-action="close-thread" aria-label="กลับไปรายชื่อแชท"><?= icon('back') ?></button>
                    <?= student_avatar($s, 'sm') ?>
                    <div><strong><?= e($s['parentName']) ?></strong><small>ผู้ปกครอง<?= e($s['nickname']) ?></small></div>
                </header>
                <?php render_chat_thread(chat_thread($s['id']), $user, $s['id']); ?>
            </section>
        <?php endforeach; ?>
    </div>
</div>
<?php mk_footer(); ?>
