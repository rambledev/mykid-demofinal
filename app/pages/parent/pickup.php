<?php
/**
 * Parent (Package A) — รับ-ส่ง: one card per child (parentStudents). One tap "กำลังไปรับลูก"
 * per child — no time / ETA. Status updates arrive live + as in-app notifications.
 */
declare(strict_types=1);

$user = current_user();
$children = parent_children($user);
$steps = mk_catalog()['pickupStatus'];
$order = array_column($steps, 'code');

mk_header(['id' => 'parent-pickup', 'title' => 'รับ-ส่ง', 'nav' => 'pickup']);
page_title('🚸', 'รับ-ส่งนักเรียน', thai_date() . (count($children) > 1 ? ' · ลูก ' . count($children) . ' คน' : ''));
?>
<?php $rows = pickup_today_rows(); ?>
<div data-live id="live-pickup" data-pickup-live data-pickup-signature="<?= e(pickup_signature($rows)) ?>">
    <?php foreach ($children as $child): ?>
        <?php
        $p = pickup_for_student($child['id']);
        $meta = $p ? pickup_status_meta($p['status']) : null;
        $reached = $p ? array_search($p['status'], $order, true) : -1;
        $room = find_row('classrooms', $child['classroom_id']);
        ?>
        <section class="card pickup-card<?= $p ? ' pickup-card--' . e($p['status']) : '' ?>" data-child="<?= $child['id'] ?>">
            <div class="pickup-card__child">
                <?= student_avatar($child, 'md') ?>
                <div>
                    <strong><?= e($child['nickname']) ?></strong>
                    <small><?= e($child['name']) ?> · ห้อง<?= e($room['name'] ?? '') ?></small>
                </div>
            </div>

            <?php if (!$p): ?>
                <button type="button" class="btn btn--primary btn--block pickup-go" data-action="pickup-go" data-student="<?= $child['id'] ?>" data-name="<?= e($child['nickname']) ?>">🚗 กำลังไปรับลูก</button>
                <p class="hint">เมื่อกดแล้ว ครูจะได้รับแจ้งว่าคุณกำลังเดินทางมารับนักเรียน</p>
            <?php else: ?>
                <div class="pickup-state pickup-state--<?= e($p['status']) ?>" role="status">
                    <span class="pickup-state__emoji" aria-hidden="true"><?= $meta['emoji'] ?></span>
                    <p><strong><?= e($meta['label']) ?></strong><?= e($meta['parent']) ?>
                        <?php if ($p['status'] === 'completed'): ?><br><small>เวลา <?= e(pickup_hm($p['completed_at'])) ?> น. · <?= e($p['completed_by_name'] ?? '') ?></small><?php endif; ?>
                    </p>
                </div>
                <ol class="pickup-steps" aria-label="ขั้นตอนการรับ-ส่ง">
                    <?php foreach ($steps as $i => $st): ?>
                        <?php $time = $p[PICKUP_TIME_COLUMN[$st['code']]] ?? null; ?>
                        <li class="pickup-steps__item<?= $i <= $reached ? ' is-done' : '' ?><?= $i === $reached ? ' is-current' : '' ?>">
                            <span aria-hidden="true"><?= $st['emoji'] ?></span>
                            <small><?= e($st['label']) ?></small>
                            <time><?= $i <= $reached && $time ? e(pickup_hm($time)) : '' ?></time>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>
    <?php if (!$children): ?>
        <?= empty_state('🚸', 'ไม่พบข้อมูลบุตรหลาน') ?>
    <?php endif; ?>
</div>
<?php render_pickup_legend(); ?>
<p class="hint hint--card">🔒 ครูจะส่งตัวนักเรียนที่จุดรับ-ส่งเท่านั้น · หน้านี้อัปเดตสถานะอัตโนมัติ และแจ้งเตือนที่เมนู 🔔</p>
<?php mk_footer(); ?>
