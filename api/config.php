<?php
/**
 * config.php — Application-wide configuration constants.
 *
 * HOW TO SET THE SECRET AND DB CREDENTIALS:
 *   Option 1 (recommended for production): Set the environment variables
 *     on your web server / hosting panel.
 *
 *   Option 2 (local): Use the provided .env file.
 */

// Load vendor autoload to ensure Dotenv is available
require_once __DIR__ . '/../vendor/autoload.php';

// Load the .env file if it exists
if (class_exists('Dotenv\Dotenv')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->safeLoad();
}

define(
    'KIOSK_HMAC_SECRET',
    $_ENV['KIOSK_HMAC_SECRET'] ?? getenv('KIOSK_HMAC_SECRET') ?: 'CHANGE_ME_before_deploying'
);

define('DB_HOST', $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: 'localhost');
define('DB_USER', $_ENV['DB_USER'] ?? getenv('DB_USER') ?: 'root');
define('DB_PASS', $_ENV['DB_PASS'] ?? getenv('DB_PASS') ?: '');
define('DB_NAME', $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: 'feedbackiq');

// SMTP Settings for password reset
define('SMTP_HOST', $_ENV['SMTP_HOST'] ?? getenv('SMTP_HOST') ?: 'smtp.gmail.com');
define('SMTP_PORT', $_ENV['SMTP_PORT'] ?? getenv('SMTP_PORT') ?: 587);
define('SMTP_USER', $_ENV['SMTP_USER'] ?? getenv('SMTP_USER') ?: 'your_email@gmail.com');
define('SMTP_PASS', $_ENV['SMTP_PASS'] ?? getenv('SMTP_PASS') ?: 'your_app_password');
define('SMTP_FROM_EMAIL', $_ENV['SMTP_FROM_EMAIL'] ?? getenv('SMTP_FROM_EMAIL') ?: 'no-reply@feedbackkiosk.local');
define('SMTP_FROM_NAME', $_ENV['SMTP_FROM_NAME'] ?? getenv('SMTP_FROM_NAME') ?: 'Feedback Kiosk Admin');
define('APP_URL', $_ENV['APP_URL'] ?? getenv('APP_URL') ?: 'http://localhost/phpl=kios/kiosk%20pp');

// ── Security: warn if HMAC secret was never changed ──────────
if (KIOSK_HMAC_SECRET === 'CHANGE_ME_before_deploying') {
    error_log('[SECURITY WARNING] FeedbackIQ: Default HMAC secret is in use. '
        . 'Set KIOSK_HMAC_SECRET in your .env or server environment before deploying to production.');
}
