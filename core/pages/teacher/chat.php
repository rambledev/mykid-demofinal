<?php
/** Teacher (Package A) — chats with parents of children in the own classroom. */
declare(strict_types=1);

$user = current_user();
$cid = $user['classroom_id'];
$students = classroom_students($cid);
$messages = group_by(scoped('messages'), 'student_id');

// Threads with messages first, newest activity on top.
$lastAt = function (int $studentId) use ($messages): string {
    $thread = $messages[$studentId] ?? [];
    return $thread ? max(array_column($thread, 'at')) : '';
};
usort($students, fn ($a, $b) => strcmp($lastAt($b['id']), $lastAt($a['id'])));

mk_header(['id' => 'teacher-chat', 'title' => 'แชทกับผู้ปกครอง', 'nav' => 'chat']);
page_title('💬', 'แชทกับผู้ปกครอง', 'เฉพาะผู้ปกครองห้อง' . $user['classroom']['name']);
?>
<div class="chat-layout" data-chat-layout data-live id="live-chat">
    <ul class="thread-list" aria-label="รายชื่อแชท">
        <?php foreach ($students as $i => $s): ?>
            <?php $thread = $messages[$s['id']] ?? []; $last = end($thread) ?: null; ?>
            <li>
                <button type="button" class="thread<?= $i === 0 ? ' is-active' : '' ?>" data-action="open-thread" data-thread="<?= $s['id'] ?>">
                    <?= student_avatar($s, 'sm') ?>
                    <span class="thread__text"><strong>ผู้ปกครอง<?= e($s['nickname']) ?></strong><small><?= e($last['text'] ?? 'ยังไม่มีข้อความ') ?></small></span>
                    <?php if ($last): ?><time><?= e(date('H:i', strtotime($last['at']))) ?></time><?php endif; ?>
                </button>
            </li>
        <?php endforeach; ?>
    </ul>
    <div class="chat-panels">
        <?php foreach ($students as $i => $s): ?>
            <section class="chat-panel" data-thread-panel="<?= $s['id'] ?>"<?= $i === 0 ? '' : ' hidden' ?>>
                <header class="chat-panel__head">
                    <button type="button" class="icon-btn chat-panel__back" data-action="close-thread" aria-label="กลับไปรายชื่อแชท"><?= icon('back') ?></button>
                    <?= student_avatar($s, 'sm') ?>
                    <div><strong><?= e($s['parentName']) ?></strong><small>ผู้ปกครอง<?= e($s['nickname']) ?></small></div>
                </header>
                <?php render_chat_thread(student_thread($s['id']), 'teacher', $s['id']); ?>
            </section>
        <?php endforeach; ?>
    </div>
</div>
<?php mk_footer(); ?>
