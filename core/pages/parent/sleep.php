<?php
/** Parent (Package A) — the child's nap today. */
declare(strict_types=1);

$user = current_user();
$child = $user['child'];

mk_header(['id' => 'parent-sleep', 'title' => 'การนอน', 'nav' => 'sleep']);
page_title('😴', 'การนอนกลางวัน', thai_date() . ' · ' . $child['nickname']);
?>
<section class="card" data-live id="live-sleep">
    <?php render_sleep_card(student_today('sleepRecords', $child['id'])); ?>
</section>
<p class="hint hint--card">💡 เด็กวัยอนุบาลควรนอนกลางวันประมาณ 1–2 ชั่วโมง คุณครูจะบันทึกเวลาให้ทุกวัน</p>
<?php mk_footer(); ?>
