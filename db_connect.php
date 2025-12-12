<?php
// Load environment variables from .env file
$envFile = __DIR__ . '/.env';

if (!file_exists($envFile)) {
    die('Error: .env file not found. Please create one.');
}

$env = parse_ini_file($envFile);

// Check if the .env file was loaded successfully
if ($env === false) {
    die('Error: Could not read the .env file. Please make sure it is readable.');
}

// Validate required environment variables
$required_keys = ['DB_HOST', 'DB_USER', 'DB_PASS', 'DB_NAME'];
foreach ($required_keys as $key) {
    if (!array_key_exists($key, $env)) {
        die("Error: Missing required configuration key '$key' in .env file.");
    }
}

// Database connection parameters from .env
define('DB_HOST', $env['DB_HOST']);
define('DB_USER', $env['DB_USER']);
define('DB_PASS', $env['DB_PASS']);
define('DB_NAME', $env['DB_NAME']);

// Create a new MySQLi connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check the connection
if ($conn->connect_error) {
    // Generate a unique request ID for correlation
    $requestId = session_id() ?: bin2hex(random_bytes(16));

    // Ensure log directory exists
    $log_dir = __DIR__ . '/private_logs';
    $log_file = $log_dir . '/db_errors.log';
    if (!is_dir($log_dir)) {
        if (!mkdir($log_dir, 0750, true)) {
            error_log("Failed to create secure log directory: " . $log_dir);
        }
    }

    // Write detailed error to secure log file
    $log_message = "[" . date('Y-m-d H:i:s') . "] Request ID: " . $requestId . " | Connection Failed: " . $conn->connect_error . PHP_EOL;
    if (file_put_contents($log_file, $log_message, FILE_APPEND) === false) {
        error_log("Failed to write to secure log file: " . $log_file);
    }

    // Log detailed error to system log
    error_log("Database connection failed [" . $requestId . "]: " . $conn->connect_error);

    // Generic error for user to prevent information leak
    die('Database connection failed.');
}

// Set character set to UTF-8 for proper encoding
$conn->set_charset('utf8mb4');
?>