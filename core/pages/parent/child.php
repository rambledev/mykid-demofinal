<?php
/** Parent — the child's profile (no other children's data). */
declare(strict_types=1);

$user = current_user();
$child = $user['child'];
$teachers = classroom_teachers($child['classroom_id']);

mk_header(['id' => 'parent-child', 'title' => 'ลูกของฉัน', 'nav' => 'child']);
page_title('🧒', has_feature('timeline') ? 'โปรไฟล์ลูก' : 'ลูกของฉัน');
?>
<section class="card card--hero-pink"><?php render_child_card($child, $user['classroom'], $user['school'], true); ?></section>

<section class="card">
    <ul class="menu-list">
        <li><span>🆔 รหัสนักเรียน</span><strong><?= e($child['code']) ?></strong></li>
        <li><span>🏷️ ชื่อเล่น</span><strong><?= e($child['nickname']) ?></strong></li>
        <li><span>🎒 ห้องเรียน</span><strong><?= e($user['classroom']['name']) ?></strong></li>
        <li><span>👤 ผู้ปกครอง</span><strong><?= e($child['parentName']) ?></strong></li>
        <?php if (has_feature('stars')): ?><li><span>⭐ ดาวสะสม</span><strong><?= stars_total(student_stars($child['id'])) ?> ดาว</strong></li><?php endif; ?>
    </ul>
</section>

<section class="card">
    <?php section_head('👩‍🏫', 'คุณครูประจำห้อง', '<span class="count-badge">' . count($teachers) . ' คน</span>'); ?>
    <ul class="teacher-list">
        <?php foreach ($teachers as $t): ?>
            <li><span class="teacher-list__avatar" aria-hidden="true"><?= $t['emoji'] ?></span><div><strong><?= e($t['nickname']) ?></strong><small><?= e($t['fullName']) ?></small></div></li>
        <?php endforeach; ?>
    </ul>
</section>

<p class="hint hint--card">🔒 เพื่อความเป็นส่วนตัว ผู้ปกครองจะเห็นเฉพาะข้อมูลของ<?= e($child['nickname']) ?>เท่านั้น ไม่แสดงรายชื่อหรือข้อมูลของเด็กคนอื่น</p>
<?php mk_footer(); ?>
