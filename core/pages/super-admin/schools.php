<?php
/** Super Admin — manage schools (add / edit). */
declare(strict_types=1);

mk_header(['id' => 'super-schools', 'title' => 'จัดการโรงเรียน', 'nav' => 'schools']);
page_title('🏫', 'จัดการโรงเรียน', count(scoped('schools')) . ' โรงเรียนในระบบ', btn_add('schools', 'เพิ่มโรงเรียน'));
?>
<div class="room-grid" data-live id="live-schools">
    <?php foreach (scoped('schools') as $s): ?>
        <?php $admins = array_filter(scoped('users'), fn ($u) => $u['school_id'] === $s['id'] && in_array($u['role'], ['admin', 'executive'], true)); ?>
        <article class="room-card" style="--room-bg: <?= e($s['color'] ?? '#FFF4CC') ?>">
            <header class="room-card__head">
                <span class="room-card__emoji" aria-hidden="true"><?= $s['emoji'] ?></span>
                <h2><?= e($s['name']) ?></h2>
                <div class="person__actions"><?= btn_edit('schools', $s) ?><?= btn_delete('schools', $s['id'], $s['name']) ?></div>
            </header>
            <div class="stat-row">
                <div class="stat"><strong><?= count(where(scoped('classrooms'), 'school_id', $s['id'])) ?></strong><span>ห้อง</span></div>
                <div class="stat"><strong><?= count(where(scoped('students'), 'school_id', $s['id'])) ?></strong><span>นักเรียน</span></div>
                <div class="stat"><strong><?= count(where(scoped('teachers'), 'school_id', $s['id'])) ?></strong><span>ครู</span></div>
            </div>
            <p class="hint">📍 <?= e($s['province'] ?: '-') ?> · <?= $s['status'] === 'active' ? '✅ ใช้งาน' : '🧪 ทดลองใช้' ?></p>
            <p class="hint">🛡️ <?= e($admins ? implode(', ', array_column($admins, 'name')) : 'ยังไม่มีผู้ดูแล') ?></p>
            <a class="btn btn--soft btn--block" href="school.php?view=<?= $s['id'] ?>">ดูข้อมูลโรงเรียน <?= icon('next') ?></a>
        </article>
    <?php endforeach; ?>
</div>
<?php
render_form_sheet('schools', 'เพิ่มโรงเรียน');
mk_footer();
