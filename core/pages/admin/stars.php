<?php
/** Admin — star overview of the own school (read only; teachers give stars in their classroom). */
declare(strict_types=1);

$user = current_user();
$rooms = my_classrooms();
$stars = scoped('stars');
$students = scoped('students');
$totals = [];
foreach ($stars as $s) {
    $totals[$s['student_id']] = ($totals[$s['student_id']] ?? 0) + (int) $s['points'];
}
arsort($totals);
usort($stars, fn ($a, $b) => [$b['date'], $b['id']] <=> [$a['date'], $a['id']]);

mk_header(['id' => 'admin-stars', 'title' => 'ดาวสะสม', 'nav' => 'stars']);
page_title('⭐', 'ดาวสะสมทั้งโรงเรียน', $user['school']['name'], '<span class="readonly-badge">👁️ ดูข้อมูล</span>');
?>
<div data-live id="live-admin-stars">
    <div class="kpi-grid kpi-grid--3">
        <?= stat_card('⭐', 'ดาวที่มอบทั้งหมด', number_format(stars_total($stars)), 'ดาว') ?>
        <?= stat_card('🧒', 'นักเรียนที่ได้ดาว', count($totals) . '/' . count($students), 'คน') ?>
        <?= stat_card('📈', 'เฉลี่ยต่อคน', $students ? number_format(stars_total($stars) / count($students), 1) : 0, 'ดาว') ?>
    </div>
    <div class="dash-grid">
        <section class="card">
            <?php section_head('🏫', 'ดาวแยกตามห้อง'); ?>
            <?= chart_hbars(array_map(fn ($r, $i) => ['label' => $r['name'], 'emoji' => $r['emoji'], 'value' => stars_total(where($stars, 'classroom_id', $r['id'])), 'color' => CHART_SERIES[$i % 3]], $rooms, array_keys($rooms)), ' ดาว') ?>
        </section>
        <section class="card">
            <?php section_head('🏆', 'ดาวสูงสุด 10 อันดับ'); ?>
            <ol class="leaderboard">
                <?php foreach (array_slice($totals, 0, 10, true) as $sid => $total): ?>
                    <?php $s = find_row('students', (int) $sid); $room = find_row('classrooms', $s['classroom_id']); ?>
                    <li><span class="leaderboard__rank">⭐</span><?= student_avatar($s, 'sm') ?><strong><?= e($s['nickname']) ?></strong>
                        <span class="hint"><?= e($room['name'] ?? '') ?></span><span class="leaderboard__stars"><?= $total ?></span></li>
                <?php endforeach; ?>
            </ol>
        </section>
    </div>
    <section class="card">
        <?php section_head('🕘', 'ประวัติการให้ดาวล่าสุด'); ?>
        <?php filter_bar('stars', array_column(array_map(fn ($r) => ['k' => $r['id'], 'v' => $r['emoji'] . ' ' . $r['name']], $rooms), 'v', 'k'), 'ค้นหาชื่อนักเรียนหรือเหตุผล...'); ?>
        <ol class="star-history" data-filter-list="stars">
            <?php foreach (array_slice($stars, 0, 120) as $r): ?>
                <?php $s = find_row('students', $r['student_id']); ?>
                <li data-filter-item data-group="<?= (int) $r['classroom_id'] ?>" data-search="<?= e(($s['nickname'] ?? '') . ' ' . $r['reason']) ?>">
                    <span class="star-history__points">+<?= (int) $r['points'] ?> ⭐</span><span><?= e($s['nickname'] ?? '') ?> · <?= e($r['reason']) ?></span><time><?= e(thai_short_date($r['date'])) ?></time></li>
            <?php endforeach; ?>
        </ol>
    </section>
</div>
<?php mk_footer(); ?>
