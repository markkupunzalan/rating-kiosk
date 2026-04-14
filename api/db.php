<?php
/**
 * db.php — Shared database connection
 *
 * Sets BOTH PHP and MySQL session timezone to Asia/Manila (UTC+8)
 * so that CURDATE(), NOW(), and date comparisons are all consistent
 * with Philippine Standard Time regardless of server defaults.
 *
 * Without this, CURDATE() in MySQL may return yesterday's date while
 * the PHP application thinks it is already the next day, causing the
 * "Today" dashboard filter to return zero results.
 */

require_once __DIR__ . '/config.php';

// ── 1. Align PHP's own timezone ───────────────────────────────
// Reads from .env / environment if set, falls back to Asia/Manila.
$appTimezone = $_ENV['APP_TIMEZONE'] ?? getenv('APP_TIMEZONE') ?: 'Asia/Manila';
date_default_timezone_set($appTimezone);

// ── 2. Open the database connection ──────────────────────────
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Database connection failed',
    ]);
    exit;
}

$conn->set_charset('utf8mb4');

// ── 3. Align MySQL session timezone to match PHP ──────────────
// Convert PHP DateTimeZone offset to the ±HH:MM format MySQL expects.
// Example: Asia/Manila → +08:00
$offset  = (new DateTimeZone($appTimezone))->getOffset(new DateTime());
$sign    = $offset >= 0 ? '+' : '-';
$abs     = abs($offset);
$hours   = intdiv($abs, 3600);
$minutes = ($abs % 3600) / 60;
$tzStr   = sprintf('%s%02d:%02d', $sign, $hours, $minutes);

$conn->query("SET time_zone = '{$tzStr}'");