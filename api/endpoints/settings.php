<?php
/**
 * settings.php — General settings endpoint
 *
 * GET  /api/settings.php   → returns flat JSON object of all settings
 * POST /api/settings.php   → updates key-value pairs
 */

// SEC-2 FIX: Removed wildcard CORS — admin-only endpoint
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }


$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $res = $conn->query("SELECT setting_key, setting_value FROM settings");
    $settings = [];
    while ($row = $res->fetch_assoc()) {
        $key = $row['setting_key'];
        $val = $row['setting_value'];

        // Convert string booleans/numbers dynamically for the frontend
        if (in_array($key, ['anonymous_feedback'])) {
            $val = (bool)$val;
        }
        $settings[$key] = $val;
    }
    echo json_encode(array_merge(['success' => true], $settings));
    exit;
}

if ($method === 'POST') {
    $raw = file_get_contents('php://input');
    $body = json_decode($raw, true);

    if (!is_array($body)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid JSON payload']);
        exit;
    }

    // SEC-6 FIX: Only allow known settings keys to be persisted
    // LOW-11: The following keys are stored and returned but are not yet
    // enforced by backend business logic. They are reserved for future implementation:
    //   anonymous_feedback — when true, should suppress PII collection in feedback rows
    $allowedKeys = [
        'business_name', 'default_language',
        'logo_url', 'primary_color',
        'anonymous_feedback',
    ];

    $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'DB prepare failed: ' . $conn->error]);
        exit;
    }

    foreach ($body as $key => $val) {
        // Skip unknown keys to prevent arbitrary data injection
        if (!in_array($key, $allowedKeys, true)) {
            continue;
        }

        // Convert booleans to 1/0, arrays to JSON, nulls to empty string
        if (is_bool($val)) {
            $val = $val ? '1' : '0';
        } elseif (is_array($val)) {
            $val = json_encode($val);
        } elseif ($val === null) {
            $val = '';
        } else {
            $val = (string)$val;
        }

        $stmt->bind_param('sss', $key, $val, $val);
        $stmt->execute();
    }
    $stmt->close();

    echo json_encode(['success' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method not allowed']);
