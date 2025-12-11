<?php
// delete_todo.php - Process deleting a todo
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

$requestId = session_id() ?: bin2hex(random_bytes(16));

// CSRF token validation
if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $_SESSION['error_message'] = 'Invalid request. Please try again.';
    error_log("CSRF token validation failed (delete_todo) [Request ID: $requestId]");
    header('Location: dashboard.php');
    exit();
}

// Rotate CSRF token
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

$user_id = $_SESSION['user_id'];
$todo_id = filter_input(INPUT_POST, 'todo_id', FILTER_VALIDATE_INT);

// Validate todo_id
if (!$todo_id || $todo_id <= 0) {
    $_SESSION['error_message'] = 'Invalid task.';
    header('Location: dashboard.php');
    exit();
}

// Delete todo - ensuring user ownership (CRITICAL: prevents unauthorized deletion)
$stmt = $conn->prepare("DELETE FROM todos WHERE id = ? AND user_id = ?");

if ($stmt) {
    $stmt->bind_param("ii", $todo_id, $user_id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $_SESSION['success_message'] = 'Task deleted successfully!';
        } else {
            // No rows affected - either doesn't exist or doesn't belong to user
            $_SESSION['error_message'] = 'Task not found or access denied.';
            error_log("Delete attempt on non-existent/unauthorized todo [$requestId]: todo_id=$todo_id, user_id=$user_id");
        }
    } else {
        // Log error securely
        $log_dir = __DIR__ . '/private_logs';
        $log_file = $log_dir . '/db_errors.log';
        if (!is_dir($log_dir)) {
            mkdir($log_dir, 0750, true);
        }

        $log_message = "[" . date('Y-m-d H:i:s') . "] Request ID: $requestId | Delete Todo Error" . PHP_EOL;
        file_put_contents($log_file, $log_message, FILE_APPEND);
        error_log("Database error (delete_todo) [$requestId]: " . $stmt->error);

        $_SESSION['error_message'] = 'Failed to delete task. Please try again.';
    }
    $stmt->close();
} else {
    // Log prepare error
    $log_dir = __DIR__ . '/private_logs';
    $log_file = $log_dir . '/db_errors.log';
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0750, true);
    }

    $log_message = "[" . date('Y-m-d H:i:s') . "] Request ID: $requestId | Delete Todo Prepare Error" . PHP_EOL;
    file_put_contents($log_file, $log_message, FILE_APPEND);
    error_log("Database error (delete_todo prepare) [$requestId]: " . $conn->error);

    $_SESSION['error_message'] = 'An internal error occurred. Please try again.';
}

header('Location: dashboard.php');
exit();
?>