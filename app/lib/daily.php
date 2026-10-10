<?php
/**
 * Daily sheets — every student gets today's EMPTY records (attendance not checked, nap pending,
 * health not recorded, no meals) that the teacher fills in. Nothing is invented: no random data,
 * no copied schedules or menus.
 *
 * daily_sheets_fill() runs inside every store_mutate() write and once per new day (store_state), so a
 * student added today gets a sheet immediately and every school day starts with a clean sheet.
 */
declare(strict_types=1);

/** Append today's empty records for students that do not have them yet. Returns how many students. */
function daily_sheets_fill(array &$state): int
{
    $today = today();
    $t = &$state['tables'];
    $has = [];
    foreach ($t['attendance'] ?? [] as $a) {
        if ($a['date'] === $today) {
            $has[$a['student_id']] = true;
        }
    }
    $added = 0;
    foreach ($t['students'] ?? [] as $st) {
        if (isset($has[$st['id']])) {
            continue;
        }
        $base = ['school_id' => $st['school_id'], 'classroom_id' => $st['classroom_id'], 'student_id' => $st['id'], 'date' => $today];
        $t['attendance'][] = ['id' => $state['nextId']++] + $base + ['status' => '', 'checkIn' => '', 'note' => ''];
        $t['sleepRecords'][] = ['id' => $state['nextId']++] + $base + ['start' => '', 'end' => '', 'quality' => 'pending', 'note' => ''];
        $t['healthRecords'][] = ['id' => $state['nextId']++] + $base + ['temperature' => null, 'condition' => '', 'symptoms' => '', 'note' => ''];
        $t['foodIntake'][] = ['id' => $state['nextId']++] + $base + ['levels' => []];
        $added++;
    }
    if ($added) {
        mk_log('Daily', 'daily_sheets_fill', ['date' => $today, 'students' => $added]);
    }
    return $added;
}
