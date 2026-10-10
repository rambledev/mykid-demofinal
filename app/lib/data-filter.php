<?php
/**
 * Core data filter — the ONLY way pages read data.
 *
 * scoped($table) returns the rows the current user may see: the role must be allowed to
 * read the table (permission matrix) and every row must sit inside the user's
 * school → classroom → student scope (permissions.php). The scope comes from the session.
 */
declare(strict_types=1);

function scoped(string $table, ?array $user = null): array
{
    $user ??= current_user();
    if (!$user || !can_read_table($user, $table)) {
        mk_log('DataFilter', 'READ DENIED', ['table' => $table, 'role' => $user['role'] ?? null]);
        return [];
    }
    return array_values(array_filter(store_rows($table), fn ($row) => row_in_scope($user, $table, $row)));
}

function scoped_find(string $table, int $id): ?array
{
    foreach (scoped($table) as $row) {
        if ($row['id'] === $id) {
            return $row;
        }
    }
    return null;
}

/* ---------------------------------------------------------------------------
 * Small array helpers
 * ------------------------------------------------------------------------ */

function where(array $rows, string $key, mixed $value): array
{
    return array_values(array_filter($rows, fn ($r) => ($r[$key] ?? null) === $value));
}

function group_by(array $rows, string $key): array
{
    $groups = [];
    foreach ($rows as $row) {
        $groups[$row[$key] ?? ''][] = $row;
    }
    return $groups;
}

function today_rows(array $rows, string $dateKey = 'date'): array
{
    return array_values(array_filter($rows, fn ($r) => ($r[$dateKey] ?? '') === today()));
}

function count_by(array $rows, string $key): array
{
    $counts = [];
    foreach ($rows as $row) {
        $counts[$row[$key]] = ($counts[$row[$key]] ?? 0) + 1;
    }
    return $counts;
}

/* ---------------------------------------------------------------------------
 * Scoped domain helpers
 * ------------------------------------------------------------------------ */

function my_classrooms(): array
{
    $rooms = scoped('classrooms');
    usort($rooms, fn ($a, $b) => [$a['school_id'], $a['name']] <=> [$b['school_id'], $b['name']]);
    return $rooms;
}

function classroom_students(int $classroomId): array
{
    return where(scoped('students'), 'classroom_id', $classroomId);
}

function classroom_teachers(int $classroomId): array
{
    return where(scoped('teachers'), 'classroom_id', $classroomId);
}

function status_view(array $row): array
{
    $def = cat_find('statuses', $row['code']) ?? ['label' => $row['code'], 'emoji' => '⭐', 'color' => '#F5F5F5'];
    $teacher = $row['by'] ? find_row('teachers', (int) $row['by']) : null; // display name only
    return $row + [
        'label'  => $def['label'],
        'emoji'  => $def['emoji'],
        'color'  => $def['color'],
        'text'   => $def['label'] . $row['note'],
        'byName' => $teacher['nickname'] ?? 'คุณครู',
    ];
}

function classroom_status(int $classroomId): ?array
{
    $row = where(scoped('statuses'), 'classroom_id', $classroomId)[0] ?? null;
    return $row ? status_view($row) : null;
}

function classroom_status_history(int $classroomId): array
{
    return array_map('status_view', array_values(array_filter(
        where(scoped('statuses'), 'classroom_id', $classroomId),
        fn ($r) => is_today($r['at'])
    )));
}

/** A classroom's activity schedule for one day (default: today). */
function classroom_activities(int $classroomId, ?string $date = null): array
{
    $date ??= today();
    $rows = array_values(array_filter(where(scoped('activities'), 'classroom_id', $classroomId), fn ($a) => ($a['date'] ?? '') === $date));
    usort($rows, fn ($a, $b) => strcmp($a['time'], $b['time']));
    return $rows;
}

/** The classroom's menu row for one day (default: today), or null. */
function classroom_menu_row(int $classroomId, ?string $date = null): ?array
{
    $date ??= today();
    foreach (where(scoped('foodMenus'), 'classroom_id', $classroomId) as $menu) {
        if (($menu['date'] ?? '') === $date) {
            return $menu;
        }
    }
    return null;
}

/** Dates (newest first) that have a menu / activities for the classroom, excluding today. */
function classroom_history_dates(string $table, int $classroomId, int $limit = 6): array
{
    $dates = array_unique(array_map(fn ($r) => $r['date'] ?? '', where(scoped($table), 'classroom_id', $classroomId)));
    $dates = array_filter($dates, fn ($d) => $d !== '' && $d < today());
    rsort($dates);
    return array_slice($dates, 0, $limit);
}

/**
 * Past menu rows of a classroom (newest first): the last $days days, plus any older day that
 * has food images — so expired images still show their place in history.
 */
function classroom_food_history(int $classroomId, int $days = 6): array
{
    $rows = array_values(array_filter(where(scoped('foodMenus'), 'classroom_id', $classroomId), fn ($m) => ($m['date'] ?? '') < today()));
    usort($rows, fn ($a, $b) => strcmp($b['date'], $a['date']));
    $withMedia = has_feature('media') ? media_by_owner('food') : [];
    return array_values(array_filter($rows, fn ($m, $i) => $i < $days || isset($withMedia[$m['id']]), ARRAY_FILTER_USE_BOTH));
}

/** Meal types merged with the classroom's menu items for one day (default: today). */
function classroom_food(int $classroomId, ?string $date = null): array
{
    $menu = classroom_menu_row($classroomId, $date) ?? [];
    return array_map(function ($meal) use ($menu) {
        $meal['items'] = $menu[$meal['meal']] ?? [];
        return $meal;
    }, mk_catalog()['meals']);
}

/** Today's record of a student-level table (attendance, sleep, health, intake, pickup). */
function student_today(string $table, int $studentId): ?array
{
    foreach (scoped($table) as $row) {
        if ($row['student_id'] === $studentId && ($row['date'] ?? '') === today()) {
            return $row;
        }
    }
    return null;
}

/** Today's records of a student-level table keyed by student id. */
function today_by_student(string $table): array
{
    $rows = [];
    foreach (today_rows(scoped($table)) as $row) {
        $rows[$row['student_id']] = $row;
    }
    return $rows;
}

function student_stars(int $studentId): array
{
    $rows = where(scoped('stars'), 'student_id', $studentId);
    usort($rows, fn ($a, $b) => [$b['date'], $b['id']] <=> [$a['date'], $a['id']]);
    return $rows;
}

function stars_total(array $rows): int
{
    return array_sum(array_column($rows, 'points'));
}

function student_thread(int $studentId): array
{
    $rows = where(scoped('messages'), 'student_id', $studentId);
    usort($rows, fn ($a, $b) => strcmp($a['at'], $b['at']));
    return $rows;
}

/* ---------------------------------------------------------------------------
 * Aggregations for Admin / Executive / Super Admin (all from scoped rows)
 * ------------------------------------------------------------------------ */

function kpis(): array
{
    $rooms = scoped('classrooms');
    $menus = today_rows(scoped('foodMenus'));
    $ready = 0;
    foreach ($rooms as $room) {
        $menu = where($menus, 'classroom_id', $room['id'])[0] ?? null;
        $ready += $menu && !empty($menu['lunch']) ? 1 : 0;
    }
    $attendance = today_rows(scoped('attendance'));
    $here = count(array_filter($attendance, fn ($a) => in_array($a['status'], ['present', 'late'], true)));

    return [
        'schools'    => count(scoped('schools')),
        'students'   => count(scoped('students')),
        'teachers'   => count(scoped('teachers')),
        'classrooms' => count($rooms),
        'parents'    => count(scoped('students')),
        'activities' => count(today_rows(scoped('activities'))),
        'activitiesPerRoom' => $rooms ? (int) round(count(today_rows(scoped('activities'))) / count($rooms)) : 0,
        'foodReady'  => $ready,
        'present'    => $here,
        'attendancePct' => percent($here, count($attendance)),
    ];
}

/** Attendance counts by status for a set of rows. */
function attendance_counts(array $rows): array
{
    $counts = array_fill_keys(array_column(mk_catalog()['attendance'], 'code'), 0);
    foreach ($rows as $row) {
        $counts[$row['status']]++;
    }
    return $counts;
}

/**
 * Attendance % for the last N school days (today = real data, earlier days = mock history
 * derived deterministically from the scope so it stays stable between reloads).
 */
function attendance_trend(int $days, string $salt = ''): array
{
    $todayPct = kpis()['attendancePct'];
    $series = [];
    $ts = time();
    $n = 0;
    while (count($series) < $days) {
        $dow = (int) date('w', $ts);
        if ($dow !== 0 && $dow !== 6) {
            $label = THAI_DAYS_SHORT[$dow] . ' ' . date('j', $ts);
            $series[] = ['label' => $label, 'value' => $n === 0 ? $todayPct : 86 + mk_rand($salt, date('Y-m-d', $ts)) % 12];
            $n++;
        }
        $ts = strtotime('-1 day', $ts);
    }
    return array_reverse($series);
}

/** Multiplier used by the mock period filter on reports. */
function period_days(string $period): int
{
    return ['today' => 1, 'week' => 5, 'month' => 22][$period] ?? 1;
}

/**
 * Latest individual status of each child TODAY (set by the teacher), keyed by student id.
 * Scope-filtered: teacher → own classroom, parent → own child.
 */
function today_student_statuses(): array
{
    if (!can_read_table(current_user(), 'studentStatuses')) {
        return [];
    }
    $latest = [];
    foreach (scoped('studentStatuses') as $row) {
        if (($row['date'] ?? '') !== today()) {
            continue;
        }
        $current = $latest[$row['student_id']] ?? null;
        if (!$current || [$row['at'], $row['id']] > [$current['at'], $current['id']]) {
            $latest[$row['student_id']] = $row;
        }
    }
    return $latest;
}
