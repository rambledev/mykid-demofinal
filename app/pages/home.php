<?php
/**
 * Mykid — entry page (role selection).
 *
 * DEMO_MODE=1: each card logs straight into that role's demo account (real login flow:
 *   /login?role=…&demo=…&go=1 pre-fills and submits the form; switching = /logout?… first).
 * DEMO_MODE=0: each card only opens the login page for that role (phone + PIN required).
 */
declare(strict_types=1);

$current = current_user();
$schools = array_column(store_rows('schools'), null, 'id');

$roles = [
    'super_admin' => ['title' => 'Super Admin', 'desc' => 'จัดการระบบและข้อมูลทุกโรงเรียน'],
    'admin'       => ['title' => 'School Admin', 'desc' => 'จัดการข้อมูลภายในโรงเรียน'],
    'executive'   => ['title' => 'Executive', 'desc' => 'ดูข้อมูลและรายงานภาพรวม'],
    'teacher'     => ['title' => 'Teacher', 'desc' => 'จัดการข้อมูลนักเรียนและกิจกรรมประจำวัน'],
    'parent'      => ['title' => 'Parent', 'desc' => 'ดูข้อมูลและกิจกรรมของบุตรหลาน'],
];

// One demo account per role and school (first one found in users).
foreach (store_rows('users') as $u) {
    if (isset($roles[$u['role']]) && !isset($roles[$u['role']]['accounts'][(int) $u['school_id']])) {
        $roles[$u['role']]['accounts'][(int) $u['school_id']] = $u;
    }
}

// Parents linked to several children (parentStudents) — extra chip on the Parent card.
$childCount = array_count_values(array_column(store_rows('parentStudents'), 'user_id'));
$multiParents = array_values(array_filter(store_rows('users'), fn ($u) => $u['role'] === 'parent' && ($childCount[$u['id']] ?? 0) > 1));

/** Link that enters the app as this account (logs the current demo user out first). */
$enter = function (array $u) use ($current): string {
    if (!demo_mode()) {
        return url('login?' . http_build_query(['role' => $u['role']]));
    }
    $query = http_build_query(['role' => $u['role'], 'demo' => $u['phone'], 'go' => 1]);
    return url(($current ? 'logout?' : 'login?') . $query);
};

mk_header(['id' => 'role-select', 'title' => 'เลือกบทบาท', 'layout' => 'landing', 'theme' => 'landing']);
?>

<section class="entry-hero">
    <span class="entry-hero__logo"><?= logo_svg() ?></span>
    <h1 class="entry-hero__title">Mykid</h1>
    <p class="entry-hero__subtitle">ระบบสื่อสารระหว่างโรงเรียนและผู้ปกครอง</p>
    <p class="entry-hero__hint">เลือกบทบาทเพื่อเข้าสู่ระบบ Demo</p>
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
        <?php
        $meta = role_meta($role);
        $accounts = $r['accounts'] ?? [];
        ksort($accounts);
        $main = reset($accounts);
        if (!$main) {
            continue;
        }
        ?>
        <article class="role-option role-option--<?= e($meta['theme']) ?>">
            <a class="role-option__main" href="<?= e($enter($main)) ?>">
                <span class="role-option__icon" aria-hidden="true"><?= $meta['emoji'] ?></span>
                <span class="role-option__text">
                    <strong><?= e($r['title']) ?></strong>
                    <small><?= e($meta['th']) ?></small>
                    <span><?= e($r['desc']) ?></span>
                </span>
                <span class="role-option__go" aria-hidden="true"><?= icon('next') ?></span>
            </a>
            <?php if (demo_mode() && (count($accounts) > 1 || ($role === 'parent' && $multiParents))): ?>
                <div class="role-option__schools" aria-label="เลือกโรงเรียนสำหรับ <?= e($r['title']) ?>">
                    <?php foreach ($accounts as $sid => $u): ?>
                        <a class="chip-btn" href="<?= e($enter($u)) ?>"><?= $schools[$sid]['emoji'] ?? '🏫' ?> <?= e($schools[$sid]['shortName'] ?? '') ?></a>
                    <?php endforeach; ?>
                    <?php if ($role === 'parent'): ?>
                        <?php foreach ($multiParents as $u): ?>
                            <a class="chip-btn" href="<?= e($enter($u)) ?>">👨‍👩‍👧 ลูก <?= (int) $childCount[$u['id']] ?> คน</a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
</nav>

<p class="entry-footer">ข้อมูลทั้งหมดเป็นข้อมูลสมมติสำหรับทดลองใช้งาน · © <?= (int) date('Y') + 543 ?> Mykid</p>

<?php mk_footer(); ?>
