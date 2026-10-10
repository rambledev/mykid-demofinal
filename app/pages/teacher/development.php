<?php
/** Teacher (Package A) — development scores for children in the own classroom. */
declare(strict_types=1);

$user = current_user();
$cid = $user['classroom_id'];

mk_header(['id' => 'teacher-development', 'title' => 'พัฒนาการ', 'nav' => 'development']);
page_title('🌱', 'พัฒนาการรายบุคคล', 'ห้อง' . $user['classroom']['name'] . ' · คะแนน 0–100');
?>
<div data-live id="live-development">
    <?php filter_bar('dev', [], 'ค้นหาชื่อนักเรียน...'); ?>
    <div class="dev-grid" data-filter-list="dev">
        <?php $records = []; foreach (scoped('development') as $d) { $records[$d['student_id']] = $d; } ?>
        <?php foreach (classroom_students($cid) as $s): ?>
            <?php $d = $records[$s['id']] ?? null; ?>
            <article class="card dev-card" data-filter-item data-search="<?= e($s['nickname']) ?>">
                <header class="dev-card__head"><?= student_avatar($s, 'sm') ?><strong><?= e($s['nickname']) ?></strong><?php if ($d): ?><?= btn_edit('development', $d) ?><?php endif; ?></header>
                <?php if ($d): ?>
                    <?php render_dev_bars($d); ?>
                    <p class="hint">💬 <?= e($d['note']) ?></p>
                <?php else: ?>
                    <p class="empty">ยังไม่มีข้อมูล</p>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
</div>
<?php
render_form_sheet('development', 'บันทึกพัฒนาการ');
mk_footer();
