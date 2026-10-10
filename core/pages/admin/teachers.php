<?php
/** Admin — teacher management and classroom assignment (own school). */
declare(strict_types=1);

$rooms = my_classrooms();
mk_header(['id' => 'admin-teachers', 'title' => 'จัดการครู', 'nav' => 'teachers']);
page_title('👩‍🏫', 'จัดการครู', 'รวม ' . count(scoped('teachers')) . ' คน', btn_add('teachers', 'เพิ่มครู'));
filter_bar('teachers', array_column(array_map(fn ($r) => ['k' => $r['id'], 'v' => $r['emoji'] . ' ' . $r['name']], $rooms), 'v', 'k'), 'ค้นหาชื่อครู...');
?>
<div class="person-grid" data-filter-list="teachers" data-live id="live-teachers">
    <?php foreach (scoped('teachers') as $t): ?>
        <?php $room = find_row('classrooms', $t['classroom_id']); ?>
        <article class="person" data-filter-item data-group="<?= $t['classroom_id'] ?>" data-search="<?= e($t['nickname'] . ' ' . $t['fullName']) ?>">
            <span class="person__emoji" aria-hidden="true"><?= $t['emoji'] ?></span>
            <div class="person__body">
                <strong><?= e($t['nickname']) ?></strong>
                <small><?= e($t['fullName']) ?></small>
                <span class="chip"><?= $room['emoji'] ?? '' ?> ห้อง<?= e($room['name'] ?? '-') ?></span>
            </div>
            <div class="person__actions"><?= btn_edit('teachers', $t) ?><?= btn_delete('teachers', $t['id'], $t['nickname']) ?></div>
        </article>
    <?php endforeach; ?>
</div>
<?php
render_form_sheet('teachers', 'เพิ่มครู');
mk_footer();
