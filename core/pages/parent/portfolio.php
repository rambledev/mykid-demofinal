<?php
/** Parent (Package A) — the child's portfolio + classroom activity photos. */
declare(strict_types=1);

$user = current_user();
$child = $user['child'];
$works = where(scoped('portfolio'), 'student_id', $child['id']);
usort($works, fn ($a, $b) => strcmp($b['date'], $a['date']));

mk_header(['id' => 'parent-portfolio', 'title' => 'ผลงาน & ภาพกิจกรรม', 'nav' => 'portfolio']);
page_title('🖼️', 'ผลงาน & ภาพกิจกรรม', $child['nickname'] . ' · ' . $user['classroom']['name']);
tab_bar('portfolio', ['works' => '🎨 ผลงานของ' . $child['nickname'], 'photos' => '📷 ภาพกิจกรรม']);
?>
<div data-live id="live-portfolio">
    <section class="card" data-tab-panel="portfolio:works"><?php render_portfolio_grid($works); ?></section>
    <section class="card" data-tab-panel="portfolio:photos" hidden><?php render_activity_media_history([$child['classroom_id']]); ?></section>
</div>
<?php mk_footer(); ?>
