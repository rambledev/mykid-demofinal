<?php
/** Executive — reports for today / this week / this month, counted from the real records. Read only, own school. */
declare(strict_types=1);

$user = current_user();
$period = in_array($_GET['period'] ?? '', ['today', 'week', 'month'], true) ? $_GET['period'] : 'today';
$from = period_start($period);
$days = $period === 'today' ? 1 : 2;
$periodLabel = ['today' => 'วันนี้', 'week' => 'สัปดาห์นี้', 'month' => 'เดือนนี้'][$period];
$rooms = my_classrooms();
$students = scoped('students');
$k = kpis();

$statusCounts = [];
foreach (rows_since(scoped('statuses'), $from, 'at') as $s) {
    $statusCounts[$s['code']] = ($statusCounts[$s['code']] ?? 0) + 1;
}
arsort($statusCounts);
$attCounts = attendance_counts(rows_since(scoped('attendance'), $from));
$periodActivities = rows_since(scoped('activities'), $from);
$periodMeals = 0; // meals with a menu entered in the period
foreach (rows_since(scoped('foodMenus'), $from) as $menu) {
    foreach (mk_catalog()['meals'] as $m) {
        $periodMeals += empty($menu[$m['meal']]) ? 0 : 1;
    }
}
$trend = attendance_trend($period === 'month' ? 22 : ($period === 'week' ? 5 : 5));

mk_header(['id' => 'exec-reports', 'title' => 'รายงาน', 'nav' => 'reports']);
page_title('📑', 'รายงานภาพรวม', $user['school']['name'] . ' · ' . $periodLabel,
    '<button type="button" class="btn btn--soft btn--sm" data-action="print">' . icon('print') . ' พิมพ์</button>');
?>
<nav class="chip-row" aria-label="ช่วงเวลา">
    <?php foreach (['today' => 'วันนี้', 'week' => 'สัปดาห์นี้', 'month' => 'เดือนนี้'] as $key => $label): ?>
        <a class="chip-btn<?= $key === $period ? ' is-active' : '' ?>" href="?period=<?= $key ?>"<?= $key === $period ? ' aria-current="true"' : '' ?>><?= e($label) ?></a>
    <?php endforeach; ?>
</nav>
<p class="hint">📌 นับจากข้อมูลที่บันทึกจริงตั้งแต่ <?= e(thai_short_date($from)) ?> ถึงวันนี้ · <span class="readonly-badge">👁️ อ่านอย่างเดียว</span></p>

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
        <?php section_head('🎨', '4. สรุปกิจกรรม', '<span class="count-badge">' . count($periodActivities) . ' รายการ</span>', $periodLabel); ?>
        <?= chart_hbars(array_map(fn ($r, $i) => ['label' => $r['name'], 'emoji' => $r['emoji'], 'value' => count(where($periodActivities, 'classroom_id', $r['id'])), 'color' => CHART_SERIES[$i % 3]], $rooms, array_keys($rooms)), ' รายการ') ?>
    </section>

    <section class="card">
        <?php section_head('🍱', '5. สรุปอาหาร', '<span class="count-badge">' . $periodMeals . ' มื้อ</span>', $periodLabel); ?>
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
        <?= $statusCounts ? chart_hbars(array_map(function ($code, $n) {
            $def = cat_find('statuses', $code);
            return ['label' => $def['label'] ?? $code, 'emoji' => $def['emoji'] ?? '', 'value' => $n];
        }, array_keys($statusCounts), $statusCounts), ' ครั้ง') : empty_state('🚦', 'ยังไม่มีการอัปเดตสถานะในช่วงนี้') ?>
    </section>

    <section class="card">
        <?php section_head('✅', 'สรุปการเข้าเรียน', '', $periodLabel . ($days > 1 ? ' (นับเป็นคน-วัน)' : '')); ?>
        <?= chart_stack(attendance_parts($attCounts), $days > 1 ? 'คน-วัน' : 'คน') ?>
        <?= $trend ? chart_bars($trend, '%', 100) : empty_state('📊', 'ยังไม่มีข้อมูลการเช็คชื่อ') ?>
    </section>

    <?php if (has_feature('stars')): ?>
        <section class="card">
            <?php section_head('🏦', 'สรุป' . STAR_BANK_NAME . ' & สุขภาพ', '<a class="btn btn--soft btn--sm" href="' . e(rurl('stars')) . '">อันดับประจำเดือน</a>'); ?>
            <?php $health = count_by(today_rows(scoped('healthRecords')), 'condition'); ?>
            <ul class="menu-list">
                <?php $monthStars = stars_in_month(scoped('stars'), date('Y-m')); ?>
                <?php $starSplit = stars_by_category($monthStars); ?>
                <li><span>⭐ ดาวสะสม (<?= e(thai_month_label(date('Y-m'))) ?>)</span><strong><?= array_sum($starSplit) ?> ดาว</strong></li>
                <li><span>🧒 ความดีของนักเรียน</span><strong><?= $starSplit['good'] ?> ดาว</strong></li>
                <li><span>🛵 ขับขี่ปลอดภัย</span><strong><?= $starSplit['safe_driving'] ?> ดาว</strong></li>
                <li><span>💚 สุขภาพปกติ (วันนี้)</span><strong><?= $health['normal'] ?? 0 ?> คน</strong></li>
                <li><span>🟡 เฝ้าระวัง (วันนี้)</span><strong><?= $health['watch'] ?? 0 ?> คน</strong></li>
                <li><span>😴 นอนกลางวันแล้ว</span><strong><?= count(array_filter(today_rows(scoped('sleepRecords')), fn ($s) => $s['end'] !== '')) ?> คน</strong></li>
            </ul>
        </section>
    <?php endif; ?>
</div>
<?php mk_footer(); ?>
