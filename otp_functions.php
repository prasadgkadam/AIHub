<?php
// OTP Generation and Email Functions

use PHPMailer\PHPMailer\Exception as MailException;
use PHPMailer\PHPMailer\PHPMailer;

require_once __DIR__ . "/email_config.php";
require_once __DIR__ . "/lib/PHPMailer/Exception.php";
require_once __DIR__ . "/lib/PHPMailer/PHPMailer.php";
require_once __DIR__ . "/lib/PHPMailer/SMTP.php";

function generateOTP($length = 6) {
    $otp = '';
    for ($i = 0; $i < $length; $i++) {
        $otp .= random_int(0, 9);
    }
    return $otp;
}

function sendOTPEmail($email, $otp) {
    $subject = "Your Password Reset OTP - AI Hub";
    $message = "
    <html>
        <body style='font-family: Arial, sans-serif; background-color: #f4f4f4; padding: 20px;'>
            <div style='background-color: white; padding: 30px; border-radius: 8px; max-width: 500px; margin: 0 auto;'>
                <h2 style='color: #333; text-align: center;'>Password Reset Code</h2>
                <p style='color: #666; font-size: 16px;'>Hi,</p>
                <p style='color: #666; font-size: 16px;'>You requested a password reset for your AI Hub account. Here is your One-Time Password (OTP):</p>

                <div style='background-color: #f0f0f0; padding: 20px; border-radius: 6px; text-align: center; margin: 20px 0;'>
                    <span style='font-size: 32px; font-weight: bold; color: #0066cc; letter-spacing: 5px;'>" . htmlspecialchars($otp) . "</span>
                </div>

                <p style='color: #999; font-size: 14px;'>This OTP will expire in 5 minutes.</p>
                <p style='color: #999; font-size: 14px;'>If you did not request this, you can ignore this email.</p>

                <hr style='border: none; border-top: 1px solid #ddd; margin: 20px 0;'>
                <p style='color: #999; font-size: 12px; text-align: center;'>2026 AI Hub. All rights reserved.</p>
            </div>
        </body>
    </html>
    ";

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Invalid email address.'];
    }

    if (
        SMTP_USERNAME === 'your-gmail-address@gmail.com' ||
        SMTP_PASSWORD === 'your-gmail-app-password' ||
        empty(SMTP_USERNAME) ||
        empty(SMTP_PASSWORD)
    ) {
        return ['success' => false, 'message' => 'Gmail SMTP is not configured. Add your Gmail address and app password in email_config.php.'];
    }

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port = SMTP_PORT;
        $mail->CharSet = 'UTF-8';

        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $message;
        $mail->AltBody = "Your AI Hub password reset OTP is {$otp}. It will expire in 5 minutes.";

        $mail->send();
        return ['success' => true, 'message' => 'OTP sent to your email.'];
    } catch (MailException $e) {
        return ['success' => false, 'message' => 'Unable to send OTP email. Please check Gmail SMTP settings.'];
    }
}

function storeOTP($conn, $email, $otp) {
    // Delete any existing OTPs for this email
    $conn->prepare("DELETE FROM otp_verification WHERE email = ?")->execute([$email]);

    // Insert new OTP with 5-minute expiry (add 10 second buffer for page load)
    $expiry = date('Y-m-d H:i:s', strtotime('+5 minutes +10 seconds'));
    $conn->prepare("INSERT INTO otp_verification (email, otp, expiry, attempts) VALUES (?, ?, ?, 0)")
         ->execute([$email, $otp, $expiry]);

    return true;
}

function verifyOTP($conn, $email, $otp) {
    $stmt = $conn->prepare("SELECT id, attempts FROM otp_verification WHERE email = ? AND otp = ? AND expiry > NOW()");
    $stmt->execute([$email, $otp]);

    if ($stmt->rowCount() == 0) {
        return ['success' => false, 'message' => 'Invalid or expired OTP'];
    }

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    // Check attempts
    if ($row['attempts'] >= 3) {
        $conn->prepare("DELETE FROM otp_verification WHERE email = ?")->execute([$email]);
        return ['success' => false, 'message' => 'Maximum attempts exceeded. Please request a new OTP'];
    }

    // OTP is valid, delete it
    $conn->prepare("DELETE FROM otp_verification WHERE email = ?")->execute([$email]);

    return ['success' => true, 'message' => 'OTP verified successfully'];
}

function incrementOTPAttempts($conn, $email) {
    $stmt = $conn->prepare("SELECT attempts FROM otp_verification WHERE email = ?");
    $stmt->execute([$email]);

    if ($stmt->rowCount() > 0) {
        $conn->prepare("UPDATE otp_verification SET attempts = attempts + 1 WHERE email = ?")
             ->execute([$email]);
    }
}

function isOTPExpired($conn, $email) {
    $stmt = $conn->prepare("SELECT expiry FROM otp_verification WHERE email = ? AND expiry <= NOW()");
    $stmt->execute([$email]);
    return $stmt->rowCount() > 0;
}

function getRemainingTime($conn, $email) {
    $stmt = $conn->prepare("SELECT TIMESTAMPDIFF(SECOND, NOW(), expiry) as seconds FROM otp_verification WHERE email = ?");
    $stmt->execute([$email]);

    if ($stmt->rowCount() > 0) {
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $seconds = $row['seconds'];

        return max(0, $seconds);
    }

    return 0;
}

?>
