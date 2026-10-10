<?php
/**
 * Core authentication — phone + 6-digit PIN against the package's users table.
 *
 * After login, $_SESSION['user'] holds: id, name, role, school_id, classroom_id, student_id.
 * current_user() re-validates that record against the users table on every request
 * (as a real system would re-check the database) and re-derives the scope from it.
 * Not a real auth system: no PIN hashing or rate limiting — sales demo only.
 */
declare(strict_types=1);

/** Query-string keys someone might try in order to jump to another scope. */
const SCOPE_QUERY_KEYS = ['school_id', 'school', 'classroom_id', 'classroom', 'room', 'student_id', 'student', 'child', 'teacher_id', 'parent_id', 'user_id', 'camera_id', 'camera'];

function normalize_phone(string $phone): string
{
    return preg_replace('/\D+/', '', $phone) ?? '';
}

function auth_login(string $role, string $phone, string $pin): bool
{
    mk_log('Auth', 'auth_login START', ['role' => $role, 'phone' => $phone]);
    if (!in_array($role, pkg()['roles'], true)) {
        return false;
    }
    $phone = normalize_phone($phone);

    foreach (store_rows('users') as $account) {
        if ($account['role'] !== $role || $account['phone'] !== $phone || !hash_equals($account['pin'], $pin)) {
            continue;
        }
        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id'           => $account['id'],
            'name'         => $account['name'],
            'role'         => $account['role'],
            'school_id'    => $account['school_id'],
            'classroom_id' => $account['classroom_id'],
            'student_id'   => $account['student_id'],
        ];
        mk_log('Auth', 'auth_login END success', $_SESSION['user']);
        return true;
    }

    mk_log('Auth', 'auth_login END failed', ['role' => $role, 'phone' => $phone]);
    return false;
}

function auth_logout(): void
{
    mk_log('Auth', 'auth_logout', ['user' => $_SESSION['user'] ?? null]);
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/** The logged-in user, re-validated and enriched for display. Null when logged out. */
function current_user(): ?array
{
    static $cache = false;
    if ($cache !== false) {
        return $cache;
    }
    $cache = null;

    $session = $_SESSION['user'] ?? null;
    if (!is_array($session) || !in_array($session['role'] ?? '', pkg()['roles'], true)) {
        return null;
    }
    $account = find_row('users', (int) $session['id']);
    if (!$account || $account['role'] !== $session['role']) {
        mk_log('Auth', 'current_user account no longer valid', $session);
        return null;
    }

    // Scope is always re-derived from the account record.
    $user = [
        'id'           => $account['id'],
        'name'         => $account['name'],
        'role'         => $account['role'],
        'school_id'    => $account['school_id'],
        'classroom_id' => $account['classroom_id'],
        'student_id'   => $account['student_id'],
        'phone'        => $account['phone'],
        'relation'     => $account['relation'] ?? null,
        'teacher_id'   => $account['teacher_id'] ?? null,
        'meta'         => role_meta($account['role']),
    ];
    // Parent ↔ children (parentStudents). Generic pages keep using the first child (student_id);
    // multi-child screens (รับ-ส่ง, ผลงานของฉัน) check this list explicitly.
    $user['student_ids'] = [];
    if ($account['role'] === 'parent') {
        foreach (store_rows('parentStudents') as $link) {
            if ($link['user_id'] === $account['id']) {
                $user['student_ids'][] = $link['student_id'];
            }
        }
        $user['student_ids'] = $user['student_ids'] ?: array_filter([$account['student_id']]);
    }
    $user['school'] = $user['school_id'] ? find_row('schools', $user['school_id']) : null;
    $user['child'] = $user['student_id'] ? find_row('students', $user['student_id']) : null;
    if ($user['child']) {
        $user['classroom_id'] = $user['child']['classroom_id']; // the child's room is the parent's room
    }
    $user['classroom'] = $user['classroom_id'] ? find_row('classrooms', $user['classroom_id']) : null;
    $user['emoji'] = match ($user['role']) {
        'parent' => $user['relation'] === 'พ่อ' ? '👨' : '👩',
        default  => $user['meta']['emoji'],
    };

    $schoolName = $user['school']['shortName'] ?? '';
    [$user['scopeLabel'], $user['lockLabel']] = match ($user['role']) {
        'super_admin' => ['ทุกโรงเรียน · ' . count(store_rows('schools')) . ' แห่ง', 'ทั้งระบบ'],
        'admin'       => [$user['school']['name'], 'เฉพาะโรงเรียนนี้'],
        'executive'   => [$user['school']['name'], 'อ่านอย่างเดียว'],
        'teacher'     => [$user['classroom']['name'] . (pkg()['multiSchool'] ? ' · ' . $schoolName : ''), 'เฉพาะห้องนี้'],
        'parent'      => [$user['child']['nickname'] . ' · ' . $user['classroom']['name'] . (pkg()['multiSchool'] ? ' · ' . $schoolName : ''), 'เฉพาะ' . $user['child']['nickname']],
    };
    if ($user['role'] === 'parent' && count($user['student_ids']) > 1) { // several children (parentStudents)
        $names = array_map(fn ($id) => find_row('students', $id)['nickname'] ?? '', $user['student_ids']);
        $user['scopeLabel'] = implode(', ', $names) . (pkg()['multiSchool'] ? ' · ' . $schoolName : '');
        $user['lockLabel'] = 'เฉพาะลูกของฉัน ' . count($names) . ' คน';
    }

    return $cache = $user;
}

function home_path(string $role): string
{
    return role_meta($role)['dir'] . '/index.php';
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        flash('login_error', 'กรุณาเข้าสู่ระบบก่อนใช้งาน');
        redirect('login.php');
    }
    return $user;
}

/** Role check + scope-query guard for a page. Returns the current user. */
function require_role(string $role): array
{
    $user = current_user();
    if (!$user) {
        flash('login_error', 'กรุณาเข้าสู่ระบบก่อนใช้งาน');
        redirect('login.php?role=' . $role);
    }
    if ($user['role'] !== $role) {
        mk_log('Auth', 'PERMISSION DENIED wrong role', ['need' => $role, 'user' => $user['role'], 'uri' => $_SERVER['REQUEST_URI'] ?? '']);
        flash('toast', ['message' => 'หน้านี้สำหรับ' . role_meta($role)['th'] . 'เท่านั้น', 'type' => 'lock']);
        redirect(home_path($user['role']));
    }
    guard_scope_query($user);
    return $user;
}

/** Direct-URL protection: ?school_id= / ?classroom_id= / ?student_id= never change the scope. */
function guard_scope_query(array $user): void
{
    $tampered = array_intersect_key($_GET, array_flip(SCOPE_QUERY_KEYS));
    if (!$tampered) {
        return;
    }
    mk_log('Auth', 'SCOPE QUERY IGNORED', ['user' => $user['id'], 'role' => $user['role'], 'query' => $tampered]);
    flash('toast', ['message' => 'คุณดูได้เฉพาะข้อมูลตามสิทธิ์ของบัญชีนี้ (' . $user['scopeLabel'] . ')', 'type' => 'lock']);
    $keep = array_diff_key($_GET, $tampered);
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . ($keep ? '?' . http_build_query($keep) : ''));
    exit;
}
