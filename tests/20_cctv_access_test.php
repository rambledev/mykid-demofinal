<?php
/**
 * CCTV Phase 3 — who may see / open which camera (can_view_camera, accessible_cameras, camera_stream).
 *
 * Cameras of school A (created through the admin API, like a real admin):
 *   ACC-C1  rooms A1+A2   teacher + parent      ACC-C5  room A3     teacher + parent
 *   ACC-C2  room A1       teacher only          ACC-C6  room A2     teacher + parent, INACTIVE
 *   ACC-C3  common        teacher only          ACC-C7  room A2     parent only
 *   ACC-C4  common        teacher + parent
 * School B: ACC-B1 room B1, teacher + parent.
 * parentA has two children (A1 + A2; users.student_id = the A1 child), parentOne one child in A3.
 */
declare(strict_types=1);

require __DIR__ . '/lib.php';

$base = getenv('TEST_BASE_URL') ?: 'http://127.0.0.1:8099';
$f = seed_fixture();
$tok = fn (string $table, int $id) => tok($table, $id);

// parentOne: a second parent with ONE child, in room A3
$f += store_mutate(function (array &$s) use ($f): array {
    $kid = $s['nextId']++;
    $parent = $s['nextId']++;
    $s['tables']['students'][] = ['id' => $kid, 'school_id' => $f['schoolA'], 'classroom_id' => $f['roomA3'], 'name' => 'kidA3', 'nickname' => 'kidA3',
        'classroom' => 'A3', 'gender' => 'f', 'avatar' => ['skin' => '#FFDBC0', 'hair' => '#3B2A20', 'bg' => '#BFE7FF', 'shirt' => '#7CC6F2', 'style' => 'short']];
    $s['tables']['users'][] = ['id' => $parent, 'name' => 'parentOne', 'phone' => '0810000006', 'pin' => pin_hash(TEST_PIN), 'must_change_pin' => false,
        'role' => 'parent', 'school_id' => $f['schoolA'], 'classroom_id' => null, 'student_id' => $kid, 'teacher_id' => null];
    $s['tables']['parentStudents'][] = ['id' => $s['nextId']++, 'school_id' => $f['schoolA'], 'user_id' => $parent, 'student_id' => $kid];
    return ['kidA3' => $kid, 'parentOne' => $parent];
})['result'];

$admin = new Client($base);
$admin->login('admin', '0810000001');
$adminB = new Client($base);
$adminB->login('admin', '0820000001');
$make = function (Client $c, string $code, array $rooms, bool $teacher, bool $parent, bool $active = true) use ($tok): void {
    $r = $c->api('save', ['table' => 'cameras', 'id' => '', 'data' => ['code' => $code, 'name' => $code, 'location' => 'จุด', 'stream_type' => 'mock',
        'status' => 'online', 'is_active' => $active ? '1' : '0', 'allow_teacher' => $teacher ? '1' : '0', 'allow_parent' => $parent ? '1' : '0',
        'area_type' => $rooms ? 'classroom' : 'common', 'classroom_ids' => array_map(fn ($id) => $tok('classrooms', $id), $rooms)]]);
    if (!($r['ok'] ?? false)) {
        T::ok("setup camera $code", false, $r['message'] ?? '');
    }
};
$make($admin, 'ACC-C1', [$f['roomA1'], $f['roomA2']], true, true);
$make($admin, 'ACC-C2', [$f['roomA1']], true, false);
$make($admin, 'ACC-C3', [], true, false);
$make($admin, 'ACC-C4', [], true, true);
$make($admin, 'ACC-C5', [$f['roomA3']], true, true);
$make($admin, 'ACC-C6', [$f['roomA2']], true, true, false);
$make($admin, 'ACC-C7', [$f['roomA2']], false, true);
$make($adminB, 'ACC-B1', [$f['roomB1']], true, true);

$cams = [];
foreach (fresh('cameras') as $c) {
    $cams[$c['code']] = $c;
}
$allA = ['ACC-C1', 'ACC-C2', 'ACC-C3', 'ACC-C4', 'ACC-C5', 'ACC-C6', 'ACC-C7'];

/** Session-shaped user for a fixture account (what current_user() derives from the DB). */
$userOf = function (int $id): array {
    $a = find_row('users', $id);
    return ['id' => $a['id'], 'role' => $a['role'], 'school_id' => $a['school_id'], 'classroom_id' => $a['classroom_id'], 'student_id' => $a['student_id']];
};
$visible = fn (int $userId) => array_values(array_map(fn ($c) => $c['code'], accessible_cameras($userOf($userId))));
$sameSet = function (array $got, array $want): bool {
    sort($got);
    sort($want);
    return $got === $want;
};
$expect = function (string $who, int $userId, array $want) use ($visible, $sameSet): void {
    fresh('cameras');
    $got = $visible($userId);
    T::ok("$who sees " . ($want ? implode(', ', $want) : 'nothing'), $sameSet($got, $want), 'got ' . implode(', ', $got));
};

T::section('1. camera covering two rooms — both teachers');
$expect('teacher A1', $f['teacherA1'], ['ACC-C1', 'ACC-C2', 'ACC-C3', 'ACC-C4']);
$expect('teacher A2', $f['teacherA2'], ['ACC-C1', 'ACC-C3', 'ACC-C4']);
$expect('teacher B1 (other school)', $f['teacherB1'], ['ACC-B1']);

T::section('2-3. parents — two children in two rooms / one child / no child in the room');
$expect('parent A (children in A1 + A2)', $f['parentA'], ['ACC-C1', 'ACC-C4', 'ACC-C7']);
T::ok('…ACC-C7 covers only A2 = the SECOND child (old code used users.student_id only)', in_array('ACC-C7', $visible($f['parentA']), true));
$expect('parent One (child in A3)', $f['parentOne'], ['ACC-C4', 'ACC-C5']);
T::ok('parent A has no child in A3 → ACC-C5 hidden', !in_array('ACC-C5', $visible($f['parentA']), true));
T::ok('room camera without parent permission (ACC-C2) hidden from parent A', !in_array('ACC-C2', $visible($f['parentA']), true));

T::section('4. child moves to another room');
store_mutate(function (array &$s) use ($f) {
    foreach ($s['tables']['students'] as &$st) {
        if ($st['id'] === $f['kidA2']) {
            $st['classroom_id'] = $f['roomA3'];
        }
    }
});
$expect('parent A after child A2 → A3', $f['parentA'], ['ACC-C1', 'ACC-C4', 'ACC-C5']);
store_mutate(function (array &$s) use ($f) {
    foreach ($s['tables']['students'] as &$st) {
        if ($st['id'] === $f['kidA2']) {
            $st['classroom_id'] = $f['roomA2'];
        }
    }
});
$expect('parent A after moving back', $f['parentA'], ['ACC-C1', 'ACC-C4', 'ACC-C7']);
store_mutate(function (array &$s) use ($f) {
    $s['tables']['parentStudents'] = array_values(array_filter($s['tables']['parentStudents'], fn ($l) => $l['user_id'] !== $f['parentOne']));
    foreach ($s['tables']['users'] as &$u) {
        if ($u['id'] === $f['parentOne']) {
            $u['student_id'] = null;
        }
    }
});
$expect('parent with no linked child any more (common cameras too)', $f['parentOne'], []);

T::section('5. common area does not open to every parent');
T::ok('ACC-C3 (common, teacher only) hidden from every parent', !in_array('ACC-C3', $visible($f['parentA']), true));
$legacy = store_mutate(function (array &$s) use ($cams, $f): int { // old-style row bound to one room must not open a common camera
    $s['tables']['cameraPermissions'][] = ['id' => $id = $s['nextId']++, 'school_id' => $f['schoolA'], 'camera_id' => $cams['ACC-C3']['id'],
        'role' => 'parent', 'classroom_id' => $f['roomA1'], 'can_view' => true];
    return $id;
})['result'];
T::ok('room-bound parent row does not open the common camera ACC-C3', !in_array('ACC-C3', $visible($f['parentA']), true));
store_mutate(function (array &$s) use ($legacy) {
    $s['tables']['cameraPermissions'] = array_values(array_filter($s['tables']['cameraPermissions'], fn ($p) => $p['id'] !== $legacy));
});

T::section('6. inactive camera');
T::ok('ACC-C6 hidden from teacher A2 and parent A', !in_array('ACC-C6', $visible($f['teacherA2']), true) && !in_array('ACC-C6', $visible($f['parentA']), true));

T::section('7. admin / executive keep their school scope');
$expect('admin A', $f['adminA'], $allA);
$expect('executive A', $f['execA'], $allA);
$expect('admin B', $f['adminB'], ['ACC-B1']);

T::section('8. camera_stream over HTTP (server-side check per request)');
$clients = [];
foreach (['admin' => '0810000001', 'executive' => '0810000002', 'teacher' => '0810000003', 'parent' => '0810000005'] as $role => $phone) {
    $clients[$role] = new Client($base);
    T::ok("$role logs in", $clients[$role]->login($role, $phone));
}
$stream = fn (string $role, string $code, array $extra = []) => $clients[$role]->api('camera_stream', ['camera_id' => $tok('cameras', $cams[$code]['id'])] + $extra);
T::ok('parent A opens ACC-C7 (second child\'s room)', ($stream('parent', 'ACC-C7')['ok'] ?? false));
T::ok('parent A denied ACC-C2 (no parent permission)', !($stream('parent', 'ACC-C2')['ok'] ?? true));
T::ok('parent A denied ACC-C5 (no child in A3)', !($stream('parent', 'ACC-C5')['ok'] ?? true));
T::ok('teacher A1 denied ACC-C7 (other room, parent-only)', !($stream('teacher', 'ACC-C7')['ok'] ?? true));
T::ok('inactive ACC-C6 refused even for admin', !($stream('admin', 'ACC-C6')['ok'] ?? true));
T::ok('executive opens ACC-C1', ($stream('executive', 'ACC-C1')['ok'] ?? false));
foreach (['admin', 'executive', 'teacher', 'parent'] as $role) {
    T::ok("$role denied school B camera", !($stream($role, 'ACC-B1')['ok'] ?? true));
}
$forged = [
    'random token'                    => ['camera_id' => 'AAAAAAAAAAAAAAAAAAAAAA'],
    'raw numeric id'                  => ['camera_id' => (string) $cams['ACC-C2']['id']],
    'token minted for another table'  => ['camera_id' => $tok('classrooms', $cams['ACC-C2']['id'])],
];
foreach ($forged as $what => $payload) {
    T::ok("forged camera id ($what) refused", !($clients['parent']->api('camera_stream', $payload)['ok'] ?? true));
}
$r = $stream('parent', 'ACC-C2', ['school_id' => $tok('schools', $f['schoolA']), 'classroom_id' => $tok('classrooms', $f['roomA1']),
    'student_id' => $tok('students', $f['kidA1']), 'role' => 'admin']);
T::ok('extra school_id / classroom_id / student_id / role in the request change nothing', !($r['ok'] ?? true));
$ok = $stream('parent', 'ACC-C1');
T::ok('allowed response carries no RTSP / secrets', ($ok['ok'] ?? false) && !str_contains(json_encode($ok), 'rtsp') && !isset($ok['camera']['stream_url']));

T::section('9. CCTV pages + dashboards for every role');
[, $execHome] = $clients['executive']->get('/executive');
T::ok('executive bottom nav: no "เมนู" button, every menu item shown (ธนาคารความดี too)',
    !str_contains($execHome, 'href="/executive/menu"') && str_contains($execHome, 'class="bottom-nav"') && str_contains($execHome, 'href="/executive/stars"'));
// "/menu" = the bottom-nav "เมนู" page every role gets when it has more menu items than fit
$pages = ['admin' => ['/admin/cctv', '/admin', '/admin/menu'], 'executive' => ['/executive/cctv', '/executive'],
    'teacher' => ['/teacher/cctv', '/teacher', '/teacher/menu'], 'parent' => ['/parent/cctv', '/parent', '/parent/menu']];
$shown = ['admin' => $allA, 'executive' => $allA, 'teacher' => ['ACC-C1', 'ACC-C2', 'ACC-C3', 'ACC-C4'], 'parent' => ['ACC-C1', 'ACC-C4', 'ACC-C7']];
foreach ($pages as $role => $paths) {
    foreach ($paths as $path) {
        [$status, $html] = $clients[$role]->get($path);
        T::ok("$role $path → 200", $status === 200, "status $status");
    }
    [, $html] = $clients[$role]->get($paths[0]);
    preg_match_all('/ACC-[A-Z0-9]+/', $html, $m);
    $listed = array_values(array_unique($m[0]));
    T::ok("$role CCTV page lists exactly its cameras", $sameSet($listed, $shown[$role]), 'listed ' . implode(', ', $listed));
}

T::done();
