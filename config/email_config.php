<?php
/**
 * Email Configuration
 * 
 * This file defines constants for SMTP email sending.
 * Credentials are loaded from the .env file for security.
 */

// Load environment variables from .env file
$envFile = dirname(__DIR__) . '/.env';

if (file_exists($envFile)) {
    $env = parse_ini_file($envFile);
}

if (isset($env) && $env !== false) {
    // SMTP Configuration - load from .env
    define('SMTP_HOST', $env['SMTP_HOST'] ?? '');
    define('SMTP_PORT', isset($env['SMTP_PORT']) ? (int) $env['SMTP_PORT'] : 587);
    define('SMTP_USER', $env['SMTP_USER'] ?? '');
    define('SMTP_PASS', $env['SMTP_PASS'] ?? '');
    define('SMTP_FROM_EMAIL', $env['SMTP_FROM_EMAIL'] ?? 'noreply@tachyon.rf.gd');
    define('SMTP_FROM_NAME', $env['SMTP_FROM_NAME'] ?? 'Tachyon Task Manager');
    define('SMTP_ENCRYPTION', $env['SMTP_ENCRYPTION'] ?? 'tls');
    define('APP_URL', $env['APP_URL'] ?? 'https://tachyon.rf.gd');
} else {
    // Production / Default Configuration (when .env is missing)
    define('SMTP_HOST', 'smtp.gmail.com'); // Placeholder/Example
    define('SMTP_PORT', 587);
    define('SMTP_USER', ''); // Needs to be configured manually or via env
    define('SMTP_PASS', '');
    define('SMTP_FROM_EMAIL', 'noreply@tachyon.rf.gd');
    define('SMTP_FROM_NAME', 'Tachyon Task Manager');
    define('SMTP_ENCRYPTION', 'tls');
    define('APP_URL', 'https://tachyon.rf.gd');
}

// Application URL for email links (Defined above)

// Email reminder settings
define('REMINDER_DAYS_BEFORE', 1); // Send reminder 1 day before due date
?>