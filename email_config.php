<?php
/**
 * Gmail SMTP settings for PHPMailer.
 *
 * For Gmail, create an App Password:
 * Google Account -> Security -> 2-Step Verification -> App passwords.
 * Use that 16-character app password here, not your normal Gmail password.
 *
 * You can also set SMTP_USERNAME and SMTP_PASSWORD as environment variables.
 */
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_SECURE', 'tls');
define('SMTP_USERNAME', getenv('SMTP_USERNAME') ?: 'reelstatus6@gmail.com');
define('SMTP_PASSWORD', getenv('SMTP_PASSWORD') ?: 'pluislruyuaaxcey');
define('SMTP_FROM_EMAIL', SMTP_USERNAME);
define('SMTP_FROM_NAME', 'AI Hub');
?>
