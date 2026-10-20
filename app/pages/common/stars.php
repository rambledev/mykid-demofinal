<?php
/**
 * ธนาคารความดี — monthly ranking (most stars first) of students, for rewards. Every star counts in the
 * student's bank, in two categories: 🧒 ความดี and 🛵 ขับขี่ปลอดภัย (the parent driving the child safely).
 *
 * Everybody sees the whole school (teachers too — permissions: tableScope stars = school).
 * ONLY teachers give stars, to any student of the school; admin / executive are read-only.
 * /{section}/stars/{YYYY-MM} picks the month (default: this month). Room chips filter the list.
 */
declare(strict_types=1);

$user = current_user();
$month = star_month(route_param('month'));
$base = $user['meta']['dir'] . '/stars';
$canGive = can_write_table($user, 'stars');
$all = scoped('stars');
$students = where(store_rows('students'), 'school_id', $user['school_id']);
$rooms = my_classrooms_all();
$studentsById = array_column($students, null, 'id');
$monthRows = stars_in_month(star_rows(null, $all), $month);
$monthByCategory = stars_by_category($monthRows);
$categories = array_column(mk_catalog()['starCategories'], null, 'code');

$ranking = array_values(array_filter(star_ranking($monthRows, 'student_id', array_keys($studentsById)), fn ($r) => isset($studentsById[$r['id']])));
$ranked = array_filter($ranking, fn ($r) => $r['rank'] !== null);
$byStudent = group_by($monthRows, 'student_id');

$months = star_months($all);
$i = array_search($month, $months, true);
$prev = $months[$i + 1] ?? null;
$next = $i > 0 ? $months[$i - 1] : null;
$giveDefaults = fn (int $studentId, string $category = 'good') => json_attr(public_row('stars', ['category' => $category, 'student_id' => $studentId, 'points' => 3]));

mk_header(['id' => $user['role'] . '-stars', 'title' => STAR_BANK_NAME, 'nav' => 'stars']);
page_title('🏦', STAR_BANK_NAME, $user['school']['name'] . ' · ความดี + ขับขี่ปลอดภัย · อันดับประจำเดือน',
    $canGive ? btn_add('stars', 'ให้ดาว', ['category' => 'good', 'points' => 3]) : '<span class="readonly-badge">👁️ ดูข้อมูล</span>');
?>
<nav class="month-nav" aria-label="เลือกเดือน">
    <?php if ($prev): ?><a class="btn btn--soft btn--sm" href="<?= e(url($base . '/' . $prev)) ?>" aria-label="เดือนก่อนหน้า">‹ <?= e(thai_month_label($prev)) ?></a><?php else: ?><span></span><?php endif; ?>
    <strong class="month-nav__current">📅 <?= e(thai_month_label($month)) ?></strong>
    <?php if ($next): ?><a class="btn btn--soft btn--sm" href="<?= e(url($base . '/' . $next)) ?>" aria-label="เดือนถัดไป"><?= e(thai_month_label($next)) ?> ›</a><?php else: ?><span></span><?php endif; ?>
</nav>

<div data-live id="live-stars">
    <div class="kpi-grid kpi-grid--3">
        <?= stat_card('⭐', 'ดาวสะสมเดือนนี้', number_format(array_sum($monthByCategory)), count($ranked) . '/' . count($students) . ' คนได้ดาว') ?>
        <?= stat_card($categories['good']['emoji'], 'ความดีของนักเรียน', number_format($monthByCategory['good']), 'ดาว') ?>
        <?= stat_card($categories['safe_driving']['emoji'], 'ขับขี่ปลอดภัย', number_format($monthByCategory['safe_driving']), 'ดาว · รวมเข้าบัญชีของลูก') ?>
    </div>

    <?php $roomChips = array_column(array_map(fn ($r) => ['k' => tok('classrooms', $r['id']), 'v' => $r['emoji'] . ' ' . $r['name']], $rooms), 'v', 'k'); ?>
    <section class="card">
        <?php section_head('🏆', 'อันดับนักเรียน · ' . thai_month_label($month), '<span class="count-badge">' . count($ranked) . ' คน</span>', 'ดาวรวมความดี + ขับขี่ปลอดภัย · 3 อันดับแรกรับรางวัลประจำเดือน'); ?>
        <?php filter_bar('rank-students', $roomChips, 'ค้นหาชื่อนักเรียน...'); ?>
        <?php if ($ranked): ?>
            <ol class="leaderboard leaderboard--ranked" data-filter-list="rank-students">
                <?php foreach ($ranked as $r): ?>
                    <?php $s = $studentsById[$r['id']]; $room = find_row('classrooms', $s['classroom_id']); $split = stars_by_category($byStudent[$s['id']] ?? []); ?>
                    <li class="<?= $r['rank'] <= 3 ? 'is-top' : '' ?>" data-filter-item data-group="<?= e(tok('classrooms', $s['classroom_id'])) ?>" data-search="<?= e($s['nickname'] . ' ' . $s['name']) ?>">
                        <span class="leaderboard__rank"><?= rank_badge($r['rank']) ?></span>
                        <?= student_avatar($s, 'sm') ?>
                        <span class="leaderboard__name"><strong><?= e($s['nickname']) ?></strong>
                            <small><?= e(($room['name'] ?? '') . ' · ' . $categories['good']['emoji'] . ' ' . $split['good'] . ' · ' . $categories['safe_driving']['emoji'] . ' ' . $split['safe_driving']) ?></small></span>
                        <span class="leaderboard__stars">⭐ <?= $r['total'] ?></span>
                        <?php if ($canGive): ?>
                            <button type="button" class="btn btn--soft btn--sm" data-action="open-form" data-table="stars" data-defaults="<?= $giveDefaults($s['id']) ?>">+ ดาว</button>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php else: ?>
            <?= empty_state('🌱', 'ยังไม่มีนักเรียนได้ดาวในเดือนนี้') ?>
        <?php endif; ?>
        <?php $zero = array_filter($ranking, fn ($r) => $r['rank'] === null); ?>
        <?php if ($zero): ?>
            <details class="leaderboard-zero">
                <summary>ยังไม่ได้รับดาวในเดือนนี้ <?= count($zero) ?> คน</summary>
                <ul class="chip-row">
                    <?php foreach ($zero as $r): ?>
                        <?php $s = $studentsById[$r['id']]; ?>
                        <li><?php if ($canGive): ?><button type="button" class="chip-btn" data-action="open-form" data-table="stars" data-defaults="<?= $giveDefaults($s['id']) ?>">+ <?= e($s['nickname']) ?></button><?php else: ?><span class="chip"><?= e($s['nickname']) ?></span><?php endif; ?></li>
                    <?php endforeach; ?>
                </ul>
            </details>
        <?php endif; ?>
    </section>

    <section class="card">
        <?php section_head('🕘', 'ประวัติการให้ดาว · ' . thai_month_label($month)); ?>
        <?php if ($monthRows): ?>
            <ol class="star-history">
                <?php foreach (array_slice($monthRows, 0, 80) as $r): ?>
                    <?php
                    $s = find_row('students', $r['student_id']);
                    $cat = $categories[star_category($r)];
                    $who = star_category($r) === 'safe_driving' && $r['parent_id'] ? ' (' . (find_row('users', (int) $r['parent_id'])['name'] ?? 'ผู้ปกครอง') . ')' : '';
                    ?>
                    <li><span class="star-history__points">+<?= (int) $r['points'] ?> ⭐</span><span><?= $cat['emoji'] ?> <?= e(($s['nickname'] ?? '') . $who) ?> · <?= e($r['reason']) ?></span><time><?= e(thai_short_date($r['date'])) ?></time></li>
                <?php endforeach; ?>
            </ol>
        <?php else: ?>
            <?= empty_state('🌱', 'ยังไม่มีการให้ดาวในเดือนนี้') ?>
        <?php endif; ?>
    </section>
</div>
<?php
if ($canGive) {
    render_form_sheet('stars', 'ให้ดาว · ' . STAR_BANK_NAME);
}
mk_footer();
