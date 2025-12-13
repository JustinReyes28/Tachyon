<?php
// delete_note.php - Delete a note (protected page)
session_start();
require_once 'db_connect.php';

// Session protection - redirect if not authenticated
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get note ID from URL
$note_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($note_id <= 0) {
    $_SESSION['error_message'] = "Invalid note ID.";
    header("Location: notes.php");
    exit();
}

// Delete the note
try {
    // First verify the note belongs to the user
    $stmt = $conn->prepare("SELECT id FROM notes WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $note_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $_SESSION['error_message'] = "Note not found or you don't have permission to delete it.";
        header("Location: notes.php");
        exit();
    }
    $stmt->close();

    // Delete the note
    $delete_stmt = $conn->prepare("DELETE FROM notes WHERE id = ? AND user_id = ?");
    $delete_stmt->bind_param("ii", $note_id, $user_id);

    if ($delete_stmt->execute()) {
        $_SESSION['success_message'] = "Note deleted successfully.";
    } else {
        $_SESSION['error_message'] = "Failed to delete note. Please try again.";
    }
    $delete_stmt->close();

} catch (Exception $e) {
    error_log("Error deleting note: " . $e->getMessage());
    $_SESSION['error_message'] = "Failed to delete note. Please try again.";
}

header("Location: notes.php");
exit();
?>