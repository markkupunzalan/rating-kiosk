<?php
/**
 * api/logout.php — Destroys admin session
 */
session_start();

// Unset all session variables
$_SESSION = array();

// Destroy session cookies
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Clear the remember me cookie
if (isset($_COOKIE['admin_remember_token'])) {
    setcookie('admin_remember_token', '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'httponly'  => true,
        'samesite' => 'Lax',
        'secure'   => false, // set true in production (HTTPS)
    ]);
}

// Support AJAX calls or direct navigation
if (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
} else {
    header("Location: ../admin/login.html");
}
exit;
