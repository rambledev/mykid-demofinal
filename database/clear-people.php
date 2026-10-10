<?php
/**
 * CLI: remove classrooms, students and teachers — and everything bound to them — so an Admin can
 * enter the real data. Runs against the database in env vars / .env, in ONE transaction.
 *
 *   php database/clear-people.php            show what would be removed (nothing is changed)
 *   php database/clear-people.php --confirm  remove it
 *
 * Removed: classrooms, students, teachers, teacher + parent accounts, parent ↔ child links and all
 *          student / classroom records (attendance, health, sleep, food, stars, chat, pickups, works,
 *          photos, activities, menus, statuses, image metadata), their notifications.
 * Kept:    the school, settings, admin / executive accounts, calendar, school-wide announcements,
 *          cameras (unassigned from the removed rooms — the admin assigns them again).
 * Back up first, e.g.: mariadb-dump -h HOST -P PORT -u USER DB > backup.sql
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/app/bootstrap.php';

const CLEAR_TABLES = ['classrooms', 'students', 'teachers', 'parentStudents',
    'attendance', 'sleepRecords', 'healthRecords', 'foodIntake', 'studentStatuses', 'stars', 'development',
    'portfolio', 'messages', 'pickups', 'mediaFiles', 'activities', 'foodMenus', 'statuses', 'photos'];

/** The state after clearing (pure function — used for the dry run and the real run). */
function clear_people(array $tables): array
{
    $removed = array_flip(array_column(array_filter($tables['users'], fn ($u) => in_array($u['role'], ['teacher', 'parent'], true)), 'id'));
    foreach (CLEAR_TABLES as $table) {
        $tables[$table] = [];
    }
    $tables['users'] = array_values(array_filter($tables['users'], fn ($u) => !isset($removed[$u['id']])));
    $tables['userNotifications'] = array_values(array_filter($tables['userNotifications'], fn ($n) => !isset($removed[$n['user_id']])));
    $tables['notifications'] = array_values(array_filter($tables['notifications'], fn ($n) => $n['classroom_id'] === null && $n['student_id'] === null));
    foreach ($tables['cameras'] as &$camera) {
        if ($camera['classroom_id'] !== null) {
            $camera['classroom_id'] = null;
            $camera['scene'] = 'common';
        }
    }
    unset($camera);
    $tables['cameraPermissions'] = array_values(array_filter($tables['cameraPermissions'], fn ($p) => $p['classroom_id'] === null));
    return $tables;
}

$confirm = in_array('--confirm', $argv, true);
printf("[ClearPeople] database %s@%s:%s/%s · %s\n\n", mk_env('DB_USER'), mk_env('DB_HOST'), mk_env('DB_PORT'), mk_env('DB_NAME'),
    $confirm ? 'REMOVING' : 'dry run (add --confirm to remove)');

$current = [];
foreach (store_tables() as $table) {
    $current[$table] = store_rows($table);
}
$after = clear_people($current);
printf("  %-20s %8s → %s\n", 'table', 'now', 'after');
foreach ($after as $table => $rows) {
    if (count($current[$table]) !== count($rows) || $table === 'cameras') {
        printf("  %-20s %8d → %d\n", $table, count($current[$table]), count($rows));
    }
}
echo "\n  accounts kept: " . implode(', ', array_map(fn ($u) => $u['role'] . ' ' . $u['phone'], $after['users'])) . "\n";

if (!$confirm) {
    echo "\nNothing changed. Run again with --confirm to remove.\n";
    exit(0);
}
store_mutate(function (array &$state) {
    $state['tables'] = clear_people($state['tables']);
});
echo "\n✅ Removed. Uploaded image files of the removed records stay in storage/uploads (no longer reachable).\n";
