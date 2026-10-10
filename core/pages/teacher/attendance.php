<?php
/** Teacher (Package A) — today's attendance for the own classroom (pickup: เมนู รับ-ส่ง). */
declare(strict_types=1);

$user = current_user();
$cid = $user['classroom_id'];
$students = classroom_students($cid);

mk_header(['id' => 'teacher-attendance', 'title' => 'เช็คชื่อ', 'nav' => 'attendance']);
page_title('✅', 'เช็คชื่อวันนี้', thai_date() . ' · ห้อง' . $user['classroom']['name']);
?>
<div data-live id="live-attendance">
    <?php $att = today_by_student('attendance'); ?>
    <section class="card">
        <?php section_head('📊', 'สรุปการมาเรียน', '<span class="count-badge">' . count($students) . ' คน</span>'); ?>
        <?= chart_stack(attendance_parts(attendance_counts($att))) ?>
    </section>

    <section class="card">
        <?php section_head('🧒', 'รายชื่อนักเรียน', '', 'แตะสถานะเพื่อบันทึกทันที'); ?>
        <?php filter_bar('attendance', array_column(array_map(fn ($a) => ['k' => $a['code'], 'v' => $a['emoji'] . ' ' . $a['label']], mk_catalog()['attendance']), 'v', 'k'), 'ค้นหาชื่อนักเรียน...'); ?>
        <ul class="att-list" data-filter-list="attendance">
            <?php foreach ($students as $s): ?>
                <?php render_attendance_row($s, $att[$s['id']] ?? null, true); ?>
            <?php endforeach; ?>
        </ul>
    </section>
</div>
<?php mk_footer(); ?>
