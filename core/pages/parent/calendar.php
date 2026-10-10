<?php
/** Parent (Package A) — school calendar of the child's school. */
declare(strict_types=1);

$user = current_user();

mk_header(['id' => 'parent-calendar', 'title' => 'ปฏิทินโรงเรียน', 'nav' => 'calendar']);
page_title('📅', 'ปฏิทินโรงเรียน', $user['school']['name']);
?>
<section class="card" data-live id="live-calendar"><?php render_calendar_list(scoped('calendarEvents')); ?></section>
<?php mk_footer(); ?>
