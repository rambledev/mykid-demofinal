<?php
/** Parent (Package A) — daily timeline: schedule + teacher updates + check-in, meals, sleep, รับ-ส่ง. */
declare(strict_types=1);

$user = current_user();
$child = $user['child'];
$cid = $child['classroom_id'];

$events = [];
foreach (classroom_activities($cid) as $a) {
    $events[] = ['time' => $a['time'], 'icon' => $a['icon'], 'title' => $a['title'], 'detail' => $a['detail'], 'kind' => 'schedule', 'activity_id' => $a['id']];
}
foreach (classroom_status_history($cid) as $s) {
    $events[] = ['time' => date('H:i', strtotime($s['at'])), 'icon' => $s['emoji'], 'title' => 'คุณครูอัปเดต: ' . $s['text'], 'detail' => 'โดย ' . $s['byName'], 'kind' => 'status'];
}
$att = student_today('attendance', $child['id']);
if ($att && $att['checkIn']) {
    $events[] = ['time' => $att['checkIn'], 'icon' => '🏫', 'title' => $child['nickname'] . 'มาถึงโรงเรียน', 'detail' => cat_find('attendance', $att['status'])['label'], 'kind' => 'child'];
}
$sleep = student_today('sleepRecords', $child['id']);
if ($sleep && $sleep['start']) {
    $events[] = ['time' => $sleep['start'], 'icon' => '😴', 'title' => $child['nickname'] . 'เริ่มนอนกลางวัน', 'detail' => $sleep['end'] ? 'ตื่น ' . $sleep['end'] . ' น. · นอน ' . thai_duration(minutes_between($sleep['start'], $sleep['end'])) : '', 'kind' => 'child'];
}
foreach (has_feature('studentStatus') ? scoped('studentStatuses') : [] as $st) {
    if ($st['student_id'] === $child['id'] && ($st['date'] ?? '') === today()) {
        $events[] = ['time' => substr($st['at'], 11, 5), 'icon' => '📝', 'title' => 'คุณครูบันทึก: ' . $st['status'], 'detail' => $st['note'] ?? '', 'kind' => 'child'];
    }
}
$pickup = has_feature('pickup') ? pickup_for_student($child['id']) : null;
if ($pickup) {
    $meta = pickup_status_meta($pickup['status']);
    $events[] = ['time' => pickup_hm($pickup['requested_at']), 'icon' => '🚸', 'title' => 'รับ-ส่ง: ' . $meta['emoji'] . ' ' . $meta['label'], 'detail' => $meta['parent'], 'kind' => 'child'];
}
usort($events, fn ($a, $b) => strcmp($a['time'], $b['time']));

mk_header(['id' => 'parent-timeline', 'title' => 'ไทม์ไลน์วันนี้', 'nav' => 'timeline']);
page_title('🕘', 'ไทม์ไลน์วันนี้', thai_date() . ' · ' . $child['nickname']);
?>
<div data-live id="live-timeline">
    <section class="card">
        <ul class="chart-legend timeline-legend">
            <li><span class="chart-legend__swatch kind--schedule"></span>ตารางกิจกรรม</li>
            <li><span class="chart-legend__swatch kind--status"></span>อัปเดตจากครู</li>
            <li><span class="chart-legend__swatch kind--child"></span>เรื่องของ<?= e($child['nickname']) ?></li>
        </ul>
        <ol class="timeline timeline--rich">
            <?php foreach ($events as $ev): ?>
                <li class="timeline__item kind--<?= e($ev['kind']) ?>">
                    <span class="timeline__time"><?= e($ev['time']) ?></span>
                    <span class="timeline__dot" aria-hidden="true"><?= $ev['icon'] ?></span>
                    <div class="timeline__body"><strong><?= e($ev['title']) ?></strong><?php if ($ev['detail']): ?><p><?= e($ev['detail']) ?></p><?php endif; ?>
                        <?php if (!empty($ev['activity_id']) && has_feature('media')) { render_media_strip(media_for('activity', $ev['activity_id']), $ev['title']); } ?></div>
                </li>
            <?php endforeach; ?>
        </ol>
    </section>
</div>
<?php mk_footer(); ?>
