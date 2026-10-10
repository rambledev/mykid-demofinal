<?php
/** Admin (Package A) — health of the whole school today. */
declare(strict_types=1);

mk_header(['id' => 'admin-health', 'title' => 'ข้อมูลสุขภาพ', 'nav' => 'health']);
page_title('🩺', 'ข้อมูลสุขภาพวันนี้', thai_date());
?>
<div data-live id="live-admin-health">
    <?php $records = today_by_student('healthRecords'); ?>
    <div class="kpi-grid kpi-grid--3">
        <?= stat_card('💚', 'ปกติ', count(array_filter($records, fn ($h) => $h['condition'] === 'normal')) . ' คน', '', 'good') ?>
        <?= stat_card('🟡', 'เฝ้าระวัง', count(array_filter($records, fn ($h) => $h['condition'] === 'watch')) . ' คน', '', 'warning') ?>
        <?= stat_card('🤒', 'ไม่สบาย', count(array_filter($records, fn ($h) => $h['condition'] === 'sick')) . ' คน', '', 'critical') ?>
    </div>
    <section class="card">
        <?php filter_bar('health', cat_options('health'), 'ค้นหาชื่อนักเรียน...'); ?>
        <div class="table-wrap">
            <table class="table" data-filter-list="health">
                <thead><tr><th scope="col">นักเรียน</th><th scope="col">ห้อง</th><th scope="col">อุณหภูมิ</th><th scope="col">สุขภาพ</th><th scope="col">อาการ</th><th scope="col"><span class="visually-hidden">แก้ไข</span></th></tr></thead>
                <tbody>
                <?php foreach (scoped('students') as $s): ?>
                    <?php $h = $records[$s['id']] ?? null; ?>
                    <tr data-filter-item data-group="<?= e($h['condition'] ?? '') ?>" data-search="<?= e($s['nickname']) ?>">
                        <th scope="row"><span class="table__person"><?= student_avatar($s, 'sm') ?> <?= e($s['nickname']) ?></span></th>
                        <td><?= e(find_row('classrooms', $s['classroom_id'])['name'] ?? '') ?></td>
                        <td><?= $h ? e(number_format((float) $h['temperature'], 1)) . '°C' : '-' ?></td>
                        <td><?= tone_badge(cat_find('health', $h['condition'] ?? null), 'ยังไม่ได้ตรวจ') ?></td>
                        <td><?= e($h['symptoms'] ?? '-') ?></td>
                        <td><?php if ($h): ?><?= btn_edit('healthRecords', $h) ?><?php endif; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
<?php
render_form_sheet('healthRecords', 'บันทึกสุขภาพ');
mk_footer();
