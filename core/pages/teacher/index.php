<?php
/** Teacher dashboard — own classroom only (classroom_id from the session). */
declare(strict_types=1);

$user = current_user();
$cid = $user['classroom_id'];
$room = $user['classroom'];
$students = classroom_students($cid);
$status = classroom_status($cid);

mk_header(['id' => 'teacher-home', 'title' => 'หน้าหลักครู', 'nav' => 'home']);
?>

<section class="greeting">
    <div>
        <p class="greeting__hello">สวัสดีค่ะ คุณครู <span aria-hidden="true">👋</span></p>
        <p class="greeting__name"><?= e($user['name']) ?> · ครูประจำชั้น<?= e($room['name']) ?></p>
        <p class="greeting__date">📅 วันนี้<?= e(thai_date()) ?></p>
    </div>
    <span class="greeting__clock" data-clock aria-label="เวลาปัจจุบัน"><?= date('H:i') ?></span>
</section>

<?php render_scope_card($room, count($students), classroom_teachers($cid)); ?>

<div class="dash-grid" data-live id="live-teacher-dash">
    <section class="dash-grid__status" aria-label="สถานะปัจจุบัน">
        <?php render_status_card($status, $room, 'ตอนนี้เด็ก ๆ กำลัง...', true); ?>
    </section>

    <?php if (has_feature('attendance')): ?>
        <?php
        $att = today_by_student('attendance');
        $health = today_by_student('healthRecords');
        $sleep = today_by_student('sleepRecords');
        $watch = count(array_filter($health, fn ($h) => $h['condition'] !== 'normal'));
        $slept = count(array_filter($sleep, fn ($s) => $s['end'] !== ''));
        $unread = count(array_filter(scoped('messages'), fn ($m) => $m['from'] === 'parent' && is_today($m['at'])));
        ?>
        <section class="card">
            <?php section_head('📊', 'สรุปวันนี้', '<a class="btn btn--soft btn--sm" href="attendance.php">เช็คชื่อ</a>'); ?>
            <?= chart_stack(attendance_parts(attendance_counts($att))) ?>
            <div class="mini-stats">
                <a href="health.php"><?= stat_card('🩺', 'เฝ้าระวังสุขภาพ', $watch . ' คน', '', $watch ? 'warning' : '') ?></a>
                <a href="sleep.php"><?= stat_card('😴', 'นอนกลางวันแล้ว', $slept . '/' . count($students), '') ?></a>
                <a href="chat.php"><?= stat_card('💬', 'ข้อความวันนี้', $unread, 'จากผู้ปกครอง') ?></a>
            </div>
        </section>
    <?php endif; ?>

    <section class="card">
        <?php section_head('📋', 'กิจกรรมวันนี้', btn_add('activities', 'เพิ่ม')); ?>
        <?php render_activities(classroom_activities($cid), true); ?>
        <a class="card__link" href="activities.php">จัดการกิจกรรมทั้งหมด <?= icon('next') ?></a>
    </section>

    <section class="card">
        <?php section_head('🍱', 'อาหารวันนี้', '<a class="btn btn--soft btn--sm" href="food.php">' . icon('edit') . ' แก้ไขเมนู</a>'); ?>
        <?php render_food_summary(classroom_food($cid)); ?>
    </section>

    <?php if (has_feature('cctv')): ?>
        <section class="card">
            <?php section_head('📹', 'CCTV', '<a class="btn btn--soft btn--sm" href="cctv.php">ดูทั้งหมด</a>', 'กล้องห้อง' . $room['name'] . ' และพื้นที่ส่วนกลางที่ได้รับอนุญาต'); ?>
            <?php render_cctv_mini(accessible_cameras($user)); ?>
        </section>
    <?php endif; ?>

    <section class="card dash-grid__wide">
        <?php $canSetStatus = has_feature('studentStatus') && can_write_table($user, 'studentStatuses'); ?>
        <?php section_head('🧒', 'นักเรียน', '<span class="count-badge">' . count($students) . ' คน</span>', $room['name'] . ($canSetStatus ? ' · กดที่ชื่อเพื่อกำหนดสถานะรายบุคคล' : '')); ?>
        <?php render_student_grid($students, $canSetStatus ? today_student_statuses() : [], $canSetStatus); ?>
    </section>

    <?php render_status_sheet($status); ?>
</div>

<?php
render_form_sheet('activities', 'เพิ่มกิจกรรม', ['classroom_id']);
if (has_feature('studentStatus') && can_write_table($user, 'studentStatuses')) {
    render_student_status_sheet();
}
mk_footer();
