<?php
/**
 * Admin — school administrator & executive accounts of the own school.
 * (Teacher / parent accounts are created with the teacher / student records.)
 * Scope: school_id is forced from the session (apply_write_scope); the API only lets an admin
 * create / edit / delete admin & executive accounts, never their own role or account.
 */
declare(strict_types=1);

$user = current_user();
$users = scoped('users');
$staff = array_values(array_filter($users, fn ($u) => in_array($u['role'], ['admin', 'executive'], true)));
$count = fn (string $role) => count(array_filter($users, fn ($u) => $u['role'] === $role));

mk_header(['id' => 'admin-users', 'title' => 'ผู้ดูแลและผู้บริหาร', 'nav' => 'users']);
page_title('🛡️', 'ผู้ดูแลและผู้บริหาร', $user['school']['name'], btn_add('users', 'เพิ่มบัญชี'));
filter_bar('users', [], 'ค้นหาชื่อหรือเบอร์โทร...');
?>
<div class="person-grid" data-filter-list="users" data-live id="live-users">
    <?php foreach ($staff as $u): ?>
        <article class="person" data-filter-item data-search="<?= e($u['name'] . ' ' . $u['phone']) ?>">
            <span class="person__emoji" aria-hidden="true"><?= role_meta($u['role'])['emoji'] ?></span>
            <div class="person__body">
                <strong><?= e($u['name']) ?><?= $u['id'] === $user['id'] ? ' <small>(บัญชีของฉัน)</small>' : '' ?></strong>
                <small>📱 <?= e($u['phone']) ?></small>
                <span class="chip"><?= e(role_meta($u['role'])['th']) ?></span>
            </div>
            <div class="person__actions">
                <?= btn_edit('users', $u) ?>
                <?= $u['id'] === $user['id'] ? '' : btn_delete('users', $u['id'], $u['name']) ?>
            </div>
        </article>
    <?php endforeach; ?>
</div>

<section class="card">
    <?php section_head('👥', 'บัญชีครูและผู้ปกครอง', '', 'สร้างพร้อมข้อมูลครูและนักเรียน'); ?>
    <ul class="menu-list">
        <li><span>👩‍🏫 ครู</span><strong><?= $count('teacher') ?> บัญชี</strong></li>
        <li><span>👨‍👩‍👧 ผู้ปกครอง</span><strong><?= $count('parent') ?> บัญชี</strong></li>
    </ul>
</section>
<?php
render_form_sheet('users', 'เพิ่มบัญชีผู้ดูแล', ['school_id']);
mk_footer();
