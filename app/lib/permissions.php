<?php
/**
 * Core permission engine.
 *
 *   USER → ROLE → SCHOOL SCOPE → CLASSROOM SCOPE → STUDENT SCOPE → DATA
 *
 * The role matrix comes from includes/permissions.php; the row LEVEL comes from
 * core/schema.php. A row is visible only when the role may read the table AND the row sits
 * inside the user's scope. Every scope value comes from the session user (current_user()),
 * never from the URL or the request body.
 */
declare(strict_types=1);

function permission_matrix(): array
{
    static $matrix = null;
    return $matrix ??= require MK_APP . '/config/permissions.php';
}

function role_rules(string $role): array
{
    return permission_matrix()[$role] ?? ['scope' => 'none', 'read' => [], 'write' => []];
}

function can_read_table(array $user, string $table): bool
{
    $read = role_rules($user['role'])['read'];
    return $read === '*' || in_array($table, $read, true);
}

function can_write_table(array $user, string $table): bool
{
    return in_array($table, role_rules($user['role'])['write'], true);
}

function is_read_only(array $user): bool
{
    return role_rules($user['role'])['write'] === [];
}

/** Is this row inside the user's scope? (school → classroom → student) */
function row_in_scope(array $user, string $table, array $row): bool
{
    $scope = role_rules($user['role'])['scope'];
    if ($scope === 'platform') {
        return true;
    }

    $level = table_def($table)['level'];

    // 1. SCHOOL SCOPE — every non-platform user is locked to their own school.
    $rowSchool = $level === 'school_self' ? ($row['id'] ?? null) : ($row['school_id'] ?? null);
    if ($rowSchool !== $user['school_id']) {
        return false;
    }
    if ($scope === 'school') {
        return true;
    }

    // 2. CLASSROOM SCOPE (teacher) / 3. STUDENT SCOPE (parent)
    $classroomId = $user['classroom_id'];
    $studentId = $user['student_id'];

    return match ($level) {
        'school', 'school_self' => true,
        'classroom_self' => ($row['id'] ?? null) === $classroomId,
        'classroom'      => ($row['classroom_id'] ?? null) === $classroomId,
        'student_self'   => $scope === 'classroom' ? ($row['classroom_id'] ?? null) === $classroomId : ($row['id'] ?? null) === $studentId,
        'student'        => $scope === 'classroom' ? ($row['classroom_id'] ?? null) === $classroomId : ($row['student_id'] ?? null) === $studentId,
        'camera'         => can_view_camera($row, $user), // CCTV has its own rule set (core/cctv.php)
        'media'          => ($row['student_id'] ?? null) !== null
                            ? ($scope === 'classroom' ? ($row['classroom_id'] ?? null) === $classroomId : $row['student_id'] === $studentId)
                            : ($row['classroom_id'] ?? null) === $classroomId,
        'targeted'       => (($row['classroom_id'] ?? null) === null || $row['classroom_id'] === $classroomId)
                            && ($scope === 'classroom' || ($row['student_id'] ?? null) === null || $row['student_id'] === $studentId),
        default          => false,
    };
}

function authorize(bool $allowed, string $message, array $context = []): void
{
    if (!$allowed) {
        mk_log('Permission', 'DENIED', $context + ['message' => $message]);
        throw new PermissionDeniedException($message);
    }
}

/**
 * Fill / verify the scope columns of a row before it is written.
 * Teachers and parents get their classroom / child forced from the session; admins may pick
 * a classroom or student, but only one that belongs to their own school.
 */
function apply_write_scope(array $user, string $table, array $data): array
{
    $level = table_def($table)['level'];
    $scope = role_rules($user['role'])['scope'];
    $ctx = ['user' => $user['id'], 'role' => $user['role'], 'table' => $table];

    // SCHOOL — forced from the session (super admin picks a real school for school-bound rows).
    if ($level !== 'school_self') {
        if ($scope === 'platform') {
            $schoolId = (int) ($data['school_id'] ?? 0);
            authorize(find_row('schools', $schoolId) !== null, 'กรุณาเลือกโรงเรียน', $ctx);
            $data['school_id'] = $schoolId;
        } else {
            $data['school_id'] = $user['school_id'];
        }
    }

    // CLASSROOM
    if (in_array($level, ['classroom', 'student_self', 'targeted', 'camera'], true)) {
        $requested = $data['classroom_id'] ?? null;
        if ($scope === 'classroom') {
            $data['classroom_id'] = $user['classroom_id'];
        } elseif ($requested !== null && $requested !== '') {
            $room = find_row('classrooms', (int) $requested);
            authorize($room !== null && $room['school_id'] === $data['school_id'], 'ไม่มีสิทธิ์จัดการข้อมูลของห้องนี้', $ctx + ['classroom_id' => $requested]);
            $data['classroom_id'] = $room['id'];
        } elseif (in_array($level, ['targeted', 'camera'], true)) {
            $data['classroom_id'] = null; // school-wide / common area
        } else {
            throw new DemoValidationException('กรุณาเลือกห้องเรียน');
        }
    }

    // STUDENT — must be a child the user can see; classroom follows the child.
    if ($level === 'student') {
        $studentId = $scope === 'student' ? $user['student_id'] : (int) ($data['student_id'] ?? 0);
        $student = find_row('students', $studentId);
        authorize($student !== null && row_in_scope($user, 'students', $student), 'ไม่มีสิทธิ์จัดการข้อมูลของเด็กคนนี้', $ctx + ['student_id' => $studentId]);
        $data['student_id'] = $student['id'];
        $data['classroom_id'] = $student['classroom_id'];
        $data['school_id'] = $student['school_id'];
    }
    if ($level === 'targeted' && !array_key_exists('student_id', $data)) {
        $data['student_id'] = null;
    }

    return $data;
}

/** UNSCOPED lookup used by the permission engine itself. */
function find_row(string $table, int $id): ?array
{
    foreach (store_rows($table) as $row) {
        if (($row['id'] ?? null) === $id) {
            return $row;
        }
    }
    return null;
}

/** Plain-language summary of what a role may do (shown on the account page). */
function permission_summary(array $user): array
{
    $rules = role_rules($user['role']);
    $labels = fn (array $tables) => array_map(fn ($t) => table_def($t)['label'], $tables);
    return [
        'scope' => match ($rules['scope']) {
            'platform'  => 'ทุกโรงเรียนในระบบ',
            'school'    => 'ทั้งโรงเรียน' . ($user['school']['name'] ?? ''),
            'classroom' => 'ห้อง' . ($user['classroom']['name'] ?? '') . ' เท่านั้น',
            'student'   => 'ข้อมูลของ' . ($user['child']['nickname'] ?? '') . ' เท่านั้น',
            default     => '-',
        },
        'write' => $rules['write'] ? implode(' · ', $labels($rules['write'])) : 'อ่านอย่างเดียว (Read Only)',
    ];
}
