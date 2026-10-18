<?php
/**
 * CCTV Phase 2 — admin camera management (HTTP against a local server + checks in the test DB).
 *
 *   tests/run-local.sh            (runs tests/*_test.php in name order, then tests/browser/*_test.mjs)
 */
declare(strict_types=1);

require __DIR__ . '/lib.php';

$base = getenv('TEST_BASE_URL') ?: 'http://127.0.0.1:8099';
$f = seed_fixture();
$tok = fn (string $table, int $id) => tok($table, $id);
$links = fn (int $cameraId) => array_map(fn ($l) => $l['classroom_id'], where(fresh('cameraClassrooms'), 'camera_id', $cameraId));
$perms = fn (int $cameraId) => array_map(fn ($p) => [$p['role'], $p['classroom_id']], where(fresh('cameraPermissions'), 'camera_id', $cameraId));
$camByCode = function (string $code, int $school): ?array {
    foreach (fresh('cameras') as $c) {
        if ($c['code'] === $code && $c['school_id'] === $school) {
            return $c;
        }
    }
    return null;
};
$form = fn (array $over) => $over + ['name' => 'กล้องทดสอบ', 'location' => 'จุดทดสอบ', 'stream_type' => 'mock', 'status' => 'online',
    'is_active' => '1', 'allow_teacher' => '1', 'allow_parent' => '0', 'stream_url' => '', 'rtsp_url' => ''];

$admin = new Client($base);
T::ok('admin A logs in', $admin->login('admin', '0810000001'));

T::section('1. add a camera covering 2 classrooms');
$r = $admin->api('save', ['table' => 'cameras', 'id' => '', 'data' => $form(['code' => 'cam-10', 'area_type' => 'classroom',
    'classroom_ids' => [$tok('classrooms', $f['roomA1']), $tok('classrooms', $f['roomA2'])]])]);
T::ok('save ok', $r['ok'] ?? false, $r['message'] ?? '');
$cam = $camByCode('CAM-10', $f['schoolA']);
T::ok('code normalised to CAM-10, school forced from session', $cam !== null);
T::ok('links = A1, A2', $cam && $links($cam['id']) === [$f['roomA1'], $f['roomA2']]);
T::ok('cameras.classroom_id = first room', $cam && $cam['classroom_id'] === $f['roomA1']);
T::ok('permission rows: teacher only, classroom_id NULL', $cam && $perms($cam['id']) === [['teacher', null]]);
T::ok('no area_type / classroom_ids stored on the camera row', $cam && !array_key_exists('area_type', $cam) && !array_key_exists('classroom_ids', $cam));

T::section('2. edit the classrooms (A1, A2 → A2, A3) + permissions');
$r = $admin->api('save', ['table' => 'cameras', 'id' => $tok('cameras', $cam['id']), 'data' => $form(['area_type' => 'classroom',
    'classroom_ids' => [$tok('classrooms', $f['roomA2']), $tok('classrooms', $f['roomA3'])], 'allow_parent' => '1'])]);
T::ok('save ok', $r['ok'] ?? false, $r['message'] ?? '');
T::ok('links = A2, A3', $links($cam['id']) === [$f['roomA2'], $f['roomA3']]);
T::ok('cameras.classroom_id follows first room', find_row('cameras', $cam['id'])['classroom_id'] === $f['roomA2']);
T::ok('permissions = teacher + parent', $perms($cam['id']) === [['teacher', null], ['parent', null]]);
T::ok('code kept when blank on edit', fresh('cameras') && $camByCode('CAM-10', $f['schoolA']) !== null);
$r = $admin->api('save', ['table' => 'cameras', 'id' => $tok('cameras', $cam['id']), 'data' => ['is_active' => '0']]);
T::ok('quick-save is_active=0 keeps links + permissions', ($r['ok'] ?? false) && $links($cam['id']) === [$f['roomA2'], $f['roomA3']]
    && count($perms($cam['id'])) === 2 && empty($camByCode('CAM-10', $f['schoolA'])['is_active']));
$r = $admin->api('save', ['table' => 'cameras', 'id' => $tok('cameras', $cam['id']), 'data' => ['area_type' => 'classroom', 'classroom_ids' => []]]);
T::ok('classroom area with no room is rejected', !($r['ok'] ?? true) && $links($cam['id']) === [$f['roomA2'], $f['roomA3']], $r['message'] ?? '');

T::section('3. classroom camera → common area');
$r = $admin->api('save', ['table' => 'cameras', 'id' => $tok('cameras', $cam['id']), 'data' => ['area_type' => 'common',
    'classroom_ids' => [$tok('classrooms', $f['roomA1'])]]]);
T::ok('save ok', $r['ok'] ?? false, $r['message'] ?? '');
T::ok('links removed (common area ignores picked rooms)', $links($cam['id']) === []);
T::ok('cameras.classroom_id = NULL', find_row('cameras', $cam['id'])['classroom_id'] === null);
T::ok('permission rows untouched (not widened to every parent)', $perms($cam['id']) === [['teacher', null], ['parent', null]]);

T::section('4. duplicate camera code');
$r = $admin->api('save', ['table' => 'cameras', 'id' => '', 'data' => $form(['code' => 'CAM-10', 'area_type' => 'common'])]);
T::ok('same code in the same school rejected', !($r['ok'] ?? true) && str_contains($r['message'] ?? '', 'มีอยู่แล้ว'), $r['message'] ?? '');
$r = $admin->api('save', ['table' => 'cameras', 'id' => '', 'data' => $form(['code' => 'cam 10!', 'area_type' => 'common'])]);
T::ok('invalid code characters rejected', !($r['ok'] ?? true), $r['message'] ?? '');
$r = $admin->api('save', ['table' => 'cameras', 'id' => '', 'data' => $form(['area_type' => 'common'])]);
$auto = array_values(array_filter(fresh('cameras'), fn ($c) => $c['school_id'] === $f['schoolA'] && $c['code'] !== 'CAM-10'));
T::ok('blank code → unique CAM-NN', ($r['ok'] ?? false) && count($auto) === 1 && preg_match('/^CAM-\d{2}$/', $auto[0]['code']) === 1);
$adminB = new Client($base);
$adminB->login('admin', '0820000001');
$r = $adminB->api('save', ['table' => 'cameras', 'id' => '', 'data' => $form(['code' => 'CAM-10', 'area_type' => 'common'])]);
T::ok('same code allowed in another school', ($r['ok'] ?? false) && $camByCode('CAM-10', $f['schoolB']) !== null, $r['message'] ?? '');

T::section('5. classroom of another school');
$before = count(fresh('cameras'));
$r = $admin->api('save', ['table' => 'cameras', 'id' => '', 'data' => $form(['code' => 'CAM-20', 'area_type' => 'classroom',
    'classroom_ids' => [$tok('classrooms', $f['roomA1']), $tok('classrooms', $f['roomB1'])]])]);
T::ok('rejected', !($r['ok'] ?? true), $r['message'] ?? '');
T::ok('nothing saved', count(fresh('cameras')) === $before && $camByCode('CAM-20', $f['schoolA']) === null);
$r = $admin->api('save', ['table' => 'cameras', 'id' => '', 'data' => $form(['code' => 'CAM-21', 'area_type' => 'classroom',
    'classroom_ids' => ['forged-token-AAAAAAAAAA'], 'school_id' => $tok('schools', $f['schoolB'])])]);
T::ok('forged classroom token rejected', !($r['ok'] ?? true));
$r = $admin->api('save', ['table' => 'cameras', 'id' => '', 'data' => $form(['code' => 'CAM-22', 'area_type' => 'common', 'school_id' => $tok('schools', $f['schoolB'])])]);
$c22 = $camByCode('CAM-22', $f['schoolA']);
T::ok('school_id from the request is ignored (forced to own school)', ($r['ok'] ?? false) && $c22 !== null && $camByCode('CAM-22', $f['schoolB']) === null);
$camB = $camByCode('CAM-10', $f['schoolB']);
$r = $admin->api('save', ['table' => 'cameras', 'id' => $tok('cameras', $camB['id']), 'data' => ['name' => 'hijack']]);
T::ok('admin A cannot edit school B camera', !($r['ok'] ?? true) && find_row('cameras', $camB['id'])['name'] !== 'hijack');
$r = $admin->api('delete', ['table' => 'cameras', 'id' => $tok('cameras', $camB['id'])]);
T::ok('admin A cannot delete school B camera', !($r['ok'] ?? true) && fresh('cameras') && find_row('cameras', $camB['id']) !== null);

T::section('6. roles without write permission');
foreach ([['executive', '0810000002'], ['teacher', '0810000003'], ['parent', '0810000005']] as [$role, $phone]) {
    $c = new Client($base);
    $c->login($role, $phone);
    $r1 = $c->api('save', ['table' => 'cameras', 'id' => '', 'data' => $form(['code' => 'X-' . $role, 'area_type' => 'common'])]);
    $r2 = $c->api('save', ['table' => 'cameras', 'id' => $tok('cameras', $c22['id']), 'data' => ['is_active' => '0']]);
    $r3 = $c->api('delete', ['table' => 'cameras', 'id' => $tok('cameras', $c22['id'])]);
    $r4 = $c->api('save', ['table' => 'cameraClassrooms', 'id' => '', 'data' => []]);
    T::ok("$role: add / edit / delete / write cameraClassrooms all denied",
        !($r1['ok'] ?? true) && !($r2['ok'] ?? true) && !($r3['ok'] ?? true) && !($r4['ok'] ?? true)
        && $camByCode('X-' . strtoupper($role), $f['schoolA']) === null && !empty(find_row('cameras', $c22['id'])['is_active']));
}
$r = $admin->api('save', ['table' => 'cameraClassrooms', 'id' => '', 'data' => ['camera_id' => 1, 'classroom_id' => 1]]);
T::ok('admin cannot write cameraClassrooms through the generic API', !($r['ok'] ?? true));
$r = $admin->api('save', ['table' => 'cameraPermissions', 'id' => '', 'data' => []]);
T::ok('admin cannot write cameraPermissions through the generic API', !($r['ok'] ?? true));

T::section('7. failure inside the transaction → rollback');
$pdo = mk_db();
$pdo->exec("CREATE TRIGGER test_fail_links BEFORE INSERT ON camera_classrooms FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'test failure'");
try {
    $beforeCams = count(fresh('cameras'));
    $beforePerms = count(fresh('cameraPermissions'));
    $r = $admin->api('save', ['table' => 'cameras', 'id' => '', 'data' => $form(['code' => 'CAM-30', 'area_type' => 'classroom',
        'classroom_ids' => [$tok('classrooms', $f['roomA1'])], 'allow_parent' => '1'])]);
    T::ok('save reports a failure (friendly message)', !($r['ok'] ?? true) && !str_contains($r['message'] ?? '', 'SQLSTATE'), $r['message'] ?? '');
    T::ok('no camera / permission rows left behind', count(fresh('cameras')) === $beforeCams && count(fresh('cameraPermissions')) === $beforePerms
        && $camByCode('CAM-30', $f['schoolA']) === null);
} finally {
    $pdo->exec('DROP TRIGGER IF EXISTS test_fail_links');
}

T::section('8. delete camera / classroom → link rows');
$r = $admin->api('save', ['table' => 'cameras', 'id' => '', 'data' => $form(['code' => 'CAM-40', 'area_type' => 'classroom',
    'classroom_ids' => [$tok('classrooms', $f['roomA1']), $tok('classrooms', $f['roomA3'])], 'allow_parent' => '1'])]);
$c40 = $camByCode('CAM-40', $f['schoolA']);
$r = $admin->api('save', ['table' => 'cameras', 'id' => '', 'data' => $form(['code' => 'CAM-41', 'area_type' => 'classroom',
    'classroom_ids' => [$tok('classrooms', $f['roomA3'])]])]);
$c41 = $camByCode('CAM-41', $f['schoolA']);
$otherLinks = count(fresh('cameraClassrooms')) - 3;
$r = $admin->api('delete', ['table' => 'cameras', 'id' => $tok('cameras', $c40['id'])]);
T::ok('camera deleted', ($r['ok'] ?? false) && find_row('cameras', $c40['id']) === null);
T::ok('its links + permissions deleted', $links($c40['id']) === [] && $perms($c40['id']) === []);
T::ok('other cameras\' rows untouched', $links($c41['id']) === [$f['roomA3']] && count(fresh('cameraClassrooms')) === $otherLinks + 1
    && $camB !== null && $perms($camB['id']) !== null && find_row('cameras', $camB['id']) !== null);
$r = $admin->api('delete', ['table' => 'classrooms', 'id' => $tok('classrooms', $f['roomA3'])]); // A3 has no students
T::ok('classroom deleted → its links removed', ($r['ok'] ?? false) && $links($c41['id']) === []);
fresh('cameras');
$c41 = find_row('cameras', $c41['id']);
T::ok('camera left without rooms is deactivated (not turned into common area)', $c41['classroom_id'] === null && empty($c41['is_active']));

T::section('9. admin page renders the form + record without secrets');
$admin->api('save', ['table' => 'cameras', 'id' => $tok('cameras', $c22['id']), 'data' => ['rtsp_url' => 'rtsp://10.9.9.9/test-secret-path']]);
[$status, $html] = $admin->get('/admin/cctv');
T::ok('page 200', $status === 200);
T::ok('multi-select field rendered', str_contains($html, 'data-multi="classroom_ids"') && str_contains($html, 'data-show-if="area_type"'));
T::ok('stored RTSP URL never in the HTML', !str_contains($html, 'test-secret-path'));
T::ok('edit record carries area_type + classroom_ids tokens', str_contains($html, 'area_type&quot;:&quot;') && str_contains($html, 'classroom_ids&quot;:['));

T::done();
