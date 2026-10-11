<?php
/**
 * Login accounts that follow the admin's people records:
 *   teacher record (teachers)  → one teacher account  (users.role = teacher, users.teacher_id)
 *   student record (students)  → the parent's account (users.role = parent) + parentStudents link;
 *                                one parent phone = one account, so a parent of several children
 *                                is linked to every child.
 *
 * The admin only types the phone: a new account starts with DEFAULT_PIN (123456) and must_change_pin,
 * so the owner sets their own PIN at the first login. account_reset_pin() puts a forgotten PIN back
 * to the default. check_* run BEFORE the record is saved (nothing is written when they fail), sync_* after.
 */
declare(strict_types=1);

/** PIN fields of a brand-new account: the default PIN, to be replaced at the first login. */
function account_default_pin(): array
{
    static $hash = null;
    return ['pin' => $hash ??= pin_hash(DEFAULT_PIN), 'must_change_pin' => true];
}

/** Admin: put a user's PIN back to the default (forgotten PIN); they set a new one at the next login. */
function account_reset_pin(array $admin, int $userId): array
{
    $target = find_row('users', $userId);
    authorize($target !== null && $target['school_id'] === $admin['school_id'] && $target['id'] !== $admin['id'],
        'ไม่สามารถรีเซ็ต PIN ของบัญชีนี้ได้', ['admin' => $admin['id'], 'user' => $userId]);
    store_mutate(function (array &$state) use ($userId) {
        foreach ($state['tables']['users'] as &$u) {
            if ($u['id'] === $userId) {
                $u = array_merge($u, ['pin' => pin_hash(DEFAULT_PIN), 'must_change_pin' => true]);
            }
        }
        unset($u);
    });
    mk_db()->prepare('DELETE FROM login_attempts WHERE phone = ?')->execute([$target['phone']]); // unlock
    mk_log('Accounts', 'pin reset by admin', ['admin' => $admin['id'], 'user' => $userId]);
    return $target;
}

/** Teacher account linked to a teacher record (null = none yet). */
function teacher_account(int $teacherId): ?array
{
    foreach (store_rows('users') as $u) {
        if ($u['role'] === 'teacher' && $u['teacher_id'] === $teacherId) {
            return $u;
        }
    }
    return null;
}

function account_by_phone(string $phone): ?array
{
    foreach (store_rows('users') as $u) {
        if ($u['phone'] === $phone) {
            return $u;
        }
    }
    return null;
}

/** Validate a teacher save (phone free for this teacher, PIN given when the account is new). */
function account_check_teacher(int $teacherId, array $data): void
{
    $phone = (string) ($data['phone'] ?? ($teacherId ? (find_row('teachers', $teacherId)['phone'] ?? '') : ''));
    if ($phone === '') {
        throw new ValidationException('กรุณากรอกเบอร์โทรศัพท์ของครู (ใช้เข้าสู่ระบบ)');
    }
    $own = $teacherId ? teacher_account($teacherId) : null;
    $other = account_by_phone($phone);
    if ($other && (!$own || $other['id'] !== $own['id'])) {
        throw new ValidationException('เบอร์ ' . $phone . ' มีผู้ใช้งานแล้ว (' . role_meta($other['role'])['th'] . ') กรุณาใช้เบอร์อื่น');
    }
}

/** Create / update the teacher's account after the teacher record was saved. */
function account_sync_teacher(int $teacherId): void
{
    $teacher = find_row('teachers', $teacherId);
    $own = teacher_account($teacherId);
    store_mutate(function (array &$state) use ($teacher, $own) {
        $fields = ['name' => $teacher['nickname'], 'phone' => $teacher['phone'], 'school_id' => $teacher['school_id'],
            'classroom_id' => $teacher['classroom_id'], 'teacher_id' => $teacher['id']];
        if ($own) {
            foreach ($state['tables']['users'] as &$u) {
                if ($u['id'] === $own['id']) {
                    $u = array_merge($u, $fields);
                }
            }
            unset($u);
            return;
        }
        $state['tables']['users'][] = ['id' => $state['nextId']++, 'role' => 'teacher', 'student_id' => null] + account_default_pin() + $fields;
    });
    mk_log('Accounts', 'teacher account synced', ['teacher' => $teacherId, 'created' => !$own]);
}

/** Delete the account of a removed teacher (and the account's own notifications). */
function account_remove_teacher(int $teacherId): void
{
    $own = teacher_account($teacherId);
    if (!$own) {
        return;
    }
    store_mutate(function (array &$state) use ($own) {
        $state['tables']['users'] = array_values(array_filter($state['tables']['users'], fn ($u) => $u['id'] !== $own['id']));
        $state['tables']['userNotifications'] = array_values(array_filter($state['tables']['userNotifications'], fn ($n) => $n['user_id'] !== $own['id']));
    });
    mk_log('Accounts', 'teacher account removed', ['teacher' => $teacherId, 'user' => $own['id']]);
}

/** Validate a student save: the parent phone may be new (PIN needed) or an existing parent account. */
function account_check_parent(int $studentId, array $data): void
{
    $phone = (string) ($data['parentPhone'] ?? ($studentId ? (find_row('students', $studentId)['parentPhone'] ?? '') : ''));
    if ($phone === '') {
        return; // no parent account (yet)
    }
    $other = account_by_phone($phone);
    if ($other && $other['role'] !== 'parent') {
        throw new ValidationException('เบอร์ ' . $phone . ' เป็นบัญชี' . role_meta($other['role'])['th'] . 'อยู่แล้ว กรุณาใช้เบอร์อื่นสำหรับผู้ปกครอง');
    }
}

/**
 * After a student save: link the child to the parent account of parentPhone (create it when new),
 * and unlink a previous parent account whose phone was replaced.
 */
function account_sync_parent(int $studentId): void
{
    $student = find_row('students', $studentId);
    $phone = (string) ($student['parentPhone'] ?? '');
    $parent = $phone !== '' ? account_by_phone($phone) : null;
    store_mutate(function (array &$state) use ($student, $phone, $parent) {
        $t = &$state['tables'];
        $relation = $student['parentRelation'] ?: 'แม่';
        // Unlink accounts of an old phone number from this child.
        $t['parentStudents'] = array_values(array_filter($t['parentStudents'], function ($l) use ($student, $parent) {
            return $l['student_id'] !== $student['id'] || ($parent && $l['user_id'] === $parent['id']);
        }));
        $parentId = 0;
        if ($phone === '') {
            // no parent account for this child any more
        } elseif ($parent) {
            foreach ($t['users'] as &$u) {
                if ($u['id'] === $parent['id']) {
                    $u['name'] = $student['parentName'] ?: $u['name'];
                    $u['relation'] = $relation;
                    if (!find_row('students', (int) $u['student_id'])) {
                        $u['student_id'] = $student['id']; // primary child no longer exists
                        $u['classroom_id'] = $student['classroom_id'];
                    }
                }
            }
            unset($u);
            $parentId = $parent['id'];
        } else {
            $parentId = $state['nextId']++;
            $t['users'][] = ['id' => $parentId, 'name' => $student['parentName'] ?: 'ผู้ปกครองของ' . $student['nickname'], 'phone' => $phone,
                'role' => 'parent', 'school_id' => $student['school_id'], 'classroom_id' => $student['classroom_id'],
                'student_id' => $student['id'], 'teacher_id' => null, 'relation' => $relation] + account_default_pin();
        }
        $linked = array_filter($t['parentStudents'], fn ($l) => $l['student_id'] === $student['id'] && $l['user_id'] === $parentId);
        if ($parentId && !$linked) {
            $t['parentStudents'][] = ['id' => $state['nextId']++, 'school_id' => $student['school_id'], 'user_id' => $parentId,
                'student_id' => $student['id'], 'relation' => $relation];
        }
        // A previous parent account whose primary child is this student: move it to another linked child.
        foreach ($t['users'] as &$u) {
            if ($u['role'] === 'parent' && $u['id'] !== $parentId && $u['student_id'] === $student['id']) {
                $next = array_values(array_filter($t['parentStudents'], fn ($l) => $l['user_id'] === $u['id']))[0] ?? null;
                $u['student_id'] = $next['student_id'] ?? null; // null + no link → removed below
            }
        }
        unset($u);
        account_drop_orphan_parents($t);
    });
    mk_log('Accounts', 'parent account synced', ['student' => $studentId, 'phone' => $phone !== '' ? substr($phone, 0, 3) . '…' : null]);
}

/** After a student was deleted: remove its links and parent accounts left without any child. */
function account_unlink_student(int $studentId): void
{
    store_mutate(function (array &$state) use ($studentId) {
        $state['tables']['parentStudents'] = array_values(array_filter($state['tables']['parentStudents'], fn ($l) => $l['student_id'] !== $studentId));
        account_drop_orphan_parents($state['tables']);
    });
}

/** Parent accounts with no linked child left are removed (inside an open store_mutate). */
function account_drop_orphan_parents(array &$t): void
{
    $linked = array_flip(array_column($t['parentStudents'], 'user_id'));
    $students = array_flip(array_column($t['students'], 'id'));
    $drop = [];
    foreach ($t['users'] as $u) {
        if ($u['role'] === 'parent' && !isset($linked[$u['id']]) && !isset($students[(int) $u['student_id']])) {
            $drop[$u['id']] = true;
        }
    }
    if ($drop) {
        $t['users'] = array_values(array_filter($t['users'], fn ($u) => !isset($drop[$u['id']])));
        $t['userNotifications'] = array_values(array_filter($t['userNotifications'], fn ($n) => !isset($drop[$n['user_id']])));
    }
}
