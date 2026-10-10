<?php
/** Teacher — today's menu of the own classroom (+ per-child intake in Package A). */
declare(strict_types=1);

$user = current_user();
$cid = $user['classroom_id'];

mk_header(['id' => 'teacher-food', 'title' => 'จัดการอาหาร', 'nav' => 'food']);
page_title('🍱', 'เมนูอาหารวันนี้', thai_date() . ' · ห้อง' . $user['classroom']['name']);
?>
<p class="hint hint--card">🔒 เมนูนี้เป็นของห้อง<?= e($user['classroom']['name']) ?> เท่านั้น กด “แก้ไข” ที่มื้อที่ต้องการ แล้วกด “บันทึก”</p>

<section data-live id="live-food">
    <?php render_food_cards(classroom_food($cid), $cid, null, classroom_menu_row($cid)); ?>
    <?php if (has_feature('media')): ?>
        <div class="card"><?php render_food_photos(classroom_menu_row($cid), 'อาหารวันนี้ ห้อง' . $user['classroom']['name']); ?></div>
    <?php endif; ?>
</section>

<?php if (has_feature('foodIntake')): ?>
    <?php $intake = today_by_student('foodIntake'); ?>
    <section class="card" data-live id="live-intake">
        <?php section_head('😋', 'การรับประทานอาหารรายคน', '', 'เลือกระดับแล้วระบบบันทึกทันที · ผู้ปกครองเห็นเฉพาะลูกตัวเอง'); ?>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th scope="col">นักเรียน</th><?php foreach (mk_catalog()['meals'] as $m): ?><th scope="col"><?= $m['icon'] ?> <?= e($m['label']) ?></th><?php endforeach; ?></tr></thead>
                <tbody>
                <?php foreach (classroom_students($cid) as $s): ?>
                    <?php $row = $intake[$s['id']] ?? null; ?>
                    <tr>
                        <th scope="row"><span class="table__person"><?= student_avatar($s, 'sm') ?> <?= e($s['nickname']) ?></span></th>
                        <?php foreach (mk_catalog()['meals'] as $m): ?>
                            <td>
                                <?php if ($row): ?>
                                    <select class="input input--sm" data-intake-id="<?= $row['id'] ?>" data-meal="<?= e($m['meal']) ?>" aria-label="<?= e($s['nickname'] . ' ' . $m['label']) ?>">
                                        <?php foreach (mk_catalog()['intake'] as $lv): ?>
                                            <option value="<?= e($lv['code']) ?>"<?= ($row['levels'][$m['meal']] ?? '') === $lv['code'] ? ' selected' : '' ?>><?= $lv['emoji'] ?> <?= e($lv['label']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php else: ?>-<?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>

<?php if (has_feature('media')): ?>
    <section class="card" data-live id="live-food-history">
        <?php section_head('🗓️', 'เมนูย้อนหลัง', '', 'รูปที่เกิน 6 เดือนจะแสดงเป็น “รูปภาพหมดเวลาเก็บไฟล์”'); ?>
        <?php render_food_history(classroom_food_history($cid)); ?>
    </section>
<?php endif; ?>

<?php mk_footer(); ?>
