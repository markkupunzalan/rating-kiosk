<?php
// CODE-4 FIX: Use __DIR__-relative paths for consistent resolution regardless
// of the PHP include_path or the directory from which the script is called.
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["success" => false, "error" => "Method not allowed"]);
    exit;
}

// SEC-7 FIX: Read body from php://input and decode as JSON, consistent with
// all other API endpoints. $_POST only works with form-encoded requests.
$raw = file_get_contents('php://input');
$body = json_decode($raw, true);
$email = trim($body['email'] ?? '');

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(["success" => false, "error" => "Valid email required"]);
    exit;
}

// Generate token
$raw_token = bin2hex(random_bytes(32));
$hashed_token = hash('sha256', $raw_token);

// Update database if email exists
$stmt = $conn->prepare("UPDATE admins SET reset_token = ?, reset_token_expires_at = DATE_ADD(NOW(), INTERVAL 30 MINUTE) WHERE email = ?");
$stmt->bind_param("ss", $hashed_token, $email);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    // Send email using PHPMailer
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;

        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($email);

        $reset_link = APP_URL . "/admin/reset-password.html?token=" . $raw_token;

        $mail->isHTML(true);
        $mail->Subject = 'Admin Password Reset Request';
        $mail->Body = "You have requested to reset your admin password.<br><br>Please click the following link to reset your password. This link is valid for 30 minutes.<br><br><a href='{$reset_link}'>{$reset_link}</a><br><br>If you did not request this, please ignore this email.";
        $mail->AltBody = "You have requested to reset your admin password.\n\nPlease copy and paste the following link into your browser to reset your password. This link is valid for 30 minutes.\n\n{$reset_link}\n\nIf you did not request this, please ignore this email.";

        // We silence the send error to prevent enumeration via email failure states
        @$mail->send();
    } catch (Exception $e) {
        // Log the error but do not leak error to user
        error_log("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
    }
}

// Always return success to prevent email enumeration
echo json_encode([
    "success" => true,
    "message" => "If that email exists in our system, a password reset link has been sent."
]);
