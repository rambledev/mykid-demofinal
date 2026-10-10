<?php
/**
 * Core seed — builds mock "database tables" for a package demo.
 *
 * School #1 (โรงเรียนอนุบาลโพนสูง) is taken from Package C's mock data so every package
 * shows the same 60 children. Extra schools (Package A) are generated deterministically.
 * Daily records (attendance, sleep, health, ...) are generated for "today".
 */
declare(strict_types=1);

/* ---------------------------------------------------------------------------
 * Name pools for generated schools (all fictional)
 * ------------------------------------------------------------------------ */

const SEED_BOY_NICKNAMES = ['กาย', 'เก้า', 'ข้าวตัง', 'โชกุน', 'ซีอิ๊ว', 'ณัฐ', 'ต้นกล้า', 'ไต้ฝุ่น', 'ทะเล', 'ธีร์',
    'นาวา', 'บอส', 'ปั้นจั่น', 'ปาย', 'พีท', 'ภูมิใจ', 'มาร์ค', 'ริว', 'วาฬ', 'ศิลา', 'สกาย', 'หมอก', 'อชิ', 'โอม',
    'ไอซ์', 'เคน', 'ชิน', 'ซัน', 'ดิว', 'ต่อ', 'ตูมตาม', 'นนท์', 'บิ๊ก', 'พัฒน์', 'ฟิวส์', 'ภัทร', 'เมษ', 'ยศ', 'ลีโอ',
    'วิน', 'เสือ', 'หยก', 'อาร์ม', 'เอิร์ธ', 'โฟกัส', 'ข้าวเหนียว', 'เต้ย', 'แทน', 'ปัน', 'ไทเกอร์', 'ดิน', 'สายฟ้า',
    'ภาม', 'กอล์ฟ', 'คอปเตอร์', 'จิมมี่', 'แจ็ค', 'ต้นน้ำ', 'ธาวิน', 'โชคดี',
    'ข้าวกล่อง', 'เจ้านาย', 'ชาบู', 'ซูชิ', 'ต้นไผ่', 'นาโน', 'ปังปอนด์', 'พอร์ช', 'ฟินน์', 'ภูเขา', 'มังกร', 'ยูโร',
    'รถถัง', 'ลูกชิ้น', 'วันใหม่', 'สิงโต', 'อัลฟ่า', 'ไอดิน', 'โอเล่', 'แบงค์', 'ก้อง', 'คีน', 'จ๊อบ', 'ชิโร่', 'ตั้ม',
    'ทิกเกอร์', 'นะโม', 'บุญรอด', 'ปลาวาฬ', 'พายัพ', 'ฟลุ๊ค', 'มาวิน', 'ไรวินท์', 'วายุ', 'ศุภ', 'สปาย', 'เหนือ',
    'อิคคิว', 'อชิระ', 'เจได', 'ข้าวหลาม', 'โกโก้', 'ณดล', 'ธันเดอร์', 'บัดดี้'];
const SEED_GIRL_NICKNAMES = ['อิงฟ้า', 'พลอย', 'ฟ้า', 'ขิม', 'แก้วตา', 'จีจี้', 'ญาญ่า', 'ณิชา', 'ดาวเหนือ', 'ตาหวาน',
    'ทราย', 'นุ่น', 'บัวขาว', 'แบมบี้', 'ปลายฟ้า', 'แพรไหม', 'ฟักทอง', 'มายด์', 'มิ้นท์', 'มุก', 'ยิ้ม', 'ลูกพีช',
    'วุ้นเส้น', 'ส้มจี๊ด', 'ใบชา', 'หยดน้ำ', 'อันดา', 'ออม', 'เอมมี่', 'ไอริน', 'แอปเปิ้ล', 'กุ๊กไก่', 'ขนมปัง',
    'คัพเค้ก', 'เชอร์รี่', 'โดนัท', 'ต่าย', 'ทิวลิป', 'น้ำตาล', 'เบลล์', 'ปุ้มปุ้ย', 'พิมพ์', 'แพนเค้ก', 'มะยม',
    'มะขาม', 'ลำไย', 'ลิลลี่', 'วาว่า', 'สตรอเบอร์รี่', 'หนูดี', 'อุ้ม', 'ไอวี่', 'ใบเฟิร์น', 'กระต่าย', 'ดาวใจ',
    'ทองหยอด', 'น้ำค้าง', 'ปีใหม่', 'มะเฟือง', 'แยม',
    'กะทิ', 'ข้าวปุ้น', 'แคนดี้', 'จันทร์เจ้า', 'ชมจันทร์', 'ซากุระ', 'ญาดา', 'ดาริน', 'ตังเม', 'ทานตะวัน', 'นาเดีย',
    'บุษบา', 'ปาล์มมี่', 'พิงค์', 'ฟ้าคราม', 'มายา', 'ยูกิ', 'ริน', 'ลูกน้ำ', 'วนิลา', 'ส้มส้ม', 'หยาดฝน', 'อมยิ้ม',
    'เอิงเอย', 'ไอซ์ซี่', 'กุ๊บกิ๊บ', 'ข้าวทิพย์', 'คุกกี้', 'จูจู', 'เฌอแตม', 'ดอกแก้ว', 'ต้นข้าว', 'นมสด', 'บัวบูชา',
    'เปียโน', 'แพรวพราว', 'มัทฉะ', 'ระฆัง', 'ลาเต้', 'วิวา', 'สายไหม', 'หนูนา', 'อุ่นใจ', 'ออมสิน', 'ไหมพรม'];
const SEED_BOY_NAMES = ['ธนภัทร', 'กฤษดา', 'ภาณุพงศ์', 'ชยพล', 'ปฐมพร', 'ณัฐวุฒิ', 'ศุภกร', 'กิตติพัฒน์', 'วรเมธ',
    'อัครพล', 'ธีรเดช', 'พีรพัฒน์', 'ปกรณ์', 'เตชินท์', 'รัชชานนท์'];
const SEED_GIRL_NAMES = ['กชกร', 'พิมพ์ชนก', 'ณัฐธิดา', 'ปุณยนุช', 'ศศิธร', 'อรปรียา', 'ชนัญชิดา', 'กัญญาณัฐ',
    'วริศรา', 'ภัทรธิดา', 'ธัญญารัตน์', 'พรนภัส', 'ปภาวรินทร์', 'ณิชกานต์', 'สุพิชญา'];
const SEED_SURNAMES = ['แก้วมณี', 'ทองประเสริฐ', 'ศรีวงศ์', 'บุญยงค์', 'ใจบุญ', 'พรหมมา', 'สุขเจริญ', 'นาคประสิทธิ์',
    'วงศ์สวัสดิ์', 'มีชัย', 'ชัยประเสริฐ', 'อินทรวงศ์', 'ศรีสมบูรณ์', 'คำภา', 'พลายงาม', 'เพชรรัตน์', 'จันทร์หอม',
    'รุ่งโรจน์', 'บุญมาก', 'ศักดิ์ดี', 'ทองคำดี', 'ธนสาร', 'สมใจ', 'แสงอรุณ', 'วิเศษสุข', 'ภูมิภักดิ์', 'ประเสริฐสุข',
    'อ่อนศรี', 'ดีงาม', 'มั่นคงดี'];
const SEED_MOTHERS = ['สุดารัตน์', 'อัมพร', 'จิราภรณ์', 'วนิดา', 'ปวีณา', 'รัชนี', 'ศิริลักษณ์', 'กาญจนาพร'];
const SEED_FATHERS = ['ธนวัฒน์', 'สมพงษ์', 'วีระชัย', 'อภิชาต', 'ชาตรี', 'พงศกร'];

const SEED_ACTIVITY_TEMPLATES = [
    [['08:00', '🚩', 'เข้าแถวหน้าเสาธง', 'เคารพธงชาติ สวดมนต์ และออกกำลังกายยามเช้า'], ['09:00', '🔢', 'เรียนรู้ตัวเลข', 'นับเลขผ่านเกมและเพลง'],
        ['10:00', '🎨', 'กิจกรรมศิลปะ', 'ระบายสีและปั้นดินน้ำมัน'], ['11:00', '🍱', 'รับประทานอาหารกลางวัน', 'ฝึกล้างมือและตักอาหารเอง'],
        ['12:00', '😴', 'นอนกลางวัน', 'พักผ่อนให้เพียงพอ'], ['14:00', '⚽', 'กิจกรรมกลางแจ้ง', 'เล่นเครื่องเล่นสนาม']],
    [['08:00', '🚩', 'เข้าแถวหน้าเสาธง', 'เคารพธงชาติ สวดมนต์'], ['09:00', '🔤', 'เรียนรู้ภาษาไทย', 'ฝึกอ่านพยัญชนะและสระ'],
        ['10:00', '🎵', 'ดนตรีและการเคลื่อนไหว', 'ร้องเพลงและเต้นประกอบจังหวะ'], ['11:00', '🍱', 'รับประทานอาหารกลางวัน', 'ฝึกมารยาทบนโต๊ะอาหาร'],
        ['12:00', '😴', 'นอนกลางวัน', 'พักผ่อนให้เพียงพอ'], ['14:00', '🌱', 'สวนผักน้อย', 'รดน้ำต้นไม้และสังเกตการเจริญเติบโต']],
    [['08:00', '🚩', 'เข้าแถวหน้าเสาธง', 'เคารพธงชาติ สวดมนต์'], ['09:00', '🔤', 'ภาษาอังกฤษหรรษา', 'เรียนคำศัพท์ผ่านเพลง'],
        ['10:00', '🔬', 'วิทยาศาสตร์น้อย', 'ทดลองผสมสีด้วยน้ำ'], ['11:00', '🍱', 'รับประทานอาหารกลางวัน', 'ฝึกเก็บจานด้วยตัวเอง'],
        ['12:00', '😴', 'นอนกลางวัน', 'พักผ่อนให้เพียงพอ'], ['14:00', '📖', 'เล่านิทาน', 'ฟังนิทานและตอบคำถาม']],
];

const SEED_MENUS = [
    ['breakfast' => ['โจ๊กหมูใส่ไข่'], 'lunch' => ['ข้าวมันไก่', 'ซุปฟักทอง'], 'snack' => ['ขนมปังลูกเกด'], 'milk' => ['นมจืด UHT'], 'fruit' => ['แตงโม']],
    ['breakfast' => ['ข้าวไข่เจียวหมูสับ'], 'lunch' => ['ก๋วยเตี๋ยวไก่', 'ผัดผักรวม'], 'snack' => ['ขนมกล้วย'], 'milk' => ['นมถั่วเหลือง'], 'fruit' => ['มะละกอ']],
    ['breakfast' => ['ข้าวต้มปลา'], 'lunch' => ['ข้าวผัดหมู', 'แกงจืดฟักหมูสับ'], 'snack' => ['เต้าฮวยน้ำขิง'], 'milk' => ['นมจืด UHT'], 'fruit' => ['ส้ม']],
];

/* ---------------------------------------------------------------------------
 * Schools
 * ------------------------------------------------------------------------ */

/** School #1 straight from Package C's mock data, tagged with school_id = 1. */
function seed_school_from_package_c(): array
{
    $c = mock_data();
    $sid = 1;
    $tag = fn (array $rows) => array_map(fn ($r) => ['school_id' => $sid] + $r, $rows);

    $menus = [];
    foreach ($c['foodMenus'] as $menu) {
        $menus[] = ['school_id' => $sid] + $menu + ['fruit' => ['กล้วยหอม']];
    }

    return [
        'school'     => ['id' => $sid, 'name' => $c['school']['name'], 'shortName' => $c['school']['shortName'], 'emoji' => '🏫',
                         'color' => '#FFF4CC', 'province' => 'ร้อยเอ็ด', 'status' => 'active'],
        'classrooms' => $tag($c['classrooms']),
        'teachers'   => $tag(array_map(fn ($t) => $t + ['phone' => ''], $c['teachers'])),
        'students'   => $tag($c['students']),
        'activities' => $tag($c['activities']),
        'foodMenus'  => $menus,
        'statuses'   => array_map(fn ($s) => ['school_id' => $sid] + $s, $c['initialStatuses']),
    ];
}

/**
 * Generate a school with 3 classrooms.
 * $ids = starting ids ['classroom' => , 'student' => , 'teacher' => , 'activity' => ]
 */
function seed_generated_school(int $sid, string $name, string $shortName, string $emoji, array $rooms, array $teacherNames, array $ids, int $poolOffset, string $status = 'active'): array
{
    $classrooms = $teachers = $students = $activities = $menus = $statuses = [];
    $skins = ['#FFDBC0', '#F6C9A3', '#EDB892', '#FCE3D0'];
    $hairs = ['#3B2A20', '#5A3A26', '#2B2B2B', '#7A4B2A'];
    $bgs = ['#FFE9A8', '#BFE7FF', '#FFD6E7', '#D4F5DF', '#E6DBFF', '#FFE1C7'];
    $shirts = ['#7CC6F2', '#FF9EC0', '#FFC94D', '#8FD9A8', '#B79CFF', '#FF9F7A'];

    $studentId = $ids['student'];
    $teacherIndex = 0;
    $activityId = $ids['activity'];
    $boys = 0;
    $girls = 0;

    foreach ($rooms as $r => [$roomName, $roomEmoji, $color, $size, $teacherCount]) {
        $cid = $ids['classroom'] + $r;
        $classrooms[] = ['id' => $cid, 'school_id' => $sid, 'name' => $roomName, 'emoji' => $roomEmoji, 'color' => $color, 'capacity' => 20];

        for ($t = 0; $t < $teacherCount; $t++, $teacherIndex++) {
            [$nick, $full] = $teacherNames[$teacherIndex];
            $teachers[] = ['id' => $ids['teacher'] + $teacherIndex, 'school_id' => $sid, 'classroom_id' => $cid, 'nickname' => $nick,
                'fullName' => $full, 'position' => 'ครูประจำชั้น', 'emoji' => '👩‍🏫', 'phone' => ''];
        }

        for ($k = 0; $k < $size; $k++, $studentId++) {
            $isGirl = $k % 2 === 1;
            $nick = $isGirl ? SEED_GIRL_NICKNAMES[$poolOffset + $girls++] : SEED_BOY_NICKNAMES[$poolOffset + $boys++];
            $surname = SEED_SURNAMES[($studentId * 7 + $sid) % count(SEED_SURNAMES)];
            $given = $isGirl ? SEED_GIRL_NAMES[$studentId % 15] : SEED_BOY_NAMES[$studentId % 15];
            $father = $studentId % 3 === 0;
            $parent = $father ? 'นาย' . SEED_FATHERS[$studentId % 6] : 'นาง' . SEED_MOTHERS[$studentId % 8];
            $students[] = [
                'id' => $studentId, 'school_id' => $sid, 'code' => sprintf('MK%03d', $studentId),
                'name' => ($isGirl ? 'ด.ญ.' : 'ด.ช.') . $given . ' ' . $surname, 'nickname' => 'น้อง' . $nick,
                'gender' => $isGirl ? 'f' : 'm', 'classroom' => $roomName, 'classroom_id' => $cid,
                'parentName' => $parent . ' ' . $surname, 'parentRelation' => $father ? 'พ่อ' : 'แม่',
                'avatar' => [
                    'skin' => $skins[$studentId % 4], 'hair' => $hairs[($studentId * 3) % 4], 'bg' => $bgs[$studentId % 6],
                    'shirt' => $shirts[($studentId * 5) % 6],
                    'style' => $isGirl ? ($studentId % 2 ? 'bob' : 'pigtails') : ($studentId % 2 ? 'spiky' : 'short'),
                ],
            ];
        }

        foreach (SEED_ACTIVITY_TEMPLATES[$r % 3] as [$time, $icon, $title, $detail]) {
            $activities[] = ['id' => $activityId++, 'school_id' => $sid, 'classroom_id' => $cid, 'time' => $time, 'icon' => $icon, 'title' => $title, 'detail' => $detail];
        }
        $menus[] = ['id' => $cid, 'school_id' => $sid, 'classroom_id' => $cid] + SEED_MENUS[($r + $sid) % 3];

        $firstTeacher = $ids['teacher'] + $teacherIndex - $teacherCount;
        $statuses[] = ['school_id' => $sid, 'classroom_id' => $cid, 'code' => 'line_up', 'note' => '', 'time' => '08:00', 'by' => $firstTeacher];
        $statuses[] = [['school_id' => $sid, 'classroom_id' => $cid, 'code' => 'study', 'note' => 'ภาษาไทย', 'time' => '09:10', 'by' => $firstTeacher],
            ['school_id' => $sid, 'classroom_id' => $cid, 'code' => 'activity', 'note' => 'ศิลปะ', 'time' => '10:20', 'by' => $firstTeacher],
            ['school_id' => $sid, 'classroom_id' => $cid, 'code' => 'eat', 'note' => '', 'time' => '11:05', 'by' => $firstTeacher]][($r + $sid) % 3];
    }

    return [
        'school' => ['id' => $sid, 'name' => $name, 'shortName' => $shortName, 'emoji' => $emoji, 'color' => $rooms[0][2],
                     'province' => ['', '', 'ขอนแก่น', 'มหาสารคาม', 'กาฬสินธุ์', 'อุดรธานี'][$sid] ?? 'ร้อยเอ็ด', 'status' => $status],
        'classrooms' => $classrooms, 'teachers' => $teachers, 'students' => $students,
        'activities' => $activities, 'foodMenus' => $menus, 'statuses' => $statuses,
    ];
}

/* ---------------------------------------------------------------------------
 * Assemble all tables
 * ------------------------------------------------------------------------ */

/**
 * Merge schools into tables and generate daily / school-level records.
 * $extraActivity: optional [time, icon, title, detail] appended to every classroom (Package A: 15:30 เตรียมกลับบ้าน).
 */
function seed_build_tables(array $schools, array $users, ?array $extraActivity = null): array
{
    $today = today();
    $t = array_fill_keys(array_keys(mk_tables()), []);
    $nextId = 100000;

    foreach ($schools as $s) {
        $t['schools'][] = $s['school'];
        foreach (['classrooms', 'teachers', 'students', 'activities', 'foodMenus'] as $table) {
            array_push($t[$table], ...$s[$table]);
        }
        foreach ($s['statuses'] as $st) {
            $t['statuses'][] = ['id' => $nextId++, 'school_id' => $st['school_id'], 'classroom_id' => $st['classroom_id'],
                'code' => $st['code'], 'note' => $st['note'], 'at' => "$today {$st['time']}:00", 'by' => $st['by']];
        }
    }
    $t['statuses'] = array_reverse($t['statuses']); // newest first
    $t['users'] = $users;
    seed_parent_students($t, $nextId);

    $classroomsById = array_column($t['classrooms'], null, 'id');
    $teachersByRoom = [];
    foreach ($t['teachers'] as $teacher) {
        $teachersByRoom[$teacher['classroom_id']][] = $teacher;
    }

    foreach ($t['classrooms'] as $room) {
        if ($extraActivity) {
            [$time, $icon, $title, $detail] = $extraActivity;
            $t['activities'][] = ['id' => $nextId++, 'school_id' => $room['school_id'], 'classroom_id' => $room['id'],
                'time' => $time, 'icon' => $icon, 'title' => $title, 'detail' => $detail];
        }
        foreach ([['🎨', 'ผลงานระบายสีของเด็ก ๆ', 'กิจกรรมศิลปะ'], ['⚽', 'สนุกกับกิจกรรมกลางแจ้ง', 'เล่นกลางแจ้ง'],
                  ['📖', 'ตั้งใจฟังนิทานยามบ่าย', 'เล่านิทาน'], ['🍱', 'ทานข้าวกลางวันด้วยตัวเอง', 'อาหารกลางวัน']] as $i => [$emoji, $caption, $activity]) {
            $t['photos'][] = ['id' => $nextId++, 'school_id' => $room['school_id'], 'classroom_id' => $room['id'],
                'date' => date('Y-m-d', strtotime("-$i day")), 'caption' => $caption, 'activity' => $activity, 'emoji' => $emoji, 'art' => ($room['id'] + $i) % 6];
        }
    }

    seed_history_days($t, $nextId);

    foreach ($t['students'] as $st) {
        seed_student_day($t, $st, $teachersByRoom[$st['classroom_id']][0] ?? null, $nextId);
    }

    foreach ($t['schools'] as $school) {
        seed_school_records($t, $school, array_values(array_filter($t['classrooms'], fn ($c) => $c['school_id'] === $school['id'])), $nextId);
    }

    seed_messages($t, $classroomsById, $teachersByRoom, $nextId);
    seed_parent_stars($t, $teachersByRoom, $nextId);
    seed_cameras($t, $nextId);
    seed_pickups_day($t, $today, $nextId);
    seed_notifications($t, $nextId);
    if (has_feature('media')) {
        seed_media($t, $nextId); // demo image files + metadata (core/media.php)
    }

    return ['tables' => $t, 'nextId' => $nextId];
}

/** Today's daily records + history (stars, development, portfolio) for one student. */
function seed_student_day(array &$t, array $st, ?array $teacher, int &$nextId): void
{
    $today = today();
    $sid = $st['id'];
    $base = ['school_id' => $st['school_id'], 'classroom_id' => $st['classroom_id'], 'student_id' => $sid];
    $isFirst = $sid === 1; // น้องต้น — matches the examples in the brief

    seed_student_daily($t, $st, $today, $nextId);

    // Stars (last two weeks). น้องต้น totals 28 as in the brief.
    $starRows = $isFirst
        ? [[0, 5, 'ตั้งใจทำกิจกรรม'], [0, 3, 'ช่วยเก็บของ'], [1, 4, 'กล้าแสดงออก'], [2, 2, 'แบ่งปันเพื่อน'],
           [4, 5, 'ตั้งใจทำกิจกรรม'], [6, 2, 'ทานข้าวหมด'], [8, 3, 'ช่วยเก็บของ'], [10, 4, 'กล้าแสดงออก']]
        : array_map(function ($i) use ($sid) {
            $reason = mk_catalog()['starReasons'][mk_rand($sid, 'star', $i) % 6];
            return [$i * 4 + mk_rand($sid, 'starday', $i) % 3, $reason['points'], $reason['reason']]; // ~6 weeks → this + last month
        }, range(0, 1 + mk_rand($sid, 'stars') % 4 + [0, 2, 3, 1, 0, 4][$st['school_id'] % 6])); // schools differ
    foreach ($starRows as [$daysAgo, $points, $reason]) {
        $t['stars'][] = ['id' => $nextId++] + $base + ['recipient' => 'student', 'parent_id' => null,
            'date' => date('Y-m-d', strtotime("-$daysAgo day")), 'points' => $points, 'reason' => $reason, 'by' => $teacher['id'] ?? null];
    }

    $dev = ['id' => $nextId++] + $base + ['updated' => $today];
    foreach (mk_catalog()['devDomains'] as $domain) {
        $dev[$domain['code']] = 60 + mk_rand($sid, $domain['code']) % 36;
    }
    $dev['note'] = $isFirst ? 'น้องต้นกล้าแสดงออกมากขึ้น ชอบวาดรูปและเล่านิทานให้เพื่อนฟัง' : 'พัฒนาการเหมาะสมตามวัย';
    $t['development'][] = $dev;

    $works = [['art', 'ภาพระบายสีครอบครัวของฉัน'], ['art', 'ปั้นดินน้ำมันรูปสัตว์'], ['language', 'ฝึกเขียนตัวอักษร ก–ฮ'],
        ['math', 'นับและจับคู่ตัวเลข'], ['science', 'ทดลองปลูกถั่วงอก'], ['music', 'ร้องเพลงช้างหน้าชั้นเรียน']];
    $workCount = $sid % 10 === 0 ? 0 : 3; // every 10th child has no work yet (ผลงานนักเรียน empty state)
    for ($i = 0; $i < $workCount; $i++) {
        [$cat, $title] = $works[(mk_rand($sid, 'work') + $i * 2) % 6];
        $t['portfolio'][] = ['id' => $nextId++] + $base + ['date' => date('Y-m-d', strtotime('-' . ($i * 6 + 1) . ' day')),
            'title' => $title, 'category' => $cat, 'art' => ($sid + $i) % 6,
            'comment' => ['ใช้สีสดใสและสร้างสรรค์มาก', 'ตั้งใจทำจนเสร็จ เก่งมากค่ะ', 'มีพัฒนาการดีขึ้นอย่างเห็นได้ชัด'][$i]];
    }
}

/**
 * One day of per-student records: attendance, sleep, health, food intake.
 * Used by the initial seed AND by seed_roll_day() — the date is part of the random key,
 * so each day looks different (น้องต้น keeps the fixed values from the brief).
 */
function seed_student_daily(array &$t, array $st, string $date, int &$nextId): void
{
    $sid = $st['id'];
    $base = ['school_id' => $st['school_id'], 'classroom_id' => $st['classroom_id'], 'student_id' => $sid];
    $isFirst = $sid === 1;
    $day = $date === today() && !isset($t['__rolling']) ? '' : $date; // first seed keeps the original values

    $r = mk_rand($sid, 'att', $day) % 100;
    $status = $isFirst ? 'present' : ($r < 82 ? 'present' : ($r < 90 ? 'late' : ($r < 96 ? 'leave' : 'absent')));
    $here = in_array($status, ['present', 'late'], true);
    $checkIn = $here ? sprintf('%02d:%02d', $status === 'late' ? 8 : 7, $status === 'late' ? 10 + $r % 25 : 15 + $r % 40) : '';
    if ($isFirst) {
        $checkIn = '07:42';
    }
    $t['attendance'][] = ['id' => $nextId++] + $base + ['date' => $date, 'status' => $status, 'checkIn' => $checkIn,
        'note' => $status === 'leave' ? (['ลาป่วย', 'ลากิจ'][$r % 2]) : ''];

    $sr = mk_rand($sid, 'sleep', $day) % 100;
    $t['sleepRecords'][] = ['id' => $nextId++] + $base + ['date' => $date,
        'start' => $isFirst ? '12:15' : ($here ? sprintf('12:%02d', 5 + $sr % 20) : ''),
        'end' => $isFirst ? '13:45' : ($here ? sprintf('13:%02d', 30 + $sr % 25) : ''),
        'quality' => $isFirst ? 'good' : (!$here ? 'none' : ($sr < 85 ? 'good' : 'restless')),
        'note' => $isFirst ? 'หลับสบาย ตื่นมาอารมณ์ดี' : ''];

    $hr = mk_rand($sid, 'health', $day) % 100;
    $watch = !$isFirst && $here && $hr < 8;
    $t['healthRecords'][] = ['id' => $nextId++] + $base + ['date' => $date,
        'temperature' => $isFirst ? 36.5 : ($watch ? 37.4 : round(36.2 + ($hr % 9) / 10, 1)),
        'condition' => $watch ? 'watch' : 'normal',
        'symptoms' => $watch ? (['มีน้ำมูกเล็กน้อย', 'ไอเล็กน้อย'][$hr % 2]) : 'ไม่มีอาการผิดปกติ',
        'note' => $isFirst ? 'ร่าเริงแจ่มใส' : ($watch ? 'ครูจะคอยสังเกตอาการ' : '')];

    $levels = [];
    foreach (mk_catalog()['meals'] as $i => $meal) {
        $x = mk_rand($sid, 'meal', $i, $day) % 100;
        $levels[$meal['meal']] = !$here ? 'none' : ($x < 65 ? 'good' : ($x < 88 ? 'some' : 'little'));
    }
    if ($isFirst) {
        $levels['lunch'] = 'some';
    }
    $t['foodIntake'][] = ['id' => $nextId++] + $base + ['date' => $date, 'levels' => $levels];
}

/** Real use: today's empty records for one student (attendance not checked yet, nap pending, ...). */
function seed_student_blank_day(array &$t, array $st, string $date, int &$nextId): void
{
    $base = ['school_id' => $st['school_id'], 'classroom_id' => $st['classroom_id'], 'student_id' => $st['id'], 'date' => $date];
    $t['attendance'][] = ['id' => $nextId++] + $base + ['status' => '', 'checkIn' => '', 'note' => ''];
    $t['sleepRecords'][] = ['id' => $nextId++] + $base + ['start' => '', 'end' => '', 'quality' => 'pending', 'note' => ''];
    $t['healthRecords'][] = ['id' => $nextId++] + $base + ['temperature' => null, 'condition' => '', 'symptoms' => '', 'note' => ''];
    $t['foodIntake'][] = ['id' => $nextId++] + $base + ['levels' => []];
}

/**
 * Activities and food menus carry a date. Today's rows keep the ids from the school seed;
 * the previous 6 days + one day ~7 months ago (for the image-retention demo) are cloned
 * with a rotated menu so the history looks real.
 */
function seed_history_days(array &$t, int &$nextId): void
{
    $today = today();
    foreach (['activities', 'foodMenus'] as $table) {
        foreach ($t[$table] as &$row) {
            $row['date'] = $today;
        }
        unset($row);
    }

    $todayActivities = $t['activities'];
    $todayMenus = $t['foodMenus'];
    foreach (array_merge(range(1, 6), [SEED_OLD_DAYS]) as $daysAgo) {
        $date = date('Y-m-d', strtotime("-$daysAgo day"));
        foreach ($todayActivities as $a) {
            $t['activities'][] = ['id' => $nextId++, 'date' => $date] + $a;
        }
        foreach ($todayMenus as $m) {
            $rotated = SEED_MENUS[($m['classroom_id'] + $daysAgo) % 3];
            $t['foodMenus'][] = ['id' => $nextId++, 'date' => $date] + array_intersect_key($rotated, array_flip(['breakfast', 'lunch', 'snack', 'milk', 'fruit'])) + $m;
        }
    }
}

const SEED_OLD_DAYS = 200; // ~6.6 months ago → images from that day are past the 6-month retention

/**
 * Move the demo forward to a new day WITHOUT deleting anything (business rule: no automatic
 * database deletes). Adds today's per-student records, a morning status, and copies the most
 * recent activity schedule / menu of each classroom to today. History stays untouched.
 */
function seed_roll_day(array &$state): void
{
    $today = today();
    $t = &$state['tables'];
    $nextId = &$state['nextId'];
    mk_log('Seed', 'seed_roll_day START', ['from' => $state['seedDate'], 'to' => $today]);

    foreach ($t['classrooms'] as $room) {
        foreach (['activities', 'foodMenus'] as $table) {
            $rows = array_values(array_filter($t[$table], fn ($r) => $r['classroom_id'] === $room['id']));
            if (!$rows || array_filter($rows, fn ($r) => ($r['date'] ?? '') === $today)) {
                continue;
            }
            $latest = max(array_map(fn ($r) => $r['date'] ?? '', $rows));
            foreach ($rows as $r) {
                if (($r['date'] ?? '') === $latest) {
                    $t[$table][] = ['id' => $nextId++, 'date' => $today] + $r;
                }
            }
        }
        $teacher = array_values(array_filter($t['teachers'], fn ($x) => $x['classroom_id'] === $room['id']))[0] ?? null;
        array_unshift($t['statuses'], ['id' => $nextId++, 'school_id' => $room['school_id'], 'classroom_id' => $room['id'],
            'code' => 'line_up', 'note' => '', 'at' => "$today 08:00:00", 'by' => $teacher['id'] ?? null]);
    }

    $t['__rolling'] = true;
    $has = array_flip(array_map(fn ($a) => $a['student_id'], array_filter($t['attendance'], fn ($a) => $a['date'] === $today)));
    foreach ($t['students'] as $st) {
        if (!isset($has[$st['id']])) {
            // DEMO_MODE: realistic random day · real use: an empty daily sheet the teacher fills in
            demo_mode() ? seed_student_daily($t, $st, $today, $nextId) : seed_student_blank_day($t, $st, $today, $nextId);
        }
    }
    unset($t['__rolling']);
    if (demo_mode()) {
        seed_pickups_day($t, $today, $nextId); // sample pickup requests are demo data only
    }

    $state['seedDate'] = $today;
    mk_log('Seed', 'seed_roll_day END', ['nextId' => $nextId]);
}

/** Calendar, notifications and settings for one school (cameras: seed_cameras()). */
function seed_school_records(array &$t, array $school, array $rooms, int &$nextId): void
{
    $sid = $school['id'];
    $day = fn (int $n) => date('Y-m-d', strtotime("+$n day"));

    foreach ([
        [2, '09:00', '🏮', 'กิจกรรมวันลอยกระทง', 'ชุดไทย', 'กระทงใบตองขนาดเล็ก', '15:30'],
        [5, '08:30', '🚌', 'ทัศนศึกษาสวนสัตว์', 'เสื้อพละ กางเกงวอร์ม', 'กระติกน้ำ หมวก ยาประจำตัว', '16:00'],
        [9, '09:30', '🦷', 'ตรวจสุขภาพฟันประจำภาคเรียน', 'ชุดนักเรียน', 'แปรงสีฟัน', '15:30'],
        [14, '13:30', '👨‍👩‍👧', 'ประชุมผู้ปกครองประจำภาคเรียน', 'ชุดนักเรียน', '-', '15:00'],
        [21, '08:00', '🏅', 'กีฬาสีอนุบาล', 'เสื้อสีประจำทีม', 'ผ้าเช็ดหน้า ขวดน้ำ', '14:30'],
    ] as [$n, $time, $emoji, $title, $dress, $bring, $dismiss]) {
        $t['calendarEvents'][] = ['id' => $nextId++, 'school_id' => $sid, 'date' => $day($n), 'time' => $time, 'emoji' => $emoji,
            'title' => $title, 'dress' => $dress, 'bring' => $bring, 'dismiss' => $dismiss];
    }

    $now = date('Y-m-d');
    $notify = function (string $type, string $title, string $body, string $time, ?int $cid = null, ?int $stuId = null) use (&$nextId, $sid, $now) {
        return ['id' => $nextId++, 'school_id' => $sid, 'classroom_id' => $cid, 'student_id' => $stuId,
            'type' => $type, 'title' => $title, 'body' => $body, 'at' => "$now $time:00"];
    };
    $t['notifications'][] = $notify('announcement', 'ประกาศหยุดเรียนวันปิยมหาราช', 'โรงเรียนหยุดทำการเรียนการสอนในวันที่ 23 ตุลาคม', '07:30');
    $t['notifications'][] = $notify('event', 'เชิญร่วมงานวันลอยกระทง', 'ขอเชิญผู้ปกครองร่วมชมการแสดงของเด็ก ๆ เวลา 09:00 น.', '08:15');
    $t['notifications'][] = $notify('alert', 'ตรวจสุขภาพฟันประจำภาคเรียน', 'กรุณาเตรียมแปรงสีฟันให้บุตรหลานในวันตรวจ', '09:00');
    if ($rooms) {
        $t['notifications'][] = $notify('announcement', $rooms[0]['name'] . ' ใส่ชุดพละพรุ่งนี้', 'พรุ่งนี้มีกิจกรรมกีฬา กรุณาให้น้องใส่ชุดพละมาโรงเรียน', '10:40', $rooms[0]['id']);
    }

    $t['settings'][] = ['id' => $nextId++, 'school_id' => $sid, 'phone' => '043-' . (500000 + $sid * 1111),
        'address' => 'ตำบลตัวอย่าง อำเภอเมือง จังหวัด' . $school['province'], 'openTime' => '07:30', 'closeTime' => '16:00',
        'motto' => 'เด็กดี มีวินัย ใฝ่เรียนรู้'];
}

/** Parent ↔ teacher conversations (น้องต้น as in the brief + one thread per classroom). */
function seed_messages(array &$t, array $classroomsById, array $teachersByRoom, int &$nextId): void
{
    $today = today();
    $firstPerRoom = [];
    foreach ($t['students'] as $st) {
        $firstPerRoom[$st['classroom_id']] ??= $st;
    }

    foreach ($firstPerRoom as $cid => $st) {
        $teacher = $teachersByRoom[$cid][0]['nickname'] ?? 'คุณครู';
        $parent = 'คุณ' . ($st['parentRelation'] ?? 'แม่');
        $nick = $st['nickname'];
        $thread = $st['id'] === 1 ? [
            ['parent', $parent, 'สวัสดีค่ะคุณครู วันนี้น้องต้นทานอาหารได้ดีไหมคะ?', '12:40'],
            ['teacher', $teacher, 'สวัสดีค่ะ วันนี้น้องทานข้าวได้ประมาณครึ่งจานค่ะ แต่ทานผลไม้หมดเลยค่ะ 🍌', '12:46'],
            ['parent', $parent, 'ขอบคุณค่ะ ตอนเย็นจะให้ทานเพิ่มนะคะ', '12:48'],
            ['teacher', $teacher, 'ได้เลยค่ะ ตอนนี้น้องนอนหลับสบายแล้วนะคะ 😴', '12:55'],
        ] : [
            ['parent', $parent, "วันนี้{$nick}ต้องเตรียมอะไรไปโรงเรียนไหมคะ?", '07:55'],
            ['teacher', $teacher, 'พรุ่งนี้ให้น้องใส่ชุดพละมานะคะ วันนี้ไม่ต้องเตรียมอะไรเพิ่มค่ะ', '08:20'],
        ];
        foreach ($thread as [$from, $sender, $text, $time]) {
            $t['messages'][] = ['id' => $nextId++, 'school_id' => $st['school_id'], 'classroom_id' => $cid, 'student_id' => $st['id'],
                'from' => $from, 'sender' => $sender, 'text' => $text, 'at' => "$today $time:00"];
        }
    }
}

/** Build user accounts from Package C's teacher / parent accounts for school #1. */
function seed_users_from_package_c(int &$id): array
{
    $c = mock_data();
    $users = [];
    foreach ($c['teacherAccounts'] as $a) {
        $users[] = ['id' => $id++, 'name' => $a['name'], 'phone' => $a['phone'], 'pin' => $a['pin'], 'role' => 'teacher',
            'school_id' => 1, 'classroom_id' => $a['classroom_id'], 'student_id' => null, 'teacher_id' => $a['teacher_id']];
    }
    foreach ($c['parentAccounts'] as $a) {
        $child = null;
        foreach ($c['students'] as $s) {
            if ($s['id'] === $a['student_id']) {
                $child = $s;
            }
        }
        $users[] = ['id' => $id++, 'name' => $a['name'], 'phone' => $a['phone'], 'pin' => $a['pin'], 'role' => 'parent',
            'school_id' => 1, 'classroom_id' => $child['classroom_id'], 'student_id' => $a['student_id'], 'teacher_id' => null,
            'relation' => $a['relation']];
    }
    return $users;
}

/* ---------------------------------------------------------------------------
 * CCTV
 * ------------------------------------------------------------------------ */

/**
 * Camera layout of a demo school (TP-Link Tapo C200C): one camera per classroom
 * (CAM-01..) + playground + entrance. 'room' = classroom index, null = common area.
 * Model / vendor are plain data, so other brands (Hikvision, Dahua, any RTSP camera)
 * only need different values — permissions and UI do not change.
 */
function cctv_school_cameras(array $statusOverrides, int $roomCount = 3): array
{
    $cameras = [];
    $notes = ['มุมกว้างหน้าห้องเรียนและมุมของเล่น', 'มุมกว้างหน้าห้องเรียนและโต๊ะกิจกรรม', 'มุมกว้างหน้าห้องเรียนและมุมหนังสือ', 'มุมกว้างหน้าห้องเรียนและมุมดนตรี'];
    for ($i = 0; $i < $roomCount; $i++) {
        $cameras[] = ['room' => $i, 'scene' => 'classroom', 'location' => 'อาคารอนุบาล ชั้น ' . ($i < 2 ? 1 : 2), 'description' => $notes[$i % 4]];
    }
    $cameras[] = ['room' => null, 'scene' => 'playground', 'name' => 'กล้องสนามเด็กเล่น', 'location' => 'สนามเด็กเล่น', 'description' => 'ลานกิจกรรมกลางแจ้ง ชิงช้าและสไลเดอร์'];
    $cameras[] = ['room' => null, 'scene' => 'gate', 'name' => 'กล้องทางเข้าโรงเรียน', 'location' => 'ทางเข้าโรงเรียน', 'description' => 'ประตูหน้าโรงเรียน จุดรับ–ส่งนักเรียน'];

    return array_map(function ($c, $i) use ($statusOverrides) {
        $code = sprintf('CAM-%02d', $i + 1);
        return $c + [
            'code'         => $code,
            'status'       => $statusOverrides[$code] ?? 'online',
            'vendor'       => 'TP-Link',
            'camera_model' => 'Tapo C200C',
            'stream_type'  => 'mock', // → 'hls' / 'webrtc' once a media server is in place
            'stream_url'   => '',     // HLS .m3u8 or WebRTC WHEP URL from the media server
            'rtsp_url'     => '',     // server-side only, never sent to the browser
            'is_active'    => true,
        ];
    }, $cameras, array_keys($cameras));
}

/** Camera rows + cameraPermissions rows from the package's cctv-data.php / cctv-permissions.php. */
function seed_cameras(array &$t, int &$nextId): void
{
    if (!function_exists('pkg_cctv_cameras')) {
        return;
    }
    $policy = require MK_APP . '/config/cctv-permissions.php';
    $id = 1;
    foreach (pkg_cctv_cameras() as $schoolId => $cameras) {
        $rooms = array_values(array_filter($t['classrooms'], fn ($r) => $r['school_id'] === $schoolId));
        foreach ($cameras as $def) {
            $room = $def['room'] !== null ? ($rooms[$def['room']] ?? null) : null;
            $camera = [
                'id' => $id++, 'school_id' => $schoolId, 'classroom_id' => $room['id'] ?? null,
                'code' => $def['code'], 'name' => $def['name'] ?? 'กล้องหน้าห้อง' . ($room['name'] ?? ''),
                'location' => $def['location'], 'description' => $def['description'], 'scene' => $def['scene'],
                'vendor' => $def['vendor'], 'camera_model' => $def['camera_model'], 'stream_type' => $def['stream_type'],
                'stream_url' => $def['stream_url'], 'rtsp_url' => $def['rtsp_url'], 'status' => $def['status'], 'is_active' => $def['is_active'],
            ];
            $t['cameras'][] = $camera;

            foreach ($policy as $role => $rule) {
                $allowed = $room ? $rule['ownClassroom'] : in_array($def['scene'], $rule['commonAreas'], true);
                if ($allowed) {
                    $t['cameraPermissions'][] = ['id' => $nextId++, 'school_id' => $schoolId, 'camera_id' => $camera['id'],
                        'role' => $role, 'classroom_id' => $camera['classroom_id'], 'can_view' => true];
                }
            }
        }
    }
}

/* ---------------------------------------------------------------------------
 * รับ-ส่ง (pickups) — mock requests so every status can be tried in the demo
 * ------------------------------------------------------------------------ */

/**
 * Today's mock pickups (new flow — no ETA): per classroom, 4 children WITHOUT a demo parent account get
 * one request each in pending / preparing / ready_for_pickup / completed. The multi-child demo parent's
 * first child is ready_for_pickup (the second has no request, so "กำลังไปรับลูก" can be tried). Children
 * of the other demo parents get none. Times are relative to the seed time.
 */
function seed_pickups_day(array &$t, string $date, int &$nextId): void
{
    if (!has_feature('pickup')) {
        return;
    }
    mk_log('Seed', 'seed_pickups_day START', ['date' => $date]);
    $t['pickups'] ??= [];
    $demoChildren = array_flip(array_column($t['parentStudents'] ?? [], 'student_id'));
    $teacherUsers = [];
    foreach ($t['users'] as $u) {
        if ($u['role'] === 'teacher' && $u['classroom_id']) {
            $teacherUsers[$u['classroom_id']] ??= $u;
        }
    }
    $now = time();
    $at = fn (?int $minutesAgo) => $minutesAgo === null ? null : date('Y-m-d H:i:s', $now - $minutesAgo * 60);
    $row = function (array $s, string $status, int $req, ?int $prep, ?int $ready, ?int $done, string $parentName, ?int $parentId) use (&$nextId, $date, $at, $teacherUsers): array {
        $teacher = $teacherUsers[$s['classroom_id']] ?? null;
        return ['id' => $nextId++, 'school_id' => $s['school_id'], 'classroom_id' => $s['classroom_id'], 'student_id' => $s['id'],
            'date' => $date, 'status' => $status, 'parent_id' => $parentId, 'parent_name' => $parentName,
            'requested_at' => $at($req), 'preparing_at' => $at($prep), 'ready_at' => $at($ready), 'completed_at' => $at($done),
            'completed_by' => $done === null ? null : ($teacher['id'] ?? null),
            'completed_by_name' => $done === null ? null : ($teacher['name'] ?? 'คุณครู')];
    };
    // [status, requested ago, preparing ago, ready ago, completed ago] (minutes)
    $plan = [['pending', 3, null, null, null], ['preparing', 8, 2, null, null], ['ready_for_pickup', 12, 6, 1, null], ['completed', 45, 33, 30, 25]];
    $added = 0;
    foreach ($t['classrooms'] as $room) {
        $kids = array_values(array_filter($t['students'], fn ($s) => $s['classroom_id'] === $room['id'] && !isset($demoChildren[$s['id']])));
        foreach ($plan as $i => [$status, $req, $prep, $ready, $done]) {
            if ($s = $kids[$i * 3] ?? null) { // spread over the class list
                $t['pickups'][] = $row($s, $status, $req, $prep, $ready, $done, 'คุณ' . ($s['parentRelation'] ?? 'แม่'), null);
                $added++;
            }
        }
    }
    foreach ($t['users'] as $u) { // multi-child demo parent: first child already at the pickup point
        if ($u['role'] === 'parent' && count($u['children'] ?? []) > 1) {
            $first = array_values(array_filter($t['students'], fn ($s) => $s['id'] === $u['children'][0]))[0] ?? null;
            if ($first) {
                $t['pickups'][] = $row($first, 'ready_for_pickup', 15, 9, 2, null, 'คุณ' . ($u['relation'] ?? 'แม่'), $u['id']);
                $added++;
            }
        }
    }
    mk_log('Seed', 'seed_pickups_day END', ['added' => $added]);
}

/**
 * ธนาคารความดีขับขี่ปลอดภัย: safe-driving stars for every parent account over the last ~6 weeks
 * (this month + last month), so the monthly parent ranking has data. Row scope = the first child.
 */
function seed_parent_stars(array &$t, array $teachersByRoom, int &$nextId): void
{
    $reasons = mk_catalog()['parentStarReasons'];
    $studentsById = array_column($t['students'], null, 'id');
    foreach ($t['users'] as $u) {
        if ($u['role'] !== 'parent') {
            continue;
        }
        $childId = $u['children'][0] ?? $u['student_id'];
        $child = $studentsById[$childId] ?? null;
        if (!$child) {
            continue;
        }
        $teacher = $teachersByRoom[$child['classroom_id']][0] ?? null;
        $n = 3 + mk_rand($u['id'], 'pstars') % 6;
        for ($i = 0; $i < $n; $i++) {
            $reason = $reasons[mk_rand($u['id'], 'preason', $i) % count($reasons)];
            $daysAgo = mk_rand($u['id'], 'pday', $i) % 42;
            $t['stars'][] = ['id' => $nextId++, 'school_id' => $child['school_id'], 'classroom_id' => $child['classroom_id'],
                'student_id' => $child['id'], 'recipient' => 'parent', 'parent_id' => $u['id'],
                'date' => date('Y-m-d', strtotime("-$daysAgo day")), 'points' => $reason['points'], 'reason' => $reason['reason'],
                'by' => $teacher['id'] ?? null];
        }
    }
}

/** parentStudents: one row per parent ↔ child link (the multi-child demo parent has two). */
function seed_parent_students(array &$t, int &$nextId): void
{
    $t['parentStudents'] = [];
    foreach ($t['users'] as $u) {
        if ($u['role'] !== 'parent') {
            continue;
        }
        foreach ($u['children'] ?? [$u['student_id']] as $sid) {
            $t['parentStudents'][] = ['id' => $nextId++, 'school_id' => $u['school_id'], 'user_id' => $u['id'], 'student_id' => $sid,
                'relation' => $u['relation'] ?? 'แม่'];
        }
    }
}

/**
 * Demo in-app notifications: what today's pickups would have produced + a few read / unread items
 * for every demo teacher and parent, so each badge starts with a meaningful unread count.
 */
function seed_notifications(array &$t, int &$nextId): void
{
    if (!has_feature('inAppNotifications')) {
        return;
    }
    $t['userNotifications'] = [];
    $students = array_column($t['students'], null, 'id');
    $day = today();
    foreach ($t['pickups'] ?? [] as $p) {
        $kid = $students[$p['student_id']]['nickname'] ?? '';
        foreach (classroom_teacher_users($t['users'], $p['classroom_id']) as $teacher) {
            $t['userNotifications'][] = notification_row($nextId++, $teacher, 'pickup', '🔵 ผู้ปกครองกำลังเดินทางมารับ' . $kid,
                'กรุณาเตรียม' . $kid . 'กลับบ้าน', 'pickup', $p['id'], $p['requested_at'], $p['status'] === 'pending' ? null : $p['preparing_at']);
        }
        foreach (student_parent_users($t, $p['student_id']) as $parent) {
            foreach ([['preparing', 'preparing_at', '🟡 เตรียมกลับบ้าน · ', 'ครูกำลังเตรียม' . $kid . 'และพาไปยังจุดรับ-ส่ง'],
                      ['ready_for_pickup', 'ready_at', '🟢 ถึงจุดรับส่งแล้ว · ', $kid . 'มาถึงจุดรับ-ส่งแล้ว ผู้ปกครองสามารถมารับได้']] as [$step, $col, $title, $msg]) {
                if ($p[$col]) {
                    $t['userNotifications'][] = notification_row($nextId++, $parent, 'pickup', $title . $kid, $msg, 'pickup', $p['id'], $p[$col], $step === 'preparing' ? $p['ready_at'] : null);
                }
            }
        }
    }
    foreach ($t['users'] as $u) {
        if (!in_array($u['role'], ['teacher', 'parent'], true)) {
            continue;
        }
        $t['userNotifications'][] = notification_row($nextId++, $u, 'announcement', '📢 ประชุมผู้ปกครองวันศุกร์นี้',
            'ขอเชิญผู้ปกครองร่วมประชุมเวลา 15:00 น. ที่ห้องประชุมโรงเรียน', null, null, "$day 08:10:00", null);
        $t['userNotifications'][] = notification_row($nextId++, $u, 'activity', '🎨 อัปเดตกิจกรรมวันนี้',
            'มีรูปภาพกิจกรรมศิลปะใหม่ของห้องเรียน', null, null, date('Y-m-d', strtotime('-1 day')) . ' 14:30:00', date('Y-m-d', strtotime('-1 day')) . ' 16:00:00');
    }
}
