<?php
// add_recurring.php - Process adding a new recurring reminder
session_start();
require_once 'db_connect.php';

// Session protection
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: recurring_reminders.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$task = trim($_POST['task'] ?? '');
$priority = $_POST['priority'] ?? 'medium';
$due_date = $_POST['due_date'] ?? null;
$recurring = $_POST['recurring'] ?? 'daily'; // Default to daily if not set or 'none'

if ($recurring === 'none') {
    $recurring = 'daily';
}

// Validate task
if (empty($task)) {
    $_SESSION['error_message'] = 'Reminder task is required.';
    header('Location: recurring_reminders.php');
    exit();
}

// Validate recurring interval
$allowed_recurring = ['daily', 'weekly', 'monthly', 'yearly'];
if (!in_array($recurring, $allowed_recurring)) {
    $recurring = 'daily';
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

// Insert into database
$description = '';
if ($final_due_date !== null) {
    $sql = "INSERT INTO todos (user_id, task, description, status, priority, due_date, recurring, created_by) VALUES (?, ?, ?, 'pending', ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isssssi", $user_id, $task, $description, $priority, $final_due_date, $recurring, $user_id);
} else {
    $sql = "INSERT INTO todos (user_id, task, description, status, priority, due_date, recurring, created_by) VALUES (?, ?, ?, 'pending', ?, NULL, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("issssi", $user_id, $task, $description, $priority, $recurring, $user_id);
}

if ($stmt && $stmt->execute()) {
    $_SESSION['success_message'] = 'Recurring reminder added successfully!';
} else {
    $_SESSION['error_message'] = 'Failed to add recurring reminder.';
}

if ($stmt)
    $stmt->close();

header('Location: recurring_reminders.php');
exit();
