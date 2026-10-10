<?php
/**
 * Parent — chat with the classroom teachers of one child. A parent with several children picks
 * the child (/parent/chat/{student token}). New messages arrive by polling (core.js Chat).
 */
declare(strict_types=1);

$user = current_user();
$childIds = chat_thread_ids($user);
$picked = untok('students', route_param('student'));
$childId = $picked !== null && in_array($picked, $childIds, true) ? $picked : ($childIds[0] ?? $user['child']['id']);
$child = find_row('students', $childId);
$room = find_row('classrooms', $child['classroom_id']);
$teachers = classroom_teachers($child['classroom_id']);
$unread = chat_unread_counts($user);

mk_header(['id' => 'parent-chat', 'title' => 'แชทกับคุณครู', 'nav' => 'chat']);
page_title('💬', 'แชทกับคุณครู', implode(', ', array_column($teachers, 'nickname')) . ' · ' . ($room['name'] ?? ''));
?>
<?php if (count($childIds) > 1): ?>
    <nav class="chip-row" aria-label="เลือกลูก">
        <?php foreach ($childIds as $id): ?>
            <?php $c = find_row('students', $id); $n = $unread[$id] ?? 0; ?>
            <a class="chip-btn<?= $id === $childId ? ' is-active' : '' ?>" href="<?= e(url('parent/chat/' . tok('students', $id))) ?>"<?= $id === $childId ? ' aria-current="page"' : '' ?>>
                <?= e($c['nickname']) ?><?php if ($n && $id !== $childId): ?> <span class="thread__unread"><?= $n ?></span><?php endif; ?>
            </a>
        <?php endforeach; ?>
    </nav>
<?php endif; ?>
<section class="card chat-card">
    <?php render_chat_thread(chat_thread($childId), $user, $childId); ?>
</section>
<?php mk_footer(); ?>
