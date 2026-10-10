<?php
/** Super Admin — compare every school side by side (platform scope). */
declare(strict_types=1);

$rows = [];
foreach (scoped('schools') as $school) {
    $sid = $school['id'];
    $in = fn (string $table) => where(scoped($table), 'school_id', $sid);
    $students = count($in('students'));
    $teachers = count($in('teachers'));
    $rooms = count($in('classrooms'));
    $attendance = today_rows($in('attendance'));
    $here = count(array_filter($attendance, fn ($a) => in_array($a['status'], ['present', 'late'], true)));
    $cams = camera_counts(array_filter(accessible_cameras(), fn ($c) => $c['school_id'] === $sid));
    $stars = stars_total($in('stars'));
    $rows[] = [
        'school'     => $school,
        'rooms'      => $rooms,
        'students'   => $students,
        'teachers'   => $teachers,
        'ratio'      => $teachers ? round($students / $teachers, 1) : 0,
        'attendance' => percent($here, count($attendance)),
        'watch'      => count(array_filter(today_rows($in('healthRecords')), fn ($h) => $h['condition'] !== 'normal')),
        'starsAvg'   => $students ? round($stars / $students, 1) : 0,
        'activities' => $rooms ? (int) round(count(today_rows($in('activities'))) / $rooms) : 0,
        'camOnline'  => $cams['online'],
        'camTotal'   => $cams['total'],
    ];
}
$total = fn (string $key) => array_sum(array_column($rows, $key));
$bars = fn (string $key, string $unit, ?int $max = null) => chart_hbars(
    array_map(fn ($r) => ['label' => $r['school']['shortName'], 'emoji' => $r['school']['emoji'], 'value' => $r[$key]], $rows), $unit, $max);

mk_header(['id' => 'super-compare', 'title' => 'เปรียบเทียบโรงเรียน', 'nav' => 'compare']);
page_title('📊', 'เปรียบเทียบโรงเรียน', count($rows) . ' โรงเรียน · ' . thai_date());
?>
<div data-live id="live-compare">
    <div class="kpi-grid">
        <?= stat_card('🏫', 'โรงเรียน', count($rows), 'แห่ง') ?>
        <?= stat_card('👧', 'นักเรียนรวม', $total('students'), 'คน') ?>
        <?= stat_card('👩‍🏫', 'ครูรวม', $total('teachers'), 'คน') ?>
        <?= stat_card('🚪', 'ห้องเรียนรวม', $total('rooms'), 'ห้อง') ?>
        <?= stat_card('📹', 'กล้อง Online', $total('camOnline') . '/' . $total('camTotal'), 'ตัว') ?>
    </div>

    <section class="card">
        <?php section_head('📋', 'ตารางเปรียบเทียบ', '', 'กดชื่อโรงเรียนเพื่อดูรายละเอียด'); ?>
        <div class="table-wrap">
            <table class="table compare-table">
                <thead><tr>
                    <th scope="col">โรงเรียน</th><th scope="col">สถานะ</th><th scope="col">ห้อง</th><th scope="col">นักเรียน</th>
                    <th scope="col">ครู</th><th scope="col">นักเรียน/ครู</th><th scope="col">มาเรียนวันนี้</th><th scope="col">เฝ้าระวังสุขภาพ</th>
                    <th scope="col">ดาวเฉลี่ย/คน</th><th scope="col">กิจกรรม/ห้อง</th><th scope="col">กล้อง Online</th>
                </tr></thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <th scope="row"><a href="school.php?view=<?= $r['school']['id'] ?>"><?= $r['school']['emoji'] ?> <?= e($r['school']['shortName']) ?></a></th>
                        <td><?= $r['school']['status'] === 'active' ? '<span class="tone tone--good">✅ ใช้งาน</span>' : '<span class="tone tone--warning">🧪 ทดลองใช้</span>' ?></td>
                        <td><?= $r['rooms'] ?></td><td><?= $r['students'] ?></td><td><?= $r['teachers'] ?></td><td><?= $r['ratio'] ?></td>
                        <td><?= $r['attendance'] ?>%</td><td><?= $r['watch'] ?> คน</td><td><?= $r['starsAvg'] ?></td><td><?= $r['activities'] ?></td>
                        <td><?= $r['camOnline'] ?>/<?= $r['camTotal'] ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <div class="report-grid">
        <section class="card"><?php section_head('👧', 'จำนวนนักเรียน'); ?><?= $bars('students', ' คน') ?></section>
        <section class="card"><?php section_head('✅', 'อัตราการมาเรียนวันนี้'); ?><?= $bars('attendance', '%', 100) ?></section>
        <section class="card"><?php section_head('👩‍🏫', 'นักเรียนต่อครู 1 คน', '', 'ยิ่งน้อย ครูดูแลได้ทั่วถึงกว่า'); ?><?= $bars('ratio', ' คน') ?></section>
        <section class="card"><?php section_head('⭐', 'ดาวเฉลี่ยต่อนักเรียน'); ?><?= $bars('starsAvg', ' ดาว') ?></section>
    </div>
</div>
<?php mk_footer(); ?>
