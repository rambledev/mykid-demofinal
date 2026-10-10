<?php
/** Parent (Package B) — today at school: activity timeline + status history. */
declare(strict_types=1);

$user = current_user();
$cid = $user['child']['classroom_id'];

mk_header(['id' => 'parent-today', 'title' => 'วันนี้ที่โรงเรียน', 'nav' => 'today']);
page_title('🏫', 'วันนี้ที่โรงเรียน', thai_date() . ' · ' . $user['classroom']['name']);
?>
<div data-live id="live-today">
    <section class="card">
        <?php section_head('🗓️', 'กิจกรรมวันนี้'); ?>
        <?php render_activities(classroom_activities($cid), false); ?>
    </section>
    <section class="card">
        <?php section_head('🚦', 'สถานะที่คุณครูอัปเดตวันนี้', '', 'สถานะของทั้งห้องเรียน'); ?>
        <?php render_status_history(classroom_status_history($cid)); ?>
    </section>
</div>
<?php mk_footer(); ?>
