<?php
/**
 * Database Schema Update Script
 * Aligns the database with database_schema.sql
 */

// Enable better error handling
ini_set('display_errors', 0);
error_reporting(E_ALL);

try {
    require_once 'db_connect.php';

    echo "<h2>Database Schema Update</h2>";
    echo "<pre style='background: #f5f5f5; padding: 20px; border-radius: 5px;'>";

    $updates = [];

    // Check if old 'password' column exists (legacy schema)
    $result = $conn->query("SHOW COLUMNS FROM users LIKE 'password'");
    if ($result && $result->num_rows > 0) {
        echo "⚠ Found legacy 'password' column\n";

        // Add password_hash column if it doesn't exist
        $conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS password_hash VARCHAR(255)");

        // Copy data from old password column to new password_hash column
        if ($conn->query("UPDATE users SET password_hash = password WHERE password_hash IS NULL OR password_hash = ''") === TRUE) {
            echo "✓ Copied existing passwords to password_hash column\n";
            $updates[] = "Migrated passwords";
        }

        // Drop the old password column
        if ($conn->query("ALTER TABLE users DROP COLUMN password") === TRUE) {
            echo "✓ Removed old password column\n";
            $updates[] = "Removed legacy password column";
        }
    }

    // Define required columns matching database_schema.sql
    $required_columns = [
        'password_hash' => "VARCHAR(255) NOT NULL",
        'password_salt' => "VARCHAR(32) NOT NULL DEFAULT ''",
        'password_changed_at' => "DATETIME(6) DEFAULT CURRENT_TIMESTAMP(6)",
        'last_password_reset' => "DATETIME(6) NULL",
        'failed_login_attempts' => "INT DEFAULT 0",
        'locked_until' => "DATETIME(6) NULL",
        'is_active' => "BOOLEAN DEFAULT TRUE",
        'email_verified' => "BOOLEAN DEFAULT FALSE",
        'verification_token' => "VARCHAR(255) NULL",
        'reset_token' => "VARCHAR(255) NULL",
        'reset_token_expires' => "DATETIME(6) NULL",
        'password_reset_token' => "VARCHAR(255) NULL",
        'password_reset_token_expires' => "DATETIME(6) NULL",
        'password_change_token' => "VARCHAR(255) NULL",
        'password_change_token_expires' => "DATETIME(6) NULL",
        'account_deletion_token' => "VARCHAR(255) NULL",
        'account_deletion_token_expires' => "DATETIME(6) NULL",
        'two_factor_secret' => "VARCHAR(255) NULL",
        'two_factor_enabled' => "BOOLEAN DEFAULT FALSE",
        'verification_code' => "VARCHAR(6) NULL",
        'verification_code_expires' => "DATETIME NULL",
        'created_at' => "DATETIME(6) DEFAULT CURRENT_TIMESTAMP(6)",
        'updated_at' => "DATETIME(6) DEFAULT CURRENT_TIMESTAMP(6)",
        'last_login' => "DATETIME(6) NULL"
    ];

    foreach ($required_columns as $column => $definition) {
        $result = $conn->query("SHOW COLUMNS FROM users LIKE '$column'");
        if ($result && $result->num_rows === 0) {
            $sql = "ALTER TABLE users ADD COLUMN $column $definition";
            if ($conn->query($sql) === TRUE) {
                echo "✓ Added column '$column'\n";
                $updates[] = "Added $column";
            } else {
                echo "✗ Error adding '$column': " . $conn->error . "\n";
            }
        }
    }

    // Check for old 'is_verified' column and migrate to 'email_verified'
    $result = $conn->query("SHOW COLUMNS FROM users LIKE 'is_verified'");
    if ($result && $result->num_rows > 0) {
        // Migrate data if email_verified exists
        $conn->query("UPDATE users SET email_verified = is_verified WHERE email_verified IS NULL OR email_verified = 0");

        // Drop old column
        if ($conn->query("ALTER TABLE users DROP COLUMN is_verified") === TRUE) {
            echo "✓ Migrated is_verified to email_verified\n";
            $updates[] = "Migrated is_verified to email_verified";
        }
    }

    // Check if todos table has proper status enum
    $statusCheck = $conn->query("SHOW COLUMNS FROM todos LIKE 'status'");
    if ($statusCheck && $statusCheck->num_rows > 0) {
        $row = $statusCheck->fetch_assoc();
        if ($row && strpos($row['Type'], 'in_progress') === false) {
            if ($conn->query("ALTER TABLE todos MODIFY COLUMN status ENUM('pending', 'in_progress', 'completed') DEFAULT 'pending'") === TRUE) {
                echo "✓ Updated status column enum in todos table\n";
                $updates[] = "Updated todos status enum";
            }
        }
    }

    // Add soft delete columns to todos table
    $todos_trash_columns = [
        'is_trashed' => "TINYINT(1) DEFAULT 0",
        'trashed_at' => "DATETIME DEFAULT NULL"
    ];

    foreach ($todos_trash_columns as $column => $definition) {
        $result = $conn->query("SHOW COLUMNS FROM todos LIKE '$column'");
        if ($result && $result->num_rows === 0) {
            $sql = "ALTER TABLE todos ADD COLUMN $column $definition";
            if ($conn->query($sql) === TRUE) {
                echo "✓ Added column '$column' to todos table\n";
                $updates[] = "Added $column to todos";
            } else {
                echo "✗ Error adding '$column' to todos: " . $conn->error . "\n";
            }
        }
    }

    // Add soft delete columns to notes table
    $notes_trash_columns = [
        'is_trashed' => "TINYINT(1) DEFAULT 0",
        'trashed_at' => "DATETIME DEFAULT NULL"
    ];

    foreach ($notes_trash_columns as $column => $definition) {
        $result = $conn->query("SHOW COLUMNS FROM notes LIKE '$column'");
        if ($result && $result->num_rows === 0) {
            $sql = "ALTER TABLE notes ADD COLUMN $column $definition";
            if ($conn->query($sql) === TRUE) {
                echo "✓ Added column '$column' to notes table\n";
                $updates[] = "Added $column to notes";
            } else {
                echo "✗ Error adding '$column' to notes: " . $conn->error . "\n";
            }
        }
    }

    // Close the connection
    $conn->close();

    echo "\n";
    if (count($updates) > 0) {
        echo "=========================\n";
        echo "✓ " . count($updates) . " update(s) applied\n";
    } else {
        echo "=========================\n";
        echo "✓ Database schema is already up to date!\n";
    }
    echo "</pre>";

} catch (Exception $e) {
    echo "<div style='background: #ffebee; color: #c62828; padding: 20px; border-radius: 5px;'>";
    echo "<h3>Update Error</h3>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
    error_log("Database update error: " . $e->getMessage());
}
?>