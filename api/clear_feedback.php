<?php
/**
 * clear_feedback.php — Clear all feedback endpoints
 *
 * POST or DELETE /api/clear_feedback.php
 * Requires admin authentication.
 */

require_once __DIR__ . '/authMiddleware.php';
header('Content-Type: application/json');
// SEC-2 FIX: Removed wildcard CORS — admin-only endpoint
header('Access-Control-Allow-Methods: POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

require_once __DIR__ . '/db.php';


$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST' || $method === 'DELETE') {
    if (!$conn->query("TRUNCATE TABLE feedback")) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'DB error: ' . $conn->error]);
        exit;
    }

    echo json_encode(['success' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method not allowed']);
