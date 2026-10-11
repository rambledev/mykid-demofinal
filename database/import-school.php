<?php
/**
 * CLI: import the school's real people from a JSON file (format: database/data/school.example.json).
 * The real file (database/data/school.json) stays on this machine — gitignored, children's data (PDPA).
 *
 *   php database/import-school.php [file.json]            dry run: show what would change (nothing written)
 *   php database/import-school.php [file.json] --confirm  write it (one transaction)
 *
 * Order (the schema's references): school → classrooms → teachers (+ login accounts) → students.
 * Re-running is safe: classrooms match by name, teachers by phone, students by room + name — they are
 * updated, never duplicated. Parents get no account here: the admin adds the real phone + PIN on the
 * student form (app/lib/accounts.php creates the account then). Run `php database/install.php` first.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
require $root . '/app/bootstrap.php';

$args = array_values(array_filter(array_slice($argv, 1), fn ($a) => !str_starts_with($a, '--')));
$confirm = in_array('--confirm', $argv, true);
$file = $args[0] ?? $root . '/database/data/school.json';
$fail = function (string $message): never {
    fwrite(STDERR, "❌ $message\n");
    exit(1);
};

/* ---------------------------------------------------------------- read + validate the file */
$data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
if (!is_array($data) || !isset($data['school'], $data['classrooms']) || !is_array($data['classrooms'])) {
    $fail("อ่านไฟล์ข้อมูลไม่ได้หรือรูปแบบไม่ถูกต้อง: $file (ดูตัวอย่าง database/data/school.example.json)");
}
$teacherPin = (string) ($data['teacherPin'] ?? '');
if (!preg_match('/^\d{6}$/', $teacherPin)) {
    $fail('teacherPin ต้องเป็นตัวเลข 6 หลัก');
}
$warnings = [];
$seenPhones = [];
foreach ($data['classrooms'] as $i => $room) {
    $n = $i + 1;
    if (trim((string) ($room['name'] ?? '')) === '') {
        $fail("ห้องที่ $n ไม่มีชื่อห้อง");
    }
    $phone = normalize_phone((string) ($room['teacher']['phone'] ?? ''));
    if (trim((string) ($room['teacher']['name'] ?? '')) === '' || !preg_match('/^0\d{9}$/', $phone)) {
        $fail("ครูของห้อง {$room['name']}: ต้องมีชื่อและเบอร์ 10 หลัก");
    }
    if (isset($seenPhones[$phone])) {
        $fail("เบอร์ครู $phone ซ้ำกันในไฟล์");
    }
    $seenPhones[$phone] = true;
    foreach ($room['students'] ?? [] as $s) {
        if (trim((string) ($s['name'] ?? '')) === '' || trim((string) ($s['nickname'] ?? '')) === '') {
            $fail("นักเรียนในห้อง {$room['name']}: ต้องมีชื่อและชื่อเล่น");
        }
    }
}

/** "เด็กชาย คณินท์ ศิริเกษ" → ['เด็กชาย คณินท์ ศิริเกษ', 'm']; unknown prefix → gender '' + warning. */
$parseStudent = function (string $name) use (&$warnings): array {
    $name = trim(preg_replace('/\s+/u', ' ', $name));
    foreach (['เด็กชาย' => 'm', 'ด.ช.' => 'm', 'เด็กหญิง' => 'f', 'ด.ญ.' => 'f'] as $prefix => $gender) {
        if (str_starts_with($name, $prefix)) {
            return [$name, $gender];
        }
    }
    $warnings[] = "ไม่พบคำนำหน้า เด็กชาย/เด็กหญิง ใน \"$name\" — บันทึกโดยไม่ระบุเพศ กรุณาตรวจสอบในหน้า จัดการนักเรียน";
    return [$name, ''];
};

/** "คุณครูนัทนันท์ แก้วเสา" → nickname "ครูนัทนันท์", fullName "นัทนันท์ แก้วเสา". */
$parseTeacher = function (string $name): array {
    $full = trim(preg_replace('/^(คุณครู|ครู)\s*/u', '', trim($name)));
    $first = explode(' ', $full)[0];
    return ['ครู' . $first, $full];
};

/* ---------------------------------------------------------------- plan against the database */
echo '[Import] ' . ($confirm ? 'WRITING' : 'dry run (add --confirm to write)') . ' · ' . mk_env('DB_HOST') . ':' . mk_env('DB_PORT') . '/' . mk_env('DB_NAME') . " · $file\n";
$school = store_rows('schools')[0] ?? $fail('ยังไม่มีโรงเรียนในฐานข้อมูล — รัน php database/install.php --school=... ก่อน');
$sid = $school['id'];

foreach ($data['classrooms'] as $room) { // a teacher phone may not belong to another role
    $phone = normalize_phone((string) $room['teacher']['phone']);
    foreach (store_rows('users') as $u) {
        if ($u['phone'] === $phone && $u['role'] !== 'teacher') {
            $fail("เบอร์ครู $phone เป็นบัญชี " . role_meta($u['role'])['th'] . ' อยู่แล้ว');
        }
    }
}

$plan = ['classrooms' => [0, 0], 'teachers' => [0, 0], 'accounts' => [0, 0], 'students' => [0, 0]]; // [new, updated]
$result = null;
$apply = function (array &$state) use ($data, $sid, $teacherPin, $parseStudent, $parseTeacher, &$plan): void {
    $t = &$state['tables'];
    foreach ($t['schools'] as &$s) {
        if ($s['id'] === $sid) {
            $s['name'] = (string) $data['school']['name'];
            $s['shortName'] = (string) ($data['school']['shortName'] ?? $data['school']['name']);
            $s['code'] = (string) ($data['school']['code'] ?? '');
            $s['status'] = strtolower((string) ($data['school']['status'] ?? 'active'));
        }
    }
    unset($s);
    $pinHash = null;
    $colors = ['#FFF4CC', '#DFF4FF', '#FFEAF2', '#DDF7E6', '#F1EAFF', '#FFE9D6'];
    $emojis = ['🐣', '🐰', '🐼', '🐨', '🦊', '🐧'];

    foreach (array_values($data['classrooms']) as $ri => $room) {
        // 1. classroom (by name)
        $roomName = trim((string) $room['name']);
        $roomId = null;
        foreach ($t['classrooms'] as &$c) {
            if ($c['school_id'] === $sid && $c['name'] === $roomName) {
                $c['level'] = $room['level'] ?? $c['level'] ?? null;
                $c['ageRange'] = $room['ageRange'] ?? $c['ageRange'] ?? null;
                $roomId = $c['id'];
            }
        }
        unset($c);
        if ($roomId) {
            $plan['classrooms'][1]++;
        } else {
            $roomId = $state['nextId']++;
            $t['classrooms'][] = ['id' => $roomId, 'school_id' => $sid, 'name' => $roomName, 'level' => $room['level'] ?? null,
                'ageRange' => $room['ageRange'] ?? null, 'emoji' => $emojis[$ri % 6], 'color' => $colors[$ri % 6], 'capacity' => 20];
            $plan['classrooms'][0]++;
        }

        // 2. teacher (by phone) + login account
        [$nickname, $fullName] = $parseTeacher((string) $room['teacher']['name']);
        $phone = normalize_phone((string) $room['teacher']['phone']);
        $teacherId = null;
        foreach ($t['teachers'] as &$tr) {
            if ($tr['school_id'] === $sid && $tr['phone'] === $phone) {
                $tr = array_merge($tr, ['nickname' => $nickname, 'fullName' => $fullName, 'classroom_id' => $roomId]);
                $teacherId = $tr['id'];
            }
        }
        unset($tr);
        if ($teacherId) {
            $plan['teachers'][1]++;
        } else {
            $teacherId = $state['nextId']++;
            $t['teachers'][] = ['id' => $teacherId, 'school_id' => $sid, 'classroom_id' => $roomId, 'nickname' => $nickname,
                'fullName' => $fullName, 'position' => 'ครูประจำชั้น', 'emoji' => '👩‍🏫', 'phone' => $phone];
            $plan['teachers'][0]++;
        }
        $account = false;
        foreach ($t['users'] as &$u) {
            if ($u['phone'] === $phone && $u['role'] === 'teacher') { // keep the PIN the teacher may have changed
                $u = array_merge($u, ['name' => $nickname, 'school_id' => $sid, 'classroom_id' => $roomId, 'teacher_id' => $teacherId]);
                $account = true;
            }
        }
        unset($u);
        if ($account) {
            $plan['accounts'][1]++;
        } else {
            $pinHash ??= pin_hash($teacherPin);
            $t['users'][] = ['id' => $state['nextId']++, 'name' => $nickname, 'phone' => $phone, 'pin' => $pinHash, 'role' => 'teacher',
                'school_id' => $sid, 'classroom_id' => $roomId, 'student_id' => null, 'teacher_id' => $teacherId];
            $plan['accounts'][0]++;
        }

        // 3. students (by room + name)
        foreach ($room['students'] ?? [] as $si => $st) {
            [$name, $gender] = $parseStudent((string) $st['name']);
            $nick = trim((string) $st['nickname']);
            $found = false;
            foreach ($t['students'] as &$x) {
                if ($x['school_id'] === $sid && $x['classroom_id'] === $roomId && $x['name'] === $name) {
                    $x['nickname'] = $nick;
                    $x['gender'] = $gender;
                    $found = true;
                }
            }
            unset($x);
            if ($found) {
                $plan['students'][1]++;
                continue;
            }
            $t['students'][] = ['id' => $state['nextId']++, 'school_id' => $sid, 'classroom_id' => $roomId,
                'code' => sprintf('%s-%d%02d', (string) ($data['school']['code'] ?? 'ST'), $ri + 1, $si + 1),
                'name' => $name, 'nickname' => $nick, 'gender' => $gender, 'classroom' => $roomName,
                'parentName' => 'ผู้ปกครองของ' . $nick, 'parentRelation' => '', 'parentPhone' => null,
                'avatar' => ['skin' => '#FFDBC0', 'hair' => '#3B2A20', 'bg' => $gender === 'f' ? '#FFD6E7' : '#BFE7FF',
                    'shirt' => $gender === 'f' ? '#FF9EC0' : '#7CC6F2', 'style' => $gender === 'f' ? 'pigtails' : 'short']];
            $plan['students'][0]++;
        }
    }
};

if ($confirm) {
    store_mutate($apply); // one transaction: all or nothing (daily sheets are opened for the new students)
} else {
    $copy = ['nextId' => store_state()['nextId'], 'tables' => []];
    foreach (store_tables() as $table) {
        $copy['tables'][$table] = store_rows($table);
    }
    $apply($copy); // plan only — nothing is written
}

/* ---------------------------------------------------------------- report */
printf("  โรงเรียน      %s (%s)\n", $data['school']['name'], $data['school']['code'] ?? '-');
foreach (['classrooms' => 'ห้องเรียน', 'teachers' => 'ครู', 'accounts' => 'บัญชีครู', 'students' => 'นักเรียน'] as $k => $label) {
    printf("  %-12s เพิ่มใหม่ %d · อัปเดต %d\n", $label, $plan[$k][0], $plan[$k][1]);
}
echo "  ผู้ปกครอง     ยังไม่สร้างบัญชี — Admin ใส่เบอร์จริง + PIN ในฟอร์มนักเรียน\n";
if ($plan['accounts'][0]) {
    echo "  PIN ครูใหม่   $teacherPin — ให้ครูเปลี่ยนเองที่หน้า บัญชี → เปลี่ยนรหัส PIN\n";
}
foreach (array_unique($warnings) as $w) {
    echo "  ⚠️  $w\n";
}
echo $confirm ? "✅ นำเข้าเรียบร้อย\n" : "ยังไม่ได้บันทึก — รันอีกครั้งพร้อม --confirm\n";
