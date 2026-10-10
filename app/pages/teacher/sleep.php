<?php
/** Teacher (Package A) — nap records for the own classroom. */
declare(strict_types=1);

$user = current_user();
$cid = $user['classroom_id'];

mk_header(['id' => 'teacher-sleep', 'title' => 'การนอน', 'nav' => 'sleep']);
page_title('😴', 'การนอนกลางวัน', thai_date() . ' · ห้อง' . $user['classroom']['name']);
?>
<div data-live id="live-sleep">
    <?php
    $records = today_by_student('sleepRecords');
    $done = array_filter($records, fn ($r) => $r['start'] && $r['end']);
    $avg = $done ? (int) round(array_sum(array_map(fn ($r) => minutes_between($r['start'], $r['end']), $done)) / count($done)) : 0;
    ?>
    <div class="kpi-grid kpi-grid--3">
        <?= stat_card('😴', 'นอนแล้ว', count($done) . ' คน', 'จาก ' . count($records) . ' คน') ?>
        <?= stat_card('⏱️', 'เฉลี่ย', thai_duration($avg) ?: '-') ?>
        <?= stat_card('👀', 'ไม่ได้นอน', count(array_filter($records, fn ($r) => $r['quality'] === 'none')) . ' คน', '', 'warning') ?>
    </div>
    <section class="card">
        <ul class="record-list">
            <?php foreach (classroom_students($cid) as $s): ?>
                <?php $r = $records[$s['id']] ?? null; ?>
                <li class="record">
                    <?= student_avatar($s, 'sm') ?>
                    <div class="record__body"><strong><?= e($s['nickname']) ?></strong>
                        <small><?= $r && $r['start'] ? e($r['start'] . ' → ' . ($r['end'] ?: '...')) . ($r['end'] ? ' · ' . e(thai_duration(minutes_between($r['start'], $r['end']))) : '') : 'ไม่มีข้อมูล' ?></small></div>
                    <?= tone_badge(cat_find('sleep', $r['quality'] ?? null)) ?>
                    <?php if ($r): ?><?= btn_edit('sleepRecords', $r) ?><?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
</div>
<?php
render_form_sheet('sleepRecords', 'บันทึกการนอน');
mk_footer();
