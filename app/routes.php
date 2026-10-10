<?php
/**
 * Route table — the ONLY URLs the app answers (everything else is a friendly 404).
 *
 * Static routes: [methods, path pattern, page, options]. {name} segments are dynamic and
 * read with route_param('name'). Pages live in app/pages/<page>.php.
 * Role sections: GET /<section> and /<section>/<page> render the page as that role
 * (require_role() — wrong role / not logged in is redirected by the auth layer).
 */
declare(strict_types=1);

return [
    'routes' => [
        ['GET',      '/',              'home'],
        ['GET|POST', '/login',         'login'],
        ['POST',     '/logout',        'logout'],
        ['GET',      '/logout',        'logout'],          // demo role switch links (DEMO_MODE); plain GET only logs out
        ['POST',     '/api',           'api',     ['json' => true]],
        ['GET',      '/media/{token}', 'media'],
        ['GET',      '/healthz',       'healthz', ['stateless' => true]],
        ['GET',      '/readyz',        'readyz',  ['stateless' => true]],
        // ธนาคารความดีขับขี่ปลอดภัย — monthly ranking of another month (YYYY-MM)
        ['GET',      '/admin/stars/{month}',     'common/stars', ['role' => 'admin']],
        ['GET',      '/executive/stars/{month}', 'common/stars', ['role' => 'executive']],
        ['GET',      '/teacher/stars/{month}',   'common/stars', ['role' => 'teacher']],
        ['GET',      '/parent/stars/{month}',    'parent/stars', ['role' => 'parent']],
        // Chat: open one thread directly (from a notification) — {student} = student id token
        ['GET',      '/teacher/chat/{student}',  'teacher/chat', ['role' => 'teacher']],
        ['GET',      '/parent/chat/{student}',   'parent/chat',  ['role' => 'parent']],
    ],
    'sections' => [
        'admin' => ['role' => 'admin', 'pages' => [
            'account'    => 'common/account',
            'activities' => 'admin/activities',
            'attendance' => 'admin/attendance',
            'calendar'   => 'admin/calendar',
            'cctv'       => 'common/cctv',
            'classrooms' => 'admin/classrooms',
            'food'       => 'admin/food',
            'health'     => 'admin/health',
            'index'      => 'admin/index',
            'menu'       => 'common/menu',
            'settings'   => 'admin/settings',
            'stars'      => 'common/stars',
            'storage'    => 'admin/storage',
            'students'   => 'admin/students',
            'teachers'   => 'admin/teachers',
            'users'      => 'admin/users',
        ]],
        'executive' => ['role' => 'executive', 'pages' => [
            'account'   => 'common/account',
            'cctv'      => 'common/cctv',
            'dashboard' => 'executive/dashboard',
            'index'     => 'executive/index',
            'reports'   => 'executive/reports',
            'stars'     => 'common/stars',
        ]],
        'teacher' => ['role' => 'teacher', 'pages' => [
            'account'       => 'common/account',
            'activities'    => 'teacher/activities',
            'attendance'    => 'teacher/attendance',
            'cctv'          => 'common/cctv',
            'chat'          => 'teacher/chat',
            'development'   => 'teacher/development',
            'food'          => 'teacher/food',
            'health'        => 'teacher/health',
            'index'         => 'teacher/index',
            'menu'          => 'common/menu',
            'notifications' => 'teacher/notifications',
            'pickup'        => 'teacher/pickup',
            'portfolio'     => 'teacher/portfolio',
            'sleep'         => 'teacher/sleep',
            'stars'         => 'common/stars',
            'status'        => 'teacher/status',
            'works'         => 'teacher/works',
        ]],
        'parent' => ['role' => 'parent', 'pages' => [
            'account'       => 'common/account',
            'attendance'    => 'parent/attendance',
            'calendar'      => 'parent/calendar',
            'cctv'          => 'common/cctv',
            'chat'          => 'parent/chat',
            'child'         => 'parent/child',
            'development'   => 'parent/development',
            'food'          => 'parent/food',
            'health'        => 'parent/health',
            'index'         => 'parent/index',
            'menu'          => 'common/menu',
            'notifications' => 'parent/notifications',
            'pickup'        => 'parent/pickup',
            'portfolio'     => 'parent/portfolio',
            'sleep'         => 'parent/sleep',
            'stars'         => 'parent/stars',
            'timeline'      => 'parent/timeline',
            'works'         => 'parent/works',
        ]],
    ],
];
