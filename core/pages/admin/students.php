<?php
/** Admin — student management (whole own school; add / edit / delete). */
declare(strict_types=1);

$rooms = my_classrooms();
mk_header(['id' => 'admin-students', 'title' => 'จัดการนักเรียน', 'nav' => 'students']);
page_title('👧', 'จัดการนักเรียน', 'ทั้งหมด ' . count(scoped('students')) . ' คน', btn_add('students', 'เพิ่มนักเรียน'));
filter_bar('students', array_column(array_map(fn ($r) => ['k' => $r['id'], 'v' => $r['emoji'] . ' ' . $r['name']], $rooms), 'v', 'k'), 'ค้นหาชื่อ ชื่อเล่น หรือผู้ปกครอง...');
?>
<div class="person-grid" data-filter-list="students" data-live id="live-students">
    <?php foreach (scoped('students') as $s): ?>
        <?php $room = find_row('classrooms', $s['classroom_id']); ?>
        <article class="person" data-filter-item data-group="<?= $s['classroom_id'] ?>" data-search="<?= e($s['nickname'] . ' ' . $s['name'] . ' ' . $s['parentName']) ?>">
            <?= student_avatar($s) ?>
            <div class="person__body">
                <strong><?= e($s['nickname']) ?></strong>
                <small><?= e($s['name']) ?></small>
                <span class="chip"><?= $room['emoji'] ?? '' ?> <?= e($room['name'] ?? '-') ?></span>
                <small>👤 <?= e($s['parentName']) ?></small>
            </div>
            <div class="person__actions"><?= btn_edit('students', $s) ?><?= btn_delete('students', $s['id'], $s['nickname']) ?></div>
        </article>
    <?php endforeach; ?>
</div>
<?php
render_form_sheet('students', 'เพิ่มนักเรียน');
mk_footer();
