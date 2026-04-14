<?php
/**
 * get_feedback_count.php — Get total feedback count
 *
 * GET /api/get_feedback_count.php
 * Requires admin authentication.
 */

require_once __DIR__ . '/authMiddleware.php';
header('Content-Type: application/json');
// SEC-2 FIX: Removed wildcard CORS — admin-only, auth-gated endpoint.

require_once __DIR__ . '/db.php';


// LOW-7 FIX: COUNT(*) has no user-supplied parameters, so a prepared statement
// adds no security benefit. A direct query is simpler and marginally faster.
$result = $conn->query("SELECT COUNT(*) AS total FROM feedback");
if (!$result) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'DB error: ' . $conn->error]);
    exit;
}
$total = (int) $result->fetch_assoc()['total'];

echo json_encode(['success' => true, 'count' => $total]);
