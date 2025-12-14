<?php
// permanent_delete_note.php - Permanently delete a note from trash
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: trash.php");
    exit();
}

// CSRF check
if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $_SESSION['error_message'] = 'Invalid request.';
    header("Location: trash.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$note_id = filter_input(INPUT_POST, 'note_id', FILTER_VALIDATE_INT);

if ($note_id) {
    // Only delete if it's already trashed
    $stmt = $conn->prepare("DELETE FROM notes WHERE id = ? AND user_id = ? AND is_trashed = 1");
    $stmt->bind_param("ii", $note_id, $user_id);

    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $_SESSION['success_message'] = "Note permanently deleted.";
    } else {
        $_SESSION['error_message'] = "Failed to delete note.";
    }
    $stmt->close();
}

header("Location: trash.php");
exit();
?>