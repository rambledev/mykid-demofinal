<?php
/** Teacher (Package A) — today's health check for the own classroom. */
declare(strict_types=1);

$user = current_user();
$cid = $user['classroom_id'];

mk_header(['id' => 'teacher-health', 'title' => 'สุขภาพ', 'nav' => 'health']);
page_title('🩺', 'สุขภาพวันนี้', thai_date() . ' · ห้อง' . $user['classroom']['name']);
?>
<div data-live id="live-health">
    <?php $records = today_by_student('healthRecords'); ?>
    <div class="kpi-grid kpi-grid--3">
        <?= stat_card('💚', 'ปกติ', count(array_filter($records, fn ($h) => $h['condition'] === 'normal')) . ' คน', '', 'good') ?>
        <?= stat_card('🟡', 'เฝ้าระวัง', count(array_filter($records, fn ($h) => $h['condition'] === 'watch')) . ' คน', '', 'warning') ?>
        <?= stat_card('🤒', 'ไม่สบาย', count(array_filter($records, fn ($h) => $h['condition'] === 'sick')) . ' คน', '', 'critical') ?>
    </div>
    <section class="card">
        <?php filter_bar('health', cat_options('health'), 'ค้นหาชื่อนักเรียน...'); ?>
        <ul class="record-list" data-filter-list="health">
            <?php foreach (classroom_students($cid) as $s): ?>
                <?php $h = $records[$s['id']] ?? null; ?>
                <li class="record" data-filter-item data-group="<?= e($h['condition'] ?? '') ?>" data-search="<?= e($s['nickname']) ?>">
                    <?= student_avatar($s, 'sm') ?>
                    <div class="record__body"><strong><?= e($s['nickname']) ?></strong><small>🌡️ <?= $h ? e(number_format((float) $h['temperature'], 1)) . '°C' : '-' ?> · <?= e($h['symptoms'] ?? '-') ?></small></div>
                    <?= tone_badge(cat_find('health', $h['condition'] ?? null), 'ยังไม่ได้ตรวจ') ?>
                    <?php if ($h): ?><?= btn_edit('healthRecords', $h) ?><?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
</div>
<?php
render_form_sheet('healthRecords', 'บันทึกสุขภาพ');
mk_footer();
