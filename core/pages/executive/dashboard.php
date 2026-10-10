<?php
/** Executive — per-classroom dashboard (read only, own school). */
declare(strict_types=1);

$user = current_user();
$rooms = my_classrooms();
$att = today_rows(scoped('attendance'));

mk_header(['id' => 'exec-dashboard', 'title' => 'แดชบอร์ด', 'nav' => 'dashboard']);
page_title('📊', 'แดชบอร์ดรายห้อง', $user['school']['name'], '<span class="readonly-badge">👁️ อ่านอย่างเดียว</span>');
?>
<div data-live id="live-exec-dashboard">
    <section class="card">
        <?php section_head('✅', 'การมาเรียนวันนี้ทั้งโรงเรียน'); ?>
        <?= chart_stack(attendance_parts(attendance_counts($att))) ?>
    </section>
    <div class="room-grid">
        <?php foreach ($rooms as $room): ?>
            <?php
            $rows = where($att, 'classroom_id', $room['id']);
            $here = count(array_filter($rows, fn ($a) => in_array($a['status'], ['present', 'late'], true)));
            $st = classroom_status($room['id']);
            $lunch = cat_find('meals', 'lunch');
            $menu = classroom_food($room['id']);
            ?>
            <article class="room-card" style="--room-bg: <?= e($room['color']) ?>">
                <header class="room-card__head"><span class="room-card__emoji" aria-hidden="true"><?= $room['emoji'] ?></span><h2><?= e($room['name']) ?></h2></header>
                <div class="room-card__body">
                    <?= chart_ring(percent($here, count($rows)), 'มาเรียน') ?>
                    <ul class="menu-list">
                        <li><span>🧒 นักเรียน</span><strong><?= count(classroom_students($room['id'])) ?> คน</strong></li>
                        <li><span>👩‍🏫 ครู</span><strong><?= count(classroom_teachers($room['id'])) ?> คน</strong></li>
                        <li><span>🎨 กิจกรรม</span><strong><?= count(classroom_activities($room['id'])) ?> รายการ</strong></li>
                        <li><span>🍱 กลางวัน</span><strong><?= e(implode(', ', $menu[array_search('lunch', array_column($menu, 'meal'), true)]['items'] ?? []) ?: '-') ?></strong></li>
                    </ul>
                </div>
                <p class="hint">🚦 <?= $st ? $st['emoji'] . ' ' . e($st['text']) . ' · ' . e(thai_time($st['at'])) : 'ยังไม่มีสถานะ' ?></p>
            </article>
        <?php endforeach; ?>
    </div>
</div>
<?php mk_footer(); ?>
