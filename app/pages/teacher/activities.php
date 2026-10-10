<?php
/** Teacher — manage today's activities of the own classroom. */
declare(strict_types=1);

$user = current_user();
$cid = $user['classroom_id'];

mk_header(['id' => 'teacher-activities', 'title' => 'จัดการกิจกรรม', 'nav' => 'activities']);
page_title('📋', 'จัดการกิจกรรมวันนี้', thai_date());
render_scope_card($user['classroom'], count(classroom_students($cid)), classroom_teachers($cid));
?>
<button type="button" class="btn btn--primary btn--lg btn--block add-cta" data-action="open-form" data-table="activities"><?= icon('plus') ?> เพิ่มกิจกรรม</button>

<section class="card" data-live id="live-activities">
    <?php section_head('🗓️', 'ตารางกิจกรรม', '', 'เรียงตามเวลาอัตโนมัติ · ' . $user['classroom']['name']); ?>
    <?php render_activities(classroom_activities($cid), true); ?>
</section>
<p class="hint hint--card">💡 กิจกรรมที่เพิ่มหรือแก้ไข จะแสดงในหน้าผู้ปกครองของห้อง<?= e($user['classroom']['name']) ?>ทันที</p>

<?php
render_form_sheet('activities', 'เพิ่มกิจกรรม', ['classroom_id']);
mk_footer();
