<?php
/**
 * kiosk_settings.php — Public kiosk settings endpoint (no auth required)
 *
 * Exposes only the subset of settings that the kiosk page needs:
 *   - default_language
 *   - business_name
 *   - logo_url
 *   - primary_color
 *
 * The admin-only settings.php endpoint remains protected by authMiddleware.
 *
 * GET /api/kiosk_settings.php → returns flat JSON object
 */

header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-store, no-cache, must-revalidate');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}


// Only expose kiosk-safe, non-sensitive keys
$allowed_keys = ['default_language', 'business_name', 'logo_url', 'primary_color'];
$placeholders = implode(',', array_fill(0, count($allowed_keys), '?'));

$stmt = $conn->prepare(
    "SELECT setting_key, setting_value FROM settings WHERE setting_key IN ($placeholders)"
);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'DB error']);
    exit;
}

// Bind dynamically
$types = str_repeat('s', count($allowed_keys));
$stmt->bind_param($types, ...$allowed_keys);
$stmt->execute();
$res = $stmt->get_result();

$settings = [
    'default_language' => 'en',   // fallback defaults
    'business_name'    => '',
    'logo_url'         => '',
    'primary_color'    => '',
];

while ($row = $res->fetch_assoc()) {
    $key = $row['setting_key'];
    $val = $row['setting_value'];
    if (array_key_exists($key, $settings)) {
        $settings[$key] = $val !== null ? $val : '';
    }
}
$stmt->close();

// Ensure default_language is never empty
if (empty(trim($settings['default_language']))) {
    $settings['default_language'] = 'en';
}

echo json_encode(array_merge(['success' => true], $settings));
