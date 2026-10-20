<?php
/**
 * คู่มือการใช้งาน: every role has a 📖 button next to "ออกจากระบบ" that opens /{role}/manual with the
 * manual of THAT role only; Word copies (teacher/parent) are downloadable.
 *
 *   tests/run-local.sh
 */
declare(strict_types=1);

require __DIR__ . '/lib.php';

$base = getenv('TEST_BASE_URL') ?: 'http://127.0.0.1:8099';
seed_fixture();

$cases = [
    'admin'     => ['0810000001', 'คู่มือผู้ดูแลโรงเรียน (Admin)', 'console'],
    'executive' => ['0810000002', 'คู่มือผู้บริหาร', 'console'],
    'teacher'   => ['0810000003', 'คู่มือคุณครู', 'app'],
    'parent'    => ['0810000005', 'คู่มือผู้ปกครอง', 'app'],
];
$titles = array_column($cases, 1);

foreach ($cases as $role => [$phone, $title, $layout]) {
    T::section($role);
    $c = new Client($base);
    T::ok("$role logs in", $c->login($role, $phone));
    [$st, $home] = $c->get('/' . $role);
    T::ok('appbar: manual button right before the logout form', $st === 200
        && preg_match('#<a class="appbar__manual" href="/' . $role . '/manual"[^>]*aria-label="คู่มือการใช้งาน"[^>]*>.*?</a>\s*<form class="logout-form"#s', $home) === 1);
    if ($layout === 'console') {
        T::ok('sidebar: "คู่มือการใช้งาน" item right above logout', preg_match('#sidebar__manual[^"]*" href="/' . $role . '/manual">.*?<span>คู่มือการใช้งาน</span></a>\s*<form class="logout-form"#s', $home) === 1);
    }
    [$st, $html] = $c->get('/' . $role . '/manual');
    $others = array_filter($titles, fn ($t) => $t !== $title && str_contains($html, $t));
    T::ok("/$role/manual → 200 with its own manual only", $st === 200 && str_contains($html, $title) && !$others, implode(',', $others));
    T::ok('sections + table of contents + bold rendered (no raw **)', str_contains($html, 'class="manual-toc"') && str_contains($html, 'id="m1"')
        && str_contains($html, '<strong>') && !str_contains($html, '**'));
    $hasWord = in_array($role, ['teacher', 'parent'], true);
    T::ok($hasWord ? 'Word download link present' : 'no Word link', str_contains($html, "assets/manuals/$role.docx") === $hasWord);
    if ($hasWord) {
        [$st, $doc] = $c->get('/assets/manuals/' . $role . '.docx');
        T::ok('Word file downloads (zip)', $st === 200 && str_starts_with($doc, 'PK'));
    }
    foreach (array_diff(array_keys($cases), [$role]) as $other) {
        [$st] = $c->get('/' . $other . '/manual');
        T::ok("cannot open /$other/manual as $role", $st !== 200);
    }
}

T::section('not logged in');
[$st] = (new Client($base))->get('/teacher/manual');
T::ok('/teacher/manual without login is not served', $st !== 200);

T::done();
