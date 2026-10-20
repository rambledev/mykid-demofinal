<?php
/**
 * Core catalog — fixed lookup lists (roles, classroom statuses, meals, levels, ...).
 * Configuration only: the school's real data (rooms, people, records) lives in the database.
 *
 * School-editable lists (admin → ตั้งค่าโรงเรียน): meals come from the mealTimes table and the
 * ธนาคารความดี presets from starReasons. Until the school's lists are seeded (catalog_seed_school),
 * the built-in defaults below are used, so a fresh install behaves exactly as before.
 */
declare(strict_types=1);

function mk_catalog(): array
{
    static $catalog = null;
    if ($catalog !== null) {
        return $catalog;
    }

    $allMeals = catalog_default_meals();
    $starReasons = catalog_school_list('starReasons');

    $catalog = [
        'roles' => [
            'admin'       => ['label' => 'Admin',       'th' => 'ผู้ดูแลโรงเรียน',  'emoji' => '🛡️', 'theme' => 'admin', 'dir' => 'admin', 'layout' => 'console'],
            'executive'   => ['label' => 'Executive',   'th' => 'ผู้บริหาร',        'emoji' => '💼', 'theme' => 'executive', 'dir' => 'executive', 'layout' => 'console'],
            'teacher'     => ['label' => 'Teacher',     'th' => 'ครู',             'emoji' => '👩‍🏫', 'theme' => 'teacher', 'dir' => 'teacher', 'layout' => 'app'],
            'parent'      => ['label' => 'Parent',      'th' => 'ผู้ปกครอง',        'emoji' => '👨‍👩‍👧', 'theme' => 'parent', 'dir' => 'parent', 'layout' => 'app'],
        ],
        // Classroom-wide statuses the teacher sets during the day.
        'statuses' => [
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
        ],
        // Icons a teacher can pick when adding an activity.
        'activityIcons' => ['🚩', '📚', '🔢', '🔤', '🎨', '🎵', '🔬', '📖', '🧩', '🌱', '🍱', '😴', '⚽', '🧸', '⭐', '🎉'],
        'meals'         => catalog_school_list('mealTimes') ?? array_values(array_filter($allMeals, fn ($m) => in_array($m['meal'], pkg()['meals'], true))),
        // Icons the admin can pick for a meal (mealTimes form).
        'mealIcons'     => ['🍚', '🍱', '🍜', '🥣', '🍌', '🍎', '🥛', '🧃', '🍪', '🥪', '🍉', '🥕'],
        'intake' => [
            ['code' => 'good',   'label' => 'กินได้ดี',       'emoji' => '😋', 'tone' => 'good'],
            ['code' => 'some',   'label' => 'กินได้บางส่วน',   'emoji' => '🙂', 'tone' => 'warning'],
            ['code' => 'little', 'label' => 'กินได้น้อย',      'emoji' => '😐', 'tone' => 'serious'],
            ['code' => 'none',   'label' => 'ไม่รับประทาน',    'emoji' => '🙅', 'tone' => 'critical'],
        ],
        'attendance' => [
            ['code' => 'present', 'label' => 'มาเรียน', 'emoji' => '✅', 'tone' => 'good',     'color' => '#0ca30c'],
            ['code' => 'late',    'label' => 'สาย',    'emoji' => '⏰', 'tone' => 'warning',  'color' => '#fab219'],
            ['code' => 'leave',   'label' => 'ลา',     'emoji' => '📝', 'tone' => 'neutral',  'color' => '#a8a49a'],
            ['code' => 'absent',  'label' => 'ขาด',    'emoji' => '❌', 'tone' => 'critical', 'color' => '#d03b3b'],
        ],
        'health' => [
            ['code' => 'normal', 'label' => 'ปกติ',      'emoji' => '💚', 'tone' => 'good'],
            ['code' => 'watch',  'label' => 'เฝ้าระวัง',   'emoji' => '🟡', 'tone' => 'warning'],
            ['code' => 'sick',   'label' => 'ไม่สบาย',    'emoji' => '🤒', 'tone' => 'critical'],
        ],
        'sleep' => [
            ['code' => 'good',     'label' => 'หลับสบาย',     'emoji' => '😴', 'tone' => 'good'],
            ['code' => 'restless', 'label' => 'หลับไม่สนิท',  'emoji' => '😪', 'tone' => 'warning'],
            ['code' => 'none',     'label' => 'ไม่ได้นอน',    'emoji' => '👀', 'tone' => 'serious'],
            ['code' => 'pending',  'label' => 'ยังไม่ถึงเวลานอน', 'emoji' => '🕐', 'tone' => 'neutral'],
        ],
        'devDomains' => [
            ['code' => 'language', 'label' => 'ภาษา',              'emoji' => '🗣️'],
            ['code' => 'math',     'label' => 'คณิตศาสตร์',         'emoji' => '🔢'],
            ['code' => 'social',   'label' => 'สังคม',              'emoji' => '🤝'],
            ['code' => 'motor',    'label' => 'กล้ามเนื้อ',          'emoji' => '🤸'],
            ['code' => 'creative', 'label' => 'ความคิดสร้างสรรค์',  'emoji' => '💡'],
        ],
        'starReasons'       => $starReasons !== null ? array_values(where($starReasons, 'recipient', 'student')) : catalog_default_star_reasons('student'),
        // ธนาคารความดีขับขี่ปลอดภัย — parents earn stars for safe driving at drop-off / pick-up.
        'parentStarReasons' => $starReasons !== null ? array_values(where($starReasons, 'recipient', 'parent')) : catalog_default_star_reasons('parent'),
        // Which preset list a starReasons row belongs to (student = ความดี, parent = ขับขี่ปลอดภัย).
        'starRecipients' => [
            ['code' => 'student', 'label' => 'ความดีของนักเรียน',  'emoji' => '🧒'],
            ['code' => 'parent',  'label' => 'ขับขี่ปลอดภัย (ผู้ปกครองรับ-ส่ง)', 'emoji' => '🛵'],
        ],
        // ธนาคารความดี categories — every star is credited to the student; 'presets' = its preset list.
        'starCategories' => [
            ['code' => 'good',         'label' => 'ความดีของนักเรียน',          'emoji' => '🧒', 'presets' => 'starReasons'],
            ['code' => 'safe_driving', 'label' => 'ขับขี่ปลอดภัย (ผู้ปกครองรับ-ส่ง)', 'emoji' => '🛵', 'presets' => 'parentStarReasons'],
        ],
        'portfolioCategories' => [
            ['code' => 'art',     'label' => 'ศิลปะ',       'emoji' => '🎨'],
            ['code' => 'language','label' => 'ภาษา',        'emoji' => '✏️'],
            ['code' => 'math',    'label' => 'คณิตศาสตร์',   'emoji' => '🔢'],
            ['code' => 'science', 'label' => 'วิทยาศาสตร์',  'emoji' => '🔬'],
            ['code' => 'music',   'label' => 'ดนตรี',       'emoji' => '🎵'],
        ],
        'notifyTypes' => [
            ['code' => 'announcement', 'label' => 'ประกาศโรงเรียน',    'emoji' => '📢'],
            ['code' => 'event',        'label' => 'กิจกรรม',          'emoji' => '🎉'],
            ['code' => 'teacher',      'label' => 'ข้อความจากครู',     'emoji' => '💬'],
            ['code' => 'alert',        'label' => 'แจ้งเตือนผู้ปกครอง', 'emoji' => '🔔'],
        ],
        // รับ-ส่ง (Package A): pending → preparing → ready_for_pickup → completed (core/pickup.php)
        // label = short status · parent / teacher = explanation shown to that role
        'pickupStatus' => [
            ['code' => 'pending',          'label' => 'กำลังไปรับลูก',     'emoji' => '🔵', 'tone' => 'info',
             'parent' => 'ครูได้รับแจ้งแล้ว กรุณารอการเตรียมนักเรียน', 'teacher' => 'ผู้ปกครองกำลังเดินทางมารับ',
             'explain' => 'ผู้ปกครองแจ้งครูแล้วว่ากำลังเดินทางมารับ'],
            ['code' => 'preparing',        'label' => 'เตรียมกลับบ้าน',     'emoji' => '🟡', 'tone' => 'warning',
             'parent' => 'ครูกำลังเตรียมนักเรียนและพาไปยังจุดรับ-ส่ง', 'teacher' => 'กำลังเตรียมนักเรียนไปจุดรับ-ส่ง',
             'explain' => 'ครูกำลังเตรียมนักเรียนและพาไปยังจุดรับ-ส่ง'],
            ['code' => 'ready_for_pickup', 'label' => 'ถึงจุดรับส่งแล้ว',   'emoji' => '🟢', 'tone' => 'good',
             'parent' => 'นักเรียนมาถึงจุดรับ-ส่งแล้ว ผู้ปกครองสามารถมารับนักเรียนได้', 'teacher' => 'นักเรียนมาถึงจุดรับ-ส่งแล้ว ผู้ปกครองสามารถมารับได้',
             'explain' => 'นักเรียนมาถึงจุดรับ-ส่งแล้ว ผู้ปกครองสามารถมารับนักเรียนได้'],
            ['code' => 'completed',        'label' => 'ส่งมอบนักเรียนแล้ว', 'emoji' => '✅', 'tone' => 'neutral',
             'parent' => 'ครูส่งมอบนักเรียนให้ผู้ปกครองเรียบร้อยแล้ว', 'teacher' => 'ส่งมอบนักเรียนแล้ว',
             'explain' => 'ครูส่งมอบนักเรียนให้ผู้ปกครองเรียบร้อยแล้ว'],
        ],
        'genders' => [
            ['code' => 'm', 'label' => 'ชาย'],
            ['code' => 'f', 'label' => 'หญิง'],
        ],
        'roomColors' => [
            ['code' => '#FFF4CC', 'label' => 'เหลือง'], ['code' => '#FFEAF2', 'label' => 'ชมพู'],
            ['code' => '#DFF4FF', 'label' => 'ฟ้า'], ['code' => '#DDF7E6', 'label' => 'เขียว'],
            ['code' => '#F1EAFF', 'label' => 'ม่วง'],
        ],
    ];
    return $catalog;
}

/** Built-in meal schedule — used until the school's mealTimes are seeded. */
function catalog_default_meals(): array
{
    return [
        ['meal' => 'breakfast', 'label' => 'อาหารเช้า',    'icon' => '🍚', 'time' => '07:30', 'color' => '#FFF4CC'],
        ['meal' => 'lunch',     'label' => 'อาหารกลางวัน', 'icon' => '🍱', 'time' => '11:00', 'color' => '#FFE9D6'],
        ['meal' => 'snack',     'label' => 'อาหารว่าง',    'icon' => '🍌', 'time' => '14:30', 'color' => '#FFF6C7'],
        ['meal' => 'milk',      'label' => 'นม',          'icon' => '🥛', 'time' => '14:30', 'color' => '#E9F5FF'],
        ['meal' => 'fruit',     'label' => 'ผลไม้',        'icon' => '🍎', 'time' => '14:30', 'color' => '#FFE4E4'],
    ];
}

/** Built-in ธนาคารความดี presets — used until the school's starReasons are seeded. */
function catalog_default_star_reasons(?string $recipient = null): array
{
    $all = [
        ['recipient' => 'student', 'reason' => 'ตั้งใจทำกิจกรรม', 'points' => 5],
        ['recipient' => 'student', 'reason' => 'ช่วยเก็บของ',     'points' => 3],
        ['recipient' => 'student', 'reason' => 'กล้าแสดงออก',    'points' => 4],
        ['recipient' => 'student', 'reason' => 'แบ่งปันเพื่อน',    'points' => 2],
        ['recipient' => 'student', 'reason' => 'ทานข้าวหมด',     'points' => 2],
        ['recipient' => 'student', 'reason' => 'นอนกลางวันเรียบร้อย', 'points' => 1],
        ['recipient' => 'parent',  'reason' => 'สวมหมวกกันน็อกทั้งผู้ปกครองและเด็ก', 'points' => 5],
        ['recipient' => 'parent',  'reason' => 'คาดเข็มขัดนิรภัย / ใช้คาร์ซีทให้เด็ก',  'points' => 5],
        ['recipient' => 'parent',  'reason' => 'จอดรถในจุดรับ-ส่งที่กำหนด',          'points' => 3],
        ['recipient' => 'parent',  'reason' => 'ขับขี่ช้าและระมัดระวังในเขตโรงเรียน',  'points' => 3],
        ['recipient' => 'parent',  'reason' => 'ไม่ใช้โทรศัพท์ขณะขับขี่',             'points' => 3],
        ['recipient' => 'parent',  'reason' => 'มารับ-ส่งตรงเวลา',                  'points' => 2],
    ];
    return $recipient === null ? $all : array_values(where($all, 'recipient', $recipient));
}

/** Pastel backgrounds given to new meals in turn. */
const MEAL_COLORS = ['#FFF4CC', '#FFE9D6', '#FFF6C7', '#E9F5FF', '#FFE4E4', '#DDF7E6', '#F1EAFF'];

/**
 * The school's own rows of an editable list (mealTimes / starReasons), or null while the list
 * is not seeded yet (→ built-in defaults). One school per database, so every row is the school's.
 * Meals are ordered by time.
 */
function catalog_school_list(string $table): ?array
{
    $rows = store_rows($table);
    if (!$rows && empty((store_rows('settings')[0] ?? [])[$table . 'Seeded'])) {
        return null;
    }
    if ($table === 'mealTimes') {
        usort($rows, fn ($a, $b) => [$a['time'], $a['id']] <=> [$b['time'], $b['id']]);
    }
    return $rows;
}

/**
 * Copy the built-in defaults into the school's editable lists once (admin opens ตั้งค่าโรงเรียน),
 * so they can be edited / deleted. The settings row remembers it, so an emptied list stays empty.
 */
function catalog_seed_school(int $schoolId): void
{
    $settings = where(store_rows('settings'), 'school_id', $schoolId)[0] ?? null;
    $todo = array_values(array_filter(['mealTimes', 'starReasons'], fn ($t) => $settings && empty($settings[$t . 'Seeded'])));
    if (!$todo) {
        return;
    }
    mk_log('Catalog', 'catalog_seed_school START', ['school' => $schoolId, 'tables' => $todo]);
    store_mutate(function (array &$state) use ($schoolId, $todo) {
        foreach ($todo as $table) {
            if (!where($state['tables'][$table], 'school_id', $schoolId)) {
                $defaults = $table === 'mealTimes'
                    ? array_values(array_filter(catalog_default_meals(), fn ($m) => in_array($m['meal'], pkg()['meals'], true)))
                    : catalog_default_star_reasons();
                foreach ($defaults as $row) {
                    $state['tables'][$table][] = ['id' => $state['nextId']++, 'school_id' => $schoolId] + $row;
                }
            }
            foreach ($state['tables']['settings'] as &$s) {
                if ($s['school_id'] === $schoolId) {
                    $s[$table . 'Seeded'] = true;
                }
            }
            unset($s);
        }
    });
    mk_log('Catalog', 'catalog_seed_school END', ['school' => $schoolId]);
}

/** Find one entry of a catalog list by its code (or meal/reason key). */
function cat_find(string $list, ?string $code): ?array
{
    foreach (mk_catalog()[$list] as $item) {
        if (($item['code'] ?? $item['meal'] ?? null) === $code) {
            return $item;
        }
    }
    return null;
}

function role_meta(string $role): array
{
    return mk_catalog()['roles'][$role];
}

/** [code => label] options for form selects. */
function cat_options(string $list): array
{
    $options = [];
    foreach (mk_catalog()[$list] as $item) {
        $options[$item['code'] ?? $item['meal']] = trim(($item['emoji'] ?? '') . ' ' . $item['label']);
    }
    return $options;
}
