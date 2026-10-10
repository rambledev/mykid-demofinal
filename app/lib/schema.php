<?php
/**
 * Core schema — every table, its permission LEVEL and its editable fields.
 *
 * LEVEL says how a row is bound to the scope hierarchy (school → classroom → student):
 *   school_self     the row IS a school               (schools)
 *   school          bound to school_id               (users, calendarEvents, settings)
 *   classroom_self  the row IS a classroom           (classrooms)
 *   classroom       bound to school_id + classroom_id (teachers, activities, foodMenus, statuses, photos)
 *   student_self    the row IS a student             (students)
 *   student         bound to school_id + classroom_id + student_id (attendance, health, sleep, ...)
 *   targeted        school-wide, or narrowed to a classroom / student (notifications)
 *   camera          a CCTV camera — visibility decided by can_view_camera() (core/cctv.php)
 *   media           an image file's metadata — student level when student_id is set (portfolio),
 *                   otherwise classroom level (activity / food images)
 *
 * FIELDS drive the generic form sheets (components.php) and server validation (api).
 * Scope fields (school_id / classroom_id / student_id) are filled or checked by
 * permissions.php — never trusted from the request.
 */
declare(strict_types=1);

function mk_tables(): array
{
    static $tables = null;
    if ($tables !== null) {
        return $tables;
    }

    $tables = [
        'schools' => ['label' => 'โรงเรียน', 'level' => 'school_self', 'fields' => [
            'name'      => ['label' => 'ชื่อโรงเรียน', 'type' => 'text', 'required' => true, 'max' => 80],
            'shortName' => ['label' => 'ชื่อย่อ', 'type' => 'text', 'max' => 40],
            'emoji'     => ['label' => 'สัญลักษณ์', 'type' => 'select', 'options' => ['🏫' => '🏫', '🌈' => '🌈', '⭐' => '⭐', '🌻' => '🌻', '🎈' => '🎈']],
            'province'  => ['label' => 'จังหวัด', 'type' => 'text', 'max' => 40],
            'status'    => ['label' => 'สถานะ', 'type' => 'select', 'options' => ['active' => 'ใช้งาน', 'trial' => 'ทดลองใช้']],
        ]],
        'users' => ['label' => 'ผู้ดูแลโรงเรียน', 'level' => 'school', 'fields' => [
            'name'      => ['label' => 'ชื่อที่แสดง', 'type' => 'text', 'required' => true, 'max' => 60],
            'role'      => ['label' => 'บทบาท', 'type' => 'select', 'required' => true, 'options' => ['admin' => '🛡️ Admin โรงเรียน', 'executive' => '💼 ผู้บริหาร']],
            'school_id' => ['label' => 'โรงเรียน', 'type' => 'school', 'required' => true],
            'phone'     => ['label' => 'เบอร์โทรศัพท์', 'type' => 'phone', 'required' => true],
            'pin'       => ['label' => 'PIN 6 หลัก', 'type' => 'pin', 'required' => true],
        ]],
        'classrooms' => ['label' => 'ห้องเรียน', 'level' => 'classroom_self', 'fields' => [
            'name'     => ['label' => 'ชื่อห้อง', 'type' => 'text', 'required' => true, 'max' => 40],
            'emoji'    => ['label' => 'สัญลักษณ์', 'type' => 'select', 'options' => ['🐣' => '🐣', '🐰' => '🐰', '🦁' => '🦁', '🐼' => '🐼', '🐨' => '🐨', '🦊' => '🦊', '🐧' => '🐧', '🐬' => '🐬', '🦄' => '🦄']],
            'color'    => ['label' => 'สีประจำห้อง', 'type' => 'select', 'options' => 'roomColors'],
            'capacity' => ['label' => 'จำนวนที่รับได้', 'type' => 'number', 'min' => 1, 'max' => 40],
        ]],
        'teachers' => ['label' => 'ครู', 'level' => 'classroom', 'fields' => [
            'nickname'     => ['label' => 'ชื่อที่ใช้เรียก', 'type' => 'text', 'required' => true, 'max' => 40, 'placeholder' => 'เช่น ครูมะลิ'],
            'fullName'     => ['label' => 'ชื่อ-นามสกุล', 'type' => 'text', 'required' => true, 'max' => 80],
            'classroom_id' => ['label' => 'ห้องที่รับผิดชอบ', 'type' => 'classroom', 'required' => true],
            'phone'        => ['label' => 'เบอร์โทรศัพท์', 'type' => 'phone'],
        ]],
        'students' => ['label' => 'นักเรียน', 'level' => 'student_self', 'fields' => [
            'nickname'     => ['label' => 'ชื่อเล่น', 'type' => 'text', 'required' => true, 'max' => 40, 'placeholder' => 'เช่น น้องต้น'],
            'name'         => ['label' => 'ชื่อ-นามสกุล', 'type' => 'text', 'required' => true, 'max' => 80],
            'gender'       => ['label' => 'เพศ', 'type' => 'select', 'options' => 'genders'],
            'classroom_id' => ['label' => 'ห้องเรียน', 'type' => 'classroom', 'required' => true],
            'parentName'   => ['label' => 'ผู้ปกครอง', 'type' => 'text', 'required' => true, 'max' => 80],
        ]],
        'activities' => ['label' => 'กิจกรรม', 'level' => 'classroom', 'fields' => [
            'classroom_id' => ['label' => 'ห้องเรียน', 'type' => 'classroom', 'required' => true],
            'time'         => ['label' => 'เวลา', 'type' => 'time', 'required' => true],
            'title'        => ['label' => 'ชื่อกิจกรรม', 'type' => 'text', 'required' => true, 'max' => 80, 'placeholder' => 'เช่น กิจกรรมเรียนรู้ตัวเลข'],
            'detail'       => ['label' => 'รายละเอียด', 'type' => 'textarea', 'max' => 200],
            'icon'         => ['label' => 'ไอคอน', 'type' => 'emoji', 'options' => 'activityIcons'],
        ]],
        'foodMenus'  => ['label' => 'เมนูอาหาร', 'level' => 'classroom', 'fields' => []],
        'statuses'   => ['label' => 'สถานะห้องเรียน', 'level' => 'classroom', 'fields' => [
            'code' => ['label' => 'สถานะ', 'type' => 'select', 'required' => true, 'options' => 'statuses'],
            'note' => ['label' => 'รายละเอียด', 'type' => 'text', 'max' => 40],
        ]],
        'attendance' => ['label' => 'การมาเรียน', 'level' => 'student', 'fields' => [
            'student_id' => ['label' => 'นักเรียน', 'type' => 'student', 'required' => true],
            'status'     => ['label' => 'สถานะ', 'type' => 'select', 'required' => true, 'options' => 'attendance'],
            'checkIn'    => ['label' => 'เวลามาถึง', 'type' => 'time'],
            'note'       => ['label' => 'หมายเหตุ', 'type' => 'text', 'max' => 80],
        ]],
        'sleepRecords' => ['label' => 'การนอน', 'level' => 'student', 'fields' => [
            'student_id' => ['label' => 'นักเรียน', 'type' => 'student', 'required' => true],
            'start'      => ['label' => 'เริ่มนอน', 'type' => 'time'],
            'end'        => ['label' => 'ตื่นนอน', 'type' => 'time'],
            'quality'    => ['label' => 'สถานะ', 'type' => 'select', 'options' => 'sleep'],
            'note'       => ['label' => 'หมายเหตุครู', 'type' => 'text', 'max' => 120],
        ]],
        'healthRecords' => ['label' => 'สุขภาพ', 'level' => 'student', 'fields' => [
            'student_id'  => ['label' => 'นักเรียน', 'type' => 'student', 'required' => true],
            'temperature' => ['label' => 'อุณหภูมิ (°C)', 'type' => 'number', 'min' => 34, 'max' => 42, 'step' => '0.1', 'required' => true],
            'condition'   => ['label' => 'สุขภาพวันนี้', 'type' => 'select', 'options' => 'health', 'required' => true],
            'symptoms'    => ['label' => 'อาการ', 'type' => 'text', 'max' => 80, 'placeholder' => 'เช่น ไม่มีอาการผิดปกติ'],
            'note'        => ['label' => 'หมายเหตุครู', 'type' => 'text', 'max' => 120],
        ]],
        'foodIntake'  => ['label' => 'การรับประทานอาหาร', 'level' => 'student', 'fields' => []],
        // Per-child status typed by the teacher (e.g. "เด็กป่วย" + symptoms). Every save is a new row (history).
        'studentStatuses' => ['label' => 'สถานะรายบุคคล', 'level' => 'student', 'fields' => [
            'student_id' => ['label' => 'นักเรียน', 'type' => 'student', 'required' => true],
            'status'     => ['label' => 'สถานะ', 'type' => 'text', 'required' => true, 'max' => 60],
            'note'       => ['label' => 'หมายเหตุ', 'type' => 'textarea', 'max' => 200],
        ]],
        'stars' => ['label' => 'ดาว', 'level' => 'student', 'fields' => [
            'student_id' => ['label' => 'นักเรียน', 'type' => 'student', 'required' => true],
            'points'     => ['label' => 'จำนวนดาว', 'type' => 'number', 'min' => 1, 'max' => 10, 'required' => true],
            'reason'     => ['label' => 'เหตุผล', 'type' => 'text', 'required' => true, 'max' => 60, 'placeholder' => 'เช่น ช่วยเก็บของ'],
        ]],
        'development' => ['label' => 'พัฒนาการ', 'level' => 'student', 'fields' => [
            'student_id' => ['label' => 'นักเรียน', 'type' => 'student', 'required' => true],
            'language'   => ['label' => 'ภาษา', 'type' => 'number', 'min' => 0, 'max' => 100],
            'math'       => ['label' => 'คณิตศาสตร์', 'type' => 'number', 'min' => 0, 'max' => 100],
            'social'     => ['label' => 'สังคม', 'type' => 'number', 'min' => 0, 'max' => 100],
            'motor'      => ['label' => 'กล้ามเนื้อ', 'type' => 'number', 'min' => 0, 'max' => 100],
            'creative'   => ['label' => 'ความคิดสร้างสรรค์', 'type' => 'number', 'min' => 0, 'max' => 100],
            'note'       => ['label' => 'ความเห็นครู', 'type' => 'textarea', 'max' => 200],
        ]],
        'portfolio' => ['label' => 'ผลงาน', 'level' => 'student', 'fields' => [
            'student_id' => ['label' => 'นักเรียน', 'type' => 'student', 'required' => true],
            'title'      => ['label' => 'ชื่อผลงาน', 'type' => 'text', 'required' => true, 'max' => 80],
            'category'   => ['label' => 'หมวด', 'type' => 'select', 'options' => 'portfolioCategories'],
            'date'       => ['label' => 'วันที่ทำผลงาน', 'type' => 'date'],
            'comment'    => ['label' => 'รายละเอียด / คำอธิบาย', 'type' => 'textarea', 'max' => 200],
            'images'     => ['label' => 'รูปผลงาน', 'type' => 'image'], // uploaded files, not a column
        ]],
        'photos' => ['label' => 'ภาพกิจกรรม', 'level' => 'classroom', 'fields' => [
            'classroom_id' => ['label' => 'ห้องเรียน', 'type' => 'classroom', 'required' => true],
            'caption'      => ['label' => 'คำอธิบายภาพ', 'type' => 'text', 'required' => true, 'max' => 80],
            'activity'     => ['label' => 'กิจกรรม', 'type' => 'text', 'max' => 60],
        ]],
        'calendarEvents' => ['label' => 'ปฏิทินโรงเรียน', 'level' => 'school', 'fields' => [
            'date'    => ['label' => 'วันที่', 'type' => 'date', 'required' => true],
            'time'    => ['label' => 'เวลา', 'type' => 'time'],
            'title'   => ['label' => 'กิจกรรม', 'type' => 'text', 'required' => true, 'max' => 80],
            'emoji'   => ['label' => 'ไอคอน', 'type' => 'select', 'options' => ['🎉' => '🎉', '🏮' => '🏮', '🚌' => '🚌', '🦷' => '🦷', '👨‍👩‍👧' => '👨‍👩‍👧', '🏅' => '🏅', '📚' => '📚']],
            'dress'   => ['label' => 'ชุดที่ต้องใส่', 'type' => 'text', 'max' => 60],
            'bring'   => ['label' => 'สิ่งที่ต้องเตรียม', 'type' => 'text', 'max' => 80],
            'dismiss' => ['label' => 'เวลาเลิก', 'type' => 'time'],
        ]],
        'messages' => ['label' => 'ข้อความ', 'level' => 'student', 'fields' => []],
        'notifications' => ['label' => 'ประกาศ', 'level' => 'targeted', 'fields' => [
            'type'         => ['label' => 'ประเภท', 'type' => 'select', 'options' => 'notifyTypes', 'required' => true],
            'title'        => ['label' => 'หัวข้อ', 'type' => 'text', 'required' => true, 'max' => 80],
            'body'         => ['label' => 'รายละเอียด', 'type' => 'textarea', 'max' => 240],
            'classroom_id' => ['label' => 'ส่งถึงห้อง (เว้นว่าง = ทั้งโรงเรียน)', 'type' => 'classroom'],
        ]],
        // รับ-ส่ง: written ONLY through the pickup_* API actions (core/pickup.php), never the generic form.
        // Row: student scope + status, parent_id/parent_name, eta_minutes, requested_at, eta_at,
        //      preparing_at, waiting_at, completed_at, completed_by, completed_by_name.
        'pickups' => ['label' => 'รับ-ส่ง', 'level' => 'student', 'fields' => []],
        // Parent ↔ student relation (a parent may have several children). Written by the seed only.
        'parentStudents' => ['label' => 'ผู้ปกครอง-นักเรียน', 'level' => 'school', 'fields' => []],
        // In-app notifications, one row per recipient user (core/notifications.php only — never the generic API).
        'userNotifications' => ['label' => 'แจ้งเตือน', 'level' => 'school', 'fields' => []],
        'cameras' => ['label' => 'กล้องวงจรปิด', 'level' => 'camera', 'fields' => [
            'school_id'     => ['label' => 'โรงเรียน', 'type' => 'school'], // super admin picks; others forced from session
            'name'          => ['label' => 'ชื่อกล้อง', 'type' => 'text', 'required' => true, 'max' => 60, 'placeholder' => 'เช่น กล้องหน้าห้องอนุบาล 1'],
            'location'      => ['label' => 'ตำแหน่งติดตั้ง', 'type' => 'text', 'required' => true, 'max' => 80],
            'description'   => ['label' => 'รายละเอียด', 'type' => 'text', 'max' => 120],
            'classroom_id'  => ['label' => 'ห้องเรียน (เว้นว่าง = พื้นที่ส่วนกลาง)', 'type' => 'classroom'],
            'camera_model'  => ['label' => 'รุ่นกล้อง', 'type' => 'text', 'max' => 40, 'placeholder' => 'เช่น Tapo C200C'],
            'stream_type'   => ['label' => 'ประเภท Stream', 'type' => 'select', 'required' => true, 'options' => ['mock' => 'Mock (Demo)', 'hls' => 'HLS (.m3u8)', 'webrtc' => 'WebRTC (WHEP)']],
            'stream_url'    => ['label' => 'Stream URL จาก Media Server', 'type' => 'url', 'max' => 300, 'placeholder' => 'https://media.example/cam01/index.m3u8'],
            'rtsp_url'      => ['label' => 'RTSP URL (ฝั่ง Server เท่านั้น)', 'type' => 'secret', 'max' => 300, 'placeholder' => 'เว้นว่าง = ใช้ค่าเดิม · ห้ามใส่รหัสผ่าน'],
            'status'        => ['label' => 'สถานะ', 'type' => 'select', 'required' => true, 'options' => ['online' => '🟢 Online', 'offline' => '🔴 Offline', 'maintenance' => '🛠️ Maintenance']],
            'is_active'     => ['label' => 'การใช้งาน', 'type' => 'select', 'required' => true, 'options' => ['1' => 'เปิดใช้งาน', '0' => 'ปิดใช้งาน']],
            'allow_teacher' => ['label' => 'ครูดูได้', 'type' => 'select', 'options' => ['1' => 'ได้', '0' => 'ไม่ได้']],
            'allow_parent'  => ['label' => 'ผู้ปกครองดูได้', 'type' => 'select', 'options' => ['1' => 'ได้', '0' => 'ไม่ได้']],
        ]],
        'cameraPermissions' => ['label' => 'สิทธิ์ดูกล้อง', 'level' => 'school', 'fields' => []],
        'mediaFiles' => ['label' => 'รูปภาพ', 'level' => 'media', 'fields' => []], // written by core/media.php only
        'settings' => ['label' => 'การตั้งค่าโรงเรียน', 'level' => 'school', 'fields' => [
            'phone'     => ['label' => 'เบอร์โทรโรงเรียน', 'type' => 'text', 'max' => 20],
            'address'   => ['label' => 'ที่อยู่', 'type' => 'textarea', 'max' => 200],
            'openTime'  => ['label' => 'เวลาเปิด', 'type' => 'time'],
            'closeTime' => ['label' => 'เวลาปิด', 'type' => 'time'],
            'motto'     => ['label' => 'คำขวัญ', 'type' => 'text', 'max' => 100],
        ]],
    ];
    return $tables;
}

function table_def(string $table): array
{
    $def = mk_tables()[$table] ?? null;
    if (!$def) {
        throw new DemoValidationException('ไม่พบข้อมูลที่ต้องการ');
    }
    return $def;
}
