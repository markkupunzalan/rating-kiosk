<?php
/**
 * api/change_username.php — Change admin username
 * Requires an active session. Validates new username, checks uniqueness, then saves.
 *
 * LOW-2 FIX: Standardized all error response payloads to use 'error' key.
 * Success response retains 'message' as a human-readable description.
 */



// ── Method guard ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// ── Parse body ────────────────────────────────────────────────
$raw  = file_get_contents('php://input');
$body = json_decode($raw, true);

if (!is_array($body)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON payload']);
    exit;
}

$new_username     = trim($body['new_username']     ?? '');
$current_password = trim($body['current_password'] ?? '');

// ── Validation ────────────────────────────────────────────────
if (empty($new_username)) {
    echo json_encode(['success' => false, 'error' => 'Username cannot be empty']);
    exit;
}

if (strlen($new_username) < 3) {
    echo json_encode(['success' => false, 'error' => 'Username must be at least 3 characters']);
    exit;
}

if (strlen($new_username) > 50) {
    echo json_encode(['success' => false, 'error' => 'Username must be 50 characters or fewer']);
    exit;
}

if (!preg_match('/^[a-zA-Z0-9_.\-]+$/', $new_username)) {
    echo json_encode(['success' => false, 'error' => 'Username may only contain letters, numbers, underscores, hyphens, and dots']);
    exit;
}

if (empty($current_password)) {
    echo json_encode(['success' => false, 'error' => 'Current password is required to change username']);
    exit;
}

// ── Fetch stored hash ─────────────────────────────────────────
$admin_id = (int) $_SESSION['admin_id'];
$stmt = $conn->prepare("SELECT username, password FROM admins WHERE id = ?");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
    exit;
}
$stmt->bind_param('i', $admin_id);
$stmt->execute();
$result = $stmt->get_result();
$admin  = $result->fetch_assoc();
$stmt->close();

if (!$admin) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Admin account not found']);
    exit;
}

// ── Verify current password ───────────────────────────────────
if (!password_verify($current_password, $admin['password'])) {
    echo json_encode(['success' => false, 'error' => 'Current password is incorrect']);
    exit;
}

// ── Check same username ───────────────────────────────────────
if (strtolower($admin['username']) === strtolower($new_username)) {
    echo json_encode(['success' => false, 'error' => 'New username is the same as the current one']);
    exit;
}

// ── Check uniqueness ─────────────────────────────────────────
$check = $conn->prepare("SELECT id FROM admins WHERE username = ? AND id != ?");
if (!$check) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
    exit;
}
$check->bind_param('si', $new_username, $admin_id);
$check->execute();
$check->store_result();
if ($check->num_rows > 0) {
    $check->close();
    echo json_encode(['success' => false, 'error' => 'That username is already taken']);
    exit;
}
$check->close();

// ── Update ────────────────────────────────────────────────────
$upd = $conn->prepare("UPDATE admins SET username = ? WHERE id = ?");
if (!$upd) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
    exit;
}
$upd->bind_param('si', $new_username, $admin_id);
$upd->execute();
$upd->close();

// Update session so the topbar reflects the new name immediately
$_SESSION['admin_username'] = $new_username;

echo json_encode(['success' => true, 'message' => 'Username updated successfully', 'username' => $new_username]);
