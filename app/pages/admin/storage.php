<?php
/**
 * Admin — storage management for image files (Portfolio / Activity / Food).
 *
 * Shows usage and expired files of the admin's own school, and lets the admin delete the
 * PHYSICAL files of expired images. Database records are never removed (only marked deleted).
 */
declare(strict_types=1);

$user = current_user();
$media = scoped('mediaFiles'); // own school only
$all = media_stats($media);
$byType = [];
foreach (['portfolio' => '🎨 ผลงานนักเรียน', 'activity' => '📷 รูปกิจกรรม', 'food' => '🍱 รูปอาหาร'] as $type => $label) {
    $byType[$type] = ['label' => $label] + media_stats(array_filter($media, fn ($m) => $m['owner_type'] === $type));
}
$expired = array_values(array_filter($media, fn ($m) => media_status($m) === 'expired'));
usort($expired, fn ($a, $b) => strcmp($a['uploaded_at'], $b['uploaded_at']));
$ownerLabel = function (array $m): string {
    $table = MEDIA_OWNERS[$m['owner_type']];
    $owner = find_row($table, (int) $m['owner_id']);
    return match ($m['owner_type']) {
        'portfolio' => '🎨 ' . ($owner['title'] ?? '-') . ' · ' . (find_row('students', (int) $m['student_id'])['nickname'] ?? ''),
        'activity'  => '📷 ' . ($owner['title'] ?? '-'),
        'food'      => '🍱 เมนู ' . thai_short_date($owner['date'] ?? ''),
    } . ' · ' . (find_row('classrooms', (int) $m['classroom_id'])['name'] ?? '');
};

mk_header(['id' => 'admin-storage', 'title' => 'จัดการพื้นที่จัดเก็บ', 'nav' => 'storage']);
page_title('🗂️', 'จัดการพื้นที่จัดเก็บ', $user['school']['name'] . ' · ไฟล์ภาพเก็บไว้ 6 เดือน');
?>
<div data-live id="live-storage">
    <div class="kpi-grid">
        <?= stat_card('🖼️', 'ไฟล์ภาพบน Server', number_format($all['total']), 'ไฟล์') ?>
        <?= stat_card('💾', 'พื้นที่ที่ใช้', human_bytes($all['bytes'])) ?>
        <?= stat_card('⏰', 'รูปหมดอายุ', number_format($all['expired']), 'ไฟล์ (เกิน 6 เดือน)', $all['expired'] ? 'warning' : 'good') ?>
        <?= stat_card('♻️', 'คืนพื้นที่ได้', human_bytes($all['expiredBytes']), '', $all['expired'] ? 'warning' : 'good') ?>
        <?= stat_card('🗑️', 'ล้างไฟล์แล้ว', number_format($all['deleted']), 'รายการ (ข้อมูลยังอยู่)') ?>
    </div>

    <section class="card">
        <?php section_head('🧹', 'ล้างไฟล์ภาพที่หมดอายุ', '', 'ลบเฉพาะไฟล์รูปจริง · ข้อมูลกิจกรรม ผลงาน อาหาร และประวัติยังอยู่ครบ'); ?>
        <?php if ($all['expired']): ?>
            <button type="button" class="btn btn--danger btn--lg btn--block" data-action="cleanup-media"
                    data-files="<?= $all['expired'] ?>" data-size="<?= e(human_bytes($all['expiredBytes'])) ?>">🗑 ล้างไฟล์ภาพที่หมดอายุ (<?= number_format($all['expired']) ?> ไฟล์ · <?= e(human_bytes($all['expiredBytes'])) ?>)</button>
        <?php else: ?>
            <p class="hint">✅ ไม่มีไฟล์ภาพที่หมดอายุ</p>
        <?php endif; ?>
        <p class="hint">ℹ️ หลังล้างแล้ว ผู้ใช้ยังเห็นรายการเดิม แต่รูปจะแสดงเป็น “รูปภาพหมดเวลาเก็บไฟล์” · ระบบไม่ลบข้อมูลในฐานข้อมูลโดยอัตโนมัติ</p>
    </section>

    <section class="card">
        <?php section_head('📊', 'แยกตามประเภท'); ?>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th scope="col">ประเภท</th><th scope="col">ไฟล์ทั้งหมด</th><th scope="col">พื้นที่</th><th scope="col">หมดอายุ</th><th scope="col">คืนพื้นที่ได้</th><th scope="col">ล้างแล้ว</th></tr></thead>
                <tbody>
                <?php foreach ($byType as $row): ?>
                    <tr><th scope="row"><?= e($row['label']) ?></th><td><?= number_format($row['total']) ?></td><td><?= e(human_bytes($row['bytes'])) ?></td>
                        <td><?= number_format($row['expired']) ?></td><td><?= e(human_bytes($row['expiredBytes'])) ?></td><td><?= number_format($row['deleted']) ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="card">
        <?php section_head('⏰', 'รายการรูปที่หมดอายุ', '<span class="count-badge">' . count($expired) . '</span>', 'อัปโหลดเกิน 6 เดือนแล้ว'); ?>
        <?php if ($expired): ?>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th scope="col">รายการ</th><th scope="col">อัปโหลด</th><th scope="col">หมดอายุ</th><th scope="col">ขนาด</th></tr></thead>
                    <tbody>
                    <?php foreach (array_slice($expired, 0, 50) as $m): ?>
                        <tr><th scope="row"><?= e($ownerLabel($m)) ?></th><td><?= e(thai_short_date(substr($m['uploaded_at'], 0, 10))) ?></td>
                            <td><?= e(thai_short_date(substr($m['expires_at'], 0, 10))) ?></td><td><?= e(human_bytes((int) $m['file_size'])) ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="empty">ไม่มีรูปที่หมดอายุ</p>
        <?php endif; ?>
    </section>
</div>
<?php mk_footer(); ?>
