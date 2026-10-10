<?php
/**
 * Mock data: 1 school (school_id = 1).
 *   ศูนย์พัฒนาเด็กเล็กบ้านโพนสูง (ศพด.บ้านโพนสูง)   3 ห้อง · 60 นักเรียน · 5 ครู (from the base mock data, renamed here)
 */
declare(strict_types=1);

function pkg_seed(): array
{
    $schools = [seed_school_from_package_c()];
    $schools[0]['school'] = ['name' => 'ศูนย์พัฒนาเด็กเล็กบ้านโพนสูง', 'shortName' => 'ศพด.บ้านโพนสูง'] + $schools[0]['school'];

    $id = 1;
    $staff = function (string $name, string $phone, string $role, ?int $school) use (&$id) {
        return ['id' => $id++, 'name' => $name, 'phone' => $phone, 'pin' => '123456', 'role' => $role,
            'school_id' => $school, 'classroom_id' => null, 'student_id' => null, 'teacher_id' => null];
    };

    $users = [
        $staff('คุณแอดมินโพนสูง', '0900000002', 'admin', 1),
        $staff('ผู้อำนวยการโพนสูง', '0900000003', 'executive', 1),
    ];
    $id = 10;
    array_push($users, ...seed_users_from_package_c($id)); // 3 teachers + 6 parents (Package C accounts)

    // A parent with TWO children in different classrooms (multi-child demo: รับ-ส่ง, ผลงานของฉัน)
    $id = 25;
    $users[] = ['id' => $id++, 'name' => 'คุณแม่ของน้องน้ำใสและน้องไผ่', 'phone' => '0898765440', 'pin' => '123456', 'role' => 'parent',
        'school_id' => 1, 'classroom_id' => 1, 'student_id' => 16, 'teacher_id' => null, 'relation' => 'แม่', 'children' => [16, 33]];

    return seed_build_tables($schools, $users, ['15:30', '🏠', 'เตรียมกลับบ้าน', 'เก็บของใช้และรอผู้ปกครองมารับ']);
}
