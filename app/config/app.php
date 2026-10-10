<?php
/**
 * App configuration — Mykid Full (formerly Package A). Multi-school (multi-tenant), 5 roles, every feature.
 */
declare(strict_types=1);

return [
    'name'        => 'Mykid Full',
    'roles'       => ['super_admin', 'admin', 'executive', 'teacher', 'parent'],
    'session'     => 'MYKIDDFA', // mykid-demofinal: own cookie name — never share sessions with the original Final Demo
    'tier'        => 'full',
    'tierLabel'   => 'Full',
    'multiSchool' => true,
    'meals'       => ['breakfast', 'lunch', 'snack', 'milk', 'fruit'],
    'modules'     => [
        'students', 'teachers', 'classrooms', 'activities', 'food', 'status', 'reports', 'attendanceReport',
        'multiSchool', 'attendance', 'health', 'sleep', 'foodIntake', 'portfolio', 'photos', 'stars',
        'development', 'calendar', 'chat', 'notifications', 'pickup', 'cctv', 'settings', 'timeline', 'media', 'studentStatus', 'inAppNotifications',
    ],

    // Navigation per role: [key, label, page, icon, show in mobile bottom nav]
    'nav' => [
        'super_admin' => [
            ['home', 'ภาพรวม', 'super-admin/index.php', 'home', true],
            ['schools', 'โรงเรียน', 'super-admin/schools.php', 'school', true],
            ['school', 'ดูรายโรงเรียน', 'super-admin/school.php', 'list', false],
            ['compare', 'เปรียบเทียบ', 'super-admin/compare.php', 'chart', true],
            ['users', 'ผู้ดูแลโรงเรียน', 'super-admin/users.php', 'users', false],
            ['cctv', 'CCTV Management', 'super-admin/cctv.php', 'camera', true],
            ['account', 'บัญชี', 'super-admin/account.php', 'user', false],
        ],
        'admin' => [
            ['home', 'หน้าหลัก', 'admin/index.php', 'home', true],
            ['students', 'นักเรียน', 'admin/students.php', 'child', true],
            ['teachers', 'ครู', 'admin/teachers.php', 'users', false],
            ['classrooms', 'ห้องเรียน', 'admin/classrooms.php', 'school', false],
            ['activities', 'กิจกรรม', 'admin/activities.php', 'list', true],
            ['food', 'อาหาร', 'admin/food.php', 'food', false],
            ['calendar', 'ปฏิทิน', 'admin/calendar.php', 'calendar', false],
            ['attendance', 'การมาเรียน', 'admin/attendance.php', 'check', true],
            ['health', 'สุขภาพ', 'admin/health.php', 'heart', false],
            ['stars', 'ดาวสะสม', 'admin/stars.php', 'star', false],
            ['cctv', 'กล้องวงจรปิด', 'admin/cctv.php', 'camera', false],
            ['storage', 'จัดการพื้นที่จัดเก็บ', 'admin/storage.php', 'image', false],
            ['settings', 'ตั้งค่าโรงเรียน', 'admin/settings.php', 'settings', false],
            ['account', 'บัญชี', 'admin/account.php', 'user', false],
        ],
        'executive' => [
            ['home', 'ภาพรวม', 'executive/index.php', 'home', true],
            ['dashboard', 'แดชบอร์ด', 'executive/dashboard.php', 'chart', true],
            ['reports', 'รายงาน', 'executive/reports.php', 'report', true],
            ['cctv', 'กล้องวงจรปิด', 'executive/cctv.php', 'camera', true],
            ['account', 'บัญชี', 'executive/account.php', 'user', true],
        ],
        'teacher' => [
            ['home', 'หน้าหลัก', 'teacher/index.php', 'home', true],
            ['status', 'สถานะ', 'teacher/status.php', 'status', true],
            ['attendance', 'เช็คชื่อ', 'teacher/attendance.php', 'check', true],
            ['pickup', 'รับ-ส่ง', 'teacher/pickup.php', 'car', true],
            ['stars', 'สะสมดาว', 'teacher/stars.php', 'star', true],
            ['chat', 'แชท', 'teacher/chat.php', 'chat', true],
            ['notifications', 'แจ้งเตือน', 'teacher/notifications.php', 'bell', false],
            ['works', 'ผลงานนักเรียน', 'teacher/works.php', 'image', false],
            ['activities', 'กิจกรรม', 'teacher/activities.php', 'list', false],
            ['food', 'อาหาร', 'teacher/food.php', 'food', false],
            ['sleep', 'การนอน', 'teacher/sleep.php', 'moon', false],
            ['health', 'สุขภาพ', 'teacher/health.php', 'heart', false],
            ['portfolio', 'ภาพกิจกรรม', 'teacher/portfolio.php', 'image', false],
            ['development', 'พัฒนาการ', 'teacher/development.php', 'sprout', false],
            ['cctv', 'กล้อง', 'teacher/cctv.php', 'camera', false],
            ['account', 'บัญชี', 'teacher/account.php', 'user', false],
        ],
        'parent' => [
            ['home', 'หน้าหลัก', 'parent/index.php', 'home', true],
            ['timeline', 'ไทม์ไลน์', 'parent/timeline.php', 'clock', true],
            ['pickup', 'รับ-ส่ง', 'parent/pickup.php', 'car', true],
            ['chat', 'แชท', 'parent/chat.php', 'chat', true],
            ['notifications', 'แจ้งเตือน', 'parent/notifications.php', 'bell', true],
            ['food', 'อาหาร', 'parent/food.php', 'food', false],
            ['sleep', 'การนอน', 'parent/sleep.php', 'moon', false],
            ['health', 'สุขภาพ', 'parent/health.php', 'heart', false],
            ['attendance', 'การมาเรียน', 'parent/attendance.php', 'check', false],
            ['works', 'ผลงานของฉัน', 'parent/works.php', 'image', false],
            ['portfolio', 'ภาพกิจกรรม', 'parent/portfolio.php', 'image', false],
            ['stars', 'ดาวสะสม', 'parent/stars.php', 'star', false],
            ['development', 'พัฒนาการ', 'parent/development.php', 'sprout', false],
            ['calendar', 'ปฏิทินโรงเรียน', 'parent/calendar.php', 'calendar', false],
            ['cctv', 'กล้อง', 'parent/cctv.php', 'camera', false],
            ['child', 'โปรไฟล์ลูก', 'parent/child.php', 'child', false],
            ['account', 'บัญชี', 'parent/account.php', 'user', false],
        ],
    ],
];
