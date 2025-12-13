<?php
// save_note.php - Process saving a new note
session_start();
require_once 'db_connect.php';

// Enable error logging
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Session protection
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: create_note.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$requestId = session_id() ?: bin2hex(random_bytes(16));

// CSRF token validation
if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $_SESSION['error_message'] = 'Invalid request. Please try again.';
    error_log("CSRF token validation failed (save_note) [Request ID: $requestId]");
    header('Location: create_note.php');
    exit();
}

// Rotate CSRF token after successful validation
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// Get and validate inputs
$title = trim($_POST['title'] ?? '');
$content = trim($_POST['content'] ?? '');
$color = trim($_POST['color'] ?? '#ffffff');
$is_pinned = isset($_POST['is_pinned']) ? 1 : 0;
$is_archived = isset($_POST['is_archived']) ? 1 : 0;

// Include helper functions
require_once 'includes/functions.php';

// Sanitize content
$content = sanitize_html(trim($_POST['content'] ?? ''));

// Validate title
if (empty($title)) {
    $_SESSION['error_message'] = 'Title is required.';
    header('Location: create_note.php');
    exit();
}

// Validate title length
if (strlen($title) > 255) {
    $_SESSION['error_message'] = 'Title is too long. Maximum 255 characters.';
    header('Location: create_note.php');
    exit();
}

// Validate content
if (empty($content)) {
    $_SESSION['error_message'] = 'Note content is required.';
    header('Location: create_note.php');
    exit();
}

// Validate color format (basic hex color validation)
if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
    $color = '#ffffff'; // Default to white if invalid
}

// Insert note into database
$sql = "INSERT INTO notes (user_id, title, content, color, is_pinned, is_archived) VALUES (?, ?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);

if ($stmt) {
    $stmt->bind_param("isssii", $user_id, $title, $content, $color, $is_pinned, $is_archived);

    if ($stmt->execute()) {
        $_SESSION['success_message'] = 'Note created successfully!';
        $note_id = $stmt->insert_id;
        $stmt->close();

        // Redirect to view note or back to dashboard
        header('Location: dashboard.php');
        exit();
    } else {
        // Log error securely
        $log_dir = __DIR__ . '/private_logs';
        $log_file = $log_dir . '/db_errors.log';
        if (!is_dir($log_dir)) {
            mkdir($log_dir, 0750, true);
        }

        $log_message = "[" . date('Y-m-d H:i:s') . "] Request ID: $requestId | Save Note Insert Error" . PHP_EOL;
        file_put_contents($log_file, $log_message, FILE_APPEND);
        error_log("Database error (save_note insert) [$requestId]: " . $stmt->error);

        $_SESSION['error_message'] = 'Failed to save note. Please try again.';
        $stmt->close();
        header('Location: create_note.php');
        exit();
    }
} else {
    // Log prepare error
    $log_dir = __DIR__ . '/private_logs';
    $log_file = $log_dir . '/db_errors.log';
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0750, true);
    }

    $log_message = "[" . date('Y-m-d H:i:s') . "] Request ID: $requestId | Save Note Prepare Error" . PHP_EOL;
    file_put_contents($log_file, $log_message, FILE_APPEND);
    error_log("Database error (save_note prepare) [$requestId]: " . $conn->error);

    $_SESSION['error_message'] = 'An internal error occurred. Please try again.';
    header('Location: create_note.php');
    exit();
}
?>