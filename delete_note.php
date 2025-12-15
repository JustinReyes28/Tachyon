<?php
// delete_note.php - Delete a note (protected page, POST only)
session_start();
require_once 'db_connect.php';

// Enable error logging for debugging
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Session protection - redirect if not authenticated
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$requestId = session_id() ?: bin2hex(random_bytes(16));

// 1. Enforce POST Method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // If accessed via GET, redirect to notes page
    header("Location: notes.php");
    exit();
}

// 2. CSRF Token Validation
// if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
//     $_SESSION['error_message'] = 'Invalid request. Please try again.';
//     error_log("CSRF token validation failed (delete_note) [Request ID: $requestId]");
//     header('Location: notes.php');
//     exit();
// }

// 3. Get and Validate Note ID
$note_id = isset($_POST['id']) ? intval($_POST['id']) : 0;

if ($note_id <= 0) {
    $_SESSION['error_message'] = "Invalid note ID.";
    header("Location: notes.php");
    exit();
}

// 4. Soft delete the note
try {
    $delete_stmt = $conn->prepare("UPDATE notes SET is_trashed = 1, trashed_at = NOW() WHERE id = ? AND user_id = ?");
    if ($delete_stmt) {
        $delete_stmt->bind_param("ii", $note_id, $user_id);

        if ($delete_stmt->execute()) {
            if ($delete_stmt->affected_rows > 0) {
                $_SESSION['success_message'] = "Note moved to trash.";
            } else {
                // No rows affected means either note doesn't exist or doesn't belong to user
                $_SESSION['error_message'] = "Note not found or permission denied.";
            }
        } else {
            $log_dir = __DIR__ . '/private_logs';
            if (!is_dir($log_dir))
                mkdir($log_dir, 0750, true);
            error_log("Database error (delete_note execute) [$requestId]: " . $delete_stmt->error);
            $_SESSION['error_message'] = "Failed to delete note. Please try again.";
        }
        $delete_stmt->close();
    } else {
        error_log("Database error (delete_note prepare) [$requestId]: " . $conn->error);
        $_SESSION['error_message'] = "An internal error occurred.";
    }

} catch (Exception $e) {
    error_log("Exception deleting note [$requestId]: " . $e->getMessage());
    $_SESSION['error_message'] = "Failed to delete note due to an error.";
}

header("Location: notes.php");
exit();
?>