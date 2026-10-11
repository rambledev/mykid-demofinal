<?php
/**
 * Admin — จัดการระบบ: import seed data from JSON files, and wipe the system data
 * (everything except the school, its settings and the admin accounts).
 */
declare(strict_types=1);

$user = current_user();
$counts = [
    'ห้องเรียน' => count(scoped('classrooms')), 'ครู' => count(scoped('teachers')), 'นักเรียน' => count(scoped('students')),
    'บัญชีผู้ใช้' => count(scoped('users')),
];

mk_header(['id' => 'admin-system', 'title' => 'จัดการระบบ', 'nav' => 'system']);
page_title('🛠️', 'จัดการระบบ', $user['school']['name']);
?>
<section class="card">
    <?php section_head('📊', 'ข้อมูลในระบบตอนนี้'); ?>
    <ul class="menu-list" data-live id="live-system-counts">
        <?php foreach ($counts as $label => $n): ?><li><span><?= e($label) ?></span><strong><?= number_format($n) ?></strong></li><?php endforeach; ?>
    </ul>
</section>

<section class="card">
    <?php section_head('📥', 'นำเข้า Seed Data จากไฟล์', '', 'ไฟล์ JSON: ครู + ห้องเรียน และ/หรือ นักเรียน · ระบบตรวจไฟล์ก่อน แล้วจึงกดนำเข้า'); ?>
    <form class="form" data-seed-form novalidate>
        <label class="field"><span class="field__label">เลือกไฟล์ (.json · เลือกได้หลายไฟล์)</span>
            <input class="input" type="file" name="files" accept=".json,application/json" multiple required></label>
        <ul class="hint seed-report" data-seed-report hidden></ul>
        <p class="form__error" data-form-error hidden></p>
        <div class="row-actions">
            <button type="submit" class="btn btn--soft" data-seed-mode="preview"><?= icon('search') ?> ตรวจสอบไฟล์</button>
            <button type="submit" class="btn btn--primary" data-seed-mode="confirm" disabled><?= icon('check') ?> นำเข้าข้อมูล</button>
        </div>
        <p class="hint">ครูจะได้บัญชีเข้าสู่ระบบด้วยเบอร์โทร + PIN เริ่มต้น <?= e(DEFAULT_PIN) ?> และต้องตั้ง PIN ใหม่เมื่อเข้าสู่ระบบครั้งแรก · นำเข้าซ้ำได้ (อัปเดต ไม่สร้างซ้ำ)</p>
    </form>
</section>

<section class="card card--danger">
    <?php section_head('⚠️', 'ล้างข้อมูลระบบ', '', 'ลบข้อมูลทั้งหมด: ห้องเรียน ครู นักเรียน บัญชีผู้ใช้ (ยกเว้นผู้ดูแล) ข้อมูลรายวัน แชท รูปภาพ ฯลฯ — เก็บไว้เฉพาะข้อมูลโรงเรียนและบัญชีผู้ดูแล · ย้อนกลับไม่ได้'); ?>
    <form class="form" data-reset-form novalidate>
        <label class="field"><span class="field__label">พิมพ์คำว่า <strong>ล้างข้อมูล</strong> เพื่อยืนยัน</span>
            <input class="input" type="text" name="phrase" autocomplete="off" required></label>
        <label class="field"><span class="field__label">รหัส PIN ของคุณ</span>
            <input class="input" type="password" name="pin" inputmode="numeric" pattern="[0-9]*" maxlength="6" autocomplete="current-password" required></label>
        <p class="form__error" data-form-error hidden></p>
        <button type="submit" class="btn btn--danger btn--block"><?= icon('trash') ?> ล้างข้อมูลทั้งหมด</button>
    </form>
</section>
<?php mk_footer(); ?>
