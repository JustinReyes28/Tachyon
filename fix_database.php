<?php
// fix_database.php - Helper script to add missing 'recurring' column to 'todos' table
require_once 'db_connect.php';

echo "<h1>Tachyon Database Repair</h1>";

try {
    // Check if column exists
    $result = $conn->query("SHOW COLUMNS FROM todos LIKE 'recurring'");

    if ($result->num_rows == 0) {
        echo "<p>Column 'recurring' does not exist. Adding it now...</p>";

        // Add the column
        $sql = "ALTER TABLE todos ADD COLUMN recurring ENUM('none', 'daily', 'weekly', 'monthly', 'yearly') DEFAULT 'none' AFTER due_date";

        if ($conn->query($sql)) {
            echo "<p style='color: green;'><strong>SUCCESS:</strong> Column 'recurring' added successfully!</p>";

            // Add index for performance (matches schema design)
            $conn->query("ALTER TABLE todos ADD INDEX idx_recurring (recurring)");
        } else {
            throw new Exception("Error adding column: " . $conn->error);
        }
    } else {
        echo "<p style='color: blue;'>Column 'recurring' already exists. No changes needed.</p>";
    }

    echo "<p><a href='recurring_reminders.php'>Back to Reminders</a> | <a href='todos.php'>Back to ToDos</a></p>";

} catch (Throwable $e) {
    echo "<p style='color: red;'><strong>ERROR:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>