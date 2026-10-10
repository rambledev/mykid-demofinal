<?php
/**
 * ธนาคารความดีขับขี่ปลอดภัย — monthly ranking (most stars first) of students and parents, for rewards.
 *
 * Admin / Executive: the whole school · Teacher: the own classroom (scoped() limits the rows).
 * /{section}/stars/{YYYY-MM} picks the month (default: this month). Admin and teacher can give stars;
 * executive is read-only.
 */
declare(strict_types=1);

$user = current_user();
$month = star_month(route_param('month'));
$base = $user['meta']['dir'] . '/stars';
$canGive = can_write_table($user, 'stars');
$isTeacher = $user['role'] === 'teacher';

$all = scoped('stars');
$students = $isTeacher ? classroom_students($user['classroom_id']) : scoped('students');
$studentsById = array_column($students, null, 'id');
$monthStudent = stars_in_month(star_rows('student', $all), $month);
$monthParent = stars_in_month(star_rows('parent', $all), $month);

$studentRanking = array_values(array_filter(star_ranking($monthStudent, 'student_id', array_keys($studentsById)), fn ($r) => isset($studentsById[$r['id']])));
// Parents of the students in scope (first linked account per child).
$parentChild = [];
foreach ($students as $s) {
    foreach (student_parent_ids($s['id']) as $pid) {
        $parentChild[$pid] ??= $s;
    }
}
$parentRanking = star_ranking($monthParent, 'parent_id', array_keys($parentChild));
$rankedStudents = array_filter($studentRanking, fn ($r) => $r['rank'] !== null);
$rankedParents = array_filter($parentRanking, fn ($r) => $r['rank'] !== null);

$months = star_months($all);
$i = array_search($month, $months, true);
$prev = $months[$i + 1] ?? null;
$next = $i > 0 ? $months[$i - 1] : null;

mk_header(['id' => $user['role'] . '-stars', 'title' => STAR_BANK_NAME, 'nav' => 'stars']);
page_title('🏦', STAR_BANK_FULL_NAME, ($isTeacher ? 'ห้อง' . $user['classroom']['name'] : $user['school']['name']) . ' · อันดับประจำเดือน',
    $canGive ? btn_add('stars', 'ให้ดาว', ['recipient' => 'student', 'points' => 3]) : '<span class="readonly-badge">👁️ ดูข้อมูล</span>');
?>
<nav class="month-nav" aria-label="เลือกเดือน">
    <?php if ($prev): ?><a class="btn btn--soft btn--sm" href="<?= e(url($base . '/' . $prev)) ?>" aria-label="เดือนก่อนหน้า">‹ <?= e(thai_month_label($prev)) ?></a><?php else: ?><span></span><?php endif; ?>
    <strong class="month-nav__current">📅 <?= e(thai_month_label($month)) ?></strong>
    <?php if ($next): ?><a class="btn btn--soft btn--sm" href="<?= e(url($base . '/' . $next)) ?>" aria-label="เดือนถัดไป"><?= e(thai_month_label($next)) ?> ›</a><?php else: ?><span></span><?php endif; ?>
</nav>

<div data-live id="live-stars">
    <div class="kpi-grid kpi-grid--3">
        <?= stat_card('🧒', 'ดาวนักเรียนเดือนนี้', number_format(stars_total($monthStudent)), count($rankedStudents) . '/' . count($students) . ' คนได้ดาว') ?>
        <?= stat_card('🛵', 'ดาวผู้ปกครอง (ขับขี่ปลอดภัย)', number_format(stars_total($monthParent)), count($rankedParents) . '/' . count($parentChild) . ' คนได้ดาว') ?>
        <?= stat_card('🏆', 'รางวัลประจำเดือน', 'อันดับ 1–3', 'ของนักเรียนและผู้ปกครอง') ?>
    </div>

    <?php tab_bar('starbank', ['students' => '🧒 นักเรียน', 'parents' => '👨‍👩‍👧 ผู้ปกครอง']); ?>

    <section class="card" data-tab-panel="starbank:students">
        <?php section_head('🏆', 'อันดับนักเรียน · ' . thai_month_label($month), '<span class="count-badge">' . count($rankedStudents) . ' คน</span>', 'เรียงจากดาวมากไปน้อย · 3 อันดับแรกรับรางวัลประจำเดือน'); ?>
        <?php if ($rankedStudents): ?>
            <ol class="leaderboard leaderboard--ranked">
                <?php foreach ($rankedStudents as $r): ?>
                    <?php $s = $studentsById[$r['id']]; $room = find_row('classrooms', $s['classroom_id']); ?>
                    <li class="<?= $r['rank'] <= 3 ? 'is-top' : '' ?>">
                        <span class="leaderboard__rank"><?= rank_badge($r['rank']) ?></span>
                        <?= student_avatar($s, 'sm') ?>
                        <span class="leaderboard__name"><strong><?= e($s['nickname']) ?></strong><small><?= e(($room['name'] ?? '') . ' · ' . $r['count'] . ' ครั้ง') ?></small></span>
                        <span class="leaderboard__stars">⭐ <?= $r['total'] ?></span>
                        <?php if ($canGive): ?>
                            <button type="button" class="btn btn--soft btn--sm" data-action="open-form" data-table="stars" data-defaults="<?= json_attr(public_row('stars', ['recipient' => 'student', 'student_id' => $s['id'], 'points' => 3])) ?>">+ ดาว</button>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php else: ?>
            <?= empty_state('🌱', 'ยังไม่มีนักเรียนได้ดาวในเดือนนี้') ?>
        <?php endif; ?>
        <?php $zero = array_filter($studentRanking, fn ($r) => $r['rank'] === null); ?>
        <?php if ($zero): ?>
            <details class="leaderboard-zero">
                <summary>ยังไม่ได้รับดาวในเดือนนี้ <?= count($zero) ?> คน</summary>
                <ul class="chip-row">
                    <?php foreach ($zero as $r): ?>
                        <?php $s = $studentsById[$r['id']]; ?>
                        <li><?php if ($canGive): ?><button type="button" class="chip-btn" data-action="open-form" data-table="stars" data-defaults="<?= json_attr(public_row('stars', ['recipient' => 'student', 'student_id' => $s['id'], 'points' => 3])) ?>">+ <?= e($s['nickname']) ?></button><?php else: ?><span class="chip"><?= e($s['nickname']) ?></span><?php endif; ?></li>
                    <?php endforeach; ?>
                </ul>
            </details>
        <?php endif; ?>
    </section>

    <section class="card" data-tab-panel="starbank:parents" hidden>
        <?php section_head('🛵', 'อันดับผู้ปกครอง · ' . thai_month_label($month), '<span class="count-badge">' . count($rankedParents) . ' คน</span>', 'ขับขี่ปลอดภัยเวลารับ-ส่งลูก · เรียงจากดาวมากไปน้อย'); ?>
        <?php if ($rankedParents): ?>
            <ol class="leaderboard leaderboard--ranked">
                <?php foreach ($rankedParents as $r): ?>
                    <?php $p = find_row('users', $r['id']); $child = $parentChild[$r['id']] ?? null; ?>
                    <li class="<?= $r['rank'] <= 3 ? 'is-top' : '' ?>">
                        <span class="leaderboard__rank"><?= rank_badge($r['rank']) ?></span>
                        <span class="leaderboard__emoji" aria-hidden="true"><?= ($p['relation'] ?? '') === 'พ่อ' ? '👨' : '👩' ?></span>
                        <span class="leaderboard__name"><strong><?= e($p['name'] ?? '-') ?></strong><small><?= e(($child ? 'ผู้ปกครองของ' . $child['nickname'] : '') . ' · ' . $r['count'] . ' ครั้ง') ?></small></span>
                        <span class="leaderboard__stars">⭐ <?= $r['total'] ?></span>
                        <?php if ($canGive && $child): ?>
                            <button type="button" class="btn btn--soft btn--sm" data-action="open-form" data-table="stars" data-defaults="<?= json_attr(public_row('stars', ['recipient' => 'parent', 'student_id' => $child['id'], 'points' => 3])) ?>">+ ดาว</button>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php else: ?>
            <?= empty_state('🛵', 'ยังไม่มีผู้ปกครองได้ดาวในเดือนนี้') ?>
        <?php endif; ?>
    </section>

    <section class="card">
        <?php section_head('🕘', 'ประวัติการให้ดาว · ' . thai_month_label($month)); ?>
        <?php $history = array_merge($monthStudent, $monthParent); usort($history, fn ($a, $b) => [$b['date'], $b['id']] <=> [$a['date'], $a['id']]); ?>
        <?php if ($history): ?>
            <ol class="star-history">
                <?php foreach (array_slice($history, 0, 80) as $r): ?>
                    <?php
                    $s = find_row('students', $r['student_id']);
                    $who = ($r['recipient'] ?? 'student') === 'parent' ? '🛵 ' . (find_row('users', (int) $r['parent_id'])['name'] ?? 'ผู้ปกครอง') : ($s['nickname'] ?? '');
                    ?>
                    <li><span class="star-history__points">+<?= (int) $r['points'] ?> ⭐</span><span><?= e($who) ?> · <?= e($r['reason']) ?></span><time><?= e(thai_short_date($r['date'])) ?></time></li>
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
