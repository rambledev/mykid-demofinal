<?php
/** Account page for every role — profile, permission scope, logout. */
declare(strict_types=1);

$user = current_user();
$summary = permission_summary($user);

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
        <li><span>🚫 เข้าถึงไม่ได้</span><strong><?= e(match ($user['role']) {
                'admin', 'executive' => 'ข้อมูลนอกโรงเรียน',
                'teacher' => 'ห้องเรียนอื่น',
                'parent' => 'ข้อมูลของเด็กคนอื่น',
                default => '-',
            }) ?></strong></li>
    </ul>
</section>

<section class="card">
    <ul class="menu-list">
        <li><span>📱 เบอร์โทรศัพท์</span><strong><?= e($user['phone']) ?></strong></li>
        <li><span>🏫 โรงเรียน</span><strong><?= e($user['school']['name'] ?? 'ทุกโรงเรียน') ?></strong></li>
        <li><span>📦 แพ็กเกจ</span><strong><?= e(pkg()['name']) ?></strong></li>
    </ul>
</section>

<section class="card">
    <?php section_head('🔑', 'เปลี่ยนรหัส PIN', '', 'รหัส 6 หลักสำหรับเข้าสู่ระบบด้วยเบอร์ ' . $user['phone']); ?>
    <form class="form" data-pin-form novalidate>
        <label class="field"><span class="field__label">รหัส PIN ปัจจุบัน</span>
            <input class="input" type="password" name="current" inputmode="numeric" pattern="[0-9]*" maxlength="6" autocomplete="current-password" required></label>
        <label class="field"><span class="field__label">รหัส PIN ใหม่ <small>(ตัวเลข 6 หลัก ไม่ใช่ 000000 / 123456)</small></span>
            <input class="input" type="password" name="new" inputmode="numeric" pattern="[0-9]*" maxlength="6" autocomplete="new-password" required></label>
        <label class="field"><span class="field__label">ยืนยันรหัส PIN ใหม่</span>
            <input class="input" type="password" name="confirm" inputmode="numeric" pattern="[0-9]*" maxlength="6" autocomplete="new-password" required></label>
        <p class="form__error" data-form-error hidden></p>
        <button type="submit" class="btn btn--primary btn--block"><?= icon('check') ?> บันทึกรหัส PIN ใหม่</button>
    </form>
</section>

<form class="logout-form" method="post" action="<?= e(url('logout')) ?>"><?= csrf_field() ?><button type="submit" class="btn btn--danger-soft btn--lg btn--block"><?= icon('logout') ?> ออกจากระบบ</button></form>

<?php mk_footer(); ?>
