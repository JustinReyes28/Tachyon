<?php
// restore_note.php - Restore a note from trash
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: trash.php");
    exit();
}

// CSRF check
// if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
//     $_SESSION['error_message'] = 'Invalid request.';
//     header("Location: trash.php");
//     exit();
// }

$user_id = $_SESSION['user_id'];
$note_id = filter_input(INPUT_POST, 'note_id', FILTER_VALIDATE_INT);

if ($note_id) {
    $stmt = $conn->prepare("UPDATE notes SET is_trashed = 0, trashed_at = NULL WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $note_id, $user_id);

    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $_SESSION['success_message'] = "Note restored successfully!";
    } else {
        $_SESSION['error_message'] = "Failed to restore note.";
    }
    $stmt->close();
}

header("Location: trash.php");
exit();
?>