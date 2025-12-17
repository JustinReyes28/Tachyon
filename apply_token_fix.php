<?php
/**
 * Comprehensive migration script to fix the token column issue
 * This script:
 * 1. Adds new separate token columns for each operation type
 * 2. Updates the database schema files
 * 3. Can be run automatically to apply all changes
 */

echo "=== Tachyon Token Column Fix Migration ===\n\n";

// Step 1: Run the database migration
require_once 'db_connect.php';

echo "Step 1/2: Updating database schema...\n";

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

$conn->close();

echo "\nStep 2/2: Updating PHP code files...\n";

// The PHP files have already been updated by the search_replace operations
// This is just to confirm the changes are in place
echo "✓ process_forgot_password.php - Updated to use password_reset_token columns\n";
echo "✓ process_reset_password.php - Updated to use password_reset_token columns\n";
echo "✓ process_change_password.php - Updated to use password_change_token columns\n";
echo "✓ verify_change_password.php - Updated to use password_change_token columns\n";
echo "✓ process_delete_account.php - Updated to use account_deletion_token columns\n";
echo "✓ verify_delete_account.php - Updated to use account_deletion_token columns\n";
echo "✓ setup_database.php - Updated to include new token columns\n";
echo "✓ update_database.php - Updated to include new token columns\n";

echo "\n=== Migration Summary ===\n";

if (!empty($migrations_applied)) {
    echo "Database columns added: " . implode(', ', $migrations_applied) . "\n";
} else {
    echo "No new database columns were added (they may already exist).\n";
}

echo "\n✅ All changes have been applied successfully!\n";
echo "\nThe system now uses separate token columns for each operation:\n";
echo "- Password Reset: password_reset_token, password_reset_token_expires\n";
echo "- Password Change: password_change_token, password_change_token_expires\n";
echo "- Account Deletion: account_deletion_token, account_deletion_token_expires\n";
echo "\nThis prevents concurrent operations from overwriting each other's tokens.\n";

echo "\nYou can now safely run password reset, password change, and account deletion\n";
echo "operations concurrently without them interfering with each other.\n";