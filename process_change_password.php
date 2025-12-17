<?php
// process_change_password.php - Process change password request
session_start();
require_once 'db_connect.php';
require_once 'includes/EmailNotifier.php';

// Session protection
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: change_password.php");
    exit();
}

// CSRF Check
if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $_SESSION['errors'] = ["Invalid CSRF token."];
    header("Location: change_password.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$current_password = $_POST['current_password'] ?? '';
$new_password = $_POST['new_password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

$errors = [];

// Validation
if (empty($current_password)) {
    $errors[] = "Current password is required.";
}
if (empty($new_password)) {
    $errors[] = "New password is required.";
}
if (strlen($new_password) < 8) {
    $errors[] = "New password must be at least 8 characters.";
}
if ($new_password !== $confirm_password) {
    $errors[] = "New passwords do not match.";
}

if (!empty($errors)) {
    $_SESSION['errors'] = $errors;
    header("Location: change_password.php");
    exit();
}

// Verify current password
$stmt = $conn->prepare("SELECT password_hash, email, username FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user || !password_verify($current_password, $user['password_hash'])) {
    $_SESSION['errors'] = ["Current password is incorrect."];
    header("Location: change_password.php");
    exit();
}

// Generate 6-digit verification code
$verification_code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

// Hash the verification code before storing
$verification_code_hash = password_hash($verification_code, PASSWORD_DEFAULT);

// Store hashed code in database (reusing reset_token fields)
$stmt = $conn->prepare("UPDATE users SET reset_token = ?, reset_token_expires = DATE_ADD(NOW(), INTERVAL 10 MINUTE) WHERE id = ?");
$stmt->bind_param("si", $verification_code_hash, $user_id);
$stmt->execute();
$stmt->close();

// Store new password hash in session temporarily
$_SESSION['pending_password_hash'] = password_hash($new_password, PASSWORD_DEFAULT);

// Send verification code email
$emailNotifier = new EmailNotifier();
if ($emailNotifier->sendChangePasswordCode($user['email'], $user['username'], $verification_code)) {
    $_SESSION['success_message'] = "A verification code has been sent to your email.";
} else {
    $_SESSION['success_message'] = "A verification code has been sent to your email.";
    // Log error but don't expose to user
    error_log("Failed to send change password verification code to user ID: $user_id");
}

header("Location: verify_change_password.php");
exit();

