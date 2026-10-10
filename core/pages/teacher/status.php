<?php
/** Teacher — change the classroom status (whole classroom, manual). */
declare(strict_types=1);

$user = current_user();
$cid = $user['classroom_id'];
$status = classroom_status($cid);

mk_header(['id' => 'teacher-status', 'title' => 'เปลี่ยนสถานะ', 'nav' => 'status']);
render_scope_card($user['classroom'], count(classroom_students($cid)), classroom_teachers($cid));
?>
<div data-live id="live-status">
    <section class="status-hero" style="--status-bg: <?= e($status['color'] ?? '#F4F6FB') ?>">
        <p class="status-hero__live"><span class="live-dot live-dot--lg" aria-hidden="true"></span>สถานะปัจจุบัน · <?= e($user['classroom']['name']) ?></p>
        <span class="status-hero__emoji" aria-hidden="true"><?= $status['emoji'] ?? '🌤️' ?></span>
        <strong class="status-hero__label"><?= $status ? 'กำลัง' . e($status['text']) : 'ยังไม่มีสถานะวันนี้' ?></strong>
        <p class="status-hero__time"><?= $status ? 'เวลา ' . e(thai_time($status['at'])) . ' · โดย ' . e($status['byName']) : 'เลือกสถานะด้านล่างเพื่อเริ่มต้น' ?></p>
    </section>

    <section class="card">
        <?php section_head('🚦', 'เปลี่ยนสถานะ', '', 'แตะเพื่ออัปเดตทันที'); ?>
        <?php render_status_picker($status); ?>
        <p class="hint">💡 สถานะนี้ใช้กับเด็กทุกคนในห้อง <?= e($user['classroom']['name']) ?> และผู้ปกครองจะเห็นทันที</p>
    </section>

    <section class="card">
        <?php section_head('🕘', 'ประวัติสถานะวันนี้'); ?>
        <?php render_status_history(classroom_status_history($cid)); ?>
    </section>
</div>
<?php mk_footer(); ?>
