<?php
/**
 * Unified Database Management Script
 * Combines init_database, update_database, apply_token_fix, and migrate_token_columns
 * functionality into a single cohesive script.
 */

class DatabaseManager {
    private $conn;
    
    public function __construct() {
        require_once 'db_connect.php';
        $this->conn = $conn;
    }
    
    /**
     * Initialize database tables if they don't exist
     */
    public function initializeTables() {
        echo "<h3>Initializing Database Tables</h3>\n";
        
        // SQL to create users table
        $sql_users = "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            password_salt VARCHAR(32) NOT NULL DEFAULT '',
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
            verification_code VARCHAR(6) NULL,
            verification_code_expires DATETIME NULL,
            created_at DATETIME(6) DEFAULT CURRENT_TIMESTAMP(6),
            updated_at DATETIME(6) DEFAULT CURRENT_TIMESTAMP(6),
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
            is_trashed TINYINT(1) DEFAULT 0,
            trashed_at DATETIME DEFAULT NULL,
            created_at DATETIME(6) DEFAULT CURRENT_TIMESTAMP(6),
            updated_at DATETIME(6) DEFAULT CURRENT_TIMESTAMP(6),
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
            is_trashed TINYINT(1) DEFAULT 0,
            trashed_at DATETIME DEFAULT NULL,
            created_at DATETIME(6) DEFAULT CURRENT_TIMESTAMP(6),
            updated_at DATETIME(6) DEFAULT CURRENT_TIMESTAMP(6),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )";

        // Execute the queries
        if ($this->conn->query($sql_users) === TRUE) {
            echo "✓ Users table created successfully or already exists.\n";
        } else {
            echo "✗ Error creating users table: " . $this->conn->error . "\n";
        }

        if ($this->conn->query($sql_todos) === TRUE) {
            echo "✓ Todos table created successfully or already exists.\n";
        } else {
            echo "✗ Error creating todos table: " . $this->conn->error . "\n";
        }

        if ($this->conn->query($sql_notes) === TRUE) {
            echo "✓ Notes table created successfully or already exists.\n";
        } else {
            echo "✗ Error creating notes table: " . $this->conn->error . "\n";
        }
    }
    
    /**
     * Update database schema with all necessary changes
     */
    public function updateSchema() {
        echo "<h3>Updating Database Schema</h3>\n";
        
        $updates = [];
        
        // Handle legacy password column migration
        $result = $this->conn->query("SHOW COLUMNS FROM users LIKE 'password'");
        if ($result && $result->num_rows > 0) {
            echo "⚠ Found legacy 'password' column\n";

            // Add password_hash column if it doesn't exist
            $this->conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS password_hash VARCHAR(255)");

            // Copy data from old password column to new password_hash column
            if ($this->conn->query("UPDATE users SET password_hash = password WHERE password_hash IS NULL OR password_hash = ''") === TRUE) {
                echo "✓ Copied existing passwords to password_hash column\n";
                $updates[] = "Migrated passwords";
            }

            // Drop the old password column
            if ($this->conn->query("ALTER TABLE users DROP COLUMN password") === TRUE) {
                echo "✓ Removed old password column\n";
                $updates[] = "Removed legacy password column";
            }
        }

        // Define required columns matching database_schema.sql
        $required_columns = [
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
            $result = $this->conn->query("SHOW COLUMNS FROM users LIKE '$column'");
            if ($result && $result->num_rows === 0) {
                $sql = "ALTER TABLE users ADD COLUMN $column $definition";
                if ($this->conn->query($sql) === TRUE) {
                    echo "✓ Added column '$column'\n";
                    $updates[] = "Added $column";
                } else {
                    echo "✗ Error adding '$column': " . $this->conn->error . "\n";
                }
            }
        }

        // Check for old 'is_verified' column and migrate to 'email_verified'
        $result = $this->conn->query("SHOW COLUMNS FROM users LIKE 'is_verified'");
        if ($result && $result->num_rows > 0) {
            // Migrate data if email_verified exists
            $this->conn->query("UPDATE users SET email_verified = is_verified WHERE email_verified IS NULL OR email_verified = 0");

            // Drop old column
            if ($this->conn->query("ALTER TABLE users DROP COLUMN is_verified") === TRUE) {
                echo "✓ Migrated is_verified to email_verified\n";
                $updates[] = "Migrated is_verified to email_verified";
            }
        }

        // Check if todos table has proper status enum
        $statusCheck = $this->conn->query("SHOW COLUMNS FROM todos LIKE 'status'");
        if ($statusCheck && $statusCheck->num_rows > 0) {
            $row = $statusCheck->fetch_assoc();
            if ($row && strpos($row['Type'], 'in_progress') === false) {
                if ($this->conn->query("ALTER TABLE todos MODIFY COLUMN status ENUM('pending', 'in_progress', 'completed') DEFAULT 'pending'") === TRUE) {
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
            $result = $this->conn->query("SHOW COLUMNS FROM todos LIKE '$column'");
            if ($result && $result->num_rows === 0) {
                $sql = "ALTER TABLE todos ADD COLUMN $column $definition";
                if ($this->conn->query($sql) === TRUE) {
                    echo "✓ Added column '$column' to todos table\n";
                    $updates[] = "Added $column to todos";
                } else {
                    echo "✗ Error adding '$column' to todos: " . $this->conn->error . "\n";
                }
            }
        }

        // Add soft delete columns to notes table
        $notes_trash_columns = [
            'is_trashed' => "TINYINT(1) DEFAULT 0",
            'trashed_at' => "DATETIME DEFAULT NULL"
        ];

        foreach ($notes_trash_columns as $column => $definition) {
            $result = $this->conn->query("SHOW COLUMNS FROM notes LIKE '$column'");
            if ($result && $result->num_rows === 0) {
                $sql = "ALTER TABLE notes ADD COLUMN $column $definition";
                if ($this->conn->query($sql) === TRUE) {
                    echo "✓ Added column '$column' to notes table\n";
                    $updates[] = "Added $column to notes";
                } else {
                    echo "✗ Error adding '$column' to notes: " . $this->conn->error . "\n";
                }
            }
        }
        
        // Apply token column migrations (if not already handled above)
        $this->migrateTokenColumns($updates);
        
        echo "\n";
        if (count($updates) > 0) {
            echo "=========================\n";
            echo "✓ " . count($updates) . " update(s) applied\n";
        } else {
            echo "=========================\n";
            echo "✓ Database schema is already up to date!\n";
        }
    }
    
    /**
     * Migrate token columns - adds separate token columns for different operations
     */
    public function migrateTokenColumns(&$updates = null) {
        echo "<h3>Migrating Token Columns</h3>\n";
        
        $migrations_applied = [];
        
        // Check if the new columns already exist
        $columns_to_add = [
            'password_reset_token' => "VARCHAR(255) NULL",
            'password_reset_token_expires' => "DATETIME(6) NULL",
            'password_change_token' => "VARCHAR(255) NULL",
            'password_change_token_expires' => "DATETIME(6) NULL",
            'account_deletion_token' => "VARCHAR(255) NULL",
            'account_deletion_token_expires' => "DATETIME(6) NULL"
        ];

        foreach ($columns_to_add as $column_name => $column_definition) {
            // Check if column already exists
            $check_query = "SHOW COLUMNS FROM users LIKE '$column_name'";
            $result = $this->conn->query($check_query);

            if ($result === false) {
                echo "Error checking for column '$column_name': " . $this->conn->error . "\n";
                continue;
            }

            if ($result->num_rows == 0) {
                // Column doesn't exist, add it
                $alter_query = "ALTER TABLE users ADD COLUMN $column_name $column_definition";
                if ($this->conn->query($alter_query) === TRUE) {
                    echo "✓ Added column '$column_name' to users table.\n";
                    $migrations_applied[] = $column_name;
                    if ($updates !== null) {
                        $updates[] = "Added $column_name";
                    }
                } else {
                    echo "✗ Error adding column '$column_name': " . $this->conn->error . "\n";
                }
            } else {
                echo "✓ Column '$column_name' already exists.\n";
            }
        }

        echo "\nToken column migration completed!\n";

        if (!empty($migrations_applied)) {
            echo "Columns added: " . implode(', ', $migrations_applied) . "\n";
        } else {
            echo "No new columns were added (they may already exist).\n";
        }
    }
    
    /**
     * Run all database management functions in sequence
     */
    public function runAll() {
        echo "<h2>Tachyon Database Management System</h2>\n";
        echo "<pre style='background: #f5f5f5; padding: 20px; border-radius: 5px;'>\n";

        echo "=== Starting Database Initialization ===\n";
        $this->initializeTables();

        echo "\n=== Starting Schema Updates ===\n";
        $this->updateSchema();

        echo "\n=== Starting Token Column Migration ===\n";
        $this->migrateTokenColumns();

        echo "\n=== Database Management Complete ===\n";
        echo "</pre>\n";

        // Connection will be closed in runOperation method
    }
    
    /**
     * Run individual operations based on parameters
     */
    public function runOperation($operation) {
        echo "<h2>Tachyon Database Manager - Operation: $operation</h2>\n";
        echo "<pre style='background: #f5f5f5; padding: 20px; border-radius: 5px;'>\n";

        switch ($operation) {
            case 'init':
                echo "=== Initializing Database Tables ===\n";
                $this->initializeTables();
                break;

            case 'update':
                echo "=== Updating Database Schema ===\n";
                $this->updateSchema();
                break;

            case 'tokens':
                echo "=== Migrating Token Columns ===\n";
                $this->migrateTokenColumns();
                break;

            case 'all':
            default:
                echo "=== Running All Operations ===\n";
                $this->runAll();
                $this->conn->close(); // Close connection only for all operation after runAll
                return; // Don't double-close
                break;
        }

        echo "</pre>\n";
        $this->conn->close();
    }
}

// Handle command line arguments
if (php_sapi_name() === 'cli') {
    $operation = isset($argv[1]) ? $argv[1] : 'all';
    $dbManager = new DatabaseManager();
    $dbManager->runOperation($operation);
} elseif (isset($_GET['op'])) {
    $dbManager = new DatabaseManager();
    $dbManager->runOperation($_GET['op']);
} else {
    // Default behavior - show options
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Tachyon Database Manager</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            .options { margin: 20px 0; }
            .option-btn { 
                display: inline-block; 
                padding: 10px 15px; 
                margin: 5px; 
                background: #007cba; 
                color: white; 
                text-decoration: none; 
                border-radius: 4px; 
            }
            .option-btn:hover { background: #005a87; }
        </style>
    </head>
    <body>
        <h1>Tachyon Database Manager</h1>
        <p>Choose an operation to run:</p>
        <div class="options">
            <a href="?op=all" class="option-btn">Run All Operations</a>
            <a href="?op=init" class="option-btn">Initialize Tables</a>
            <a href="?op=update" class="option-btn">Update Schema</a>
            <a href="?op=tokens" class="option-btn">Migrate Tokens</a>
        </div>
        <?php if (isset($_GET['op'])): ?>
            <?php
            $dbManager = new DatabaseManager();
            $dbManager->runOperation($_GET['op']);
            ?>
        <?php endif; ?>
    </body>
    </html>
    <?php
}
?>