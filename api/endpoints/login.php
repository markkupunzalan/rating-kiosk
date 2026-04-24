<?php
/**
 * api/login.php — Handles admin authentication
 *
 * LOW-2 FIX: Standardized error response key to 'error' (was 'message').
 * All other endpoints use 'error' for failure payloads. login.js already
 * reads data.message || data.error so this change is backward-compatible.
 */


$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$raw = file_get_contents('php://input');
$body = json_decode($raw, true);

if (!is_array($body)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON payload']);
    exit;
}

$username = $body['username'] ?? '';
$password = $body['password'] ?? '';
$rememberMe = $body['rememberMe'] ?? false;

if (empty($username) || empty($password)) {
    echo json_encode(['success' => false, 'error' => 'Username and password are required']);
    exit;
}

$stmt = $conn->prepare("SELECT id, username, password FROM admins WHERE username = ?");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
    exit;
}

$stmt->bind_param('s', $username);
$stmt->execute();
$result = $stmt->get_result();
$admin = $result->fetch_assoc();
$stmt->close();

if ($admin && password_verify($password, $admin['password'])) {
    // Valid credentials
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_id'] = $admin['id'];
    $_SESSION['admin_username'] = $admin['username'];

    if ($rememberMe) {
        $mac = hash_hmac('sha256', (string)$admin['id'], KIOSK_HMAC_SECRET);
        $cookie_value = base64_encode($admin['id'] . '::' . $mac);
        // expire in 30 days — with security flags
        setcookie('admin_remember_token', $cookie_value, [
            'expires'  => time() + (86400 * 30),
            'path'     => '/',
            'httponly'  => true,
            'samesite' => 'Lax',
            'secure'   => false, // set true in production (HTTPS)
        ]);
    }

    echo json_encode(['success' => true]);
} else {
    // Invalid credentials — deliberately generic to avoid username enumeration
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Invalid username or password']);
}
