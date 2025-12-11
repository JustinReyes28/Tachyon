<?php
// Load environment variables from .env file
$env = parse_ini_file('.env');

// Check if the .env file was loaded successfully
if ($env === false) {
    die('Error: Could not read the .env file. Please make sure it exists and is readable.');
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
    die('Connection failed: ' . $conn->connect_error);
}

// Set character set to UTF-8 for proper encoding
$conn->set_charset('utf8mb4');
?>
