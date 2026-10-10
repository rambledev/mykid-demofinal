<?php
/** Teacher (Package A) — star rewards for children in the own classroom. */
declare(strict_types=1);

$user = current_user();
$cid = $user['classroom_id'];

mk_header(['id' => 'teacher-stars', 'title' => 'สะสมดาว', 'nav' => 'stars']);
page_title('⭐', 'สะสมดาว', 'ห้อง' . $user['classroom']['name'], btn_add('stars', 'ให้ดาว'));
?>
<div data-live id="live-stars">
    <?php
    $stars = scoped('stars');
    $board = [];
    foreach (classroom_students($cid) as $s) {
        $board[] = ['student' => $s, 'total' => stars_total(where($stars, 'student_id', $s['id']))];
    }
    usort($board, fn ($a, $b) => $b['total'] <=> $a['total']);
    usort($stars, fn ($a, $b) => [$b['date'], $b['id']] <=> [$a['date'], $a['id']]);
    ?>
    <section class="card">
        <?php section_head('🏆', 'ดาวสะสมของห้อง', '<span class="count-badge">' . array_sum(array_column($board, 'total')) . ' ⭐</span>'); ?>
        <ol class="leaderboard">
            <?php foreach ($board as $i => $b): ?>
                <li>
                    <span class="leaderboard__rank"><?= $i < 3 ? ['🥇', '🥈', '🥉'][$i] : $i + 1 ?></span>
                    <?= student_avatar($b['student'], 'sm') ?>
                    <strong><?= e($b['student']['nickname']) ?></strong>
                    <span class="leaderboard__stars">⭐ <?= $b['total'] ?></span>
                    <button type="button" class="btn btn--soft btn--sm" data-action="open-form" data-table="stars" data-defaults="<?= json_attr(['student_id' => $b['student']['id'], 'points' => 3]) ?>">+ ดาว</button>
                </li>
            <?php endforeach; ?>
        </ol>
    </section>
    <section class="card">
        <?php section_head('🕘', 'ประวัติล่าสุด'); ?>
        <ol class="star-history">
            <?php foreach (array_slice($stars, 0, 12) as $r): ?>
                <li><span class="star-history__points">+<?= (int) $r['points'] ?> ⭐</span><span><?= e(find_row('students', $r['student_id'])['nickname'] ?? '') ?> · <?= e($r['reason']) ?></span><time><?= e(thai_short_date($r['date'])) ?></time></li>
            <?php endforeach; ?>
        </ol>
    </section>
</div>
<?php
render_form_sheet('stars', 'ให้ดาว');
mk_footer();
