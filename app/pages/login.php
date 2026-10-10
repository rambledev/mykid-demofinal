<?php
/** Login — role + phone + 6-digit PIN (hashed, throttled; see app/lib/security.php). */
declare(strict_types=1);

if ($existing = current_user()) {
    redirect(home_path($existing['role']));
}

$roles = pkg()['roles'];
$requested = $_POST['role'] ?? $_GET['role'] ?? '';
$role = in_array($requested, $roles, true) ? $requested : $roles[0];
$phone = '';
$error = flash('login_error');
$notice = isset($_GET['logout']) ? 'ออกจากระบบเรียบร้อยแล้ว' : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone = normalize_phone((string) ($_POST['phone'] ?? ''));
    $pinInput = $_POST['pin'] ?? '';
    $pin = is_array($pinInput) ? implode('', array_map('strval', $pinInput)) : (string) $pinInput;

    $wait = strlen($phone) === 10 ? login_lock_seconds($phone) : 0;
    if (!verify_csrf($_POST['csrf'] ?? null)) {
        $error = 'หน้านี้เปิดค้างไว้นานเกินไป กรุณาลองใหม่อีกครั้ง';
    } elseif (strlen($phone) !== 10) {
        $error = 'กรุณากรอกเบอร์โทรศัพท์ 10 หลัก';
    } elseif (!preg_match('/^\d{6}$/', $pin)) {
        $error = 'กรุณากรอกรหัส PIN ให้ครบ 6 หลัก';
    } elseif ($wait > 0) {
        mk_log('Auth', 'login LOCKED', ['phone' => $phone, 'wait' => $wait]);
        $error = 'ใส่รหัสไม่ถูกต้องหลายครั้ง กรุณารอประมาณ ' . max(1, (int) ceil($wait / 60)) . ' นาทีแล้วลองใหม่อีกครั้ง';
    } elseif (auth_login($role, $phone, $pin)) {
        login_record_attempt($phone, true);
        flash('toast', 'เข้าสู่ระบบสำเร็จ ยินดีต้อนรับค่ะ 🎉');
        redirect(home_path($role));
    } else {
        login_record_attempt($phone, false);
        $error = 'เบอร์โทรศัพท์หรือรหัส PIN ไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง';
    }
}

mk_header(['id' => 'login', 'title' => 'เข้าสู่ระบบ', 'layout' => 'auth', 'theme' => role_meta($role)['theme']]);
?>
<div class="auth">
    <a class="topnav__back auth__back" href="<?= e(url('')) ?>"><?= icon('back') ?> กลับ</a>
    <div class="auth__art" aria-hidden="true">
        <span class="auth__sun"><?= decor_svg('sun') ?></span>
        <span class="auth__cloud"><?= decor_svg('cloud') ?></span>
        <span class="auth__rainbow"><?= decor_svg('rainbow') ?></span>
        <span class="auth__logo"><?= logo_svg() ?></span>
    </div>
    <h1 class="auth__title">เข้าสู่ <?= e(pkg()['name']) ?></h1>
    <p class="auth__sub"><?= e(pkg()['multiSchool'] ? 'Mykid Platform · ' . count(store_rows('schools')) . ' โรงเรียน' : store_rows('schools')[0]['name']) ?></p>

    <form class="auth__card" method="post" action="<?= e(url('login')) ?>" data-login-form novalidate>
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

        <fieldset class="role-picker">
            <legend class="field__label">เลือกบทบาท</legend>
            <div class="role-picker__grid">
                <?php foreach ($roles as $r): ?>
                    <?php $meta = role_meta($r); ?>
                    <label class="role-picker__item role-picker__item--<?= e($meta['theme']) ?>">
                        <input type="radio" name="role" value="<?= e($r) ?>" data-theme="<?= e($meta['theme']) ?>"<?= $r === $role ? ' checked' : '' ?>>
                        <span><b aria-hidden="true"><?= $meta['emoji'] ?></b><?= e($meta['th']) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </fieldset>

        <?php if ($notice): ?><p class="alert alert--ok" role="status">👋 <?= e($notice) ?></p><?php endif; ?>
        <?php if ($error): ?><p class="alert alert--error" role="alert"><?= e($error) ?></p><?php endif; ?>

        <label class="field">
            <span class="field__label"><?= icon('phone') ?> เบอร์โทรศัพท์</span>
            <input class="input input--lg" type="tel" name="phone" inputmode="numeric" autocomplete="tel" maxlength="12" placeholder="08x-xxx-xxxx" value="<?= e($phone) ?>" required data-phone-input>
        </label>

        <div class="field">
            <span class="field__label" id="pin-label"><?= icon('lock') ?> รหัส PIN 6 หลัก</span>
            <div class="pin" role="group" aria-labelledby="pin-label">
                <?php for ($i = 0; $i < 6; $i++): ?>
                    <input class="pin__box" type="password" name="pin[]" inputmode="numeric" pattern="[0-9]*" maxlength="1" autocomplete="one-time-code" aria-label="PIN หลักที่ <?= $i + 1 ?>">
                <?php endfor; ?>
            </div>
        </div>

        <button class="btn btn--primary btn--lg btn--block" type="submit">เข้าสู่ระบบ</button>

    </form>
</div>

<?php mk_footer(); ?>
