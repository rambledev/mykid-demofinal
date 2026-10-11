<?php
/**
 * First login (or after an admin PIN reset): the account still has the default PIN, so the user sets
 * their own 6-digit PIN before anything else (the router and the API send them here until it is done).
 */
declare(strict_types=1);

$user = current_user();
if (!$user['must_change_pin']) {
    redirect(home_path($user['role']));
}

mk_header(['id' => 'set-pin', 'title' => 'ตั้งรหัส PIN', 'layout' => 'auth', 'theme' => $user['meta']['theme']]);
?>
<div class="auth">
    <div class="auth__art" aria-hidden="true"><span class="auth__logo"><?= logo_svg() ?></span></div>
    <h1 class="auth__title">ตั้งรหัส PIN ของคุณ</h1>
    <p class="auth__sub">สวัสดี <?= e($user['name']) ?> · ใช้ครั้งแรก กรุณาตั้งรหัส PIN 6 หลักของคุณเองแทนรหัสเริ่มต้น</p>
    <form class="auth__card form" data-pin-form data-first-setup novalidate>
        <label class="field"><span class="field__label"><?= icon('lock') ?> รหัส PIN ใหม่ <small>(ตัวเลข 6 หลัก ห้ามใช้ 123456 / 000000)</small></span>
            <input class="input input--lg" type="password" name="new" inputmode="numeric" pattern="[0-9]*" maxlength="6" autocomplete="new-password" required autofocus></label>
        <label class="field"><span class="field__label">ยืนยันรหัส PIN ใหม่</span>
            <input class="input input--lg" type="password" name="confirm" inputmode="numeric" pattern="[0-9]*" maxlength="6" autocomplete="new-password" required></label>
        <p class="form__error" data-form-error hidden></p>
        <button type="submit" class="btn btn--primary btn--lg btn--block"><?= icon('check') ?> บันทึกรหัส PIN</button>
        <p class="hint">จำรหัสนี้ไว้ใช้เข้าสู่ระบบครั้งต่อไปด้วยเบอร์ <?= e($user['phone']) ?></p>
    </form>
    <form class="logout-form" method="post" action="<?= e(url('logout')) ?>"><?= csrf_field() ?><button type="submit" class="btn btn--ghost btn--block">ออกจากระบบ</button></form>
</div>
<?php mk_footer(); ?>
