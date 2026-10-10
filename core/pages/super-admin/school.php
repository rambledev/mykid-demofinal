<?php
/**
 * Super Admin — drill into one school (read-only snapshot of that tenant).
 *
 * ?view=<id> only picks WHICH school to display. It is not a permission: this page is
 * Super Admin only (platform scope = every school) and the id must be an existing school.
 * Other roles can never reach this page (require_role in the page file).
 */
declare(strict_types=1);

$schools = scoped('schools');
$school = null;
foreach ($schools as $s) {
    if ($s['id'] === (int) ($_GET['view'] ?? 0)) {
        $school = $s;
    }
}
$school ??= $schools[0];
$sid = $school['id'];
$in = fn (string $table) => where(scoped($table), 'school_id', $sid);

$rooms = array_values(array_filter(my_classrooms(), fn ($r) => $r['school_id'] === $sid));
$students = $in('students');
$teachers = $in('teachers');
$staff = array_values(array_filter($in('users'), fn ($u) => in_array($u['role'], ['admin', 'executive'], true)));
$accounts = array_count_values(array_column($in('users'), 'role'));
$cameras = array_values(array_filter(accessible_cameras(), fn ($c) => $c['school_id'] === $sid));
$cam = camera_counts($cameras);
$attendance = today_rows($in('attendance'));
$here = count(array_filter($attendance, fn ($a) => in_array($a['status'], ['present', 'late'], true)));
$health = count_by(today_rows($in('healthRecords')), 'condition');

mk_header(['id' => 'super-school', 'title' => 'ดูรายโรงเรียน', 'nav' => 'school']);
page_title($school['emoji'], $school['name'], ($school['province'] ?: '-') . ' · ' . ($school['status'] === 'active' ? 'ใช้งาน' : 'ทดลองใช้') . ' · มุมมอง Super Admin',
    '<span class="readonly-badge">👁️ ดูข้อมูลทั้งโรงเรียน</span>');
?>
<nav class="chip-row" aria-label="เลือกโรงเรียน">
    <?php foreach ($schools as $s): ?>
        <a class="chip-btn<?= $s['id'] === $sid ? ' is-active' : '' ?>" href="?view=<?= $s['id'] ?>"<?= $s['id'] === $sid ? ' aria-current="true"' : '' ?>><?= $s['emoji'] ?> <?= e($s['shortName']) ?></a>
    <?php endforeach; ?>
</nav>
<p class="hint hint--card">🏫 ข้อมูลทั้งหมดในหน้านี้กรองด้วย <code>school_id = <?= $sid ?></code> — ผู้ใช้ของโรงเรียนนี้เห็นเฉพาะข้อมูลชุดนี้ และมองไม่เห็นโรงเรียนอื่น</p>

<div data-live id="live-super-school">
    <div class="kpi-grid">
        <?= stat_card('🚪', 'ห้องเรียน', count($rooms), 'ห้อง') ?>
        <?= stat_card('👧', 'นักเรียน', count($students), 'คน') ?>
        <?= stat_card('👩‍🏫', 'ครู', count($teachers), 'คน') ?>
        <?= stat_card('✅', 'มาเรียนวันนี้', percent($here, count($attendance)) . '%', $here . ' จาก ' . count($attendance) . ' คน', 'good') ?>
        <?= stat_card('📹', 'กล้อง', $cam['total'] . ' ตัว', '🟢 ' . $cam['online'] . ' · 🔴 ' . $cam['offline'] . ($cam['maintenance'] ? ' · 🛠️ ' . $cam['maintenance'] : '')) ?>
    </div>

    <div class="dash-grid">
        <section class="card">
            <?php section_head('🚦', 'ห้องเรียนตอนนี้'); ?>
            <ul class="room-status">
                <?php foreach ($rooms as $room): ?>
                    <?php $st = classroom_status($room['id']); ?>
                    <li style="--room-bg: <?= e($room['color']) ?>">
                        <span class="room-status__emoji"><?= $room['emoji'] ?></span>
                        <div><strong><?= e($room['name']) ?></strong><small><?= count(where($students, 'classroom_id', $room['id'])) ?> คน · <?= e(implode(', ', array_column(where($teachers, 'classroom_id', $room['id']), 'nickname'))) ?></small></div>
                        <span class="room-status__now"><?= $st ? $st['emoji'] . ' ' . e($st['text']) : '-' ?><small><?= $st ? e(thai_time($st['at'])) : '' ?></small></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>

        <section class="card">
            <?php section_head('✅', 'การมาเรียนวันนี้'); ?>
            <?= chart_stack(attendance_parts(attendance_counts($attendance))) ?>
            <div class="kpi-grid kpi-grid--3" style="margin-top: 12px">
                <?= stat_card('💚', 'สุขภาพปกติ', ($health['normal'] ?? 0) . ' คน', '', 'good') ?>
                <?= stat_card('🟡', 'เฝ้าระวัง', ($health['watch'] ?? 0) . ' คน', '', 'warning') ?>
                <?= stat_card('⭐', 'ดาวรวม', stars_total($in('stars'))) ?>
            </div>
        </section>

        <section class="card">
            <?php section_head('🛡️', 'ผู้ดูแลและบัญชีผู้ใช้', '<a class="btn btn--soft btn--sm" href="users.php">จัดการ</a>'); ?>
            <ul class="menu-list">
                <?php foreach ($staff as $u): ?>
                    <li><span><?= role_meta($u['role'])['emoji'] ?> <?= e($u['name']) ?></span><strong><?= e($u['phone']) ?></strong></li>
                <?php endforeach; ?>
                <li><span>👩‍🏫 บัญชีครู</span><strong><?= $accounts['teacher'] ?? 0 ?> บัญชี</strong></li>
                <li><span>👨‍👩‍👧 บัญชีผู้ปกครอง</span><strong><?= $accounts['parent'] ?? 0 ?> บัญชี (Demo)</strong></li>
            </ul>
        </section>

        <section class="card">
            <?php section_head('📹', 'กล้องวงจรปิด', '<a class="btn btn--soft btn--sm" href="cctv.php?view=' . $sid . '">ดูกล้อง</a>'); ?>
            <?php render_cctv_mini($cameras, 6); ?>
        </section>

        <section class="card">
            <?php section_head('📅', 'กิจกรรมโรงเรียนที่จะถึง'); ?>
            <?php render_calendar_list(array_slice($in('calendarEvents'), 0, 3)); ?>
        </section>

        <section class="card">
            <?php section_head('👩‍🏫', 'ครู', '<span class="count-badge">' . count($teachers) . ' คน</span>'); ?>
            <ul class="teacher-list">
                <?php foreach ($teachers as $t): ?>
                    <?php $room = find_row('classrooms', $t['classroom_id']); ?>
                    <li><span class="teacher-list__avatar" aria-hidden="true"><?= $t['emoji'] ?></span><div><strong><?= e($t['nickname']) ?></strong><small><?= e($t['fullName']) ?> · <?= e($room['name'] ?? '') ?></small></div></li>
                <?php endforeach; ?>
            </ul>
        </section>

        <section class="card dash-grid__wide">
            <?php section_head('🧒', 'นักเรียน', '<span class="count-badge">' . count($students) . ' คน</span>', 'แยกตามห้อง'); ?>
            <?php tab_bar('rooms', array_column(array_map(fn ($r) => ['k' => $r['id'], 'v' => $r['emoji'] . ' ' . $r['name']], $rooms), 'v', 'k')); ?>
            <?php foreach ($rooms as $i => $room): ?>
                <div data-tab-panel="rooms:<?= $room['id'] ?>"<?= $i ? ' hidden' : '' ?>><?php render_student_grid(where($students, 'classroom_id', $room['id'])); ?></div>
            <?php endforeach; ?>
        </section>
    </div>
</div>
<?php mk_footer(); ?>
