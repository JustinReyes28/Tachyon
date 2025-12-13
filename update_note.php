<?php
// update_note.php - Handle updating existing notes
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
    header("Location: dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$requestId = session_id() ?: bin2hex(random_bytes(16));

// CSRF token validation
if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $_SESSION['error_message'] = 'Invalid request. Please try again.';
    error_log("CSRF token validation failed (update_note) [Request ID: $requestId]");
    header('Location: dashboard.php');
    exit();
}

// Rotate CSRF token after successful validation
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// Get and validate note_id
$note_id = filter_input(INPUT_POST, 'note_id', FILTER_VALIDATE_INT);

if (!$note_id || $note_id <= 0) {
    $_SESSION['error_message'] = 'Invalid note.';
    header('Location: dashboard.php');
    exit();
}

// First, verify the note exists and belongs to the user
$stmt = $conn->prepare("SELECT id FROM notes WHERE id = ? AND user_id = ?");

if (!$stmt) {
    // Log prepare error
    $log_dir = __DIR__ . '/private_logs';
    $log_file = $log_dir . '/db_errors.log';
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0750, true);
    }

    $log_message = "[" . date('Y-m-d H:i:s') . "] Request ID: $requestId | Update Note Select Prepare Error" . PHP_EOL;
    file_put_contents($log_file, $log_message, FILE_APPEND);
    error_log("Database error (update_note select prepare) [$requestId]: " . $conn->error);

    $_SESSION['error_message'] = 'An internal error occurred. Please try again.';
    header('Location: dashboard.php');
    exit();
}

$stmt->bind_param("ii", $note_id, $user_id);

if (!$stmt->execute()) {
    // Log error securely
    $log_dir = __DIR__ . '/private_logs';
    $log_file = $log_dir . '/db_errors.log';
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0750, true);
    }

    $log_message = "[" . date('Y-m-d H:i:s') . "] Request ID: $requestId | Update Note Select Error" . PHP_EOL;
    file_put_contents($log_file, $log_message, FILE_APPEND);
    error_log("Database error (update_note select) [$requestId]: " . $stmt->error);

    $_SESSION['error_message'] = 'Failed to update note. Please try again.';
    $stmt->close();
    header('Location: dashboard.php');
    exit();
}

$result = $stmt->get_result();
$note = $result->fetch_assoc();
$stmt->close();

// Check if note exists and belongs to the user
if (!$note) {
    $_SESSION['error_message'] = 'Note not found or access denied.';
    error_log("Update attempt on non-existent/unauthorized note [$requestId]: note_id=$note_id, user_id=$user_id");
    header('Location: dashboard.php');
    exit();
}

// Get and validate inputs
$title = trim($_POST['title'] ?? '');
$content = trim($_POST['content'] ?? '');
$color = trim($_POST['color'] ?? '#ffffff');
$is_pinned = isset($_POST['is_pinned']) ? 1 : 0;
$is_archived = isset($_POST['is_archived']) ? 1 : 0;

// Validate title
if (empty($title)) {
    $_SESSION['error_message'] = 'Title is required.';
    header("Location: edit_note.php?id=$note_id");
    exit();
}

// Validate title length
if (strlen($title) > 255) {
    $_SESSION['error_message'] = 'Title is too long. Maximum 255 characters.';
    header("Location: edit_note.php?id=$note_id");
    exit();
}

// Validate content
if (empty($content)) {
    $_SESSION['error_message'] = 'Note content is required.';
    header("Location: edit_note.php?id=$note_id");
    exit();
}

// Validate color format (basic hex color validation)
if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
    $color = '#ffffff'; // Default to white if invalid
}

// Update note in database
$update_stmt = $conn->prepare("UPDATE notes SET title = ?, content = ?, color = ?, is_pinned = ?, is_archived = ? WHERE id = ? AND user_id = ?");

if (!$update_stmt) {
    // Log prepare error
    $log_dir = __DIR__ . '/private_logs';
    $log_file = $log_dir . '/db_errors.log';
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0750, true);
    }

    $log_message = "[" . date('Y-m-d H:i:s') . "] Request ID: $requestId | Update Note Update Prepare Error" . PHP_EOL;
    file_put_contents($log_file, $log_message, FILE_APPEND);
    error_log("Database error (update_note update prepare) [$requestId]: " . $conn->error);

    $_SESSION['error_message'] = 'An internal error occurred. Please try again.';
    header('Location: dashboard.php');
    exit();
}

$update_stmt->bind_param("sssiiII", $title, $content, $color, $is_pinned, $is_archived, $note_id, $user_id);

if ($update_stmt->execute()) {
    if ($update_stmt->affected_rows > 0) {
        $_SESSION['success_message'] = 'Note updated successfully!';
    } else {
        $_SESSION['error_message'] = 'No changes were made to the note.';
    }
} else {
    // Log error securely
    $log_dir = __DIR__ . '/private_logs';
    $log_file = $log_dir . '/db_errors.log';
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0750, true);
    }

    $log_message = "[" . date('Y-m-d H:i:s') . "] Request ID: $requestId | Update Note Update Error" . PHP_EOL;
    file_put_contents($log_file, $log_message, FILE_APPEND);
    error_log("Database error (update_note update) [$requestId]: " . $update_stmt->error);

    $_SESSION['error_message'] = 'Failed to update note. Please try again.';
}

$update_stmt->close();

// Redirect back to notes page
header('Location: notes.php');
exit();
?>