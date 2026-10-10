<?php
/**
 * Permission matrix: ROLE → SCOPE → tables it may READ / WRITE.
 *
 *   USER → ROLE → SCHOOL SCOPE → CLASSROOM SCOPE → STUDENT SCOPE → DATA
 *
 *   scope 'school'    → the user's school only (school_id from the session)
 *   scope 'classroom' → the user's school + classroom (Teacher)
 *   scope 'student'   → the user's school + child (Parent)
 *   tableScope        → per-table override of the scope (e.g. teacher + stars = whole school)
 */
declare(strict_types=1);

$studentDaily = ['attendance', 'healthRecords', 'sleepRecords', 'foodIntake', 'portfolio', 'photos', 'stars', 'development', 'pickups', 'mediaFiles', 'studentStatuses'];

return [
    'admin' => [
        'scope' => 'school',
        'read'  => '*',
        'write' => ['students', 'teachers', 'classrooms', 'activities', 'foodMenus', 'calendarEvents', 'attendance',
            'healthRecords', 'portfolio', 'notifications', 'settings', 'cameras', 'mediaFiles',
            'users'], // users: Admin / Executive accounts of the own school only (app/pages/api.php)
    ],
    'executive' => [
        'scope' => 'school',
        'read'  => '*',
        'write' => [], // read only
    ],
    'teacher' => [
        'scope' => 'classroom',
        'read'  => array_merge(['schools', 'classrooms', 'teachers', 'students', 'activities', 'foodMenus', 'statuses',
            'calendarEvents', 'messages', 'notifications', 'settings'], $studentDaily),
        'write' => ['activities', 'foodMenus', 'statuses', 'attendance', 'healthRecords', 'sleepRecords', 'foodIntake',
            'portfolio', 'photos', 'stars', 'development', 'messages', 'mediaFiles', 'pickups', 'studentStatuses'],
        // ธนาคารความดี: ONLY teachers give stars, to any student / parent of the school (not just the own room).
        'tableScope' => ['stars' => 'school'],
    ],
    'parent' => [
        'scope' => 'student',
        'read'  => array_merge(['schools', 'classrooms', 'teachers', 'students', 'activities', 'foodMenus', 'statuses',
            'calendarEvents', 'messages', 'notifications', 'settings'], $studentDaily), // cameras: via can_view_camera() only
        'write' => ['messages', 'pickups'], // pickups: create only (core/pickup.php)
    ],
];
