<?php
// empty_trash.php - Delete all trashed items
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
$deleted_count = 0;

// Delete trashed todos
if ($conn->query("DELETE FROM todos WHERE user_id = $user_id AND is_trashed = 1") === TRUE) {
    $deleted_count += $conn->affected_rows;
}

// Delete trashed notes
if ($conn->query("DELETE FROM notes WHERE user_id = $user_id AND is_trashed = 1") === TRUE) {
    $deleted_count += $conn->affected_rows;
}

if ($deleted_count > 0) {
    $_SESSION['success_message'] = "Trash emptied. $deleted_count items deleted forever.";
} else {
    $_SESSION['success_message'] = "Trash was already empty.";
}

header("Location: trash.php");
exit();
?>