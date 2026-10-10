<?php
/** Admin (Package A) — basic school settings + announcements to parents. */
declare(strict_types=1);

$user = current_user();
$settings = scoped('settings')[0] ?? null;

mk_header(['id' => 'admin-settings', 'title' => 'ตั้งค่าโรงเรียน', 'nav' => 'settings']);
page_title('⚙️', 'ตั้งค่าพื้นฐาน', $user['school']['name']);
?>
<div data-live id="live-settings">
    <section class="card">
        <?php section_head('🏫', 'ข้อมูลโรงเรียน', $settings ? '<button type="button" class="btn btn--soft btn--sm" data-action="open-form" data-table="settings" data-record="' . json_attr($settings) . '">' . icon('edit') . ' แก้ไข</button>' : ''); ?>
        <ul class="menu-list">
            <li><span>📞 เบอร์โทร</span><strong><?= e($settings['phone'] ?? '-') ?></strong></li>
            <li><span>📍 ที่อยู่</span><strong><?= e($settings['address'] ?? '-') ?></strong></li>
            <li><span>🕢 เวลาเปิด–ปิด</span><strong><?= e(($settings['openTime'] ?? '-') . ' – ' . ($settings['closeTime'] ?? '-')) ?> น.</strong></li>
            <li><span>✨ คำขวัญ</span><strong><?= e($settings['motto'] ?? '-') ?></strong></li>
        </ul>
    </section>
    <section class="card">
        <?php section_head('📢', 'ประกาศถึงผู้ปกครอง', btn_add('notifications', 'ส่งประกาศ')); ?>
        <?php render_notification_list(scoped('notifications')); ?>
    </section>
</div>
<?php
render_form_sheet('settings', 'แก้ไขข้อมูลโรงเรียน');
render_form_sheet('notifications', 'ส่งประกาศ');
mk_footer();
