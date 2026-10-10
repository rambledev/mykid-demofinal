<?php
/** Logout — POST + CSRF only (forms in the app bar / sidebar / account page). */
declare(strict_types=1);

if (!verify_csrf($_POST['csrf'] ?? null)) {
    mk_log('Auth', 'logout CSRF failed');
    redirect('');
}
$previousRole = current_user()['role'] ?? null;
auth_logout(); // session_destroy()
redirect('login?logout=1' . ($previousRole ? '&role=' . $previousRole : ''));
