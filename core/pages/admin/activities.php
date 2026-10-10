<?php
/** Admin — daily activities per classroom (own school). */
declare(strict_types=1);

$rooms = my_classrooms();
mk_header(['id' => 'admin-activities', 'title' => 'กิจกรรมประจำวัน', 'nav' => 'activities']);
page_title('🎨', 'กิจกรรมประจำวัน', 'เลือกห้องเพื่อจัดการ');
tab_bar('rooms', array_column(array_map(fn ($r) => ['k' => $r['id'], 'v' => $r['emoji'] . ' ' . $r['name']], $rooms), 'v', 'k'));
?>
<div data-live id="live-admin-activities">
    <?php foreach ($rooms as $i => $room): ?>
        <section class="card" data-tab-panel="rooms:<?= $room['id'] ?>"<?= $i ? ' hidden' : '' ?>>
            <?php section_head($room['emoji'], 'ห้อง' . $room['name'], btn_add('activities', 'เพิ่มกิจกรรม', ['classroom_id' => $room['id']])); ?>
            <?php render_activities(classroom_activities($room['id']), true); ?>
        </section>
    <?php endforeach; ?>
</div>
<?php
render_form_sheet('activities', 'เพิ่มกิจกรรม');
mk_footer();
