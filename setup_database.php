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

// Create a new MySQLi connection (without specifying database initially to create it if needed)
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS);

// Check the connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create database if it doesn't exist
$sql = "CREATE DATABASE IF NOT EXISTS " . DB_NAME;
if ($conn->query($sql) === TRUE) {
    echo "Database '" . DB_NAME . "' created successfully or already exists.<br>";
} else {
    echo "Error creating database: " . $conn->error . "<br>";
}

// Select the database
$conn->select_db(DB_NAME);

// SQL to create users table
$sql_users = "CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    password_salt VARCHAR(32) NOT NULL,
    password_changed_at DATETIME(6) DEFAULT CURRENT_TIMESTAMP(6),
    last_password_reset DATETIME(6) NULL,
    failed_login_attempts INT DEFAULT 0,
    locked_until DATETIME(6) NULL,
    is_active BOOLEAN DEFAULT TRUE,
    email_verified BOOLEAN DEFAULT FALSE,
    verification_token VARCHAR(255) NULL,
    reset_token VARCHAR(255) NULL,
    reset_token_expires DATETIME(6) NULL,
    password_reset_token VARCHAR(255) NULL,
    password_reset_token_expires DATETIME(6) NULL,
    password_change_token VARCHAR(255) NULL,
    password_change_token_expires DATETIME(6) NULL,
    account_deletion_token VARCHAR(255) NULL,
    account_deletion_token_expires DATETIME(6) NULL,
    two_factor_secret VARCHAR(255) NULL,
    two_factor_enabled BOOLEAN DEFAULT FALSE,
    created_at DATETIME(6) DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    last_login DATETIME(6) NULL
)";

// SQL to create todos table
$sql_todos = "CREATE TABLE IF NOT EXISTS todos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
    status ENUM('pending', 'in_progress', 'completed') DEFAULT 'pending',
    due_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";

// SQL to create notes table
$sql_notes = "CREATE TABLE IF NOT EXISTS notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    content LONGTEXT,
    color VARCHAR(7) DEFAULT '#ffffff',
    is_pinned BOOLEAN DEFAULT FALSE,
    is_archived BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";

// Execute the queries
if ($conn->query($sql_users) === TRUE) {
    echo "Users table created successfully or already exists.<br>";

    // Migration: Check if new columns exist, if not, add them
    $columns_to_check = [
        'email_verified' => "BOOLEAN DEFAULT FALSE",
        'verification_token' => "VARCHAR(255) NULL",
        'reset_token' => "VARCHAR(255) NULL",
        'reset_token_expires' => "DATETIME(6) NULL",
        'password_reset_token' => "VARCHAR(255) NULL",
        'password_reset_token_expires' => "DATETIME(6) NULL",
        'password_change_token' => "VARCHAR(255) NULL",
        'password_change_token_expires' => "DATETIME(6) NULL",
        'account_deletion_token' => "VARCHAR(255) NULL",
        'account_deletion_token_expires' => "DATETIME(6) NULL"
    ];

    // Add new token columns for separate operations
    $new_columns_to_check = [
        'password_reset_token' => "VARCHAR(255) NULL",
        'password_reset_token_expires' => "DATETIME(6) NULL",
        'password_change_token' => "VARCHAR(255) NULL",
        'password_change_token_expires' => "DATETIME(6) NULL",
        'account_deletion_token' => "VARCHAR(255) NULL",
        'account_deletion_token_expires' => "DATETIME(6) NULL"
    ];

    foreach ($new_columns_to_check as $col => $def) {
        $check = $conn->query("SHOW COLUMNS FROM users LIKE '$col'");
        if ($check->num_rows == 0) {
            $alter = "ALTER TABLE users ADD COLUMN $col $def";
            if ($conn->query($alter) === TRUE) {
                echo "Added column '$col' to users table.<br>";
            } else {
                echo "Error adding column '$col': " . $conn->error . "<br>";
            }
        }
    }
} else {
    echo "Error creating users table: " . $conn->error . "<br>";
}

if ($conn->query($sql_todos) === TRUE) {
    echo "Todos table created successfully or already exists.<br>";
} else {
    echo "Error creating todos table: " . $conn->error . "<br>";
}

if ($conn->query($sql_notes) === TRUE) {
    echo "Notes table created successfully or already exists.<br>";
} else {
    echo "Error creating notes table: " . $conn->error . "<br>";
}

// Close the connection
$conn->close();

echo "Database initialization completed!";
?>