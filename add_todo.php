<?php
// add_todo.php - Process adding a new todo
// Wrapped in try-catch for debugging 500 error

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

$debug_log_file = __DIR__ . '/debug_add_todo.log';

function debug_log($message)
{
    global $debug_log_file;
    file_put_contents($debug_log_file, "[" . date('Y-m-d H:i:s') . "] " . $message . PHP_EOL, FILE_APPEND);
}

try {
    debug_log("Script started");

    session_start();
    debug_log("Session started");

    require_once 'db_connect.php';
    debug_log("db_connect included");

    // Session protection
    if (!isset($_SESSION['user_id'])) {
        debug_log("User not logged in, redirecting");
        header("Location: login.php");
        exit();
    }

    // Only accept POST requests
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        debug_log("Not POST request");
        header("Location: todos.php");
        exit();
    }

    $errors = [];
    $requestId = session_id() ?: bin2hex(random_bytes(16));

    // CSRF token validation
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['error_message'] = 'Invalid request. Please try again.';
        debug_log("CSRF validation failed");
        error_log("CSRF token validation failed (add_todo) [Request ID: $requestId]");
        header('Location: todos.php');
        exit();
    }

    // Rotate CSRF token after successful validation
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    $user_id = $_SESSION['user_id'];

    // Get and validate inputs
    $task = trim($_POST['task'] ?? '');
    $priority = $_POST['priority'] ?? 'medium';
    $due_date = $_POST['due_date'] ?? null;

    debug_log("Inputs received - Task: " . substr($task, 0, 20) . "...");

    // Validate task
    if (empty($task)) {
        $_SESSION['error_message'] = 'Task is required.';
        header('Location: todos.php');
        exit();
    }

    // Validate task length
    if (strlen($task) > 1000) {
        $_SESSION['error_message'] = 'Task is too long. Maximum 1000 characters.';
        header('Location: todos.php');
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
        $sql = "INSERT INTO todos (user_id, task, description, status, priority, due_date, created_by) VALUES (?, ?, ?, 'pending', ?, ?, ?)";
    } else {
        $sql = "INSERT INTO todos (user_id, task, description, status, priority, due_date, created_by) VALUES (?, ?, ?, 'pending', ?, NULL, ?)";
    }

    debug_log("Preparing statement");
    $stmt = $conn->prepare($sql);

    if ($stmt) {
        // Setting description as empty since it's not in the form
        $description = '';

        debug_log("Binding parameters");
        if ($final_due_date !== null) {
            $stmt->bind_param("issssi", $user_id, $task, $description, $priority, $final_due_date, $user_id);
        } else {
            $stmt->bind_param("isssi", $user_id, $task, $description, $priority, $user_id);
        }

        debug_log("Executing statement");
        if ($stmt->execute()) {
            $_SESSION['success_message'] = 'Task added successfully!';
            debug_log("Execute successful");
        } else {
            // Log error securely
            debug_log("Execute failed: " . $stmt->error);

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
        debug_log("Prepare failed: " . $conn->error);

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

    header('Location: todos.php');
    exit();

} catch (Throwable $e) {
    debug_log("FATAL EXCEPTION: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());

    // Attempt to show standard error page if possible, or just exit
    http_response_code(500);
    echo "An error occurred. Check debug_add_todo.log for details.";
}
?>