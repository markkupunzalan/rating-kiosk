<?php
/**
 * Global API Router
 * All requests are funneled here via .htaccess
 */
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

$requestPath = $_GET['request'] ?? '';

// Clean the request path to prevent directory traversal
$route = basename(str_replace('.php', '', $requestPath));

if (!$route) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No endpoint provided']);
    exit;
}

// ── Define Public Endpoints ──
$publicEndpoints = [
    'login',
    'forgot_password',
    'reset_password',
    'feedback',
    'kiosk_settings'
];

// ── Define Auth-Gated Endpoints ──
$privateEndpoints = [
    'analytics',
    'authCheck',
    'change_password',
    'change_username',
    'clear_feedback',
    'get_feedback_count',
    'logout',
    'settings',
    'upload_logo'
];

$endpointFile = __DIR__ . '/endpoints/' . $route . '.php';

if (!file_exists($endpointFile)) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Endpoint not found']);
    exit;
}

if (in_array($route, $privateEndpoints)) {
    // ── Execute Auth Middleware ──
    require_once __DIR__ . '/authMiddleware.php';
} elseif (!in_array($route, $publicEndpoints)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Endpoint not allowed']);
    exit;
}

// ── Execute the actual endpoint ──
require_once $endpointFile;
