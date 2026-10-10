<?php
/** Admin — classrooms of the own school. */
declare(strict_types=1);

mk_header(['id' => 'admin-classrooms', 'title' => 'ห้องเรียน', 'nav' => 'classrooms']);
page_title('🏫', 'ห้องเรียน', count(my_classrooms()) . ' ห้อง', btn_add('classrooms', 'เพิ่มห้อง'));
?>
<div class="room-grid" data-live id="live-classrooms">
    <?php foreach (my_classrooms() as $room): ?>
        <?php $teachers = classroom_teachers($room['id']); $st = classroom_status($room['id']); ?>
        <article class="room-card" style="--room-bg: <?= e($room['color']) ?>">
            <header class="room-card__head">
                <span class="room-card__emoji" aria-hidden="true"><?= $room['emoji'] ?></span>
                <h2><?= e($room['name']) ?></h2>
                <div class="person__actions"><?= btn_edit('classrooms', $room) ?><?= btn_delete('classrooms', $room['id'], $room['name']) ?></div>
            </header>
            <div class="stat-row">
                <div class="stat"><strong><?= count(classroom_students($room['id'])) ?></strong><span>นักเรียน</span></div>
                <div class="stat"><strong><?= count($teachers) ?></strong><span>ครู</span></div>
                <div class="stat"><strong><?= count(classroom_activities($room['id'])) ?></strong><span>กิจกรรม</span></div>
            </div>
            <p class="hint">👩‍🏫 <?= e($teachers ? implode(', ', array_column($teachers, 'nickname')) : 'ยังไม่มีครูประจำห้อง') ?></p>
            <p class="hint">🚦 <?= $st ? $st['emoji'] . ' ' . e($st['text']) . ' · ' . e(thai_time($st['at'])) : 'ยังไม่มีสถานะวันนี้' ?></p>
        </article>
    <?php endforeach; ?>
</div>
<?php
render_form_sheet('classrooms', 'เพิ่มห้องเรียน');
mk_footer();
