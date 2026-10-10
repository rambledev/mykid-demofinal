<?php
/** Parent — today's menu of the child's classroom (+ how much the child ate, Package A). */
declare(strict_types=1);

$user = current_user();
$child = $user['child'];

mk_header(['id' => 'parent-food', 'title' => 'อาหารวันนี้', 'nav' => 'food']);
page_title('🍽️', 'อาหารวันนี้', 'เมนูที่' . $child['nickname'] . 'ได้ทานวันนี้');
?>
<section data-live id="live-food">
    <?php render_food_cards(classroom_food($child['classroom_id']), null, has_feature('foodIntake') ? student_today('foodIntake', $child['id']) : null, classroom_menu_row($child['classroom_id'])); ?>
    <?php if (has_feature('media')): ?>
        <div class="card"><?php render_food_photos(classroom_menu_row($child['classroom_id']), 'อาหารวันนี้'); ?></div>
    <?php endif; ?>
</section>
<?php if (has_feature('media')): ?>
    <section class="card">
        <?php section_head('🗓️', 'เมนูย้อนหลัง'); ?>
        <?php render_food_history(classroom_food_history($child['classroom_id'])); ?>
    </section>
<?php endif; ?>
<?php mk_footer(); ?>
