<?php
/** Teacher (Package A) — classroom activity photos + children's portfolio. */
declare(strict_types=1);

$user = current_user();
$cid = $user['classroom_id'];

mk_header(['id' => 'teacher-portfolio', 'title' => 'ผลงาน & ภาพกิจกรรม', 'nav' => 'portfolio']);
page_title('🖼️', 'ผลงาน & ภาพกิจกรรม', 'ห้อง' . $user['classroom']['name']);
tab_bar('portfolio', ['photos' => '📷 ภาพกิจกรรม', 'works' => '🎨 ผลงานเด็ก']);
?>
<div data-live id="live-portfolio">
    <section class="card" data-tab-panel="portfolio:photos">
        <?php section_head('📷', 'ภาพกิจกรรมของห้อง', '<a class="btn btn--soft btn--sm" href="activities.php">📷 เพิ่มรูปที่กิจกรรม</a>', 'แยกตามวันและกิจกรรม · ไฟล์ภาพเก็บไว้ 6 เดือน'); ?>
        <?php render_activity_media_history([$cid]); ?>
    </section>
    <section class="card" data-tab-panel="portfolio:works" hidden>
        <?php section_head('🎨', 'ผลงานเด็ก', btn_add('portfolio', 'เพิ่มผลงาน'), 'เลือกนักเรียน → ชื่อผลงาน → รายละเอียด → รูปภาพ'); ?>
        <?php filter_bar('works', cat_options('portfolioCategories'), 'ค้นหาผลงานหรือชื่อเด็ก...'); ?>
        <?php
        $works = scoped('portfolio');
        usort($works, fn ($a, $b) => strcmp($b['date'], $a['date']));
        ?>
        <div data-filter-list="works"><?php render_portfolio_grid($works, true, true); ?></div>
    </section>
</div>
<?php
render_form_sheet('portfolio', 'เพิ่มผลงาน');
mk_footer();
