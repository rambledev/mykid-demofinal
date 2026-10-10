<?php
/** Executive — reports with a mock period filter (today / week / month). Read only, own school. */
declare(strict_types=1);

$user = current_user();
$period = in_array($_GET['period'] ?? '', ['today', 'week', 'month'], true) ? $_GET['period'] : 'today';
$days = period_days($period);
$periodLabel = ['today' => 'วันนี้', 'week' => 'สัปดาห์นี้', 'month' => 'เดือนนี้'][$period];
$rooms = my_classrooms();
$students = scoped('students');
$k = kpis();

// Mock period scaling: today's real numbers × school days, with stable variation.
$scale = fn (int $value, string $salt) => $days === 1 ? $value : (int) round($value * $days * (0.92 + (mk_rand($salt, $period) % 9) / 100));

$statusCounts = [];
foreach (array_filter(scoped('statuses'), fn ($s) => is_today($s['at'])) as $s) {
    $statusCounts[$s['code']] = ($statusCounts[$s['code']] ?? 0) + 1;
}
arsort($statusCounts);
$attCounts = attendance_counts(today_rows(scoped('attendance')));
if ($days > 1) {
    foreach ($attCounts as $code => $n) {
        $attCounts[$code] = $scale($n, 'att' . $code);
    }
}

mk_header(['id' => 'exec-reports', 'title' => 'รายงาน', 'nav' => 'reports']);
page_title('📑', 'รายงานภาพรวม', $user['school']['name'] . ' · ' . $periodLabel,
    '<button type="button" class="btn btn--soft btn--sm" data-action="print">' . icon('print') . ' พิมพ์</button>');
?>
<nav class="chip-row" aria-label="ช่วงเวลา">
    <?php foreach (['today' => 'วันนี้', 'week' => 'สัปดาห์นี้', 'month' => 'เดือนนี้'] as $key => $label): ?>
        <a class="chip-btn<?= $key === $period ? ' is-active' : '' ?>" href="?period=<?= $key ?>"<?= $key === $period ? ' aria-current="true"' : '' ?>><?= e($label) ?></a>
    <?php endforeach; ?>
</nav>
<p class="hint">📌 ตัวเลขสัปดาห์/เดือนเป็นข้อมูลจำลองสำหรับ Demo · <span class="readonly-badge">👁️ อ่านอย่างเดียว</span></p>

<div class="report-grid" data-live id="live-reports">
    <section class="card">
        <?php section_head('👧', '1. สรุปนักเรียน', '<span class="count-badge">' . count($students) . ' คน</span>'); ?>
        <?php $gender = count_by($students, 'gender'); ?>
        <?= chart_hbars(array_map(fn ($r, $i) => ['label' => $r['name'], 'emoji' => $r['emoji'], 'value' => count(classroom_students($r['id'])), 'color' => CHART_SERIES[$i % 3]], $rooms, array_keys($rooms)), ' คน') ?>
        <p class="hint">👦 ชาย <?= $gender['m'] ?? 0 ?> คน · 👧 หญิง <?= $gender['f'] ?? 0 ?> คน</p>
    </section>

    <section class="card">
        <?php section_head('👩‍🏫', '2. สรุปครู', '<span class="count-badge">' . $k['teachers'] . ' คน</span>'); ?>
        <div class="table-wrap"><table class="table">
            <thead><tr><th scope="col">ห้อง</th><th scope="col">ครู</th><th scope="col">อัตราส่วน</th></tr></thead>
            <tbody>
            <?php foreach ($rooms as $r): ?>
                <?php $t = classroom_teachers($r['id']); ?>
                <tr><th scope="row"><?= $r['emoji'] ?> <?= e($r['name']) ?></th><td><?= e(implode(', ', array_column($t, 'nickname'))) ?></td><td>1 : <?= $t ? (int) round(count(classroom_students($r['id'])) / count($t)) : '-' ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    </section>

    <section class="card">
        <?php section_head('🏫', '3. สรุปห้องเรียน', '<span class="count-badge">' . count($rooms) . ' ห้อง</span>'); ?>
        <div class="table-wrap"><table class="table">
            <thead><tr><th scope="col">ห้อง</th><th scope="col">นักเรียน</th><th scope="col">รับได้</th><th scope="col">ใช้ที่นั่ง</th></tr></thead>
            <tbody>
            <?php foreach ($rooms as $r): ?>
                <?php $n = count(classroom_students($r['id'])); ?>
                <tr><th scope="row"><?= $r['emoji'] ?> <?= e($r['name']) ?></th><td><?= $n ?></td><td><?= (int) $r['capacity'] ?></td><td><?= percent($n, (int) $r['capacity']) ?>%</td></tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    </section>

    <section class="card">
        <?php section_head('🎨', '4. สรุปกิจกรรม', '<span class="count-badge">' . $scale($k['activities'], 'act') . ' รายการ</span>', $periodLabel); ?>
        <?= chart_hbars(array_map(fn ($r, $i) => ['label' => $r['name'], 'emoji' => $r['emoji'], 'value' => $scale(count(classroom_activities($r['id'])), 'act' . $r['id']), 'color' => CHART_SERIES[$i % 3]], $rooms, array_keys($rooms)), ' รายการ') ?>
    </section>

    <section class="card">
        <?php section_head('🍱', '5. สรุปอาหาร', '<span class="count-badge">' . $scale($k['foodReady'] * count(mk_catalog()['meals']), 'food') . ' มื้อ</span>', $periodLabel); ?>
        <ul class="menu-list">
            <?php foreach ($rooms as $r): ?>
                <?php $lunch = array_values(array_filter(classroom_food($r['id']), fn ($m) => $m['meal'] === 'lunch'))[0]['items'] ?? []; ?>
                <li><span><?= $r['emoji'] ?> <?= e($r['name']) ?> · กลางวันวันนี้</span><strong><?= e($lunch ? implode(' + ', $lunch) : '-') ?></strong></li>
            <?php endforeach; ?>
        </ul>
        <?php if (has_feature('foodIntake')): ?>
            <?php
            $levels = array_fill_keys(array_column(mk_catalog()['intake'], 'code'), 0);
            foreach (today_rows(scoped('foodIntake')) as $row) {
                $levels[$row['levels']['lunch'] ?? 'none']++;
            }
            $toneColor = ['good' => '#0ca30c', 'warning' => '#fab219', 'serious' => '#ec835a', 'critical' => '#d03b3b'];
            ?>
            <p class="hint">การรับประทานอาหารกลางวัน (วันนี้)</p>
            <?= chart_stack(array_map(fn ($lv) => ['label' => $lv['label'], 'value' => $levels[$lv['code']], 'color' => $toneColor[$lv['tone']], 'emoji' => $lv['emoji']], mk_catalog()['intake'])) ?>
        <?php endif; ?>
    </section>

    <section class="card">
        <?php section_head('🚦', '6. สรุป Status', '', 'จำนวนครั้งที่ครูอัปเดตแต่ละสถานะ · ' . $periodLabel); ?>
        <?= chart_hbars(array_map(function ($code, $n) use ($scale) {
            $def = cat_find('statuses', $code);
            return ['label' => $def['label'], 'emoji' => $def['emoji'], 'value' => $scale($n, 'st' . $code)];
        }, array_keys($statusCounts), $statusCounts), ' ครั้ง') ?>
    </section>

    <section class="card">
        <?php section_head('✅', 'สรุปการเข้าเรียน', '', $periodLabel . ($days > 1 ? ' (นับเป็นคน-วัน)' : '')); ?>
        <?= chart_stack(attendance_parts($attCounts), $days > 1 ? 'คน-วัน' : 'คน') ?>
        <?= chart_bars(attendance_trend(min(10, max(5, $days)), 'school' . $user['school_id']), '%', 100) ?>
    </section>

    <?php if (has_feature('stars')): ?>
        <section class="card">
            <?php section_head('⭐', 'สรุปดาวสะสม & สุขภาพ'); ?>
            <?php $health = count_by(today_rows(scoped('healthRecords')), 'condition'); ?>
            <ul class="menu-list">
                <li><span>⭐ ดาวที่มอบทั้งหมด</span><strong><?= stars_total(scoped('stars')) ?> ดาว</strong></li>
                <li><span>💚 สุขภาพปกติ (วันนี้)</span><strong><?= $health['normal'] ?? 0 ?> คน</strong></li>
                <li><span>🟡 เฝ้าระวัง (วันนี้)</span><strong><?= $health['watch'] ?? 0 ?> คน</strong></li>
                <li><span>😴 นอนกลางวันแล้ว</span><strong><?= count(array_filter(today_rows(scoped('sleepRecords')), fn ($s) => $s['end'] !== '')) ?> คน</strong></li>
            </ul>
        </section>
    <?php endif; ?>
</div>
<?php mk_footer(); ?>
