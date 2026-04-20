<?php
/**
 * upload_logo.php — Logo image upload endpoint
 *
 * POST /api/upload_logo.php  (multipart/form-data, field: "logo")
 *   → Converts the uploaded image to a base64 data URI and stores it
 *     in the settings table. Does NOT save to disk.
 *   → Returns: { success: true, logo_url: "data:image/png;base64,..." }
 */

header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Validate upload
if (empty($_FILES['logo']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
    $errCode = $_FILES['logo']['error'] ?? -1;
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Upload failed (code ' . $errCode . ')']);
    exit;
}

$file = $_FILES['logo'];
$mimeType = mime_content_type($file['tmp_name']);
$allowed = ['image/png', 'image/jpeg', 'image/gif', 'image/webp', 'image/svg+xml'];

if (!in_array($mimeType, $allowed, true)) {
    http_response_code(415);
    echo json_encode(['success' => false, 'error' => 'Unsupported file type: ' . $mimeType]);
    exit;
}

// Max 5 MB
if ($file['size'] > 5 * 1024 * 1024) {
    http_response_code(413);
    echo json_encode(['success' => false, 'error' => 'File too large (max 5 MB)']);
    exit;
}

// Build a safe filename
$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$ext = preg_replace('/[^a-zA-Z0-9]/', '', $ext); // strip weird chars
$filename = 'logo_' . bin2hex(random_bytes(8)) . '.' . strtolower($ext);

$uploadDir = dirname(__DIR__, 2) . '/uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$destination = $uploadDir . $filename;

if (!move_uploaded_file($file['tmp_name'], $destination)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not save file']);
    exit;
}

// Relative URL to be stored and served
$logoUrl = 'uploads/' . $filename;

// Persist to the settings table

$stmt = $conn->prepare(
    "INSERT INTO settings (setting_key, setting_value)
     VALUES ('logo_url', ?)
     ON DUPLICATE KEY UPDATE setting_value = ?"
);
if ($stmt) {
    $stmt->bind_param('ss', $logoUrl, $logoUrl);
    $stmt->execute();
    $stmt->close();
}

echo json_encode(['success' => true, 'logo_url' => $logoUrl]);
