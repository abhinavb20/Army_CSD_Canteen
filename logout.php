<?php
session_start();

// Only accept a logout request from a form on this site
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

$sentToken = $_POST['csrf'] ?? '';
$profileOk = hash_equals($_SESSION['profile_csrf'] ?? '', $sentToken);
$adminOk   = hash_equals($_SESSION['admin_csrf'] ?? '', $sentToken);

if (!$profileOk && !$adminOk) {
    http_response_code(403);
    die('Invalid request. Please go back and try again.');
}

// Clear all session data
$_SESSION = [];

// Remove the session cookie
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}

session_destroy();

// Start a fresh session for the login page message
session_start();
$_SESSION['flash_msg'] = 'You have been logged out.';

header("Location: login.php?msg=logged_out");
exit;