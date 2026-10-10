<?php
/** Logout: destroy the session and return to the login page. */
declare(strict_types=1);

$previousRole = current_user()['role'] ?? null;
auth_logout(); // session_destroy()
// Switching role from the entry page: continue to the chosen demo account (validated values only).
$nextRole = (string) ($_GET['role'] ?? '');
$nextPhone = normalize_phone((string) ($_GET['demo'] ?? ''));
if (in_array($nextRole, pkg()['roles'], true) && strlen($nextPhone) === 10) {
    redirect('login.php?' . http_build_query(['role' => $nextRole, 'demo' => $nextPhone, 'go' => 1]));
}
redirect('login.php?logout=1' . ($previousRole ? '&role=' . $previousRole : ''));
