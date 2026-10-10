<?php
/** Parent (Package A) — the child's health check today. */
declare(strict_types=1);

$user = current_user();
$child = $user['child'];

mk_header(['id' => 'parent-health', 'title' => 'สุขภาพ', 'nav' => 'health']);
page_title('🩺', 'สุขภาพวันนี้', thai_date() . ' · ' . $child['nickname']);
?>
<section class="card" data-live id="live-health">
    <?php render_health_card(student_today('healthRecords', $child['id'])); ?>
</section>
<?php mk_footer(); ?>
