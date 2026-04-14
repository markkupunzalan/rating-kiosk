<?php
/**
 * authMiddleware.php — Centralized authentication check
 * Starts the session, parses the remember-me cookie if present securely,
 * and ensures the user is logged in. Returns a 401 Unauthorized if not.
 */
session_start();

require_once __DIR__ . '/config.php';

// ── Restore session from remember-me cookie ──────────────────
if (empty($_SESSION['admin_logged_in']) && isset($_COOKIE['admin_remember_token'])) {
    $parts = explode('::', base64_decode($_COOKIE['admin_remember_token']));
    if (count($parts) === 2) {
        $id = $parts[0];
        $hash = $parts[1];
        if (hash_equals(hash_hmac('sha256', (string)$id, KIOSK_HMAC_SECRET), $hash)) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = $id;
        }
    }
}

// ── Auth guard ───────────────────────────────────────────────
if (empty($_SESSION['admin_logged_in'])) {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}
