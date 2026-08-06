<?php
/**
 * Gmail SMTP settings for PHPMailer.
 *
 * For Gmail, create an App Password:
 * Google Account -> Security -> 2-Step Verification -> App passwords.
 * Use that 16-character app password here, not your normal Gmail password.
 *
 * Credentials are read from environment variables only — nothing is
 * hardcoded here. Set these in your local .env / server environment:
 *   SMTP_USERNAME=your_gmail_address@gmail.com
 *   SMTP_PASSWORD=your_16_char_app_password
 */
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_SECURE', 'tls');
define('SMTP_USERNAME', getenv('SMTP_USERNAME') ?: '');
define('SMTP_PASSWORD', getenv('SMTP_PASSWORD') ?: '');
define('SMTP_FROM_EMAIL', SMTP_USERNAME);
define('SMTP_FROM_NAME', 'AI Hub');
