<?php
// CODE-4 FIX: Use __DIR__-relative paths for consistent resolution.
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["success" => false, "error" => "Method not allowed"]);
    exit;
}

// SEC-8 FIX: Read from php://input JSON body — consistent with all other endpoints.
$raw      = file_get_contents('php://input');
$body     = json_decode($raw, true);
$token    = $body['token']    ?? '';
$password = $body['password'] ?? '';

if (empty($token) || empty($password)) {
    echo json_encode(["success" => false, "error" => "Token and new password are required"]);
    exit;
}

if (strlen($password) < 8) {
    echo json_encode(["success" => false, "error" => "Password must be at least 8 characters"]);
    exit;
}

$hashed_token = hash('sha256', $token);

// Find the user with this token that hasn't expired
$stmt = $conn->prepare("SELECT id FROM admins WHERE reset_token = ? AND reset_token_expires_at > NOW()");
$stmt->bind_param("s", $hashed_token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(["success" => false, "error" => "Invalid or expired reset token"]);
    exit;
}

$row = $result->fetch_assoc();
$admin_id = $row['id'];

// Securely hash the new password
$password_hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

// Update the password and invalidate the token
$update_stmt = $conn->prepare("UPDATE admins SET password = ?, reset_token = NULL, reset_token_expires_at = NULL WHERE id = ?");
$update_stmt->bind_param("si", $password_hash, $admin_id);
$update_stmt->execute();

if ($update_stmt->affected_rows > 0) {
    echo json_encode(["success" => true, "message" => "Password has been successfully reset"]);
} else {
    echo json_encode(["success" => false, "error" => "Failed to update password"]);
}
