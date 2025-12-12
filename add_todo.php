<?php
// add_todo.php - Process adding a new todo
session_start();
require_once 'db_connect.php';

// Session protection
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.php");
    exit();
}

$errors = [];
$requestId = session_id() ?: bin2hex(random_bytes(16));

// CSRF token validation
if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $_SESSION['error_message'] = 'Invalid request. Please try again.';
    error_log("CSRF token validation failed (add_todo) [Request ID: $requestId]");
    header('Location: dashboard.php');
    exit();
}

// Rotate CSRF token after successful validation
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

$user_id = $_SESSION['user_id'];

// Get and validate inputs
// Get and validate inputs
$title = trim($_POST['title'] ?? '');
$priority = $_POST['priority'] ?? 'medium';
$due_date = $_POST['due_date'] ?? null;

// Validate title
if (empty($title)) {
    $_SESSION['error_message'] = 'Title is required.';
    header('Location: dashboard.php');
    exit();
}

// Validate title length (match VARCHAR(255))
if (strlen($title) > 255) {
    $_SESSION['error_message'] = 'Title is too long. Maximum 255 characters.';
    header('Location: dashboard.php');
    exit();
}

// Validate priority
$allowed_priorities = ['low', 'medium', 'high'];
if (!in_array($priority, $allowed_priorities)) {
    $priority = 'medium';
}

// Validate and sanitize due_date
$final_due_date = null;
if (!empty($due_date)) {
    $date_obj = DateTime::createFromFormat('Y-m-d', $due_date);
    if ($date_obj && $date_obj->format('Y-m-d') === $due_date) {
        $final_due_date = $due_date;
    }
}

// Insert todo into database
if ($final_due_date !== null) {
    $sql = "INSERT INTO todos (user_id, title, description, status, priority, due_date) VALUES (?, ?, ?, 'pending', ?, ?)";
} else {
    $sql = "INSERT INTO todos (user_id, title, description, status, priority, due_date) VALUES (?, ?, ?, 'pending', ?, NULL)";
}

$stmt = $conn->prepare($sql);

if ($stmt) {
    // Setting description as empty since it's not in the form
    $description = '';

    if ($final_due_date !== null) {
        $stmt->bind_param("issss", $user_id, $title, $description, $priority, $final_due_date);
    } else {
        $stmt->bind_param("isss", $user_id, $title, $description, $priority);
    }

    if ($stmt->execute()) {
        $_SESSION['success_message'] = 'Task added successfully!';
    } else {
        // Log error securely
        $log_dir = __DIR__ . '/private_logs';
        $log_file = $log_dir . '/db_errors.log';
        if (!is_dir($log_dir)) {
            mkdir($log_dir, 0750, true);
        }

        $log_message = "[" . date('Y-m-d H:i:s') . "] Request ID: $requestId | Add Todo Insert Error" . PHP_EOL;
        file_put_contents($log_file, $log_message, FILE_APPEND);
        error_log("Database error (add_todo insert) [$requestId]: " . $stmt->error);

        $_SESSION['error_message'] = 'Failed to add task. Please try again.';
    }
    $stmt->close();
} else {
    // Log prepare error
    $log_dir = __DIR__ . '/private_logs';
    $log_file = $log_dir . '/db_errors.log';
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0750, true);
    }

    $log_message = "[" . date('Y-m-d H:i:s') . "] Request ID: $requestId | Add Todo Prepare Error" . PHP_EOL;
    file_put_contents($log_file, $log_message, FILE_APPEND);
    error_log("Database error (add_todo prepare) [$requestId]: " . $conn->error);

    $_SESSION['error_message'] = 'An internal error occurred. Please try again.';
}

header('Location: dashboard.php');
exit();
?>