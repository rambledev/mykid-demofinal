<?php
/**
 * Mykid — entry page: pick a role, then sign in with phone + PIN on /login?role=… .
 * A signed-in user also gets a shortcut back to their home page.
 */
declare(strict_types=1);

$current = current_user();
$school = store_rows('schools')[0] ?? null;

$roles = [
    'admin'     => ['title' => 'School Admin', 'desc' => 'จัดการข้อมูลภายในโรงเรียน'],
    'executive' => ['title' => 'Executive', 'desc' => 'ดูข้อมูลและรายงานภาพรวม'],
    'teacher'   => ['title' => 'Teacher', 'desc' => 'จัดการข้อมูลนักเรียนและกิจกรรมประจำวัน'],
    'parent'    => ['title' => 'Parent', 'desc' => 'ดูข้อมูลและกิจกรรมของบุตรหลาน'],
];

mk_header(['id' => 'role-select', 'title' => 'เลือกบทบาท', 'layout' => 'landing', 'theme' => 'landing']);
?>

<section class="entry-hero">
    <span class="entry-hero__logo"><?= logo_svg() ?></span>
    <h1 class="entry-hero__title">Mykid</h1>
    <p class="entry-hero__subtitle"><?= e($school['name'] ?? 'ระบบสื่อสารระหว่างโรงเรียนและผู้ปกครอง') ?></p>
    <p class="entry-hero__hint">เลือกบทบาทเพื่อเข้าสู่ระบบ</p>
</section>

<?php if ($current): ?>
    <div class="entry-session">
        <span aria-hidden="true"><?= $current['emoji'] ?></span>
        <p>กำลังใช้งานอยู่ในชื่อ <strong><?= e($current['name']) ?></strong><small><?= e($current['scopeLabel']) ?></small></p>
        <a class="btn btn--soft btn--sm" href="<?= e(url(home_path($current['role']))) ?>">ไปหน้าหลัก</a>
    </div>
<?php endif; ?>

<nav class="role-select" aria-label="เลือกบทบาท">
    <?php foreach ($roles as $role => $r): ?>
        <?php $meta = role_meta($role); ?>
        <article class="role-option role-option--<?= e($meta['theme']) ?>">
            <a class="role-option__main" href="<?= e(url('login?' . http_build_query(['role' => $role]))) ?>">
                <span class="role-option__icon" aria-hidden="true"><?= $meta['emoji'] ?></span>
                <span class="role-option__text">
                    <strong><?= e($r['title']) ?></strong>
                    <small><?= e($meta['th']) ?></small>
                    <span><?= e($r['desc']) ?></span>
                </span>
                <span class="role-option__go" aria-hidden="true"><?= icon('next') ?></span>
            </a>
        </article>
    <?php endforeach; ?>
</nav>

<p class="entry-footer">© <?= (int) date('Y') + 543 ?> Mykid</p>

<?php mk_footer(); ?>
