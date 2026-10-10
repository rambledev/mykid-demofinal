<?php
/** Parent dashboard — own child only (student_id from the session). */
declare(strict_types=1);

$user = current_user();
$child = $user['child'];
$cid = $child['classroom_id'];
$room = $user['classroom'];
$full = has_feature('timeline');

mk_header(['id' => 'parent-home', 'title' => 'หน้าหลักผู้ปกครอง', 'nav' => 'home']);
?>
<section class="greeting greeting--parent">
    <div>
        <p class="greeting__hello">สวัสดีค่ะ คุณ<?= e($user['relation'] ?? 'แม่') ?> <span aria-hidden="true">💗</span></p>
        <p class="greeting__child"><?= student_avatar($child, 'sm') ?> <?= e($child['nickname']) ?> · <?= e($room['name']) ?></p>
        <p class="greeting__date">📅 วันนี้<?= e(thai_date()) ?></p>
    </div>
    <span class="greeting__clock" data-clock aria-label="เวลาปัจจุบัน"><?= date('H:i') ?></span>
</section>

<div class="dash-grid" data-live id="live-parent-dash">
    <section class="dash-grid__status" aria-label="สถานะปัจจุบันของลูก">
        <?php if ($full): ?><p class="question">❓ ตอนนี้ลูกกำลังทำอะไร?</p><?php endif; ?>
        <?php render_status_card(classroom_status($cid), $room, 'ตอนนี้' . $child['nickname'] . 'กำลัง...', false); ?>
    </section>

    <?php if (has_feature('studentStatus')): ?>
        <?php render_student_status_card(today_student_statuses()[$child['id']] ?? null, $child['nickname']); ?>
    <?php endif; ?>

    <section class="card">
        <?php if ($full): ?><p class="question">❓ วันนี้มีกิจกรรมอะไร?</p><?php endif; ?>
        <?php section_head('🏫', 'วันนี้ที่โรงเรียน', '<a class="btn btn--soft btn--sm" href="' . ($full ? 'timeline.php' : 'activities.php') . '">ดูทั้งหมด</a>'); ?>
        <?php render_activities(classroom_activities($cid), false, true); ?>
    </section>

    <section class="card">
        <?php if ($full): ?><p class="question">❓ วันนี้กินอะไร?</p><?php endif; ?>
        <?php section_head('🍽️', 'อาหารวันนี้', '<a class="btn btn--soft btn--sm" href="food.php">ดูเมนู</a>'); ?>
        <?php render_food_summary(classroom_food($cid), has_feature('foodIntake') ? student_today('foodIntake', $child['id']) : null); ?>
    </section>

    <?php if ($full): ?>
        <section class="card">
            <p class="question">❓ วันนี้นอนหรือยัง?</p>
            <?php section_head('😴', 'การนอนกลางวัน', '<a class="btn btn--soft btn--sm" href="sleep.php">รายละเอียด</a>'); ?>
            <?php render_sleep_card(student_today('sleepRecords', $child['id'])); ?>
        </section>

        <section class="card">
            <p class="question">❓ มีประกาศอะไรจากโรงเรียน?</p>
            <?php section_head('📢', 'ประกาศล่าสุด', '<a class="btn btn--soft btn--sm" href="notifications.php">ทั้งหมด</a>'); ?>
            <?php
            $notes = scoped('notifications');
            usort($notes, fn ($a, $b) => strcmp($b['at'], $a['at']));
            render_notification_list(array_slice($notes, 0, 3));
            ?>
        </section>

        <section class="card dash-grid__wide">
            <?php section_head('🧩', 'ดูข้อมูลของ' . $child['nickname']); ?>
            <nav class="menu-grid menu-grid--compact" aria-label="ฟีเจอร์ผู้ปกครอง">
                <?php foreach (array_filter(nav_items('parent'), fn ($i) => !$i[4] && $i[0] !== 'account') as [$key, $label, $href, $iconName]): ?>
                    <a class="menu-grid__item" href="<?= e(url($href)) ?>"><span class="menu-grid__icon"><?= icon($iconName) ?></span><span><?= e($label) ?></span></a>
                <?php endforeach; ?>
            </nav>
        </section>
    <?php endif; ?>

    <?php if (has_feature('cctv') && ($cams = accessible_cameras($user))): ?>
        <section class="card">
            <?php section_head('📹', 'กล้องวงจรปิด', '<a class="btn btn--soft btn--sm" href="cctv.php">ทั้งหมด</a>', 'ดูกิจกรรมของ' . $child['nickname'] . 'แบบ Realtime'); ?>
            <?php render_cctv_mini($cams, 3); ?>
        </section>
    <?php endif; ?>

    <section class="card">
        <?php section_head('🧒', 'ข้อมูลลูก'); ?>
        <?php render_child_card($child, $room, $user['school']); ?>
    </section>
</div>
<?php mk_footer(); ?>
