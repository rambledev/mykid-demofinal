<?php
/** Parent (Package A) — the child's development. */
declare(strict_types=1);

$user = current_user();
$child = $user['child'];
$dev = where(scoped('development'), 'student_id', $child['id'])[0] ?? null;
$avg = $dev ? (int) round(array_sum(array_map(fn ($d) => $dev[$d['code']], mk_catalog()['devDomains'])) / count(mk_catalog()['devDomains'])) : 0;

mk_header(['id' => 'parent-development', 'title' => 'พัฒนาการ', 'nav' => 'development']);
page_title('🌱', 'พัฒนาการ', $child['nickname'] . ($dev ? ' · อัปเดต ' . thai_short_date($dev['updated']) : ''));
?>
<div data-live id="live-development">
    <?php if ($dev): ?>
        <section class="card dev-summary">
            <?= chart_ring($avg, 'ภาพรวม') ?>
            <p class="hint">💬 <?= e($dev['note']) ?></p>
        </section>
        <section class="card"><?php section_head('📈', 'แยกตามด้าน', '', 'คะแนน 0–100'); ?><?php render_dev_bars($dev); ?></section>
    <?php else: ?>
        <?= empty_state('🌱', 'คุณครูยังไม่ได้บันทึกพัฒนาการ') ?>
    <?php endif; ?>
</div>
<?php mk_footer(); ?>
