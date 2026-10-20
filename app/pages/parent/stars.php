<?php
/**
 * Parent — ธนาคารความดี of the child for a month: one bank, two categories (🧒 ความดี + 🛵 ขับขี่ปลอดภัย —
 * the parent driving the child safely counts in the child's bank), with the monthly rank in the classroom.
 * Only the rank NUMBER is computed from other families' rows — their names / stars are never shown.
 */
declare(strict_types=1);

$user = current_user();
$child = $user['child'];
$month = star_month(route_param('month'));
$base = $user['meta']['dir'] . '/stars';
$categories = array_column(mk_catalog()['starCategories'], null, 'code');

$childRows = student_stars($child['id']);
$childMonth = stars_in_month($childRows, $month);
$split = stars_by_category($childMonth);

// Rank within the classroom on the combined bank (unscoped rows: numbers only).
$classIds = array_column(where(store_rows('students'), 'classroom_id', $child['classroom_id']), 'id');
$classRows = array_filter(store_rows('stars'), fn ($r) => in_array($r['student_id'], $classIds, true));
$childRank = star_rank_of(star_ranking(stars_in_month(star_rows(null, $classRows), $month), 'student_id', $classIds), $child['id']);

$months = star_months($childRows);
$i = array_search($month, $months, true);
$prev = $months[$i + 1] ?? null;
$next = $i > 0 ? $months[$i - 1] : null;
$rankText = fn (?array $r, int $of) => $r && $r['rank'] ? 'อันดับที่ ' . $r['rank'] . ' จาก ' . $of . ($r['rank'] <= 3 ? ' ' . rank_badge($r['rank']) . ' ลุ้นรับรางวัลประจำเดือน' : '') : 'ยังไม่มีอันดับในเดือนนี้';

mk_header(['id' => 'parent-stars', 'title' => STAR_BANK_NAME, 'nav' => 'stars']);
page_title('🏦', STAR_BANK_NAME, $child['nickname'] . ' · ความดี + ขับขี่ปลอดภัย');
?>
<nav class="month-nav" aria-label="เลือกเดือน">
    <?php if ($prev): ?><a class="btn btn--soft btn--sm" href="<?= e(url($base . '/' . $prev)) ?>">‹ <?= e(thai_month_label($prev)) ?></a><?php else: ?><span></span><?php endif; ?>
    <strong class="month-nav__current">📅 <?= e(thai_month_label($month)) ?></strong>
    <?php if ($next): ?><a class="btn btn--soft btn--sm" href="<?= e(url($base . '/' . $next)) ?>"><?= e(thai_month_label($next)) ?> ›</a><?php else: ?><span></span><?php endif; ?>
</nav>

<div data-live id="live-stars">
    <section class="star-hero">
        <?= student_avatar($child, 'xl') ?>
        <div><p><?= e($child['nickname']) ?> · เดือน<?= e(thai_month_label($month)) ?></p><strong>⭐ <?= array_sum($split) ?> ดาว</strong>
            <small><?= e($rankText($childRank, count($classIds))) ?> ในห้อง<?= e($user['classroom']['name'] ?? '') ?></small></div>
    </section>

    <section class="card">
        <?php section_head('📊', 'ดาวสะสมแยกตามหมวด · ' . thai_month_label($month)); ?>
        <ul class="menu-list">
            <?php foreach ($categories as $code => $cat): ?>
                <li><span><?= $cat['emoji'] ?> <?= e($cat['label']) ?></span><strong><?= $split[$code] ?> ⭐</strong></li>
            <?php endforeach; ?>
        </ul>
        <p class="hint">ดาวขับขี่ปลอดภัยที่คุณได้ตอนรับ-ส่ง นับรวมเข้าธนาคารความดีของ<?= e($child['nickname']) ?></p>
    </section>

    <section class="card">
        <?php section_head('🛵', 'รับดาวขับขี่ปลอดภัยได้จาก'); ?>
        <ul class="menu-list">
            <?php foreach (mk_catalog()['parentStarReasons'] as $r): ?>
                <li><span><?= e($r['reason']) ?></span><strong>+<?= (int) $r['points'] ?> ⭐</strong></li>
            <?php endforeach; ?>
        </ul>
    </section>

    <section class="card">
        <?php section_head('🕘', 'ประวัติดาวของ' . $child['nickname'] . ' · ' . thai_month_label($month)); ?>
        <?php render_star_history($childMonth); ?>
    </section>
</div>
<?php mk_footer(); ?>
