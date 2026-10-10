<?php
/**
 * Image endpoint — the ONLY way an uploaded image reaches the browser.
 *
 * ?id=<media id> is just a lookup key: the viewer must be logged in and the media row must be
 * inside the viewer's scope (school → classroom → student). Files under storage/ are blocked
 * from direct HTTP access, and the stored path is never exposed. Expired or deleted images
 * return 410 with a small placeholder instead of the old file.
 */
declare(strict_types=1);

$user = current_user();
$media = find_row('mediaFiles', (int) ($_GET['id'] ?? 0));

if (!$user || !$media || !can_view_media($media, $user)) {
    mk_log('Media', 'view DENIED', ['user' => $user['id'] ?? null, 'id' => $_GET['id'] ?? null]);
    http_response_code(404); // same answer for "missing" and "not yours" — no enumeration
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'ไม่พบรูปภาพ';
    exit;
}

header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'");
header('Cache-Control: private, max-age=300');

if (media_status($media) !== 'available') {
    http_response_code(410);
    header('Content-Type: image/svg+xml; charset=UTF-8');
    echo '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 240"><rect width="320" height="240" fill="#EEF0F6"/>'
        . '<text x="160" y="110" font-size="48" text-anchor="middle">📷</text>'
        . '<text x="160" y="160" font-size="16" text-anchor="middle" fill="#6B7591" font-family="sans-serif">รูปภาพหมดเวลาเก็บไฟล์</text></svg>';
    exit;
}

$path = media_abs_path($media);
$mime = isset(MEDIA_ALLOWED[$media['mime_type']]) ? $media['mime_type'] : 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline; filename="mykid-' . $media['id'] . '.' . (MEDIA_ALLOWED[$mime] ?? 'bin') . '"');
readfile($path);
