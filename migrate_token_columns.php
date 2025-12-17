<?php
/**
 * Migration script to add separate token columns for different operations
 * This resolves the issue where concurrent operations overwrite each other's tokens
 */

require_once 'db_connect.php';

echo "Starting token columns migration...\n\n";

// Check if the new columns already exist
$columns_to_add = [
    'password_reset_token' => "VARCHAR(255) NULL",
    'password_reset_token_expires' => "DATETIME(6) NULL",
    'password_change_token' => "VARCHAR(255) NULL",
    'password_change_token_expires' => "DATETIME(6) NULL",
    'account_deletion_token' => "VARCHAR(255) NULL",
    'account_deletion_token_expires' => "DATETIME(6) NULL"
];

$migrations_applied = [];

foreach ($columns_to_add as $column_name => $column_definition) {
    // Check if column already exists
    $check_query = "SHOW COLUMNS FROM users LIKE '$column_name'";
    $result = $conn->query($check_query);
    
    if ($result === false) {
        echo "Error checking for column '$column_name': " . $conn->error . "\n";
        continue;
    }
    
    if ($result->num_rows == 0) {
        // Column doesn't exist, add it
        $alter_query = "ALTER TABLE users ADD COLUMN $column_name $column_definition";
        if ($conn->query($alter_query) === TRUE) {
            echo "✓ Added column '$column_name' to users table.\n";
            $migrations_applied[] = $column_name;
        } else {
            echo "✗ Error adding column '$column_name': " . $conn->error . "\n";
        }
    } else {
        echo "✓ Column '$column_name' already exists.\n";
    }
}

echo "\nMigration completed!\n";

if (!empty($migrations_applied)) {
    echo "Columns added: " . implode(', ', $migrations_applied) . "\n";
} else {
    echo "No new columns were added (they may already exist).\n";
}

$conn->close();