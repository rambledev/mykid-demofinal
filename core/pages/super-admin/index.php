<?php
/** Super Admin — platform overview across every school, with a school selector. */
declare(strict_types=1);

$schools = scoped('schools');
$perSchool = [];
foreach ($schools as $s) {
    $perSchool[$s['id']] = [
        'schools'    => 1,
        'teachers'   => count(where(scoped('teachers'), 'school_id', $s['id'])),
        'students'   => count(where(scoped('students'), 'school_id', $s['id'])),
        'parents'    => count(where(scoped('students'), 'school_id', $s['id'])),
        'classrooms' => count(where(scoped('classrooms'), 'school_id', $s['id'])),
    ];
}
$all = ['schools' => count($schools)];
foreach (['teachers', 'students', 'parents', 'classrooms'] as $key) {
    $all[$key] = array_sum(array_column($perSchool, $key));
}

mk_header(['id' => 'super-home', 'title' => 'ภาพรวมระบบ', 'nav' => 'home']);
page_title('🪐', 'ภาพรวมทุกโรงเรียน', 'Mykid Platform · ' . thai_date());
?>
<nav class="chip-row" aria-label="เลือกโรงเรียน" data-school-picker>
    <button type="button" class="chip-btn is-active" data-school-pick="all">🌐 ทุกโรงเรียน</button>
    <?php foreach ($schools as $s): ?>
        <button type="button" class="chip-btn" data-school-pick="<?= $s['id'] ?>"><?= $s['emoji'] ?> <?= e($s['shortName']) ?></button>
    <?php endforeach; ?>
</nav>

<?php foreach (['all' => $all] + $perSchool as $key => $n): ?>
    <div class="kpi-grid" data-school-kpi="<?= $key ?>"<?= $key === 'all' ? '' : ' hidden' ?>>
        <?= stat_card('🏫', 'โรงเรียน', $n['schools'], 'แห่ง') ?>
        <?= stat_card('👩‍🏫', 'ครู', $n['teachers'], 'คน') ?>
        <?= stat_card('👧', 'นักเรียน', $n['students'], 'คน') ?>
        <?= stat_card('👨‍👩‍👧', 'ผู้ปกครอง', $n['parents'], 'ครอบครัว') ?>
        <?= stat_card('🚪', 'ห้องเรียน', $n['classrooms'], 'ห้อง') ?>
    </div>
<?php endforeach; ?>

<div class="dash-grid">
    <section class="card">
        <?php section_head('📊', 'นักเรียนแยกตามโรงเรียน'); ?>
        <?= chart_hbars(array_map(fn ($s, $i) => ['label' => $s['shortName'], 'emoji' => $s['emoji'], 'value' => $perSchool[$s['id']]['students'], 'color' => CHART_SERIES[$i % 3]], $schools, array_keys($schools)), ' คน') ?>
    </section>
    <section class="card">
        <?php section_head('✅', 'อัตราการมาเรียนวันนี้'); ?>
        <?= chart_hbars(array_map(function ($s, $i) {
            $rows = where(today_rows(scoped('attendance')), 'school_id', $s['id']);
            $here = count(array_filter($rows, fn ($a) => in_array($a['status'], ['present', 'late'], true)));
            return ['label' => $s['shortName'], 'emoji' => $s['emoji'], 'value' => percent($here, count($rows)), 'color' => CHART_SERIES[$i % 3]];
        }, $schools, array_keys($schools)), '%', 100) ?>
    </section>
</div>

<section class="card">
    <?php section_head('🏫', 'โรงเรียนในระบบ', '<a class="btn btn--soft btn--sm" href="compare.php">เปรียบเทียบ</a> <a class="btn btn--soft btn--sm" href="schools.php">จัดการ</a>'); ?>
    <ul class="school-list">
        <?php foreach ($schools as $s): ?>
            <li data-school-row="<?= $s['id'] ?>">
                <span class="school-list__emoji" aria-hidden="true"><?= $s['emoji'] ?></span>
                <div><strong><?= e($s['name']) ?></strong><small><?= e($s['province']) ?> · <?= $perSchool[$s['id']]['classrooms'] ?> ห้อง · <?= $perSchool[$s['id']]['students'] ?> นักเรียน</small></div>
                <span class="tone tone--<?= $s['status'] === 'active' ? 'good' : 'warning' ?>"><?= $s['status'] === 'active' ? '✅ ใช้งาน' : '🧪 ทดลองใช้' ?></span>
                <a class="btn btn--soft btn--sm" href="school.php?view=<?= $s['id'] ?>">ดูข้อมูล</a>
            </li>
        <?php endforeach; ?>
    </ul>
</section>
<?php mk_footer(); ?>
