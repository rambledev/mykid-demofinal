<?php
/**
 * Logout — POST + CSRF (forms in the app bar / sidebar / account page).
 * DEMO_MODE: GET /logout?role=…&demo=… switches to another demo account from the home page.
 */
declare(strict_types=1);

$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
if ($isPost && !verify_csrf($_POST['csrf'] ?? null)) {
    mk_log('Auth', 'logout CSRF failed');
    redirect('');
}
$nextRole = (string) ($_GET['role'] ?? '');
$nextPhone = normalize_phone((string) ($_GET['demo'] ?? ''));
$demoSwitch = demo_mode() && in_array($nextRole, pkg()['roles'], true) && strlen($nextPhone) === 10;
if (!$isPost && !$demoSwitch) {
    redirect(''); // a plain GET never logs anybody out (no logout CSRF)
}

$previousRole = current_user()['role'] ?? null;
auth_logout(); // session_destroy()
if ($demoSwitch) {
    redirect('login?' . http_build_query(['role' => $nextRole, 'demo' => $nextPhone, 'go' => 1]));
}
redirect('login?logout=1' . ($previousRole ? '&role=' . $previousRole : ''));
