<?php
/**
 * Parent (Package A) — แจ้งเตือน:
 *   1) my in-app notifications (รับ-ส่ง ฯลฯ) with ● unread / ○ read + "อ่านทั้งหมด"
 *   2) school announcements, events and teacher messages (unchanged list)
 */
declare(strict_types=1);

$user = current_user();
$rows = scoped('notifications');
foreach (scoped('messages') as $m) {
    if ($m['from'] === 'teacher') {
        $rows[] = ['type' => 'teacher', 'title' => 'ข้อความจาก' . $m['sender'], 'body' => $m['text'], 'at' => $m['at']];
    }
}

mk_header(['id' => 'parent-notifications', 'title' => 'แจ้งเตือน', 'nav' => 'notifications']);
page_title('🔔', 'แจ้งเตือน', 'แจ้งเตือนของฉัน · ประกาศโรงเรียน · ข้อความจากครู');
?>
<?php if (has_feature('inAppNotifications')): ?>
    <section class="card" data-live id="live-user-notifications">
        <?php section_head('🔔', 'แจ้งเตือนของฉัน', '<button type="button" class="btn btn--soft btn--sm" data-action="notification-read-all">✔️ อ่านทั้งหมด</button>'); ?>
        <?php render_user_notifications($user, notifications_for($user)); ?>
    </section>
<?php endif; ?>
<section class="card" data-live id="live-notifications">
    <?php section_head('📢', 'ประกาศและข้อความจากโรงเรียน'); ?>
    <?php render_notification_list($rows); ?>
</section>
<?php mk_footer(); ?>
