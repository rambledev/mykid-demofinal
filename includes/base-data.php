<?php
/**
 * Base mock data (originally Package C's) — classrooms, students, statuses, activity icons.
 *
 * Every collection is a flat list of records with an `id` and foreign keys such as
 * `classroom_id` / `student_id`, shaped like database rows so it can be swapped for real
 * queries later.
 *
 * Pages must NOT read these lists directly — go through includes/data-filter.php, which
 * applies the logged-in user's permission scope (USER → ROLE → SCOPE → DATA).
 *
 * All names are fictional.
 */
declare(strict_types=1);

function mock_data(): array
{
    static $data = null;
    if ($data !== null) {
        return $data;
    }

    $school = [
        'id'        => 1,
        'name'      => 'โรงเรียนอนุบาลโพนสูง',
        'shortName' => 'อนุบาลโพนสูง',
        'emoji'     => '🏫',
    ];

    $classrooms = [
        ['id' => 1, 'name' => 'อนุบาล 1', 'emoji' => '🐣', 'color' => '#FFF4CC', 'capacity' => 20],
        ['id' => 2, 'name' => 'อนุบาล 2', 'emoji' => '🐰', 'color' => '#FFEAF2', 'capacity' => 20],
        ['id' => 3, 'name' => 'อนุบาล 3', 'emoji' => '🦁', 'color' => '#DFF4FF', 'capacity' => 20],
    ];

    // Homeroom teachers: อนุบาล 1 = 2, อนุบาล 2 = 1, อนุบาล 3 = 2 (5 in total).
    $teachers = [
        ['id' => 1, 'classroom_id' => 1, 'nickname' => 'ครูมะลิ',   'fullName' => 'นางสาวมะลิ ดอกแก้ว',     'position' => 'ครูประจำชั้น', 'emoji' => '👩‍🏫'],
        ['id' => 2, 'classroom_id' => 2, 'nickname' => 'ครูใบเตย',  'fullName' => 'นางใบเตย บุญประคอง',     'position' => 'ครูประจำชั้น', 'emoji' => '👩‍🏫'],
        ['id' => 3, 'classroom_id' => 3, 'nickname' => 'ครูน้ำ',    'fullName' => 'นางสาวน้ำฝน แสงจันทร์',   'position' => 'ครูประจำชั้น', 'emoji' => '👩‍🏫'],
        ['id' => 4, 'classroom_id' => 1, 'nickname' => 'ครูวรรณา', 'fullName' => 'นางวรรณา ศรีประเสริฐ',    'position' => 'ครูประจำชั้น', 'emoji' => '👩‍🏫'],
        ['id' => 5, 'classroom_id' => 3, 'nickname' => 'ครูกมลา',  'fullName' => 'นางกมลา ทองมี',          'position' => 'ครูประจำชั้น', 'emoji' => '👩‍🏫'],
    ];

    // [nickname, full name, gender (m/f), parent name] — 20 per classroom, in classroom order.
    $studentRows = [
        // อนุบาล 1
        ['น้องต้น', 'ด.ช.ธนกฤต ใจดี', 'm', 'นางสุภาพร ใจดี'],
        ['น้องน้ำ', 'ด.ญ.ณัฐนิชา รักเรียน', 'f', 'นายวิทยา รักเรียน'],
        ['น้องข้าวหอม', 'ด.ญ.ปุณิกา สุขสันต์', 'f', 'นายสมชาย สุขสันต์'],
        ['น้องใบเตย', 'ด.ญ.ชญาดา ศรีสุข', 'f', 'นางกมลวรรณ ศรีสุข'],
        ['น้องภูผา', 'ด.ช.ภูผา แสงทอง', 'm', 'นายวีระพงษ์ แสงทอง'],
        ['น้องข้าวปั้น', 'ด.ช.ปัณณวิชญ์ มีสุข', 'm', 'นางจันทร์เพ็ญ มีสุข'],
        ['น้องมะปราง', 'ด.ญ.ปวริศา บุญมา', 'f', 'นางสาวรัตนา บุญมา'],
        ['น้องไออุ่น', 'ด.ญ.อรุณรัตน์ ทองดี', 'f', 'นางพรทิพย์ ทองดี'],
        ['น้องธันวา', 'ด.ช.ธันวา พูลผล', 'm', 'นายประเสริฐ พูลผล'],
        ['น้องพราว', 'ด.ญ.พิชชาภา แก้วใส', 'f', 'นางนันทนา แก้วใส'],
        ['น้องภูมิ', 'ด.ช.ภูมิพัฒน์ สายสุข', 'm', 'นางอรทัย สายสุข'],
        ['น้องชมพู่', 'ด.ญ.ชนิดาภา ดวงดี', 'f', 'นางศิริพร ดวงดี'],
        ['น้องกัปตัน', 'ด.ช.กฤตภาส ชัยมงคล', 'm', 'นายอนุชา ชัยมงคล'],
        ['น้องปิ่น', 'ด.ญ.ปิ่นมณี ศรีงาม', 'f', 'นางเยาวลักษณ์ ศรีงาม'],
        ['น้องข้าวกล้า', 'ด.ช.กล้าณรงค์ นาดี', 'm', 'นางบุษบา นาดี'],
        ['น้องน้ำใส', 'ด.ญ.ธารารัตน์ ใสสะอาด', 'f', 'นางมณีรัตน์ ใสสะอาด'],
        ['น้องตะวัน', 'ด.ช.ตะวัน รุ่งเรือง', 'm', 'นายสุรเชษฐ์ รุ่งเรือง'],
        ['น้องแพรวา', 'ด.ญ.แพรวา พรมมา', 'f', 'นางลำดวน พรมมา'],
        ['น้องคิน', 'ด.ช.คินน์ ทรัพย์มาก', 'm', 'นางสาวปิยะนุช ทรัพย์มาก'],
        ['น้องมะลิ', 'ด.ญ.มลิวัลย์ หอมจันทร์', 'f', 'นางบัวผัน หอมจันทร์'],
        // อนุบาล 2
        ['น้องภู', 'ด.ช.ภูริ ศรีสวัสดิ์', 'm', 'นางอัญชลี ศรีสวัสดิ์'],
        ['น้องขวัญข้าว', 'ด.ญ.ขวัญชนก นามวงศ์', 'f', 'นายบุญเลิศ นามวงศ์'],
        ['น้องปลื้ม', 'ด.ช.ปลื้มปิติ ใจงาม', 'm', 'นางสุนิสา ใจงาม'],
        ['น้องใบบัว', 'ด.ญ.บัวชมพู คำแสน', 'f', 'นางเพ็ญศรี คำแสน'],
        ['น้องไทม์', 'ด.ช.ธีรภัทร วงศ์ใหญ่', 'm', 'นางดวงใจ วงศ์ใหญ่'],
        ['น้องน้ำหวาน', 'ด.ญ.วรินทร ทองคำ', 'f', 'นางสมพร ทองคำ'],
        ['น้องธาม', 'ด.ช.ธามม์ บุญเรือง', 'm', 'นายชาญวิทย์ บุญเรือง'],
        ['น้องข้าวฟ่าง', 'ด.ญ.ฟ้าใส มั่นคง', 'f', 'นางรุ่งนภา มั่นคง'],
        ['น้องปุณณ์', 'ด.ช.ปุณณ์ เจริญสุข', 'm', 'นางวาสนา เจริญสุข'],
        ['น้องส้มโอ', 'ด.ญ.โอบขวัญ สีดา', 'f', 'นางสาวจิราพร สีดา'],
        ['น้องเพชร', 'ด.ช.เพชรกล้า ภูมิใจ', 'm', 'นายสมศักดิ์ ภูมิใจ'],
        ['น้องน้ำผึ้ง', 'ด.ญ.ผึ้งหวาน ไชยมาตร', 'f', 'นางทองใบ ไชยมาตร'],
        ['น้องไผ่', 'ด.ช.ไผ่ทอง ศรีบุญ', 'm', 'นางอำไพ ศรีบุญ'],
        ['น้องลูกตาล', 'ด.ญ.ตาลหวาน สมบูรณ์', 'f', 'นางสุดารัตน์ สมบูรณ์'],
        ['น้องกันต์', 'ด.ช.กันตภณ พลอยงาม', 'm', 'นายณรงค์ พลอยงาม'],
        ['น้องใบข้าว', 'ด.ญ.ข้าวขวัญ นาคา', 'f', 'นางสายสุนีย์ นาคา'],
        ['น้องภูริ', 'ด.ช.ภูริณัฐ คงมั่น', 'm', 'นางจารุวรรณ คงมั่น'],
        ['น้องมีนา', 'ด.ญ.มีนรดา แสนดี', 'f', 'นางปราณี แสนดี'],
        ['น้องตั้งใจ', 'ด.ช.ตั้งใจ เพียรดี', 'm', 'นายวิชัย เพียรดี'],
        ['น้องดาว', 'ด.ญ.ดาวประกาย ฟ้างาม', 'f', 'นางนงลักษณ์ ฟ้างาม'],
        // อนุบาล 3
        ['น้องปุยฝ้าย', 'ด.ญ.ฝ้ายคำ บุญส่ง', 'f', 'นางสร้อยทิพย์ บุญส่ง'],
        ['น้องตฤณ', 'ด.ช.ตฤณ อินทร์แก้ว', 'm', 'นางกาญจนา อินทร์แก้ว'],
        ['น้องอิ่มบุญ', 'ด.ญ.บุญญิสา อิ่มใจ', 'f', 'นายสมบัติ อิ่มใจ'],
        ['น้องพายุ', 'ด.ช.พายุ ทะเลทอง', 'm', 'นางวันเพ็ญ ทะเลทอง'],
        ['น้องชบา', 'ด.ญ.ชบาไพร สุขใจ', 'f', 'นางละออ สุขใจ'],
        ['น้องภาคิน', 'ด.ช.ภาคิน ศรีเมือง', 'm', 'นายเกียรติศักดิ์ ศรีเมือง'],
        ['น้องมะนาว', 'ด.ญ.นาวินดา ชื่นชม', 'f', 'นางสาวพัชรี ชื่นชม'],
        ['น้องซันนี่', 'ด.ช.สันติสุข แดงงาม', 'm', 'นางอุไรวรรณ แดงงาม'],
        ['น้องเอวา', 'ด.ญ.เอวิกา ทองสุข', 'f', 'นางมาลี ทองสุข'],
        ['น้องข้าวโอ๊ต', 'ด.ช.โอฬาร นาคสุข', 'm', 'นางสุวรรณี นาคสุข'],
        ['น้องแก้มใส', 'ด.ญ.แก้มใส บุญยืน', 'f', 'นางเรณู บุญยืน'],
        ['น้องปัณณ์', 'ด.ช.ปัณณธร มณีวงศ์', 'm', 'นายธนพล มณีวงศ์'],
        ['น้องน้ำฝน', 'ด.ญ.ฝนทิพย์ คำดี', 'f', 'นางบุญมี คำดี'],
        ['น้องเมฆ', 'ด.ช.เมฆา ฟ้าใส', 'm', 'นางศรีสุดา ฟ้าใส'],
        ['น้องทับทิม', 'ด.ญ.ทับทิม จันทร์งาม', 'f', 'นางพัชราภรณ์ จันทร์งาม'],
        ['น้องภีม', 'ด.ช.ภีมพล สิงห์ทอง', 'm', 'นายสุริยา สิงห์ทอง'],
        ['น้องใบหม่อน', 'ด.ญ.หม่อนไหม ศรีวิไล', 'f', 'นางทัศนีย์ ศรีวิไล'],
        ['น้องณภัทร', 'ด.ช.ณภัทร ใจเย็น', 'm', 'นางอรอนงค์ ใจเย็น'],
        ['น้องลูกแก้ว', 'ด.ญ.แก้วกานดา รัตนพันธ์', 'f', 'นางเกศินี รัตนพันธ์'],
        ['น้องไอติม', 'ด.ช.ติณณ์ หวานใจ', 'm', 'นางสาวอริสา หวานใจ'],
    ];

    // Cartoon avatar palette — combined per student to give each child a unique look.
    $skins  = ['#FFDBC0', '#F6C9A3', '#EDB892', '#FCE3D0'];
    $hairs  = ['#3B2A20', '#5A3A26', '#2B2B2B', '#7A4B2A'];
    $bgs    = ['#FFE9A8', '#BFE7FF', '#FFD6E7', '#D4F5DF', '#E6DBFF', '#FFE1C7'];
    $shirts = ['#7CC6F2', '#FF9EC0', '#FFC94D', '#8FD9A8', '#B79CFF', '#FF9F7A'];

    $students = [];
    foreach ($studentRows as $i => [$nickname, $fullName, $gender, $parentName]) {
        $classroom = $classrooms[intdiv($i, 20)];
        $isGirl = $gender === 'f';
        $students[] = [
            'id'             => $i + 1,
            'code'           => sprintf('MK%03d', $i + 1),
            'name'           => $fullName,
            'nickname'       => $nickname,
            'gender'         => $gender,
            'classroom'      => $classroom['name'],
            'classroom_id'   => $classroom['id'],
            'parentName'     => $parentName,
            'parentRelation' => str_starts_with($parentName, 'นาย') ? 'พ่อ' : 'แม่',
            'avatar'         => [
                'skin'  => $skins[$i % 4],
                'hair'  => $hairs[($i * 3) % 4],
                'bg'    => $bgs[$i % 6],
                'shirt' => $shirts[($i * 5) % 6],
                'style' => $isGirl ? ($i % 2 ? 'bob' : 'pigtails') : ($i % 2 ? 'spiky' : 'short'),
            ],
        ];
    }

    // Classroom-wide statuses (Package C has no automatic or per-child status).
    $statuses = [
        ['code' => 'line_up',  'label' => 'เข้าแถว',        'emoji' => '🚩', 'color' => '#FFF4CC'],
        ['code' => 'study',    'label' => 'เข้าเรียน',       'emoji' => '📚', 'color' => '#DFF4FF'],
        ['code' => 'toilet',   'label' => 'เข้าห้องน้ำ',      'emoji' => '🚻', 'color' => '#E3F6FF'],
        ['code' => 'eat',      'label' => 'ทานอาหาร',       'emoji' => '🍱', 'color' => '#FFE9D6'],
        ['code' => 'rest',     'label' => 'พักกลางวัน',      'emoji' => '🧸', 'color' => '#F1EAFF'],
        ['code' => 'sleep',    'label' => 'นอนหลับ',        'emoji' => '😴', 'color' => '#E6E3FF'],
        ['code' => 'activity', 'label' => 'ทำกิจกรรม',      'emoji' => '🎨', 'color' => '#FFEAF2'],
        ['code' => 'outdoor',  'label' => 'เล่นกลางแจ้ง',    'emoji' => '⚽', 'color' => '#DDF7E6'],
        ['code' => 'pack',     'label' => 'เก็บของ',         'emoji' => '🎒', 'color' => '#FFF1D6'],
        ['code' => 'go_home',  'label' => 'เตรียมกลับบ้าน',  'emoji' => '🏠', 'color' => '#FFE1E1'],
    ];

    // Default daily schedule — one row per activity, bound to a classroom.
    $activities = [
        ['id' => 1,  'classroom_id' => 1, 'time' => '08:00', 'icon' => '🚩', 'title' => 'เข้าแถวหน้าเสาธง',       'detail' => 'เคารพธงชาติ สวดมนต์ และออกกำลังกายยามเช้า'],
        ['id' => 2,  'classroom_id' => 1, 'time' => '09:00', 'icon' => '🔢', 'title' => 'เรียนรู้ตัวเลข',          'detail' => 'นับเลข 1–10 ผ่านเกมและเพลงสนุก ๆ'],
        ['id' => 3,  'classroom_id' => 1, 'time' => '10:00', 'icon' => '🎨', 'title' => 'กิจกรรมศิลปะ',          'detail' => 'ระบายสีภาพสัตว์น่ารักด้วยสีเทียน'],
        ['id' => 4,  'classroom_id' => 1, 'time' => '11:00', 'icon' => '🍱', 'title' => 'รับประทานอาหารกลางวัน', 'detail' => 'ฝึกล้างมือก่อนทานและตักอาหารด้วยตัวเอง'],
        ['id' => 5,  'classroom_id' => 1, 'time' => '12:00', 'icon' => '😴', 'title' => 'นอนกลางวัน',            'detail' => 'พักผ่อนให้เพียงพอ พร้อมสำหรับช่วงบ่าย'],
        ['id' => 6,  'classroom_id' => 1, 'time' => '14:00', 'icon' => '⚽', 'title' => 'กิจกรรมกลางแจ้ง',        'detail' => 'เล่นเครื่องเล่นสนามและเกมเก็บลูกบอล'],

        ['id' => 7,  'classroom_id' => 2, 'time' => '08:00', 'icon' => '🚩', 'title' => 'เข้าแถวหน้าเสาธง',       'detail' => 'เคารพธงชาติ สวดมนต์ และออกกำลังกายยามเช้า'],
        ['id' => 8,  'classroom_id' => 2, 'time' => '09:00', 'icon' => '🔤', 'title' => 'เรียนรู้พยัญชนะ ก–ฮ',     'detail' => 'ฝึกออกเสียงพยัญชนะผ่านบัตรคำ'],
        ['id' => 9,  'classroom_id' => 2, 'time' => '10:00', 'icon' => '🎵', 'title' => 'ดนตรีและการเคลื่อนไหว',   'detail' => 'ร้องเพลงและเต้นประกอบจังหวะ'],
        ['id' => 10, 'classroom_id' => 2, 'time' => '11:00', 'icon' => '🍱', 'title' => 'รับประทานอาหารกลางวัน', 'detail' => 'ฝึกมารยาทบนโต๊ะอาหาร'],
        ['id' => 11, 'classroom_id' => 2, 'time' => '12:00', 'icon' => '😴', 'title' => 'นอนกลางวัน',            'detail' => 'พักผ่อนให้เพียงพอ'],
        ['id' => 12, 'classroom_id' => 2, 'time' => '14:00', 'icon' => '⚽', 'title' => 'เล่นกลางแจ้ง',           'detail' => 'เล่นทรายและเครื่องเล่นสนาม'],

        ['id' => 13, 'classroom_id' => 3, 'time' => '08:00', 'icon' => '🚩', 'title' => 'เข้าแถวหน้าเสาธง',       'detail' => 'เคารพธงชาติ สวดมนต์ และออกกำลังกายยามเช้า'],
        ['id' => 14, 'classroom_id' => 3, 'time' => '09:00', 'icon' => '🔤', 'title' => 'ภาษาอังกฤษหรรษา',       'detail' => 'เรียนคำศัพท์ผลไม้ผ่านเพลง'],
        ['id' => 15, 'classroom_id' => 3, 'time' => '10:00', 'icon' => '🔬', 'title' => 'วิทยาศาสตร์น้อย',        'detail' => 'ทดลองจมหรือลอยด้วยของใกล้ตัว'],
        ['id' => 16, 'classroom_id' => 3, 'time' => '11:00', 'icon' => '🍱', 'title' => 'รับประทานอาหารกลางวัน', 'detail' => 'ฝึกเก็บจานด้วยตัวเองหลังทานเสร็จ'],
        ['id' => 17, 'classroom_id' => 3, 'time' => '12:00', 'icon' => '😴', 'title' => 'นอนกลางวัน',            'detail' => 'พักผ่อนให้เพียงพอ'],
        ['id' => 18, 'classroom_id' => 3, 'time' => '14:00', 'icon' => '📖', 'title' => 'เล่านิทาน',               'detail' => 'ฟังนิทานและตอบคำถามจากเรื่อง'],
    ];

    // Icons a teacher can pick when adding an activity.
    $activityIcons = ['🚩', '📚', '🔢', '🔤', '🎨', '🎵', '🔬', '📖', '🧩', '🌱', '🍱', '😴', '⚽', '🧸', '⭐', '🎉'];

    // Meal types (fixed) — the menu items themselves live in $foodMenus per classroom.
    $mealTypes = [
        ['meal' => 'breakfast', 'label' => 'อาหารเช้า',    'icon' => '🍚', 'time' => '07:30', 'color' => '#FFF4CC'],
        ['meal' => 'lunch',     'label' => 'อาหารกลางวัน', 'icon' => '🍱', 'time' => '11:00', 'color' => '#FFE9D6'],
        ['meal' => 'snack',     'label' => 'อาหารว่าง',    'icon' => '🍌', 'time' => '14:30', 'color' => '#FFF6C7'],
        ['meal' => 'milk',      'label' => 'นม',          'icon' => '🥛', 'time' => '14:30', 'color' => '#E9F5FF'],
    ];

    // Today's menu — one row per classroom.
    $foodMenus = [
        ['id' => 1, 'classroom_id' => 1, 'breakfast' => ['ข้าวต้มหมู'],        'lunch' => ['ข้าวผัดไก่', 'แกงจืดเต้าหู้หมูสับ'], 'snack' => ['กล้วยน้ำว้า'],    'milk' => ['นมจืด UHT']],
        ['id' => 2, 'classroom_id' => 2, 'breakfast' => ['โจ๊กหมูใส่ไข่'],      'lunch' => ['ข้าวมันไก่', 'ซุปฟักทอง'],          'snack' => ['ขนมปังลูกเกด'],  'milk' => ['นมจืด UHT']],
        ['id' => 3, 'classroom_id' => 3, 'breakfast' => ['ข้าวไข่เจียวหมูสับ'], 'lunch' => ['ก๋วยเตี๋ยวไก่', 'ผัดผักรวม'],        'snack' => ['แตงโม'],         'milk' => ['นมถั่วเหลือง']],
    ];

    // Starting status history per classroom — times are "today", `by` = teacher id.
    $initialStatuses = [
        ['classroom_id' => 1, 'code' => 'line_up',  'note' => '',            'time' => '08:00', 'by' => 1],
        ['classroom_id' => 1, 'code' => 'study',    'note' => 'คณิตศาสตร์',   'time' => '09:00', 'by' => 1],
        ['classroom_id' => 1, 'code' => 'toilet',   'note' => '',            'time' => '09:50', 'by' => 4],
        ['classroom_id' => 1, 'code' => 'activity', 'note' => 'ศิลปะ',        'time' => '10:30', 'by' => 1],
        ['classroom_id' => 2, 'code' => 'line_up',  'note' => '',            'time' => '08:00', 'by' => 2],
        ['classroom_id' => 2, 'code' => 'study',    'note' => 'ภาษาไทย',      'time' => '09:00', 'by' => 2],
        ['classroom_id' => 3, 'code' => 'line_up',  'note' => '',            'time' => '08:00', 'by' => 3],
        ['classroom_id' => 3, 'code' => 'activity', 'note' => 'วิทยาศาสตร์',   'time' => '10:15', 'by' => 5],
    ];

    /*
     * Demo login accounts (phone + 6-digit PIN). Plain text on purpose — demo only.
     * Scope binding: Teacher → classroom_id, Parent → student_id (→ the child's classroom_id).
     */
    $teacherAccounts = [
        ['id' => 1, 'name' => 'ครูมะลิ',  'phone' => '0812345678', 'pin' => '123456', 'role' => 'teacher', 'classroom_id' => 1, 'teacher_id' => 1],
        ['id' => 2, 'name' => 'ครูใบเตย', 'phone' => '0812345679', 'pin' => '123456', 'role' => 'teacher', 'classroom_id' => 2, 'teacher_id' => 2],
        ['id' => 3, 'name' => 'ครูน้ำ',   'phone' => '0812345680', 'pin' => '123456', 'role' => 'teacher', 'classroom_id' => 3, 'teacher_id' => 3],
    ];
    $parentAccounts = [
        ['id' => 1, 'name' => 'คุณแม่ของน้องต้น',      'relation' => 'แม่', 'phone' => '0898765432', 'pin' => '123456', 'role' => 'parent', 'student_id' => 1],
        ['id' => 2, 'name' => 'คุณพ่อของน้องน้ำ',      'relation' => 'พ่อ', 'phone' => '0898765433', 'pin' => '123456', 'role' => 'parent', 'student_id' => 2],
        ['id' => 3, 'name' => 'คุณแม่ของน้องภู',       'relation' => 'แม่', 'phone' => '0898765434', 'pin' => '123456', 'role' => 'parent', 'student_id' => 21],
        ['id' => 4, 'name' => 'คุณพ่อของน้องขวัญข้าว', 'relation' => 'พ่อ', 'phone' => '0898765435', 'pin' => '123456', 'role' => 'parent', 'student_id' => 22],
        ['id' => 5, 'name' => 'คุณแม่ของน้องปุยฝ้าย',  'relation' => 'แม่', 'phone' => '0898765436', 'pin' => '123456', 'role' => 'parent', 'student_id' => 41],
        ['id' => 6, 'name' => 'คุณพ่อของน้องภาคิน',    'relation' => 'พ่อ', 'phone' => '0898765437', 'pin' => '123456', 'role' => 'parent', 'student_id' => 46],
    ];

    $data = compact(
        'school', 'classrooms', 'teachers', 'students', 'statuses', 'activities', 'activityIcons',
        'mealTypes', 'foodMenus', 'initialStatuses', 'teacherAccounts', 'parentAccounts'
    );
    return $data;
}
