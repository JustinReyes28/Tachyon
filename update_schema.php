<?php
// update_schema.php - Update the database schema to sync with application expectations
require_once 'db_connect.php';

$updates_applied = [];

// Check and add 'title' column to todos table if using 'task' (rename scenario)
// Actually, based on our analysis, we should add 'task' column if it doesn't exist, 
// but it's easier to adjust the app to match the schema. We handled this in fetch_todos.php already.

// Add 'description' column to todos if it doesn't exist (it should already exist per init_database.php)
$result = $conn->query("SHOW COLUMNS FROM todos LIKE 'description'");
if ($result === false) {
    echo "Error checking for 'description' column: " . $conn->error . "\n";
} elseif ($result->num_rows == 0) {
    $sql = "ALTER TABLE todos ADD COLUMN description TEXT";
    if ($conn->query($sql) === TRUE) {
        echo "Added 'description' column to todos table.\n";
        $updates_applied[] = "Added 'description'";
    } else {
        echo "Error adding 'description' column: " . $conn->error . "\n";
    }
}

// Add 'completed_at' column to todos if it doesn't exist
$result = $conn->query("SHOW COLUMNS FROM todos LIKE 'completed_at'");
if ($result === false) {
    echo "Error checking for 'completed_at' column: " . $conn->error . "\n";
} elseif ($result->num_rows == 0) {
    $sql = "ALTER TABLE todos ADD COLUMN completed_at TIMESTAMP NULL";
    if ($conn->query($sql) === TRUE) {
        echo "Added 'completed_at' column to todos table.\n";
        $updates_applied[] = "Added 'completed_at'";
    } else {
        echo "Error adding 'completed_at' column: " . $conn->error . "\n";
    }
}

// Add 'created_by' column to todos if it doesn't exist
$result = $conn->query("SHOW COLUMNS FROM todos LIKE 'created_by'");
if ($result === false) {
    echo "Error checking for 'created_by' column: " . $conn->error . "\n";
} elseif ($result->num_rows == 0) {
    $sql = "ALTER TABLE todos ADD COLUMN created_by INT";
    if ($conn->query($sql) === TRUE) {
        echo "Added 'created_by' column to todos table.\n";
        $updates_applied[] = "Added 'created_by'";
    } else {
        echo "Error adding 'created_by' column: " . $conn->error . "\n";
    }
}

// Add 'updated_by' column to todos if it doesn't exist
$result = $conn->query("SHOW COLUMNS FROM todos LIKE 'updated_by'");
if ($result === false) {
    echo "Error checking for 'updated_by' column: " . $conn->error . "\n";
} elseif ($result->num_rows == 0) {
    $sql = "ALTER TABLE todos ADD COLUMN updated_by INT";
    if ($conn->query($sql) === TRUE) {
        echo "Added 'updated_by' column to todos table.\n";
        $updates_applied[] = "Added 'updated_by'";
    } else {
        echo "Error adding 'updated_by' column: " . $conn->error . "\n";
    }
}

// Add 'password_hash' column to users if it doesn't exist (rename scenario - we actually need to rename the password column)
// Since we already fixed the code to use 'password', we should ensure that column exists
$result = $conn->query("SHOW COLUMNS FROM users LIKE 'password'");
if ($result === false) {
    echo "Error checking for 'password' column: " . $conn->error . "\n";
} elseif ($result->num_rows == 0) {
    // Try checking for 'password_hash'
    $result = $conn->query("SHOW COLUMNS FROM users LIKE 'password_hash'");
    if ($result === false) {
        echo "Error checking for 'password_hash' column: " . $conn->error . "\n";
    } elseif ($result->num_rows == 0) {
        $sql = "ALTER TABLE users ADD COLUMN password VARCHAR(255) NOT NULL";
        if ($conn->query($sql) === TRUE) {
            echo "Added 'password' column to users table.\n";
            $updates_applied[] = "Added 'password'";
        } else {
            echo "Error adding 'password' column: " . $conn->error . "\n";
        }
    } else {
        // If 'password_hash' exists, rename it to 'password'
        $sql = "ALTER TABLE users CHANGE COLUMN password_hash password VARCHAR(255) NOT NULL";
        if ($conn->query($sql) === TRUE) {
            echo "Renamed 'password_hash' to 'password' in users table.\n";
            $updates_applied[] = "Renamed 'password_hash' to 'password'";
        } else {
            echo "Error renaming 'password_hash' to 'password': " . $conn->error . "\n";
        }
    }
}

// Add 'password_salt' column to users if it doesn't exist
$result = $conn->query("SHOW COLUMNS FROM users LIKE 'password_salt'");
if ($result === false) {
    echo "Error checking for 'password_salt' column: " . $conn->error . "\n";
} elseif ($result->num_rows == 0) {
    $sql = "ALTER TABLE users ADD COLUMN password_salt VARCHAR(255)";
    if ($conn->query($sql) === TRUE) {
        echo "Added 'password_salt' column to users table.\n";
        $updates_applied[] = "Added 'password_salt'";
    } else {
        echo "Error adding 'password_salt' column: " . $conn->error . "\n";
    }
}

echo "\nSchema update completed!\n";
if (!empty($updates_applied)) {
    echo "Updates applied:\n";
    foreach ($updates_applied as $update) {
        echo "- " . $update . "\n";
    }
} else {
    echo "No updates were needed.\n";
}

$conn->close();
?>