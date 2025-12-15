<?php
// update_todo.php - Toggle task status between complete/incomplete
session_start();
require_once 'db_connect.php';

// Session protection
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: todos.php");
    exit();
}

$requestId = session_id() ?: bin2hex(random_bytes(16));

// CSRF token validation
// if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
//     $_SESSION['error_message'] = 'Invalid request. Please try again.';
//     error_log("CSRF token validation failed (update_todo) [Request ID: $requestId]");
//     header('Location: dashboard.php');
//     exit();
// }

// Rotate CSRF token after successful validation
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

$user_id = $_SESSION['user_id'];
$todo_id = filter_input(INPUT_POST, 'todo_id', FILTER_VALIDATE_INT);

// Validate todo_id
if (!$todo_id || $todo_id <= 0) {
    $_SESSION['error_message'] = 'Invalid task.';
    header('Location: dashboard.php');
    exit();
}

// First, fetch the current status of the todo to toggle it (ensuring user ownership)
$stmt = $conn->prepare("SELECT id, status FROM todos WHERE id = ? AND user_id = ?");

if (!$stmt) {
    // Log prepare error
    $log_dir = __DIR__ . '/private_logs';
    $log_file = $log_dir . '/db_errors.log';
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0750, true);
    }

    $log_message = "[" . date('Y-m-d H:i:s') . "] Request ID: $requestId | Update Todo Select Prepare Error" . PHP_EOL;
    file_put_contents($log_file, $log_message, FILE_APPEND);
    error_log("Database error (update_todo select prepare) [$requestId]: " . $conn->error);

    $_SESSION['error_message'] = 'An internal error occurred. Please try again.';
    header('Location: dashboard.php');
    exit();
}

$stmt->bind_param("ii", $todo_id, $user_id);

if (!$stmt->execute()) {
    // Log error securely
    $log_dir = __DIR__ . '/private_logs';
    $log_file = $log_dir . '/db_errors.log';
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0750, true);
    }

    $log_message = "[" . date('Y-m-d H:i:s') . "] Request ID: $requestId | Update Todo Select Error" . PHP_EOL;
    file_put_contents($log_file, $log_message, FILE_APPEND);
    error_log("Database error (update_todo select) [$requestId]: " . $stmt->error);

    $_SESSION['error_message'] = 'Failed to update task. Please try again.';
    $stmt->close();
    header('Location: dashboard.php');
    exit();
}

$result = $stmt->get_result();
$todo = $result->fetch_assoc();
$stmt->close();

// Check if todo exists and belongs to the user
if (!$todo) {
    $_SESSION['error_message'] = 'Task not found or access denied.';
    error_log("Update attempt on non-existent/unauthorized todo [$requestId]: todo_id=$todo_id, user_id=$user_id");
    header('Location: dashboard.php');
    exit();
}

// Determine new status (toggle between 'completed' and 'pending')
$current_status = $todo['status'];
if ($current_status === 'completed') {
    $new_status = 'pending';
    $completed_at = null;
    $success_msg = 'Task marked as incomplete!';
} else {
    $new_status = 'completed';
    $completed_at = date('Y-m-d H:i:s');
    $success_msg = 'Task marked as complete!';
}

// Update the todo status
$update_stmt = $conn->prepare("UPDATE todos SET status = ?, completed_at = ?, updated_by = ?, updated_at = NOW() WHERE id = ? AND user_id = ?");

if (!$update_stmt) {
    // Log prepare error
    $log_dir = __DIR__ . '/private_logs';
    $log_file = $log_dir . '/db_errors.log';
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0750, true);
    }

    $log_message = "[" . date('Y-m-d H:i:s') . "] Request ID: $requestId | Update Todo Update Prepare Error" . PHP_EOL;
    file_put_contents($log_file, $log_message, FILE_APPEND);
    error_log("Database error (update_todo update prepare) [$requestId]: " . $conn->error);

    $_SESSION['error_message'] = 'An internal error occurred. Please try again.';
    header('Location: dashboard.php');
    exit();
}

$update_stmt->bind_param("ssiii", $new_status, $completed_at, $user_id, $todo_id, $user_id);

if ($update_stmt->execute()) {
    if ($update_stmt->affected_rows > 0) {
        $_SESSION['success_message'] = $success_msg;
    } else {
        $_SESSION['error_message'] = 'No changes were made to the task.';
    }
} else {
    // Log error securely
    $log_dir = __DIR__ . '/private_logs';
    $log_file = $log_dir . '/db_errors.log';
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0750, true);
    }

    $log_message = "[" . date('Y-m-d H:i:s') . "] Request ID: $requestId | Update Todo Update Error" . PHP_EOL;
    file_put_contents($log_file, $log_message, FILE_APPEND);
    error_log("Database error (update_todo update) [$requestId]: " . $update_stmt->error);

    $_SESSION['error_message'] = 'Failed to update task. Please try again.';
}

$update_stmt->close();

header('Location: dashboard.php');
exit();
?>