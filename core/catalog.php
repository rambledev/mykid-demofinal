<?php
/**
 * Core catalog — fixed lookup lists (roles, statuses, meals, levels, ...).
 * Classroom statuses and activity icons are reused from Package C's mock data.
 */
declare(strict_types=1);

function mk_catalog(): array
{
    static $catalog = null;
    if ($catalog !== null) {
        return $catalog;
    }

    $allMeals = array_merge(mock_data()['mealTypes'], [
        ['meal' => 'fruit', 'label' => 'ผลไม้', 'icon' => '🍎', 'time' => '14:30', 'color' => '#FFE4E4'],
    ]);

    $catalog = [
        'roles' => [
            'super_admin' => ['label' => 'Super Admin', 'th' => 'ผู้ดูแลระบบกลาง', 'emoji' => '🪐', 'theme' => 'super', 'dir' => 'super-admin', 'layout' => 'console'],
            'admin'       => ['label' => 'Admin',       'th' => 'ผู้ดูแลโรงเรียน',  'emoji' => '🛡️', 'theme' => 'admin', 'dir' => 'admin', 'layout' => 'console'],
            'executive'   => ['label' => 'Executive',   'th' => 'ผู้บริหาร',        'emoji' => '💼', 'theme' => 'executive', 'dir' => 'executive', 'layout' => 'console'],
            'teacher'     => ['label' => 'Teacher',     'th' => 'ครู',             'emoji' => '👩‍🏫', 'theme' => 'teacher', 'dir' => 'teacher', 'layout' => 'app'],
            'parent'      => ['label' => 'Parent',      'th' => 'ผู้ปกครอง',        'emoji' => '👨‍👩‍👧', 'theme' => 'parent', 'dir' => 'parent', 'layout' => 'app'],
        ],
        'statuses'      => mock_data()['statuses'],
        'activityIcons' => mock_data()['activityIcons'],
        'meals'         => array_values(array_filter($allMeals, fn ($m) => in_array($m['meal'], pkg()['meals'], true))),
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
        'starReasons' => [
            ['reason' => 'ตั้งใจทำกิจกรรม', 'points' => 5],
            ['reason' => 'ช่วยเก็บของ',     'points' => 3],
            ['reason' => 'กล้าแสดงออก',    'points' => 4],
            ['reason' => 'แบ่งปันเพื่อน',    'points' => 2],
            ['reason' => 'ทานข้าวหมด',     'points' => 2],
            ['reason' => 'นอนกลางวันเรียบร้อย', 'points' => 1],
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
