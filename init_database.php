<?php
require_once 'db_connect.php';

// SQL to create users table
$sql_users = "CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
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
    echo "Users table created successfully or already exists.\n";
} else {
    echo "Error creating users table: " . $conn->error . "\n";
}

if ($conn->query($sql_todos) === TRUE) {
    echo "Todos table created successfully or already exists.\n";
} else {
    echo "Error creating todos table: " . $conn->error . "\n";
}

if ($conn->query($sql_notes) === TRUE) {
    echo "Notes table created successfully or already exists.\n";
} else {
    echo "Error creating notes table: " . $conn->error . "\n";
}

// Close the connection
$conn->close();

echo "Database initialization completed!";
?>