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

/** Every classroom of the user's school (for school-wide features such as ธนาคารความดี). */
function my_classrooms_all(): array
{
    $rooms = where(store_rows('classrooms'), 'school_id', current_user()['school_id']);
    usort($rooms, fn ($a, $b) => $a['name'] <=> $b['name']);
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

/* ---------------------------------------------------------------------------
 * ธนาคารความดีขับขี่ปลอดภัย (stars) — students and parents, ranked per month
 * ------------------------------------------------------------------------ */

const STAR_BANK_NAME = 'ธนาคารความดี';
const STAR_BANK_FULL_NAME = 'ธนาคารความดีขับขี่ปลอดภัย';

/** 'good' (ความดี) | 'safe_driving' (ขับขี่ปลอดภัย); rows from before categories: a parent star = safe_driving. */
function star_category(array $row): string
{
    $category = (string) ($row['category'] ?? '');
    if ($category === 'safe_driving' || ($row['recipient'] ?? 'student') === 'parent') {
        return 'safe_driving';
    }
    return 'good';
}

/** Star rows (all of them, or one category), newest first. Every row is credited to its student_id. */
function star_rows(?string $category = null, ?array $rows = null): array
{
    $rows = array_values(array_filter($rows ?? scoped('stars'), fn ($r) => $category === null || star_category($r) === $category));
    usort($rows, fn ($a, $b) => [$b['date'], $b['id']] <=> [$a['date'], $a['id']]);
    return $rows;
}

/** A student's bank: good deeds + safe driving together. */
function student_stars(int $studentId): array
{
    return where(star_rows(), 'student_id', $studentId);
}

/** [category => stars] of some rows, every category present. */
function stars_by_category(array $rows): array
{
    $totals = array_fill_keys(array_column(mk_catalog()['starCategories'], 'code'), 0);
    foreach ($rows as $r) {
        $totals[star_category($r)] += (int) $r['points'];
    }
    return $totals;
}

function stars_total(array $rows): int
{
    return array_sum(array_column($rows, 'points'));
}

/** Month from the URL ("2026-10"), or the current month when missing / invalid / in the future. */
function star_month(?string $param): string
{
    return $param && preg_match('/^(20\d\d)-(0[1-9]|1[0-2])$/', $param) && $param <= date('Y-m') ? $param : date('Y-m');
}

/** "ตุลาคม 2569" */
function thai_month_label(string $month): string
{
    [$y, $m] = array_map('intval', explode('-', $month));
    return THAI_MONTHS[$m] . ' ' . ($y + 543);
}

function stars_in_month(array $rows, string $month): array
{
    return array_values(array_filter($rows, fn ($r) => str_starts_with((string) $r['date'], $month)));
}

/** Months that have stars (newest first), always including the current month. */
function star_months(array $rows): array
{
    $months = array_unique(array_merge([date('Y-m')], array_map(fn ($r) => substr((string) $r['date'], 0, 7), $rows)));
    rsort($months);
    return $months;
}

/**
 * Monthly ranking: [['id' => recipient id, 'total' => stars, 'count' => times, 'rank' => 1..]] sorted by
 * stars (most first). Equal totals share a rank (1, 1, 3, ...). $key = 'student_id' | 'parent_id'.
 * $ids lists everybody who should appear even with 0 stars (e.g. the whole class).
 */
function star_ranking(array $monthRows, string $key, array $ids = []): array
{
    $totals = array_fill_keys($ids, 0);
    $counts = array_fill_keys($ids, 0);
    foreach ($monthRows as $r) {
        if ($r[$key] === null) {
            continue;
        }
        $totals[$r[$key]] = ($totals[$r[$key]] ?? 0) + (int) $r['points'];
        $counts[$r[$key]] = ($counts[$r[$key]] ?? 0) + 1;
    }
    $list = [];
    foreach ($totals as $id => $total) {
        $list[] = ['id' => (int) $id, 'total' => $total, 'count' => $counts[$id] ?? 0];
    }
    usort($list, fn ($a, $b) => [$b['total'], $a['id']] <=> [$a['total'], $b['id']]);
    $rank = 0;
    $previous = null;
    foreach ($list as $i => &$row) {
        if ($row['total'] !== $previous) {
            $rank = $i + 1;
            $previous = $row['total'];
        }
        $row['rank'] = $row['total'] > 0 ? $rank : null; // nobody is ranked with 0 stars
    }
    unset($row);
    return $list;
}

/** Rank position of one id inside a ranking (null = no stars this month). */
function star_rank_of(array $ranking, int $id): ?array
{
    foreach ($ranking as $row) {
        if ($row['id'] === $id) {
            return $row;
        }
    }
    return null;
}

/** Parent user ids linked to a student (parentStudents, or the legacy users.student_id link). */
function student_parent_ids(int $studentId): array
{
    $ids = array_column(where(store_rows('parentStudents'), 'student_id', $studentId), 'user_id');
    foreach (store_rows('users') as $u) {
        if ($u['role'] === 'parent' && $u['student_id'] === $studentId) {
            $ids[] = $u['id'];
        }
    }
    return array_values(array_unique($ids));
}

/** "🥇" / "🥈" / "🥉" / "4" — medal for the top three. */
function rank_badge(?int $rank): string
{
    return $rank === null ? '–' : ([1 => '🥇', 2 => '🥈', 3 => '🥉'][$rank] ?? (string) $rank);
}

function student_thread(int $studentId): array
{
    $rows = where(scoped('messages'), 'student_id', $studentId);
    usort($rows, fn ($a, $b) => strcmp($a['at'], $b['at']));
    return $rows;
}

/* ---------------------------------------------------------------------------
 * Aggregations for Admin / Executive (all from scoped rows)
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
        if (isset($counts[$row['status']])) { // '' = not checked yet (real use, new day)
            $counts[$row['status']]++;
        }
    }
    return $counts;
}

/**
 * Attendance % of the last N school days that have checked attendance (real records only):
 * present + late ÷ students with a status that day. Days without any check are skipped.
 */
function attendance_trend(int $days): array
{
    $byDate = [];
    foreach (scoped('attendance') as $a) {
        if (($a['status'] ?? '') === '') {
            continue; // sheet opened, not checked yet
        }
        $byDate[$a['date']]['all'] = ($byDate[$a['date']]['all'] ?? 0) + 1;
        $byDate[$a['date']]['here'] = ($byDate[$a['date']]['here'] ?? 0) + (in_array($a['status'], ['present', 'late'], true) ? 1 : 0);
    }
    krsort($byDate);
    $series = [];
    foreach (array_slice($byDate, 0, $days, true) as $date => $n) {
        $ts = strtotime($date);
        $series[] = ['label' => THAI_DAYS_SHORT[(int) date('w', $ts)] . ' ' . date('j', $ts), 'value' => percent($n['here'], $n['all'])];
    }
    return array_reverse($series);
}

/** First day (Y-m-d) of a report period: today, this week (Monday) or this month. */
function period_start(string $period): string
{
    return match ($period) {
        'week'  => date('Y-m-d', strtotime('monday this week')),
        'month' => date('Y-m-01'),
        default => today(),
    };
}

/** Rows whose date (or datetime) column falls between $from and today. */
function rows_since(array $rows, string $from, string $field = 'date'): array
{
    return array_values(array_filter($rows, fn ($r) => ($d = substr((string) ($r[$field] ?? ''), 0, 10)) !== '' && $d >= $from && $d <= today()));
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
