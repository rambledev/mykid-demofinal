<?php
/** Parent (Package A) — the child's stars. */
declare(strict_types=1);

$user = current_user();
$child = $user['child'];
$rows = student_stars($child['id']);

mk_header(['id' => 'parent-stars', 'title' => 'ดาวสะสม', 'nav' => 'stars']);
page_title('⭐', 'ดาวสะสม', $child['nickname']);
?>
<div data-live id="live-stars">
    <section class="star-hero">
        <?= student_avatar($child, 'xl') ?>
        <div><p><?= e($child['nickname']) ?></p><strong>⭐ <?= stars_total($rows) ?> ดาว</strong><small>สะสมจากความตั้งใจในห้องเรียน</small></div>
    </section>
    <section class="card">
        <?php section_head('🕘', 'ประวัติการได้ดาว'); ?>
        <?php render_star_history($rows); ?>
    </section>
</div>
<?php mk_footer(); ?>
