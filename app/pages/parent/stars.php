<?php
/**
 * Parent — ธนาคารความดีขับขี่ปลอดภัย: the child's stars and the parent's own safe-driving stars for a month,
 * with the monthly rank. Only the rank NUMBER is computed from other families' rows — their names / stars
 * are never shown to a parent.
 */
declare(strict_types=1);

$user = current_user();
$child = $user['child'];
$month = star_month(route_param('month'));
$base = $user['meta']['dir'] . '/stars';

$childRows = student_stars($child['id']);
$myRows = parent_stars($user['id']);
$childMonth = stars_in_month($childRows, $month);
$myMonth = stars_in_month($myRows, $month);

// Ranks: the child within the classroom, the parent within the school (server-side, numbers only).
$everything = store_rows('stars');
$classIds = array_column(where(store_rows('students'), 'classroom_id', $child['classroom_id']), 'id'); // whole class (unscoped: numbers only)
$childRank = star_rank_of(star_ranking(stars_in_month(where(star_rows('student', $everything), 'classroom_id', $child['classroom_id']), $month), 'student_id', $classIds), $child['id']);
$parentIds = array_column(array_filter(store_rows('users'), fn ($u) => $u['role'] === 'parent' && $u['school_id'] === $user['school_id']), 'id');
$myRank = star_rank_of(star_ranking(stars_in_month(where(star_rows('parent', $everything), 'school_id', $user['school_id']), $month), 'parent_id', $parentIds), $user['id']);

$months = star_months(array_merge($childRows, $myRows));
$i = array_search($month, $months, true);
$prev = $months[$i + 1] ?? null;
$next = $i > 0 ? $months[$i - 1] : null;
$rankText = fn (?array $r, int $of) => $r && $r['rank'] ? 'อันดับที่ ' . $r['rank'] . ' จาก ' . $of . ($r['rank'] <= 3 ? ' ' . rank_badge($r['rank']) . ' ลุ้นรับรางวัลประจำเดือน' : '') : 'ยังไม่มีอันดับในเดือนนี้';

mk_header(['id' => 'parent-stars', 'title' => STAR_BANK_NAME, 'nav' => 'stars']);
page_title('🏦', STAR_BANK_FULL_NAME, $child['nickname'] . ' และคุณ' . ($user['relation'] ?? 'ผู้ปกครอง'));
?>
<nav class="month-nav" aria-label="เลือกเดือน">
    <?php if ($prev): ?><a class="btn btn--soft btn--sm" href="<?= e(url($base . '/' . $prev)) ?>">‹ <?= e(thai_month_label($prev)) ?></a><?php else: ?><span></span><?php endif; ?>
    <strong class="month-nav__current">📅 <?= e(thai_month_label($month)) ?></strong>
    <?php if ($next): ?><a class="btn btn--soft btn--sm" href="<?= e(url($base . '/' . $next)) ?>"><?= e(thai_month_label($next)) ?> ›</a><?php else: ?><span></span><?php endif; ?>
</nav>

<div data-live id="live-stars">
    <section class="star-hero">
        <?= student_avatar($child, 'xl') ?>
        <div><p><?= e($child['nickname']) ?> · เดือน<?= e(thai_month_label($month)) ?></p><strong>⭐ <?= stars_total($childMonth) ?> ดาว</strong>
            <small><?= e($rankText($childRank, count($classIds))) ?> ในห้อง<?= e($user['classroom']['name']) ?></small></div>
    </section>

    <section class="star-hero star-hero--parent">
        <span class="star-hero__emoji" aria-hidden="true">🛵</span>
        <div><p>ดาวของคุณ<?= e($user['relation'] ?? '') ?> · ขับขี่ปลอดภัย</p><strong>⭐ <?= stars_total($myMonth) ?> ดาว</strong>
            <small><?= e($rankText($myRank, count($parentIds))) ?> ของผู้ปกครองทั้งโรงเรียน</small></div>
    </section>

    <section class="card">
        <?php section_head('💡', 'รับดาวขับขี่ปลอดภัยได้จาก'); ?>
        <ul class="menu-list">
            <?php foreach (mk_catalog()['parentStarReasons'] as $r): ?>
                <li><span><?= e($r['reason']) ?></span><strong>+<?= (int) $r['points'] ?> ⭐</strong></li>
            <?php endforeach; ?>
        </ul>
    </section>

    <section class="card">
        <?php section_head('🧒', 'ความดีของ' . $child['nickname'] . ' · ' . thai_month_label($month)); ?>
        <?php render_star_history($childMonth); ?>
    </section>
    <section class="card">
        <?php section_head('🛵', 'ความดีของคุณ' . ($user['relation'] ?? '') . ' · ' . thai_month_label($month)); ?>
        <?php render_star_history($myMonth); ?>
    </section>
</div>
<?php mk_footer(); ?>
