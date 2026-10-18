<?php
/**
 * Admin → ตั้งค่ากล้อง CCTV (app/pages/admin/cctv-settings.php, api action save_camera_settings).
 *
 *   tests/run-local.sh   (resets the test DB — use a separate *_test DB when the dev DB matters)
 */
declare(strict_types=1);

require __DIR__ . '/lib.php';

$base = getenv('TEST_BASE_URL') ?: 'http://127.0.0.1:8099';
$f = seed_fixture();
$tok = fn (string $table, int $id) => tok($table, $id);
$byName = function (string $name): ?array {
    foreach (fresh('cameras') as $c) {
        if ($c['name'] === $name) {
            return $c;
        }
    }
    return null;
};
$save = fn (Client $c, string $id, array $data) => $c->api('save_camera_settings', ['table' => 'cameras', 'id' => $id, 'data' => $data]);

$admin = new Client($base);
T::ok('admin A logs in', $admin->login('admin', '0810000001'));
$adminB = new Client($base);
$adminB->login('admin', '0820000001');

T::section('1. page + route');
[$status, $html] = $admin->get('/admin/cctv-settings');
T::ok('GET /admin/cctv-settings → 200', $status === 200, "status $status");
T::ok('menu link present', str_contains($html, 'href="/admin/cctv-settings"'));
T::ok('form posts to save_camera_settings with only name / view_url / sort_order / is_active',
    str_contains($html, 'data-form-action="save_camera_settings"') && str_contains($html, 'name="view_url"') && str_contains($html, 'name="sort_order"')
    && !str_contains($html, 'name="rtsp_url"') && !str_contains($html, 'name="stream_url"'));
foreach ([['executive', '0810000002'], ['teacher', '0810000003'], ['parent', '0810000005']] as [$role, $phone]) {
    $c = new Client($base);
    $c->login($role, $phone);
    [$s] = $c->get('/admin/cctv-settings');
    T::ok("$role cannot open the admin page (redirected / denied)", $s !== 200, "status $s");
}

T::section('2. add / edit / toggle / delete');
$r = $save($admin, '', ['name' => 'ห้องเรียน 1', 'view_url' => '/cctv/cam01/', 'sort_order' => '2', 'is_active' => '1']);
T::ok('add camera', $r['ok'] ?? false, $r['message'] ?? '');
$cam = $byName('ห้องเรียน 1');
T::ok('stored: school from session, view_url, sort_order, active, timestamps', $cam && $cam['school_id'] === $f['schoolA'] && $cam['view_url'] === '/cctv/cam01/'
    && $cam['sort_order'] === 2 && !empty($cam['is_active']) && !empty($cam['created_at']) && !empty($cam['updated_at']));
T::ok('new camera: common area, mock stream, no teacher / parent rights', $cam && $cam['classroom_id'] === null && $cam['stream_type'] === 'mock'
    && where(fresh('cameraPermissions'), 'camera_id', $cam['id']) === [] && preg_match('/^CAM-\d{2}$/', $cam['code']) === 1);
$save($admin, '', ['name' => 'สนามเด็กเล่น', 'view_url' => 'https://cctv.example.ac.th/cam02/', 'sort_order' => '1', 'is_active' => '1']);
[, $html] = $admin->get('/admin/cctv-settings');
T::ok('list ordered by sort_order', strpos($html, 'สนามเด็กเล่น') < strpos($html, 'ห้องเรียน 1'));
T::ok('"ดูภาพ" opens the saved URL in a new tab', str_contains($html, 'href="/cctv/cam01/" target="_blank" rel="noopener noreferrer"'));
$r = $save($admin, $tok('cameras', $cam['id']), ['name' => 'ห้องเรียน 1 (แก้)', 'view_url' => '/cctv/cam09/', 'sort_order' => '5', 'is_active' => '1']);
fresh('cameras');
$after = find_row('cameras', $cam['id']);
T::ok('edit', ($r['ok'] ?? false) && $after['name'] === 'ห้องเรียน 1 (แก้)' && $after['view_url'] === '/cctv/cam09/' && $after['sort_order'] === 5);
T::ok('edit keeps code / stream settings / created_at', $after['code'] === $cam['code'] && $after['stream_type'] === 'mock' && $after['created_at'] === $cam['created_at']);
$r = $admin->api('save', ['table' => 'cameras', 'id' => $tok('cameras', $cam['id']), 'data' => ['is_active' => '0']]);
fresh('cameras');
T::ok('toggle off (quick-save)', ($r['ok'] ?? false) && empty(find_row('cameras', $cam['id'])['is_active']));
$r = $admin->api('delete', ['table' => 'cameras', 'id' => $tok('cameras', $cam['id'])]);
fresh('cameras');
T::ok('delete', ($r['ok'] ?? false) && find_row('cameras', $cam['id']) === null);
T::ok('delete button asks for confirmation (data-action="delete" → confirmDialog)', str_contains($html, 'data-action="delete" data-table="cameras"'));

T::section('3. validation');
$bad = [
    'empty name'              => ['name' => '', 'view_url' => '/cctv/cam01/'],
    'empty url'               => ['name' => 'x', 'view_url' => ''],
    'name too long'           => ['name' => str_repeat('ก', 61), 'view_url' => '/cctv/cam01/'],
    'rtsp url'                => ['name' => 'x', 'view_url' => 'rtsp://10.0.0.5/stream'],
    'http url'                => ['name' => 'x', 'view_url' => 'http://100.116.192.95:8888/cam01/'],
    'javascript: url'         => ['name' => 'x', 'view_url' => 'javascript:alert(1)'],
    'protocol-relative //host' => ['name' => 'x', 'view_url' => '//evil.example/cam'],
    'path traversal'          => ['name' => 'x', 'view_url' => '/cctv/../admin/system'],
    'credentials in url'      => ['name' => 'x', 'view_url' => 'https://user:pass@cctv.example.ac.th/cam01/'],
    'quote / markup in url'   => ['name' => 'x', 'view_url' => '/cctv/cam01/"><script>'],
    'url too long'            => ['name' => 'x', 'view_url' => '/cctv/' . str_repeat('a', 300)],
    'sort_order out of range' => ['name' => 'x', 'view_url' => '/cctv/cam01/', 'sort_order' => '100000'],
    'is_active not 0/1'       => ['name' => 'x', 'view_url' => '/cctv/cam01/', 'is_active' => '7'],
];
$count = count(fresh('cameras'));
foreach ($bad as $what => $data) {
    $r = $save($admin, '', $data + ['is_active' => '1']);
    T::ok("$what → rejected", !($r['ok'] ?? true), $r['message'] ?? '');
}
T::ok('nothing saved by rejected requests', count(fresh('cameras')) === $count);
$r = $save($admin, '', ['name' => '<img src=x onerror=alert(1)>', 'view_url' => '/cctv/cam03/', 'is_active' => '1']);
[, $html] = $admin->get('/admin/cctv-settings');
T::ok('name with markup is stored but printed escaped (no XSS)', ($r['ok'] ?? false) && !str_contains($html, '<img src=x') && str_contains($html, '&lt;img src=x'));

T::section('4. school isolation + permissions');
$save($adminB, '', ['name' => 'กล้องโรงเรียนB', 'view_url' => '/cctv/camb/', 'is_active' => '1']);
$camB = $byName('กล้องโรงเรียนB');
T::ok('school B camera stored with school B', $camB && $camB['school_id'] === $f['schoolB']);
[, $html] = $admin->get('/admin/cctv-settings');
T::ok('admin A does not see school B camera', !str_contains($html, 'กล้องโรงเรียนB'));
$r = $save($admin, $tok('cameras', $camB['id']), ['name' => 'hijack', 'view_url' => '/cctv/x/', 'is_active' => '1']);
fresh('cameras');
T::ok('admin A cannot edit school B camera', !($r['ok'] ?? true) && find_row('cameras', $camB['id'])['name'] === 'กล้องโรงเรียนB');
$r = $admin->api('delete', ['table' => 'cameras', 'id' => $tok('cameras', $camB['id'])]);
fresh('cameras');
T::ok('admin A cannot delete school B camera', !($r['ok'] ?? true) && find_row('cameras', $camB['id']) !== null);
$r = $admin->api('save', ['table' => 'cameras', 'id' => $tok('cameras', $camB['id']), 'data' => ['is_active' => '0']]);
fresh('cameras');
T::ok('admin A cannot toggle school B camera', !($r['ok'] ?? true) && !empty(find_row('cameras', $camB['id'])['is_active']));
$r = $save($admin, '', ['name' => 'school-id-forge', 'view_url' => '/cctv/f/', 'is_active' => '1', 'school_id' => $tok('schools', $f['schoolB'])]);
T::ok('school_id in the request ignored (saved to own school)', ($r['ok'] ?? false) && ($byName('school-id-forge')['school_id'] ?? null) === $f['schoolA']);
foreach ([['executive', '0810000002'], ['teacher', '0810000003'], ['parent', '0810000005']] as [$role, $phone]) {
    $c = new Client($base);
    $c->login($role, $phone);
    $r = $save($c, '', ['name' => "x-$role", 'view_url' => '/cctv/x/', 'is_active' => '1']);
    T::ok("$role cannot use save_camera_settings", !($r['ok'] ?? true) && $byName("x-$role") === null);
}
$r = $save($admin, 'AAAAAAAAAAAAAAAAAAAAAA', ['name' => 'forged-id', 'view_url' => '/cctv/x/', 'is_active' => '1']);
T::ok('forged camera id rejected', !($r['ok'] ?? true) && $byName('forged-id') === null);

T::done();
