<?php
/**
 * Seed data import (admin "นำเข้า Seed Data" button + database/import-school.php) and the admin
 * "ล้างข้อมูลระบบ" reset.
 *
 * Files (JSON), recognised by their shape:
 *   teachers + classrooms: [{ "teacher": {firstname, lastname, phone, role}, "classroom": {name, level, age_range} }, ...]
 *   students:              [{ firstname, lastname, nickname, classroom }, ...]
 *
 * Import order (the records reference each other): classrooms → teachers (+ login accounts with the
 * default PIN, set at first login) → students. One transaction; re-running updates instead of
 * duplicating (rooms by name, teachers by phone, students by room + name). Parents get accounts
 * later, when the admin adds the real phone on the student form.
 */
declare(strict_types=1);

const SEED_MAX_BYTES = 2 * 1024 * 1024;

/** Sort decoded JSON documents into teacher rows and student rows. */
function seed_classify(array $docs): array
{
    $out = ['teachers' => [], 'students' => []];
    foreach ($docs as $name => $doc) {
        if (!is_array($doc) || !array_is_list($doc) || !$doc) {
            throw new ValidationException('ไฟล์ ' . $name . ' ต้องเป็นรายการข้อมูล (JSON array)');
        }
        if (isset($doc[0]['teacher'], $doc[0]['classroom'])) {
            $out['teachers'] = array_merge($out['teachers'], $doc);
        } elseif (isset($doc[0]['firstname'], $doc[0]['classroom'])) {
            $out['students'] = array_merge($out['students'], $doc);
        } else {
            throw new ValidationException('ไม่รู้จักรูปแบบไฟล์ ' . $name . ' (ต้องเป็นไฟล์ครู+ห้องเรียน หรือไฟล์นักเรียน)');
        }
    }
    return $out;
}

/** Decode uploaded JSON files (admin button). */
function seed_from_uploads(array $files): array
{
    if (!$files) {
        throw new ValidationException('กรุณาเลือกไฟล์ Seed Data (.json)');
    }
    $docs = [];
    foreach ($files as $f) {
        if ($f['error'] !== UPLOAD_ERR_OK || $f['size'] > SEED_MAX_BYTES || !is_uploaded_file($f['tmp_name'])) {
            throw new ValidationException('อัปโหลดไฟล์ ' . $f['name'] . ' ไม่สำเร็จ (ไฟล์ต้องไม่เกิน 2 MB)');
        }
        $doc = json_decode((string) file_get_contents($f['tmp_name']), true);
        if ($doc === null) {
            throw new ValidationException('ไฟล์ ' . $f['name'] . ' ไม่ใช่ JSON ที่ถูกต้อง');
        }
        $docs[$f['name']] = $doc;
    }
    return seed_classify($docs);
}

/**
 * Validate + plan (and with $write, apply) a seed. Returns ['plan' => [...], 'warnings' => [...], 'written' => bool].
 * Every check runs before anything is written.
 */
function seed_import(array $seed, bool $write): array
{
    mk_log('Seed', 'seed_import START', ['teachers' => count($seed['teachers']), 'students' => count($seed['students']), 'write' => $write]);
    $school = store_rows('schools')[0] ?? throw new ValidationException('ยังไม่มีข้อมูลโรงเรียนในระบบ');
    $sid = $school['id'];
    $warnings = [];
    $clean = fn ($v) => trim(preg_replace('/\s+/u', ' ', (string) $v));

    // ---- validate teachers + classrooms
    $rooms = [];
    $phones = [];
    foreach ($seed['teachers'] as $i => $row) {
        $n = $i + 1;
        $first = $clean($row['teacher']['firstname'] ?? '');
        $last = $clean($row['teacher']['lastname'] ?? '');
        $phone = normalize_phone((string) ($row['teacher']['phone'] ?? ''));
        $roomName = $clean($row['classroom']['name'] ?? '');
        if ($first === '' || $roomName === '') {
            throw new ValidationException("ไฟล์ครู แถวที่ $n: ต้องมีชื่อครูและชื่อห้องเรียน");
        }
        if (!preg_match('/^0\d{9}$/', $phone)) {
            throw new ValidationException("ไฟล์ครู แถวที่ $n ($first): เบอร์โทรต้องเป็นตัวเลข 10 หลัก");
        }
        if (strtoupper((string) ($row['teacher']['role'] ?? 'TEACHER')) !== 'TEACHER') {
            throw new ValidationException("ไฟล์ครู แถวที่ $n ($first): role ต้องเป็น TEACHER");
        }
        if (isset($phones[$phone])) {
            throw new ValidationException("ไฟล์ครู: เบอร์ $phone ซ้ำกัน");
        }
        $phones[$phone] = true;
        foreach (store_rows('users') as $u) {
            if ($u['phone'] === $phone && $u['role'] !== 'teacher') {
                throw new ValidationException("เบอร์ครู $phone เป็นบัญชี" . role_meta($u['role'])['th'] . 'อยู่แล้ว');
            }
        }
        $rooms[$roomName] = ['level' => $clean($row['classroom']['level'] ?? ''), 'ageRange' => $clean($row['classroom']['age_range'] ?? ''),
            'teacher' => ['nickname' => 'ครู' . $first, 'fullName' => trim($first . ' ' . $last), 'phone' => $phone]];
    }

    // ---- validate students (their room must be in the file or already in the system)
    $existingRooms = array_column(where(store_rows('classrooms'), 'school_id', $sid), 'name');
    $students = [];
    foreach ($seed['students'] as $i => $row) {
        $n = $i + 1;
        $first = $clean($row['firstname'] ?? '');
        $last = $clean($row['lastname'] ?? '');
        $nick = $clean($row['nickname'] ?? '');
        $room = $clean($row['classroom'] ?? '');
        if ($first === '' || $nick === '' || $room === '') {
            throw new ValidationException("ไฟล์นักเรียน แถวที่ $n: ต้องมีชื่อ ชื่อเล่น และห้องเรียน");
        }
        if (!isset($rooms[$room]) && !in_array($room, $existingRooms, true)) {
            throw new ValidationException("ไฟล์นักเรียน แถวที่ $n ($first): ไม่พบห้อง \"$room\" ในไฟล์ครูหรือในระบบ");
        }
        $students[] = ['name' => trim($first . ' ' . $last), 'nickname' => str_starts_with($nick, 'น้อง') ? $nick : 'น้อง' . $nick, 'room' => $room];
    }
    if ($students) {
        $warnings[] = 'ไฟล์ไม่มีข้อมูลเพศ — แก้ไขได้ภายหลังในหน้า จัดการนักเรียน';
    }

    $plan = ['classrooms' => [0, 0], 'teachers' => [0, 0], 'students' => [0, 0]]; // [new, updated]
    $apply = function (array &$state) use ($sid, $rooms, $students, &$plan): void {
        $t = &$state['tables'];
        $colors = ['#FFF4CC', '#DFF4FF', '#FFEAF2', '#DDF7E6', '#F1EAFF', '#FFE9D6'];
        $emojis = ['🐣', '🐰', '🐼', '🐨', '🦊', '🐧'];
        $roomIds = [];
        foreach (where($t['classrooms'], 'school_id', $sid) as $c) {
            $roomIds[$c['name']] = $c['id'];
        }
        $ri = count($roomIds);
        foreach ($rooms as $name => $room) {
            if (isset($roomIds[$name])) {
                foreach ($t['classrooms'] as &$c) {
                    if ($c['id'] === $roomIds[$name]) {
                        $c['level'] = $room['level'] ?: ($c['level'] ?? null);
                        $c['ageRange'] = $room['ageRange'] ?: ($c['ageRange'] ?? null);
                    }
                }
                unset($c);
                $plan['classrooms'][1]++;
            } else {
                $roomIds[$name] = $state['nextId']++;
                $t['classrooms'][] = ['id' => $roomIds[$name], 'school_id' => $sid, 'name' => $name, 'level' => $room['level'] ?: null,
                    'ageRange' => $room['ageRange'] ?: null, 'emoji' => $emojis[$ri % 6], 'color' => $colors[$ri % 6], 'capacity' => 20];
                $ri++;
                $plan['classrooms'][0]++;
            }
            // teacher (by phone) + account (default PIN, set at first login; an existing PIN is kept)
            $tr = $room['teacher'];
            $teacherId = null;
            foreach ($t['teachers'] as &$x) {
                if ($x['school_id'] === $sid && $x['phone'] === $tr['phone']) {
                    $x = array_merge($x, ['nickname' => $tr['nickname'], 'fullName' => $tr['fullName'], 'classroom_id' => $roomIds[$name]]);
                    $teacherId = $x['id'];
                }
            }
            unset($x);
            if ($teacherId === null) {
                $teacherId = $state['nextId']++;
                $t['teachers'][] = ['id' => $teacherId, 'school_id' => $sid, 'classroom_id' => $roomIds[$name], 'nickname' => $tr['nickname'],
                    'fullName' => $tr['fullName'], 'position' => 'ครูประจำชั้น', 'emoji' => '👩‍🏫', 'phone' => $tr['phone']];
                $plan['teachers'][0]++;
            } else {
                $plan['teachers'][1]++;
            }
            $hasAccount = false;
            foreach ($t['users'] as &$u) {
                if ($u['phone'] === $tr['phone'] && $u['role'] === 'teacher') {
                    $u = array_merge($u, ['name' => $tr['nickname'], 'school_id' => $sid, 'classroom_id' => $roomIds[$name], 'teacher_id' => $teacherId]);
                    $hasAccount = true;
                }
            }
            unset($u);
            if (!$hasAccount) {
                $t['users'][] = ['id' => $state['nextId']++, 'name' => $tr['nickname'], 'phone' => $tr['phone'], 'role' => 'teacher', 'school_id' => $sid,
                    'classroom_id' => $roomIds[$name], 'student_id' => null, 'teacher_id' => $teacherId] + account_default_pin();
            }
        }
        $seq = [];
        foreach ($students as $st) {
            $roomId = $roomIds[$st['room']];
            $found = false;
            foreach ($t['students'] as &$x) {
                if ($x['school_id'] === $sid && $x['classroom_id'] === $roomId && $x['name'] === $st['name']) {
                    $x['nickname'] = $st['nickname'];
                    $found = true;
                }
            }
            unset($x);
            if ($found) {
                $plan['students'][1]++;
                continue;
            }
            $seq[$roomId] = ($seq[$roomId] ?? count(where($t['students'], 'classroom_id', $roomId))) + 1;
            $t['students'][] = ['id' => $state['nextId']++, 'school_id' => $sid, 'classroom_id' => $roomId,
                'code' => sprintf('S%03d-%02d', array_search($st['room'], array_keys($roomIds), true) + 1, $seq[$roomId]),
                'name' => $st['name'], 'nickname' => $st['nickname'], 'gender' => '', 'classroom' => $st['room'],
                'parentName' => 'ผู้ปกครองของ' . $st['nickname'], 'parentRelation' => '', 'parentPhone' => null,
                'avatar' => ['skin' => '#FFDBC0', 'hair' => '#3B2A20', 'bg' => '#BFE7FF', 'shirt' => '#7CC6F2', 'style' => 'short']];
            $plan['students'][0]++;
        }
    };

    if ($write) {
        store_mutate($apply); // all or nothing; today's daily sheets are opened for the new students
    } else {
        $copy = ['nextId' => store_state()['nextId'], 'tables' => []];
        foreach (store_tables() as $table) {
            $copy['tables'][$table] = store_rows($table);
        }
        $apply($copy);
    }
    mk_log('Seed', 'seed_import END', ['plan' => $plan, 'write' => $write]);
    return ['plan' => $plan, 'warnings' => $warnings, 'written' => $write];
}

/** Human summary of seed_import() (API toast + CLI). */
function seed_report(array $r): array
{
    $lines = [];
    foreach (['classrooms' => 'ห้องเรียน', 'teachers' => 'ครู (+ บัญชี PIN เริ่มต้น ' . DEFAULT_PIN . ')', 'students' => 'นักเรียน'] as $k => $label) {
        $lines[] = sprintf('%s: เพิ่มใหม่ %d · อัปเดต %d', $label, $r['plan'][$k][0], $r['plan'][$k][1]);
    }
    return array_merge($lines, $r['warnings']);
}

/**
 * Admin "ล้างข้อมูลระบบ": remove EVERYTHING except the school, its settings and the admin accounts
 * (one transaction), then the uploaded image files. Returns how many rows were removed.
 */
function system_reset(array $admin): int
{
    mk_log('System', 'system_reset START', ['admin' => $admin['id']]);
    $removed = store_mutate(function (array &$state): int {
        $n = 0;
        foreach (store_tables() as $table) {
            $keep = match ($table) {
                'schools', 'settings' => $state['tables'][$table],
                'users'               => array_values(array_filter($state['tables']['users'], fn ($u) => $u['role'] === 'admin')),
                default               => [],
            };
            $n += count($state['tables'][$table] ?? []) - count($keep);
            $state['tables'][$table] = $keep;
        }
        return $n;
    })['result'];
    $root = realpath(media_root());
    if ($root && is_dir($root)) {
        $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($items as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
    }
    mk_db()->exec('DELETE FROM login_attempts');
    mk_log('System', 'system_reset END', ['admin' => $admin['id'], 'rows' => $removed]);
    return $removed;
}
