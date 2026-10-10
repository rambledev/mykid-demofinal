<?php
/**
 * Parent (Package A) — ผลงานของฉัน: works of each of MY children (parentStudents relation),
 * one tab per child when there are several. Images open full screen; several images per work.
 */
declare(strict_types=1);

$user = current_user();
$children = parent_children($user);
$childIds = array_flip(array_column($children, 'id'));
// Relation check on the server: only works of the parent's own children in the parent's school.
$works = array_values(array_filter(store_rows('portfolio'), fn ($w) => isset($childIds[$w['student_id']]) && $w['school_id'] === $user['school_id']));

mk_header(['id' => 'parent-works', 'title' => 'ผลงานของฉัน', 'nav' => 'works']);
page_title('🎨', 'ผลงานของฉัน', count($children) > 1 ? 'เลือกลูกเพื่อดูผลงาน' : 'ผลงานของ' . ($children[0]['nickname'] ?? ''));
if (count($children) > 1) {
    tab_bar('wchild', array_column(array_map(fn ($c) => ['k' => $c['id'], 'v' => $c['nickname']], $children), 'v', 'k'));
}
?>
<div data-live id="live-works">
    <?php foreach ($children as $i => $child): ?>
        <section class="card" data-tab-panel="wchild:<?= $child['id'] ?>"<?= $i ? ' hidden' : '' ?>>
            <div class="pickup-card__child work-child">
                <?= student_avatar($child, 'md') ?>
                <div><strong><?= e($child['nickname']) ?></strong><small><?= e($child['name']) ?></small></div>
            </div>
            <?php render_student_works(where($works, 'student_id', $child['id']), false); ?>
        </section>
    <?php endforeach; ?>
    <?php if (!$children): ?><?= empty_state('🎨', 'ไม่พบข้อมูลบุตรหลาน') ?><?php endif; ?>
</div>
<p class="hint hint--card">🔒 เห็นเฉพาะผลงานของบุตรหลานของคุณ · แตะรูปเพื่อดูเต็มจอ</p>
<?php mk_footer(); ?>
