<?php
/** Admin (Package A) — attendance of the whole school today. */
declare(strict_types=1);

$rooms = my_classrooms();
mk_header(['id' => 'admin-attendance', 'title' => 'การมาเรียน', 'nav' => 'attendance']);
page_title('✅', 'การมาเรียนวันนี้', thai_date());
?>
<div data-live id="live-admin-attendance">
    <?php $att = today_by_student('attendance'); ?>
    <div class="dash-grid">
        <section class="card">
            <?php section_head('📊', 'ทั้งโรงเรียน'); ?>
            <?= chart_stack(attendance_parts(attendance_counts($att))) ?>
        </section>
        <section class="card">
            <?php section_head('🏫', 'มาเรียนแยกตามห้อง (%)'); ?>
            <?= chart_hbars(array_map(function ($room, $i) use ($att) {
                $rows = array_filter($att, fn ($a) => $a['classroom_id'] === $room['id']);
                $here = count(array_filter($rows, fn ($a) => in_array($a['status'], ['present', 'late'], true)));
                return ['label' => $room['name'], 'emoji' => $room['emoji'], 'value' => percent($here, count($rows)), 'color' => CHART_SERIES[$i % 3]];
            }, $rooms, array_keys($rooms)), '%', 100) ?>
        </section>
    </div>
    <section class="card">
        <?php filter_bar('att', array_column(array_map(fn ($a) => ['k' => $a['code'], 'v' => $a['emoji'] . ' ' . $a['label']], mk_catalog()['attendance']), 'v', 'k'), 'ค้นหาชื่อนักเรียน...'); ?>
        <ul class="att-list" data-filter-list="att">
            <?php foreach (scoped('students') as $s): ?><?php render_attendance_row($s, $att[$s['id']] ?? null, true); ?><?php endforeach; ?>
        </ul>
    </section>
</div>
<?php mk_footer(); ?>
