<?php
/** Teacher (Package A) — own in-app notifications (e.g. "ผู้ปกครองกำลังเดินทางมารับ…"). */
declare(strict_types=1);

$user = current_user();
mk_header(['id' => 'teacher-notifications', 'title' => 'แจ้งเตือน', 'nav' => 'notifications']);
page_title('🔔', 'แจ้งเตือน', 'เฉพาะการแจ้งเตือนของบัญชีนี้', '<button type="button" class="btn btn--soft btn--sm" data-action="notification-read-all">✔️ อ่านทั้งหมด</button>');
?>
<section class="card" data-live id="live-user-notifications"><?php render_user_notifications($user, notifications_for($user)); ?></section>
<?php mk_footer(); ?>
