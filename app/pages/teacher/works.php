<?php
/**
 * Teacher (Package A) — ผลงานนักเรียน: choose classroom → choose a child → add / edit / delete works
 * (title, category, date, description, several images). Works always belong to ONE child.
 * Only the teacher's own classroom(s) and children are listed (scoped on the server).
 */
declare(strict_types=1);

$user = current_user();
$rooms = my_classrooms();

mk_header(['id' => 'teacher-works', 'title' => 'ผลงานนักเรียน', 'nav' => 'works']);
page_title('🎨', 'ผลงานนักเรียน', 'เลือกห้องเรียน → เลือกนักเรียน → เพิ่มผลงาน');
?>
<?php if (count($rooms) > 1): ?>
    <?php tab_bar('wroom', array_column(array_map(fn ($r) => ['k' => $r['id'], 'v' => $r['emoji'] . ' ' . $r['name']], $rooms), 'v', 'k')); ?>
<?php else: ?>
    <p class="chip-row"><span class="chip"><?= $rooms[0]['emoji'] ?? '' ?> ห้อง<?= e($rooms[0]['name'] ?? '') ?></span></p>
<?php endif; ?>

<div data-live id="live-works">
    <?php $works = scoped('portfolio'); ?>
    <?php foreach ($rooms as $r => $room): ?>
        <?php $kids = classroom_students($room['id']); ?>
        <section data-tab-panel="wroom:<?= $room['id'] ?>"<?= $r ? ' hidden' : '' ?>>
            <div class="card">
                <?php section_head('🧒', 'เลือกนักเรียน', '<span class="count-badge">' . count($kids) . ' คน</span>'); ?>
                <div class="work-students" role="tablist" data-tabs="wstu<?= $room['id'] ?>">
                    <?php foreach ($kids as $i => $s): ?>
                        <?php $n = count(where($works, 'student_id', $s['id'])); ?>
                        <button type="button" role="tab" class="work-students__item<?= $i ? '' : ' is-active' ?>" data-tab="<?= $s['id'] ?>" aria-selected="<?= $i ? 'false' : 'true' ?>">
                            <?= student_avatar($s, 'sm') ?><span><?= e($s['nickname']) ?></span><small><?= $n ?> ผลงาน</small>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php foreach ($kids as $i => $s): ?>
                <section class="card" data-tab-panel="wstu<?= $room['id'] ?>:<?= $s['id'] ?>"<?= $i ? ' hidden' : '' ?>>
                    <?php section_head('🎨', 'ผลงานของ' . $s['nickname'], btn_add('portfolio', 'เพิ่มผลงาน', ['student_id' => $s['id'], 'date' => today()]), $s['name']); ?>
                    <?php render_student_works(where($works, 'student_id', $s['id']), true); ?>
                </section>
            <?php endforeach; ?>
        </section>
    <?php endforeach; ?>
</div>
<p class="hint hint--card">🔒 แสดงเฉพาะนักเรียนในห้องที่รับผิดชอบ · รูปผลงานเก็บไฟล์ 6 เดือน (ข้อมูลผลงานยังอยู่ครบ)</p>
<?php
render_form_sheet('portfolio', 'ผลงานนักเรียน');
mk_footer();
