<?php
/** Admin dashboard — the whole school of the admin (school_id from the session). */
declare(strict_types=1);

$user = current_user();
$k = kpis();
$rooms = my_classrooms();
$quick = array_values(array_filter([
    ['👧', 'นักเรียน', 'students.php', true], ['👩‍🏫', 'ครู', 'teachers.php', true], ['🏫', 'ห้องเรียน', 'classrooms.php', true],
    ['🎨', 'กิจกรรม', 'activities.php', true], ['🍱', 'อาหาร', 'food.php', true],
    ['📅', 'ปฏิทิน', 'calendar.php', has_feature('calendar')], ['✅', 'การมาเรียน', 'attendance.php', has_feature('attendance')],
    ['🩺', 'สุขภาพ', 'health.php', has_feature('health')], ['📹', 'กล้องวงจรปิด', 'cctv.php', has_feature('cctv')],
    ['⚙️', 'ตั้งค่า', 'settings.php', has_feature('settings')],
], fn ($q) => $q[3]));

mk_header(['id' => 'admin-home', 'title' => 'หน้าหลัก Admin', 'nav' => 'home']);
page_title($user['school']['emoji'] ?? '🏫', $user['school']['name'], 'ภาพรวมวันนี้ · ' . thai_date());
?>
<div data-live id="live-admin-home">
    <div class="kpi-grid">
        <?= stat_card('👧', 'นักเรียน', $k['students'] . ' คน') ?>
        <?= stat_card('👩‍🏫', 'ครู', $k['teachers'] . ' คน') ?>
        <?= stat_card('🏫', 'ห้องเรียน', $k['classrooms'] . ' ห้อง') ?>
        <?= stat_card('🎨', 'กิจกรรมวันนี้', $k['activitiesPerRoom'] . ' รายการ', 'ต่อห้อง · รวม ' . $k['activities'] . ' รายการ') ?>
        <?= stat_card('🍱', 'อาหารวันนี้', $k['foodReady'] === $k['classrooms'] ? 'พร้อมแล้ว' : 'ยังไม่ครบ', $k['foodReady'] . '/' . $k['classrooms'] . ' ห้อง', $k['foodReady'] === $k['classrooms'] ? 'good' : 'warning') ?>
    </div>

    <section class="card">
        <?php section_head('⚡', 'เมนูลัด'); ?>
        <nav class="quick-menu" aria-label="เมนูลัด">
            <?php foreach ($quick as [$emoji, $label, $href]): ?>
                <a class="quick-menu__item" href="<?= e($href) ?>"><span aria-hidden="true"><?= $emoji ?></span><?= e($label) ?></a>
            <?php endforeach; ?>
        </nav>
    </section>

    <div class="dash-grid">
        <section class="card">
            <?php section_head('🚦', 'สถานะห้องเรียนตอนนี้'); ?>
            <ul class="room-status">
                <?php foreach ($rooms as $room): ?>
                    <?php $st = classroom_status($room['id']); ?>
                    <li style="--room-bg: <?= e($room['color']) ?>">
                        <span class="room-status__emoji"><?= $room['emoji'] ?></span>
                        <div><strong><?= e($room['name']) ?></strong><small><?= count(classroom_students($room['id'])) ?> คน · <?= e(implode(', ', array_column(classroom_teachers($room['id']), 'nickname'))) ?></small></div>
                        <span class="room-status__now"><?= $st ? $st['emoji'] . ' ' . e($st['text']) : '-' ?><small><?= $st ? e(thai_time($st['at'])) : '' ?></small></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>

        <?php if (has_feature('cctv')): ?>
            <?php $cams = accessible_cameras($user); $camN = camera_counts($cams); ?>
            <section class="card">
                <?php section_head('📹', 'กล้องวงจรปิด', '<a class="btn btn--soft btn--sm" href="cctv.php">จัดการกล้อง</a>', $camN['total'] . ' กล้อง · 🟢 Online ' . $camN['online'] . ' · 🔴 Offline ' . $camN['offline']); ?>
                <?php render_cctv_mini($cams); ?>
            </section>
        <?php endif; ?>

        <?php if (has_feature('attendance')): ?>
            <section class="card">
                <?php section_head('✅', 'การมาเรียนวันนี้', '<a class="btn btn--soft btn--sm" href="attendance.php">ดูทั้งหมด</a>'); ?>
                <?= chart_stack(attendance_parts(attendance_counts(today_rows(scoped('attendance'))))) ?>
                <?php $watch = array_filter(today_rows(scoped('healthRecords')), fn ($h) => $h['condition'] !== 'normal'); ?>
                <p class="hint">🩺 เฝ้าระวังสุขภาพ <?= count($watch) ?> คน · <a href="health.php">ดูรายละเอียด</a></p>
            </section>
            <section class="card">
                <?php section_head('📅', 'กิจกรรมโรงเรียนที่จะถึง', '<a class="btn btn--soft btn--sm" href="calendar.php">ปฏิทิน</a>'); ?>
                <?php render_calendar_list(array_slice(scoped('calendarEvents'), 0, 3)); ?>
            </section>
        <?php endif; ?>
    </div>
</div>
<?php mk_footer(); ?>
