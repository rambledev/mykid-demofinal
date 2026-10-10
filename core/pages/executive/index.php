<?php
/** Executive overview — KPIs, live classroom status, key charts (read only, own school). */
declare(strict_types=1);

$user = current_user();
$k = kpis();
$rooms = my_classrooms();

mk_header(['id' => 'exec-home', 'title' => 'ภาพรวมผู้บริหาร', 'nav' => 'home']);
page_title('💼', 'ภาพรวมโรงเรียน', $user['school']['name'] . ' · ' . thai_date(), '<span class="readonly-badge">👁️ อ่านอย่างเดียว</span>');
?>
<div data-live id="live-exec-home">
    <div class="kpi-grid">
        <?= stat_card('👧', 'นักเรียนทั้งหมด', $k['students'], 'คน') ?>
        <?= stat_card('👩‍🏫', 'ครู', $k['teachers'], 'คน') ?>
        <?= stat_card('🏫', 'ห้องเรียน', $k['classrooms'], 'ห้อง') ?>
        <?= stat_card('🎨', 'กิจกรรมวันนี้', $k['activitiesPerRoom'], 'รายการต่อห้อง') ?>
        <?= stat_card('✅', 'มาเรียนวันนี้', $k['attendancePct'] . '%', $k['present'] . ' จาก ' . $k['students'] . ' คน', 'good') ?>
        <?php if (has_feature('cctv')): ?>
            <?php $cam = camera_counts(accessible_cameras($user)); ?>
            <a class="stat-link" href="cctv.php" aria-label="ดู CCTV"><?= stat_card('📹', 'CCTV', $cam['total'] . ' กล้อง', '🟢 Online ' . $cam['online'] . ' · 🔴 Offline ' . $cam['offline'] . ' · ดู CCTV →', $cam['offline'] ? 'warning' : 'good') ?></a>
        <?php endif; ?>
    </div>

    <section class="card">
        <?php section_head('🚦', 'สถานะปัจจุบันของแต่ละห้อง'); ?>
        <div class="room-live">
            <?php foreach ($rooms as $room): ?>
                <?php $st = classroom_status($room['id']); ?>
                <article class="room-live__card" style="--room-bg: <?= e($st['color'] ?? $room['color']) ?>">
                    <small><?= $room['emoji'] ?> <?= e($room['name']) ?></small>
                    <span class="room-live__emoji" aria-hidden="true"><?= $st['emoji'] ?? '🌤️' ?></span>
                    <strong><?= e($st['text'] ?? 'ยังไม่มีสถานะ') ?></strong>
                    <small><?= $st ? e(thai_time($st['at'])) . ' · ' . e($st['byName']) : '' ?></small>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <div class="dash-grid">
        <section class="card">
            <?php section_head('📈', 'อัตราการมาเรียน 5 วันล่าสุด', '', 'เปอร์เซ็นต์ของนักเรียนที่มาเรียน (รวมมาสาย)'); ?>
            <?= chart_bars(attendance_trend(5, 'school' . $user['school_id']), '%', 100) ?>
        </section>
        <section class="card">
            <?php section_head('🧒', 'นักเรียนแยกตามห้อง'); ?>
            <?= chart_hbars(array_map(fn ($r, $i) => ['label' => $r['name'], 'emoji' => $r['emoji'], 'value' => count(classroom_students($r['id'])), 'color' => CHART_SERIES[$i % 3]], $rooms, array_keys($rooms)), ' คน') ?>
        </section>
        <?php if (has_feature('stars')): ?>
            <section class="card">
                <?php section_head('⭐', 'ดาวสะสมแยกตามห้อง'); ?>
                <?php $stars = scoped('stars'); ?>
                <?= chart_hbars(array_map(fn ($r, $i) => ['label' => $r['name'], 'emoji' => $r['emoji'], 'value' => stars_total(where($stars, 'classroom_id', $r['id'])), 'color' => CHART_SERIES[$i % 3]], $rooms, array_keys($rooms)), ' ดาว') ?>
            </section>
            <section class="card">
                <?php section_head('🩺', 'สุขภาพวันนี้'); ?>
                <?php $health = count_by(today_rows(scoped('healthRecords')), 'condition'); ?>
                <div class="kpi-grid kpi-grid--3">
                    <?= stat_card('💚', 'ปกติ', ($health['normal'] ?? 0) . ' คน', '', 'good') ?>
                    <?= stat_card('🟡', 'เฝ้าระวัง', ($health['watch'] ?? 0) . ' คน', '', 'warning') ?>
                    <?= stat_card('🤒', 'ไม่สบาย', ($health['sick'] ?? 0) . ' คน', '', 'critical') ?>
                </div>
            </section>
        <?php endif; ?>
    </div>
    <a class="btn btn--primary btn--lg btn--block" href="reports.php"><?= icon('report') ?> ดูรายงานฉบับเต็ม</a>
</div>
<?php mk_footer(); ?>
