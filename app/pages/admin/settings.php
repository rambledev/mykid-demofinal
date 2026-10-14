<?php
/** Admin (Package A) — basic school settings, meal times, ธนาคารความดี presets + announcements to parents. */
declare(strict_types=1);

$user = current_user();
$school = scoped('schools')[0] ?? null;
catalog_seed_school($user['school_id']); // first visit: built-in meal times / good-deed presets become editable rows
$meals = catalog_school_list('mealTimes') ?? [];
$reasons = group_by(catalog_school_list('starReasons') ?? [], 'recipient');
$settings = scoped('settings')[0] ?? null;

mk_header(['id' => 'admin-settings', 'title' => 'ตั้งค่าโรงเรียน', 'nav' => 'settings']);
page_title('⚙️', 'ตั้งค่าพื้นฐาน', $school['name'] ?? '');
?>
<div data-live id="live-settings">
    <section class="card">
        <?php section_head('🏷️', 'ชื่อโรงเรียน', $school ? '<button type="button" class="btn btn--soft btn--sm" data-action="open-form" data-table="schools" data-record="' . json_attr(public_row('schools', $school)) . '">' . icon('edit') . ' แก้ไข</button>' : ''); ?>
        <ul class="menu-list">
            <li><span>🏫 ชื่อโรงเรียน</span><strong><?= e(($school['name'] ?? '') ?: '-') ?></strong></li>
            <li><span>🔤 ชื่อย่อ</span><strong><?= e(($school['shortName'] ?? '') ?: '-') ?></strong></li>
            <li><span>🗺️ จังหวัด</span><strong><?= e(($school['province'] ?? '') ?: '-') ?></strong></li>
            <li><span>✨ สัญลักษณ์</span><strong><?= e(($school['emoji'] ?? '') ?: '-') ?></strong></li>
        </ul>
    </section>
    <section class="card">
        <?php section_head('🏫', 'ข้อมูลโรงเรียน', $settings ? '<button type="button" class="btn btn--soft btn--sm" data-action="open-form" data-table="settings" data-record="' . json_attr(public_row('settings', $settings)) . '">' . icon('edit') . ' แก้ไข</button>' : ''); ?>
        <ul class="menu-list">
            <li><span>📞 เบอร์โทร</span><strong><?= e($settings['phone'] ?? '-') ?></strong></li>
            <li><span>📍 ที่อยู่</span><strong><?= e($settings['address'] ?? '-') ?></strong></li>
            <li><span>🕢 เวลาเปิด–ปิด</span><strong><?= e(($settings['openTime'] ?? '-') . ' – ' . ($settings['closeTime'] ?? '-')) ?> น.</strong></li>
            <li><span>✨ คำขวัญ</span><strong><?= e($settings['motto'] ?? '-') ?></strong></li>
        </ul>
    </section>
    <section class="card">
        <?php section_head('🍽️', 'ตารางเวลาอาหาร', btn_add('mealTimes', 'เพิ่มมื้อ'), 'มื้อที่ครูบันทึกเมนูและการรับประทานของเด็กได้'); ?>
        <?php if ($meals): ?>
            <ul class="menu-list">
                <?php foreach ($meals as $m): ?>
                    <li><span><?= e($m['icon']) ?> <?= e($m['label']) ?> · <?= e($m['time']) ?> น.</span>
                        <span class="person__actions"><?= btn_edit('mealTimes', $m) ?><?= btn_delete('mealTimes', $m['id'], $m['label']) ?></span></li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <?= empty_state('🍽️', 'ยังไม่มีมื้ออาหาร') ?>
        <?php endif; ?>
    </section>
    <?php foreach (['student' => ['🧒', 'รายการทำความดีสำเร็จรูป · นักเรียน'], 'parent' => ['🛵', 'รายการทำความดีสำเร็จรูป · ผู้ปกครอง (ขับขี่ปลอดภัย)']] as $recipient => [$emoji, $title]): ?>
        <section class="card">
            <?php section_head($emoji, $title, btn_add('starReasons', 'เพิ่มรายการ', ['recipient' => $recipient]), 'ครูเลือกได้ทันทีตอนให้ดาวในธนาคารความดี'); ?>
            <?php if (!empty($reasons[$recipient])): ?>
                <ul class="menu-list">
                    <?php foreach ($reasons[$recipient] as $r): ?>
                        <li><span><?= e($r['reason']) ?> · +<?= (int) $r['points'] ?> ⭐</span>
                            <span class="person__actions"><?= btn_edit('starReasons', $r) ?><?= btn_delete('starReasons', $r['id'], $r['reason']) ?></span></li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <?= empty_state('⭐', 'ยังไม่มีรายการ') ?>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>
    <section class="card">
        <?php section_head('📢', 'ประกาศถึงผู้ปกครอง', btn_add('notifications', 'ส่งประกาศ')); ?>
        <?php render_notification_list(scoped('notifications')); ?>
    </section>
</div>
<?php
render_form_sheet('schools', 'แก้ไขชื่อโรงเรียน', ['status']);
render_form_sheet('settings', 'แก้ไขข้อมูลโรงเรียน');
render_form_sheet('mealTimes', 'มื้ออาหาร');
render_form_sheet('starReasons', 'รายการทำความดี');
render_form_sheet('notifications', 'ส่งประกาศ');
mk_footer();
