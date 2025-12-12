<?php
require_once 'db_connect.php';

if ($conn) {
    echo "Database connection successful!<br>";
    echo "Connected to database: " . DB_NAME . "<br>";
    echo "Host: " . DB_HOST . "<br>";
    
    // Check if tables exist
    $result = $conn->query("SHOW TABLES LIKE 'users'");
    if ($result->num_rows > 0) {
        echo "Users table exists.<br>";
    } else {
        echo "Users table does not exist.<br>";
    }
    
    $result = $conn->query("SHOW TABLES LIKE 'todos'");
    if ($result->num_rows > 0) {
        echo "Todos table exists.<br>";
    } else {
        echo "Todos table does not exist.<br>";
    }
} else {
    echo "Database connection failed.";
}
?>