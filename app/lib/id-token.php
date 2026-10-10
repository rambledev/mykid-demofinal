<?php
/**
 * Secure ID tokens — database ids never reach the browser.
 *
 * tok('students', 42) → "Yq3k0P2c7xVJ5c8wZb1m9A" (22 chars, URL-safe). The token is one AES-128
 * block = [4-byte table tag | 8-byte id | 4 zero bytes] encrypted with a key derived from APP_KEY.
 * It is deterministic (the same row always gets the same token, so <select> values match row
 * data in JS) and bound to its table: a token for one table never decodes for another, and any
 * tampering fails the tag / zero check (≈ 2^-64 chance to forge). Decoding only maps the token
 * back to an id — permission checks (permissions.php) still run on every request.
 *
 * id_fields_* convert the id-bearing keys of rows (output) and request payloads (input).
 */
declare(strict_types=1);

/** Which table an id-bearing key points to (rows of any table). */
const ID_FIELD_TABLES = [
    'school_id'    => 'schools',
    'classroom_id' => 'classrooms',
    'student_id'   => 'students',
    'teacher_id'   => 'teachers',
    'user_id'      => 'users',
    'parent_id'    => 'users',
    'by_user'      => 'users',
    'completed_by' => 'users',
    'uploaded_by'  => 'users',
    'camera_id'    => 'cameras',
];

function id_token_key(): string
{
    static $key = null;
    if ($key === null) {
        $secret = (string) mk_env('APP_KEY', '');
        if (strlen($secret) < 32) {
            error_log('[Mykid][IdToken][key] ERROR APP_KEY missing or shorter than 32 characters');
            throw new RuntimeException('APP_KEY is not configured');
        }
        $key = hash_hkdf('sha256', $secret, 16, 'mykid-id-token-v1');
    }
    return $key;
}

function id_table_tag(string $table): string
{
    return substr(hash('sha256', 'tbl:' . $table, true), 0, 4);
}

/** id → token (null / 0 stay as-is so "not set" keeps its meaning). */
function tok(string $table, int|string|null $id): ?string
{
    if ($id === null || $id === '' || (int) $id <= 0) {
        return null;
    }
    $block = id_table_tag($table) . pack('J', (int) $id) . "\0\0\0\0";
    $raw = openssl_encrypt($block, 'aes-128-ecb', id_token_key(), OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING);
    return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
}

/** token → id, or null when the token is invalid / for another table. */
function untok(string $table, mixed $token): ?int
{
    if (!is_string($token) || !preg_match('/^[A-Za-z0-9_-]{22}$/', $token)) {
        return null;
    }
    $raw = base64_decode(strtr($token, '-_', '+/') . '==', true);
    if ($raw === false || strlen($raw) !== 16) {
        return null;
    }
    $block = openssl_decrypt($raw, 'aes-128-ecb', id_token_key(), OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING);
    if ($block === false || !hash_equals(id_table_tag($table), substr($block, 0, 4)) || substr($block, 12, 4) !== "\0\0\0\0") {
        return null;
    }
    $id = unpack('J', substr($block, 4, 8))[1];
    return $id > 0 ? $id : null;
}

/** untok() that rejects bad tokens with a friendly validation error. */
function untok_or_fail(string $table, mixed $token): int
{
    $id = untok($table, $token);
    if ($id === null) {
        mk_log('IdToken', 'invalid token', ['table' => $table]);
        throw new ValidationException('ไม่พบข้อมูลที่ต้องการ');
    }
    return $id;
}

/**
 * Row → browser-safe row: `id` and every key of ID_FIELD_TABLES become tokens; lists of ids
 * (users.children) too. Secrets are dropped.
 */
function public_row(string $table, array $row): array
{
    unset($row['pin'], $row['rtsp_url']);
    foreach ($row as $key => $value) {
        if ($key === 'id') {
            $row[$key] = tok($table, $value);
        } elseif (isset(ID_FIELD_TABLES[$key])) {
            $row[$key] = tok(ID_FIELD_TABLES[$key], $value);
        } elseif ($key === 'children' && is_array($value)) {
            $row[$key] = array_map(fn ($id) => tok('students', $id), $value);
        } elseif ($key === 'owner_id' && isset($row['owner_type'], MEDIA_OWNERS[$row['owner_type']])) {
            $row[$key] = tok(MEDIA_OWNERS[$row['owner_type']], $value);
        }
    }
    return $row;
}

/**
 * Request data → ids: every key of ID_FIELD_TABLES holding a token is decoded (invalid → error).
 * Empty values stay empty (= "not set"); plain integers are rejected (ids must come as tokens).
 */
function decode_id_fields(array $data): array
{
    foreach ($data as $key => $value) {
        if (!isset(ID_FIELD_TABLES[$key])) {
            continue;
        }
        if ($value === null || $value === '' || $value === 0 || $value === '0') {
            $data[$key] = null;
            continue;
        }
        $data[$key] = untok_or_fail(ID_FIELD_TABLES[$key], $value);
    }
    return $data;
}
