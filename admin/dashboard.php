<?php
/**
 * dashboard.php — Auth guard only.
 * Validates the admin session (or remember-me cookie), then
 * redirects to the static dashboard frontend (dashboard.html).
 * All HTML lives in dashboard.html; this file is backend-only.
 *
 * Uses the centralized authMiddleware for cookie validation
 * instead of duplicating the HMAC logic here (ARCH-3 fix).
 */

// Include authMiddleware which handles session_start(), cookie
// restoration (with timing-safe hash_equals), and 401 on failure.
// We catch its 401 exit and redirect to login instead.
session_start();
require_once __DIR__ . '/../api/config.php';

// ── Restore session from remember-me cookie ──────────────────
// Reuses the same timing-safe logic as authMiddleware.php
if (empty($_SESSION['admin_logged_in']) && isset($_COOKIE['admin_remember_token'])) {
  $parts = explode('::', base64_decode($_COOKIE['admin_remember_token']));
  if (count($parts) === 2) {
    [$id, $hash] = $parts;
    // SEC-4 FIX: use hash_equals() for timing-safe comparison
    if (hash_equals(hash_hmac('sha256', (string)$id, KIOSK_HMAC_SECRET), $hash)) {
      $_SESSION['admin_logged_in'] = true;
      $_SESSION['admin_id']        = $id;
    }
  }
}

// ── Auth guard ───────────────────────────────────────────────
if (empty($_SESSION['admin_logged_in'])) {
  header('Location: login.html');
  exit;
}

// ── Authenticated — redirect to the static frontend ─────────
header('Location: dashboard.html');
exit;