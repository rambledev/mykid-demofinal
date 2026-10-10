<?php
/** Admin (Package A) — school calendar. */
declare(strict_types=1);

mk_header(['id' => 'admin-calendar', 'title' => 'ปฏิทินโรงเรียน', 'nav' => 'calendar']);
page_title('📅', 'ปฏิทินโรงเรียน', 'วัน เวลา ชุดที่ต้องใส่ และสิ่งที่ต้องเตรียม', btn_add('calendarEvents', 'เพิ่มกิจกรรม'));
?>
<section class="card" data-live id="live-calendar"><?php render_calendar_list(scoped('calendarEvents'), true); ?></section>
<?php
render_form_sheet('calendarEvents', 'เพิ่มกิจกรรมในปฏิทิน');
mk_footer();
