<?php
/** Teacher (Package A) — รับ-ส่งนักเรียน: today's pickup requests of the own classroom, grouped by status. */
declare(strict_types=1);

$user = current_user();
$students = array_column(classroom_students($user['classroom_id']), null, 'id');

mk_header(['id' => 'teacher-pickup', 'title' => 'รับ-ส่ง', 'nav' => 'pickup']);
page_title('🚸', 'รับนักเรียนกลับบ้าน', thai_date() . ' · ห้อง' . $user['classroom']['name']);
?>
<?php $rows = pickup_today_rows(); ?>
<div data-live id="live-pickup" data-pickup-live data-pickup-signature="<?= e(pickup_signature($rows)) ?>">
    <section class="card">
        <div class="pickup-summary">
            <?php foreach (mk_catalog()['pickupStatus'] as $st): ?>
                <a class="pickup-summary__item tone--<?= e($st['tone']) ?>" href="#pickup-<?= e($st['code']) ?>">
                    <span aria-hidden="true"><?= $st['emoji'] ?></span>
                    <strong><?= count(array_filter($rows, fn ($p) => $p['status'] === $st['code'])) ?></strong>
                    <small><?= e($st['label']) ?></small>
                </a>
            <?php endforeach; ?>
        </div>
    </section>

    <?php foreach (mk_catalog()['pickupStatus'] as $st): ?>
        <?php $group = array_values(array_filter($rows, fn ($p) => $p['status'] === $st['code'])); ?>
        <section class="card pickup-group pickup-group--<?= e($st['code']) ?>" id="pickup-<?= e($st['code']) ?>">
            <?php section_head($st['emoji'], $st['label'], '<span class="count-badge">' . count($group) . ' คน</span>'); ?>
            <?php if (!$group): ?>
                <p class="hint">ไม่มีรายการ</p>
            <?php else: ?>
                <ul class="pickup-list">
                    <?php foreach ($group as $p): ?>
                        <?php $s = $students[$p['student_id']] ?? null; if (!$s) { continue; } ?>
                        <li class="pickup-item">
                            <?= student_avatar($s, 'sm') ?>
                            <div class="pickup-item__body">
                                <strong><?= e($s['nickname']) ?></strong>
                                <small><?= e($s['name']) ?> · ห้อง<?= e($s['classroom']) ?></small>
                                <p class="pickup-item__msg"><?= e($st['teacher']) ?></p>
                                <dl class="pickup-item__meta">
                                    <div><dt>แจ้งเมื่อ</dt><dd><?= e(pickup_hm($p['requested_at'])) ?> น. · <?= e($p['parent_name']) ?></dd></div>
                                    <?php if ($p['status'] === 'completed'): ?>
                                        <div><dt>ส่งมอบ</dt><dd><?= e(pickup_hm($p['completed_at'])) ?> น. · <?= e($p['completed_by_name'] ?? '') ?></dd></div>
                                    <?php endif; ?>
                                </dl>
                            </div>
                            <div class="pickup-item__side">
                                <?= tone_badge($st) ?>
                                <?php if ($action = PICKUP_ACTIONS[$p['status']] ?? null): ?>
                                    <button type="button" class="btn btn--primary btn--sm" data-action="pickup-update" data-id="<?= $p['id'] ?>" data-status="<?= e(PICKUP_FLOW[$p['status']]) ?>" data-name="<?= e($s['nickname']) ?>">
                                        <span aria-hidden="true"><?= $action['icon'] ?></span> <?= e($action['label']) ?>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>
</div>
<?php render_pickup_legend(); ?>
<p class="hint hint--card">🔄 หน้านี้อัปเดตอัตโนมัติเมื่อผู้ปกครองกด “กำลังไปรับลูก” และมีแจ้งเตือนที่เมนู 🔔</p>
<?php mk_footer(); ?>
