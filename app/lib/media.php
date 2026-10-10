<?php
/**
 * Core media — image files for Student Portfolio, Activity Images and Food Images.
 *
 * One metadata table (mediaFiles) for every image:
 *   id, school_id, classroom_id, student_id (portfolio only), owner_type (portfolio|activity|food),
 *   owner_id, file_path (relative, never sent to the browser), file_size, mime_type, width, height,
 *   uploaded_at, expires_at (= uploaded_at + 6 months), deleted_at, image_status, uploaded_by
 *
 * Business rules:
 *   - Image FILES are kept for 6 months. After that the image is "expired" and the UI shows a
 *     placeholder ("รูปภาพหมดเวลาเก็บไฟล์") instead of the picture — never a broken image or URL.
 *   - Database records (activity / portfolio / food / media metadata) are NEVER deleted
 *     automatically. Only an admin's "ล้างไฟล์ภาพที่หมดอายุ" removes physical files, and then
 *     only marks the metadata deleted_at / image_status = deleted.
 *   - There is no cron job. Status is computed when read.
 *
 * Files live in storage/uploads (blocked from direct HTTP access by Apache) and are
 * served only through media.php?id=…, which re-checks the viewer's permission every time.
 */
declare(strict_types=1);

const MEDIA_RETENTION = '+6 months';
const MEDIA_MAX_UPLOAD_BYTES = 10 * 1024 * 1024;   // per file, before optimisation
const MEDIA_MAX_FILES = 6;                          // per request
const MEDIA_MAX_DIMENSION = 1600;                   // longest side after optimisation
const MEDIA_JPEG_QUALITY = 82;
const MEDIA_ALLOWED = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
const MEDIA_ALLOWED_EXT = ['jpg', 'jpeg', 'png', 'webp'];
const MEDIA_OWNERS = ['portfolio' => 'portfolio', 'activity' => 'activities', 'food' => 'foodMenus'];

function media_root(): string
{
    return MK_STORAGE . '/uploads';
}

/** Absolute path of a media file, or null if the stored path escapes the uploads root. */
function media_abs_path(array $media): ?string
{
    $relative = (string) ($media['file_path'] ?? '');
    if ($relative === '' || str_contains($relative, '..') || str_starts_with($relative, '/')) {
        return null;
    }
    return media_root() . '/' . $relative;
}

/** available | expired | deleted — computed on read (no cron). */
function media_status(array $media): string
{
    if (!empty($media['deleted_at'])) {
        return 'deleted';
    }
    if (time() >= strtotime((string) $media['expires_at'])) {
        return 'expired';
    }
    $path = media_abs_path($media);
    return $path !== null && is_file($path) ? 'available' : 'deleted';
}

function media_expires_at(string $uploadedAt): string
{
    return date('Y-m-d H:i:s', strtotime($uploadedAt . ' ' . MEDIA_RETENTION));
}

/** Scoped media rows (permission-filtered) grouped by owner id for one owner type. */
function media_by_owner(string $ownerType): array
{
    static $cache = [];
    if (!isset($cache[$ownerType])) {
        $cache[$ownerType] = group_by(array_values(array_filter(scoped('mediaFiles'), fn ($m) => $m['owner_type'] === $ownerType)), 'owner_id');
    }
    return $cache[$ownerType];
}

function media_for(string $ownerType, int $ownerId): array
{
    return media_by_owner($ownerType)[$ownerId] ?? [];
}

function can_view_media(array $media, array $user): bool
{
    if (!can_read_table($user, 'mediaFiles')) {
        return false;
    }
    // A parent with several children may also open the work images of their OTHER children
    // (relation parentStudents, same school) — never images of children they are not linked to.
    if ($user['role'] === 'parent' && $media['owner_type'] === 'portfolio' && $media['student_id'] !== null) {
        return in_array($media['student_id'], $user['student_ids'] ?? [], true) && $media['school_id'] === $user['school_id'];
    }
    return row_in_scope($user, 'mediaFiles', $media);
}

function human_bytes(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    $value = (float) $bytes;
    while ($value >= 1024 && $i < count($units) - 1) {
        $value /= 1024;
        $i++;
    }
    return ($i === 0 ? (string) $bytes : number_format($value, 1)) . ' ' . $units[$i];
}

/** Counts and bytes per status for a set of media rows. */
function media_stats(array $rows): array
{
    $stats = ['total' => 0, 'bytes' => 0, 'available' => 0, 'availableBytes' => 0, 'expired' => 0, 'expiredBytes' => 0, 'deleted' => 0];
    foreach ($rows as $m) {
        $status = media_status($m);
        $stats[$status]++;
        if ($status !== 'deleted') {
            $stats['total']++;
            $stats['bytes'] += (int) $m['file_size'];
            $stats[$status . 'Bytes'] += (int) $m['file_size'];
        }
    }
    return $stats;
}

/* ============================================================================
 * Upload (security + optimisation)
 * ========================================================================= */

/** Normalise $_FILES['images'] (single or multiple) into a list of file arrays. */
function uploaded_files(string $field = 'images'): array
{
    $raw = $_FILES[$field] ?? null;
    if (!$raw) {
        return [];
    }
    if (!is_array($raw['name'])) {
        return $raw['error'] === UPLOAD_ERR_NO_FILE ? [] : [$raw];
    }
    $files = [];
    foreach ($raw['name'] as $i => $name) {
        if ($raw['error'][$i] !== UPLOAD_ERR_NO_FILE) {
            $files[] = ['name' => $name, 'type' => $raw['type'][$i], 'tmp_name' => $raw['tmp_name'][$i], 'error' => $raw['error'][$i], 'size' => $raw['size'][$i]];
        }
    }
    return $files;
}

/**
 * Validate one uploaded image. Never trusts the client: checks the PHP upload status, size,
 * the extension (whitelist + no hidden script extensions), the real MIME type (finfo) and that
 * the bytes decode as an image. Returns [mime, width, height].
 */
function validate_image_upload(array $file): array
{
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new DemoValidationException(in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
            ? 'ไฟล์รูปภาพมีขนาดใหญ่เกินไป' : 'อัปโหลดไฟล์ไม่สำเร็จ กรุณาลองใหม่อีกครั้ง');
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        throw new DemoValidationException('ไม่พบไฟล์ที่อัปโหลด');
    }
    if ($file['size'] <= 0 || $file['size'] > MEDIA_MAX_UPLOAD_BYTES) {
        throw new DemoValidationException('รูปภาพต้องมีขนาดไม่เกิน ' . human_bytes(MEDIA_MAX_UPLOAD_BYTES));
    }

    $name = strtolower((string) $file['name']);
    $ext = pathinfo($name, PATHINFO_EXTENSION);
    if (!in_array($ext, MEDIA_ALLOWED_EXT, true) || preg_match('/\.(php\d?|phtml|phar|pht|cgi|pl|py|sh|exe|bat|js|html?|svg)(\.|$)/', $name)) {
        throw new DemoValidationException('รองรับเฉพาะไฟล์รูปภาพ JPG, PNG หรือ WebP');
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']) ?: '';
    $info = @getimagesize($file['tmp_name']);
    if (!isset(MEDIA_ALLOWED[$mime]) || $info === false || ($info['mime'] ?? '') !== $mime) {
        mk_log('Media', 'REJECTED upload', ['name' => $file['name'], 'mime' => $mime]);
        throw new DemoValidationException('ไฟล์นี้ไม่ใช่รูปภาพที่รองรับ (JPG, PNG, WebP)');
    }
    if ($info[0] < 1 || $info[1] < 1 || $info[0] * $info[1] > 36_000_000) { // ≤ 36 MP keeps GD within memory_limit
        throw new DemoValidationException('รูปภาพมีความละเอียดสูงเกินไป กรุณาใช้รูปไม่เกิน 36 ล้านพิกเซล');
    }
    return [$mime, (int) $info[0], (int) $info[1]];
}

/**
 * Resize to MEDIA_MAX_DIMENSION and re-encode (also strips EXIF / any appended payload).
 * Without GD the validated original is kept (production images install GD — see Dockerfile).
 * Returns [bytes, width, height].
 */
function optimise_image(string $source, string $mime, int $width, int $height): array
{
    if (!function_exists('imagecreatetruecolor')) {
        mk_log('Media', 'GD not available — storing validated original');
        return [(string) file_get_contents($source), $width, $height];
    }
    $img = match ($mime) {
        'image/jpeg' => @imagecreatefromjpeg($source),
        'image/png'  => @imagecreatefrompng($source),
        'image/webp' => @imagecreatefromwebp($source),
    };
    if (!$img) {
        throw new DemoValidationException('ไม่สามารถอ่านไฟล์รูปภาพได้');
    }
    $scale = min(1, MEDIA_MAX_DIMENSION / max($width, $height));
    $w = max(1, (int) round($width * $scale));
    $h = max(1, (int) round($height * $scale));
    $out = imagecreatetruecolor($w, $h);
    if ($mime !== 'image/jpeg') {
        imagealphablending($out, false);
        imagesavealpha($out, true);
    }
    imagecopyresampled($out, $img, 0, 0, 0, 0, $w, $h, $width, $height);

    ob_start();
    match ($mime) {
        'image/jpeg' => imagejpeg($out, null, MEDIA_JPEG_QUALITY),
        'image/png'  => imagepng($out, null, 7),
        'image/webp' => imagewebp($out, null, MEDIA_JPEG_QUALITY),
    };
    $bytes = (string) ob_get_clean();
    imagedestroy($img);
    imagedestroy($out);
    return [$bytes, $w, $h];
}

/** Write bytes under uploads/{school}/{YYYY}/{MM}/{random}.{ext} — never the user's file name. */
function write_media_file(int $schoolId, string $ext, string $bytes): string
{
    $relative = sprintf('%d/%s/%s.%s', $schoolId, date('Y/m'), bin2hex(random_bytes(16)), $ext);
    $path = media_root() . '/' . $relative;
    if (!is_dir(dirname($path)) && !mkdir(dirname($path), 0775, true) && !is_dir(dirname($path))) {
        throw new RuntimeException('Cannot create upload folder');
    }
    if (file_put_contents($path, $bytes, LOCK_EX) === false) {
        throw new RuntimeException('Cannot write upload');
    }
    return $relative;
}

/**
 * Store uploaded images for an owner row the caller has ALREADY authorised.
 * $owner supplies the scope (school_id / classroom_id / student_id). Returns inserted ids.
 */
function attach_uploaded_images(string $ownerType, array $owner, array $user, array $files, ?string $meal = null): array
{
    if (count($files) > MEDIA_MAX_FILES) {
        throw new DemoValidationException('อัปโหลดได้ครั้งละไม่เกิน ' . MEDIA_MAX_FILES . ' รูป');
    }
    $prepared = [];
    foreach ($files as $file) { // validate everything before writing anything
        [$mime, $w, $h] = validate_image_upload($file);
        $prepared[] = [$file, $mime, $w, $h];
    }

    $ids = [];
    foreach ($prepared as [$file, $mime, $w, $h]) {
        [$bytes, $width, $height] = optimise_image($file['tmp_name'], $mime, $w, $h);
        $now = date('Y-m-d H:i:s');
        $row = [
            'school_id'    => $owner['school_id'],
            'classroom_id' => $owner['classroom_id'] ?? null,
            'student_id'   => $ownerType === 'portfolio' ? $owner['student_id'] : null,
            'owner_type'   => $ownerType,
            'owner_id'     => $owner['id'],
            'file_path'    => write_media_file($owner['school_id'], MEDIA_ALLOWED[$mime], $bytes),
            'file_size'    => strlen($bytes),
            'mime_type'    => $mime,
            'width'        => $width,
            'height'       => $height,
            'uploaded_at'  => $now,
            'expires_at'   => media_expires_at($now),
            'deleted_at'   => null,
            'image_status' => 'available',
            'uploaded_by'  => $user['id'],
        ];
        if ($ownerType === 'food' && $meal !== null) {
            $row['meal'] = $meal; // which meal of the day the photo shows (breakfast, lunch, ...)
        }
        authorize(row_in_scope($user, 'mediaFiles', $row), 'ไม่มีสิทธิ์อัปโหลดรูปภาพนี้', ['owner' => $ownerType, 'id' => $owner['id']]);
        $ids[] = store_insert('mediaFiles', $row);
        mk_log('Media', 'image stored', ['owner' => $ownerType, 'owner_id' => $owner['id'], 'bytes' => strlen($bytes), 'size' => "{$width}x{$height}"]);
    }
    return $ids;
}

/**
 * Admin cleanup: delete the PHYSICAL files of expired images in the user's scope and mark
 * their metadata deleted. No database record is removed.
 */
function cleanup_expired_media(array $user): array
{
    $root = realpath(media_root());
    $result = ['files' => 0, 'bytes' => 0];
    store_mutate(function (array &$state) use ($user, $root, &$result) {
        foreach ($state['tables']['mediaFiles'] as &$m) {
            if (!row_in_scope($user, 'mediaFiles', $m) || media_status($m) !== 'expired') {
                continue;
            }
            $path = media_abs_path($m);
            $real = $path !== null ? realpath($path) : false;
            if ($real && $root && str_starts_with($real, $root . DIRECTORY_SEPARATOR) && is_file($real)) {
                unlink($real);
            }
            $m['deleted_at'] = date('Y-m-d H:i:s');
            $m['image_status'] = 'deleted';
            $result['files']++;
            $result['bytes'] += (int) $m['file_size'];
        }
        unset($m);
    });
    mk_log('Media', 'cleanup_expired_media', ['user' => $user['id'], 'school' => $user['school_id']] + $result);
    return $result;
}

/* ============================================================================
 * Demo seed images (cartoon scenes drawn with GD — no real photos of children)
 * ========================================================================= */

/** Create (once) a demo JPEG under uploads/seed/ and return its file metadata. */
function media_seed_file(string $key, int $variant): array
{
    $relative = 'seed/' . preg_replace('/[^a-z0-9\-]/', '', $key) . '.jpg';
    $path = media_root() . '/' . $relative;
    if (!is_file($path)) {
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }
        file_put_contents($path, media_seed_jpeg($variant));
    }
    $size = @getimagesize($path) ?: [800, 600];
    return ['file_path' => $relative, 'file_size' => (int) filesize($path), 'mime_type' => 'image/jpeg', 'width' => $size[0], 'height' => $size[1]];
}

function media_seed_jpeg(int $variant): string
{
    if (!function_exists('imagecreatetruecolor')) {
        return base64_decode('/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/wAALCAABAAEBAREA/8QAFAABAAAAAAAAAAAAAAAAAAAACf/EABQQAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQEAAD8AKp//2Q==');
    }
    [$w, $h] = [800, 600];
    $im = imagecreatetruecolor($w, $h);
    $c = fn (string $hex) => imagecolorallocate($im, hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2)));
    $palettes = [
        ['#DFF4FF', '#8FD9A8', '#FF9EC0', '#FFD66B'], ['#FFF4CC', '#FFC4C4', '#7CC6F2', '#8FD9A8'],
        ['#FFEAF2', '#B79CFF', '#FFC94D', '#7CC6F2'], ['#E6F7EE', '#7CC6F2', '#FF9F7A', '#FFD66B'],
        ['#F1EAFF', '#FFD66B', '#FF9EC0', '#8FD9A8'], ['#FFE9D6', '#FF9F7A', '#7CC6F2', '#B79CFF'],
    ];
    [$bg, $a, $b, $d] = array_map($c, $palettes[$variant % 6]);
    imagefilledrectangle($im, 0, 0, $w, $h, $bg);
    imagefilledrectangle($im, 0, (int) ($h * .72), $w, $h, $a);            // ground / table
    imagefilledellipse($im, 650, 120, 130, 130, $d);                       // sun / lamp
    for ($i = 0; $i < 7; $i++) {                                           // toys / food / paint blobs
        $x = 90 + $i * 100 + ($variant * 37) % 40;
        $y = 300 + (($i * 53 + $variant * 29) % 120);
        imagefilledellipse($im, $x, $y, 70 + ($i % 3) * 20, 70 + ($i % 2) * 25, $i % 2 ? $b : $d);
    }
    imagefilledrectangle($im, 120, 150, 330, 300, $b);                     // block / plate / frame
    imagefilledpolygon($im, [100, 160, 225, 70, 350, 160], $d);
    for ($i = 0; $i < 4000; $i++) {                                        // light "photo" grain
        imagesetpixel($im, random_int(0, $w - 1), random_int(0, $h - 1), $c('#FFFFFF'));
    }
    imagestring($im, 3, 16, $h - 24, 'MYKID DEMO IMAGE #' . $variant, $c('#2F3A56'));
    ob_start();
    imagejpeg($im, null, 72);
    imagedestroy($im);
    return (string) ob_get_clean();
}

/**
 * Seed media rows: per classroom — activity images (today, 2 days ago, ~7 months ago = expired,
 * one already-cleaned = deleted), food images (today, yesterday, ~7 months ago) and portfolio
 * images for the first 3 children (recent + one old expired work).
 */
function seed_media(array &$t, int &$nextId): void
{
    $at = fn (string $date, string $time) => "$date $time";
    $add = function (array $owner, string $type, string $uploadedAt, string $key, int $variant, ?int $studentId = null, bool $deleted = false) use (&$t, &$nextId) {
        $file = $deleted ? ['file_path' => 'seed/removed-' . $key . '.jpg', 'file_size' => 184320 + $variant * 1000, 'mime_type' => 'image/jpeg', 'width' => 800, 'height' => 600]
            : media_seed_file($key, $variant);
        $t['mediaFiles'][] = ['id' => $nextId++, 'school_id' => $owner['school_id'], 'classroom_id' => $owner['classroom_id'] ?? null,
            'student_id' => $studentId, 'owner_type' => $type, 'owner_id' => $owner['id']] + $file + [
            'uploaded_at' => $uploadedAt, 'expires_at' => media_expires_at($uploadedAt),
            'deleted_at' => $deleted ? date('Y-m-d H:i:s', strtotime($uploadedAt . ' +6 months +3 days')) : null,
            'image_status' => $deleted ? 'deleted' : 'available', 'uploaded_by' => null];
    };
    $old = date('Y-m-d', strtotime('-' . SEED_OLD_DAYS . ' day'));
    $dates = ['today' => today(), 'd1' => date('Y-m-d', strtotime('-1 day')), 'd2' => date('Y-m-d', strtotime('-2 day')), 'old' => $old];

    foreach ($t['classrooms'] as $room) {
        $cid = $room['id'];
        $acts = fn (string $date) => array_values(array_filter($t['activities'], fn ($a) => $a['classroom_id'] === $cid && $a['date'] === $date));
        $art = fn (array $list) => $list[2] ?? $list[0]; // the 10:00 activity (art / music / science)

        $today = $acts($dates['today']);
        if ($today) {
            $add($art($today), 'activity', $at($dates['today'], '10:25:00'), "act-$cid-t1", $cid);
            $add($art($today), 'activity', $at($dates['today'], '10:31:00'), "act-$cid-t2", $cid + 1);
            $add($today[5] ?? $today[0], 'activity', $at($dates['today'], '14:20:00'), "act-$cid-t3", $cid + 2);
        }
        if ($d2 = $acts($dates['d2'])) {
            $add($art($d2), 'activity', $at($dates['d2'], '10:40:00'), "act-$cid-d2", $cid + 3);
        }
        if ($o = $acts($dates['old'])) {
            $add($art($o), 'activity', $at($dates['old'], '10:30:00'), "act-$cid-o1", $cid + 4);
            $add($art($o), 'activity', $at($dates['old'], '10:35:00'), "act-$cid-o2", $cid + 5);
            $add($o[5] ?? $o[0], 'activity', $at($dates['old'], '14:10:00'), "act-$cid-o3", $cid, null, true);
        }

        foreach (['today' => '11:05:00', 'd1' => '11:10:00', 'old' => '11:00:00'] as $k => $time) {
            foreach ($t['foodMenus'] as $menu) {
                if ($menu['classroom_id'] === $cid && $menu['date'] === $dates[$k]) {
                    $add($menu, 'food', $at($dates[$k], $time), "food-$cid-$k", $cid + 7);
                }
            }
        }

        // Children with work images: the first 3 of each room + children of multi-child demo parents.
        $siblings = array_merge(...array_map(fn ($u) => count($u['children'] ?? []) > 1 ? $u['children'] : [], $t['users']));
        $roomKids = array_values(array_filter($t['students'], fn ($s) => $s['classroom_id'] === $cid));
        $kids = array_merge(array_slice($roomKids, 0, 3), array_values(array_filter($roomKids, fn ($s) => in_array($s['id'], $siblings, true))));
        foreach ($kids as $i => $kid) {
            $works = array_values(array_filter($t['portfolio'], fn ($p) => $p['student_id'] === $kid['id']));
            foreach (array_slice($works, 0, 2) as $j => $work) {
                $add($work, 'portfolio', $at($work['date'], '15:00:00'), "work-{$kid['id']}-$j", $kid['id'] + $j, $kid['id']);
                if ($j === 0) { // the first work of each child has several images (gallery demo)
                    $add($work, 'portfolio', $at($work['date'], '15:01:00'), "work-{$kid['id']}-{$j}b", $kid['id'] + 4, $kid['id']);
                }
            }
            $oldWork = ['id' => $nextId++, 'school_id' => $kid['school_id'], 'classroom_id' => $cid, 'student_id' => $kid['id'],
                'date' => $old, 'title' => 'ภาพวาดบ้านของฉัน (ภาคเรียนที่แล้ว)', 'category' => 'art', 'art' => $kid['id'] % 6,
                'comment' => 'ผลงานจากภาคเรียนที่แล้ว'];
            $t['portfolio'][] = $oldWork;
            $add($oldWork, 'portfolio', $at($old, '15:00:00'), "work-{$kid['id']}-old", $kid['id'] + 3, $kid['id']);
        }
    }
    mk_log('Media', 'seed_media', ['rows' => count($t['mediaFiles'])]);
}
