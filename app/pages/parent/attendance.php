<?php
/** Parent (Package A) — the child's attendance today + this month (mock history). */
declare(strict_types=1);

$user = current_user();
$child = $user['child'];
$today = student_today('attendance', $child['id']);

// Mock month history for the calendar view (today = real record).
$days = [];
$counts = array_fill_keys(array_column(mk_catalog()['attendance'], 'code'), 0);
for ($d = 1; $d <= (int) date('j'); $d++) {
    $date = date('Y-m-') . sprintf('%02d', $d);
    $dow = (int) date('w', strtotime($date));
    if ($dow === 0 || $dow === 6) {
        $days[] = ['date' => $date, 'status' => null];
        continue;
    }
    $r = mk_rand($child['id'], $date) % 100;
    $status = $date === today() ? ($today['status'] ?? 'present') : ($r < 88 ? 'present' : ($r < 95 ? 'late' : 'leave'));
    $counts[$status]++;
    $days[] = ['date' => $date, 'status' => $status];
}

mk_header(['id' => 'parent-attendance', 'title' => 'การมาเรียน', 'nav' => 'attendance']);
page_title('✅', 'การมาเรียน', $child['nickname'] . ' · ' . THAI_MONTHS[(int) date('n')]);
?>
<div data-live id="live-attendance">
    <section class="card">
        <?php section_head('📍', 'วันนี้'); ?>
        <p class="att-today"><?= tone_badge(cat_find('attendance', $today['status'] ?? null), 'ยังไม่มีข้อมูล') ?>
            <?php if (!empty($today['checkIn'])): ?><span>มาถึง <?= e($today['checkIn']) ?> น.</span><?php endif; ?></p>
    </section>
    <section class="card">
        <?php section_head('🗓️', 'เดือนนี้'); ?>
        <?= chart_stack(attendance_parts($counts), 'วัน') ?>
        <ol class="month-grid" aria-label="การมาเรียนรายวัน">
            <?php foreach ($days as $day): ?>
                <?php $item = cat_find('attendance', $day['status']); ?>
                <li class="month-grid__day<?= $day['status'] ? ' tone--' . e($item['tone']) : ' is-weekend' ?>" title="<?= e(thai_short_date($day['date']) . ' ' . ($item['label'] ?? 'วันหยุด')) ?>">
                    <span><?= (int) date('j', strtotime($day['date'])) ?></span><?= $item['emoji'] ?? '' ?>
                </li>
            <?php endforeach; ?>
        </ol>
    </section>
</div>
<?php mk_footer(); ?>
