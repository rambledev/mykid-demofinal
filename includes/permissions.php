<?php
/**
 * Package A — permission matrix: ROLE → SCOPE → tables it may READ / WRITE.
 *
 *   USER → ROLE → SCHOOL SCOPE → CLASSROOM SCOPE → STUDENT SCOPE → DATA
 *
 *   scope 'platform'  → every school (Super Admin)
 *   scope 'school'    → the user's school only (school_id from the session)
 *   scope 'classroom' → the user's school + classroom (Teacher)
 *   scope 'student'   → the user's school + child (Parent)
 */
declare(strict_types=1);

$studentDaily = ['attendance', 'healthRecords', 'sleepRecords', 'foodIntake', 'portfolio', 'photos', 'stars', 'development', 'pickups', 'mediaFiles', 'studentStatuses'];

return [
    'super_admin' => [
        'scope' => 'platform',
        'read'  => '*',
        'write' => ['schools', 'users', 'cameras'],
    ],
    'admin' => [
        'scope' => 'school',
        'read'  => '*',
        'write' => ['students', 'teachers', 'classrooms', 'activities', 'foodMenus', 'calendarEvents', 'attendance',
            'healthRecords', 'portfolio', 'stars', 'notifications', 'settings', 'cameras', 'mediaFiles'],
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
    ],
    'parent' => [
        'scope' => 'student',
        'read'  => array_merge(['schools', 'classrooms', 'teachers', 'students', 'activities', 'foodMenus', 'statuses',
            'calendarEvents', 'messages', 'notifications', 'settings'], $studentDaily), // cameras: via can_view_camera() only
        'write' => ['messages', 'pickups'], // pickups: create only (core/pickup.php)
    ],
];
