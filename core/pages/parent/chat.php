<?php
/** Parent (Package A) — chat with the child's teachers. */
declare(strict_types=1);

$user = current_user();
$child = $user['child'];
$teachers = classroom_teachers($child['classroom_id']);

mk_header(['id' => 'parent-chat', 'title' => 'แชทกับคุณครู', 'nav' => 'chat']);
page_title('💬', 'แชทกับคุณครู', implode(', ', array_column($teachers, 'nickname')) . ' · ' . $user['classroom']['name']);
?>
<section class="card chat-card" data-live id="live-chat">
    <?php render_chat_thread(student_thread($child['id']), 'parent', $child['id']); ?>
</section>
<?php mk_footer(); ?>
