<?php
/** Account page for every role — profile, permission scope, demo tools, logout. */
declare(strict_types=1);

$user = current_user();
$summary = permission_summary($user);
$canReset = in_array($user['role'], ['super_admin', 'admin', 'teacher'], true);

mk_header(['id' => $user['role'] . '-account', 'title' => 'บัญชี', 'nav' => 'account']);
?>

<section class="profile">
    <span class="profile__avatar" aria-hidden="true"><?= $user['emoji'] ?></span>
    <h1 class="profile__name"><?= e($user['name']) ?></h1>
    <p class="profile__meta"><?= e($user['scopeLabel']) ?></p>
    <span class="chip chip--role"><?= $user['meta']['emoji'] ?> <?= e($user['meta']['th']) ?> (<?= e($user['meta']['label']) ?>)</span>
</section>

<?php if ($user['role'] === 'teacher' && $user['classroom']): ?>
    <?php render_scope_card($user['classroom'], count(classroom_students($user['classroom_id'])), classroom_teachers($user['classroom_id'])); ?>
<?php elseif ($user['role'] === 'parent' && $user['child']): ?>
    <section class="card card--hero-pink"><?php render_child_card($user['child'], $user['classroom'], $user['school']); ?></section>
<?php endif; ?>

<section class="card">
    <?php section_head('🔐', 'สิทธิ์การใช้งาน', '', 'กำหนดจากบัญชีที่เข้าสู่ระบบ (Session)'); ?>
    <ul class="menu-list">
        <li><span>👤 บทบาท</span><strong><?= e($user['meta']['th']) ?></strong></li>
        <li><span>🏫 ขอบเขตข้อมูล</span><strong><?= e($summary['scope']) ?></strong></li>
        <li><span>✏️ จัดการได้</span><strong><?= e($summary['write']) ?></strong></li>
        <?php if ($user['role'] !== 'super_admin'): ?>
            <li><span>🚫 เข้าถึงไม่ได้</span><strong><?= e(match ($user['role']) {
                'admin', 'executive' => 'โรงเรียนอื่นทั้งหมด',
                'teacher' => 'ห้องอื่นและโรงเรียนอื่น',
                'parent' => 'ข้อมูลของเด็กคนอื่น',
                default => '-',
            }) ?></strong></li>
        <?php endif; ?>
    </ul>
</section>

<section class="card">
    <ul class="menu-list">
        <li><span>📱 เบอร์โทรศัพท์</span><strong><?= e($user['phone']) ?></strong></li>
        <li><span>🏫 โรงเรียน</span><strong><?= e($user['school']['name'] ?? 'ทุกโรงเรียน') ?></strong></li>
        <li><span>📦 แพ็กเกจ</span><strong><?= e(pkg()['name']) ?></strong></li>
    </ul>
</section>

<?php if ($canReset): ?>
    <section class="card">
        <?php section_head('🧪', 'เครื่องมือ Demo', '', 'คืนค่าข้อมูลในขอบเขตของบัญชีนี้กลับเป็นค่าเริ่มต้น'); ?>
        <button type="button" class="btn btn--soft btn--block" data-action="reset-demo"><?= icon('refresh') ?> รีเซ็ตข้อมูล Demo</button>
    </section>
<?php endif; ?>

<a class="btn btn--danger-soft btn--lg btn--block" href="<?= e(url('logout.php')) ?>"><?= icon('logout') ?> ออกจากระบบ</a>

<?php mk_footer(); ?>
