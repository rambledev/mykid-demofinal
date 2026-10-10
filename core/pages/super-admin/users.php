<?php
/** Super Admin — school administrators & executives (and a read-only count of other accounts). */
declare(strict_types=1);

$schools = scoped('schools');
$users = scoped('users');

mk_header(['id' => 'super-users', 'title' => 'ผู้ดูแลโรงเรียน', 'nav' => 'users']);
page_title('🛡️', 'ผู้ดูแลโรงเรียน', 'Admin และผู้บริหารของแต่ละโรงเรียน', btn_add('users', 'เพิ่มผู้ดูแล'));
filter_bar('users', array_column(array_map(fn ($s) => ['k' => $s['id'], 'v' => $s['emoji'] . ' ' . $s['shortName']], $schools), 'v', 'k'), 'ค้นหาชื่อหรือเบอร์โทร...');
?>
<div class="person-grid" data-filter-list="users" data-live id="live-users">
    <?php foreach ($users as $u): ?>
        <?php if (!in_array($u['role'], ['admin', 'executive'], true)) { continue; } ?>
        <?php $school = find_row('schools', (int) $u['school_id']); ?>
        <article class="person" data-filter-item data-group="<?= (int) $u['school_id'] ?>" data-search="<?= e($u['name'] . ' ' . $u['phone']) ?>">
            <span class="person__emoji" aria-hidden="true"><?= role_meta($u['role'])['emoji'] ?></span>
            <div class="person__body">
                <strong><?= e($u['name']) ?></strong>
                <small>📱 <?= e($u['phone']) ?></small>
                <span class="chip"><?= e(role_meta($u['role'])['th']) ?> · <?= e($school['shortName'] ?? '-') ?></span>
            </div>
            <div class="person__actions"><?= btn_edit('users', $u) ?><?= btn_delete('users', $u['id'], $u['name']) ?></div>
        </article>
    <?php endforeach; ?>
</div>

<section class="card">
    <?php section_head('👥', 'บัญชีครูและผู้ปกครอง', '', 'จัดการโดย Admin ของแต่ละโรงเรียน'); ?>
    <div class="table-wrap"><table class="table">
        <thead><tr><th scope="col">โรงเรียน</th><th scope="col">ครู</th><th scope="col">ผู้ปกครอง</th></tr></thead>
        <tbody>
        <?php foreach ($schools as $s): ?>
            <tr><th scope="row"><?= $s['emoji'] ?> <?= e($s['shortName']) ?></th>
                <td><?= count(array_filter($users, fn ($u) => $u['school_id'] === $s['id'] && $u['role'] === 'teacher')) ?> บัญชี</td>
                <td><?= count(array_filter($users, fn ($u) => $u['school_id'] === $s['id'] && $u['role'] === 'parent')) ?> บัญชี</td></tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</section>
<?php
render_form_sheet('users', 'เพิ่มผู้ดูแลโรงเรียน');
mk_footer();
