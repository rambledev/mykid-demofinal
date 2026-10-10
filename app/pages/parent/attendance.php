<?php
/** Parent — the child's attendance today + this month (from the teacher's daily check). */
declare(strict_types=1);

$user = current_user();
$child = $user['child'];
$today = student_today('attendance', $child['id']);

// This month's real records (a day without a check shows as empty).
$byDate = array_column(rows_since(where(scoped('attendance'), 'student_id', $child['id']), date('Y-m-01')), 'status', 'date');
$days = [];
$counts = array_fill_keys(array_column(mk_catalog()['attendance'], 'code'), 0);
for ($d = 1; $d <= (int) date('j'); $d++) {
    $date = date('Y-m-') . sprintf('%02d', $d);
    $status = ($byDate[$date] ?? '') !== '' ? $byDate[$date] : null;
    if ($status !== null && isset($counts[$status])) {
        $counts[$status]++;
    }
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
                <li class="month-grid__day<?= $day['status'] ? ' tone--' . e($item['tone']) : ' is-weekend' ?>" title="<?= e(thai_short_date($day['date']) . ' ' . ($item['label'] ?? 'ไม่มีข้อมูล')) ?>">
                    <span><?= (int) date('j', strtotime($day['date'])) ?></span><?= $item['emoji'] ?? '' ?>
                </li>
            <?php endforeach; ?>
        </ol>
    </section>
</div>
<?php mk_footer(); ?>
