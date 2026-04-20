<?php
/**
 * api/change_password.php — Change admin password
 * Requires an active session. Verifies current password, then saves new hash.
 */



// ── Method guard ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    // LOW-2 FIX: Standardized to 'error' key (was 'message') for consistency
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

// CODE-1 FIX: Do NOT trim() passwords — leading/trailing whitespace is valid and
// intentional in a password. Trimming would corrupt credentials silently.
$current_password = $body['current_password'] ?? '';
$new_password     = $body['new_password']     ?? '';
$confirm_password = $body['confirm_password'] ?? '';

// ── Password strength helper ──────────────────────────────────
/**
 * Mirrors the JS getPasswordStrength() scoring model.
 *
 * Score:
 *   Length:  8–11 → +1 | 12–15 → +2 | 16+ → +3
 *   Types:   lowercase +1 | uppercase +1 | digit +1 | symbol +1
 *   Penalty: dominant char >60 % of length → −1  (e.g. "aaaaaaaa")
 *
 * Returns: 'weak' | 'medium' | 'strong'
 */
function get_password_strength(string $pw): string {
    $len   = strlen($pw);
    $score = 0;

    if      ($len >= 16) $score += 3;
    elseif  ($len >= 12) $score += 2;
    elseif  ($len >= 8)  $score += 1;

    if (preg_match('/[a-z]/', $pw))        $score += 1;
    if (preg_match('/[A-Z]/', $pw))        $score += 1;
    if (preg_match('/[0-9]/', $pw))        $score += 1;
    if (preg_match('/[^A-Za-z0-9]/', $pw)) $score += 1;

    // Repetition penalty
    $counts   = array_count_values(str_split($pw));
    $maxCount = max($counts);
    if ($len > 0 && ($maxCount / $len) > 0.6) $score -= 1;

    if ($score >= 5) return 'strong';
    if ($score >= 3) return 'medium';
    return 'weak';
}

// ── Validation ────────────────────────────────────────────────
if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
    echo json_encode(['success' => false, 'error' => 'All fields are required']);
    exit;
}

if ($new_password !== $confirm_password) {
    echo json_encode(['success' => false, 'error' => 'New passwords do not match']);
    exit;
}

if (strlen($new_password) < 8) {
    echo json_encode(['success' => false, 'error' => 'New password must be at least 8 characters']);
    exit;
}

if (get_password_strength($new_password) === 'weak') {
    echo json_encode(['success' => false, 'error' => 'Password is too weak. Try a longer password or add uppercase letters, numbers, or symbols.']);
    exit;
}

// ── Fetch stored hash ─────────────────────────────────────────
$admin_id = (int) $_SESSION['admin_id'];
$stmt = $conn->prepare("SELECT password FROM admins WHERE id = ?");
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

// ── Hash & update ─────────────────────────────────────────────
$new_hash = password_hash($new_password, PASSWORD_BCRYPT, ['cost' => 12]);

$upd = $conn->prepare("UPDATE admins SET password = ? WHERE id = ?");
if (!$upd) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
    exit;
}
$upd->bind_param('si', $new_hash, $admin_id);
$upd->execute();
$upd->close();

echo json_encode(['success' => true, 'message' => 'Password updated successfully']);
