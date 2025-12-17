<?php
// process_delete_account.php - Process delete account request
session_start();
require_once 'db_connect.php';
require_once 'includes/EmailNotifier.php';

// Session protection
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: delete_account.php");
    exit();
}

// CSRF Check
if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $_SESSION['errors'] = ["Invalid CSRF token."];
    header("Location: delete_account.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$password = $_POST['password'] ?? '';

if (empty($password)) {
    $_SESSION['errors'] = ["Password is required."];
    header("Location: delete_account.php");
    exit();
}

// Verify password
$stmt = $conn->prepare("SELECT password_hash, email, username FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user || !password_verify($password, $user['password_hash'])) {
    $_SESSION['errors'] = ["Incorrect password."];
    header("Location: delete_account.php");
    exit();
}

// Generate 6-digit verification code
$verification_code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

// Hash the verification code before storing
$verification_code_hash = password_hash($verification_code, PASSWORD_DEFAULT);

// Store hashed code in database (using account_deletion_token fields)
$stmt = $conn->prepare("UPDATE users SET account_deletion_token = ?, account_deletion_token_expires = DATE_ADD(NOW(), INTERVAL 10 MINUTE) WHERE id = ?");
$stmt->bind_param("si", $verification_code_hash, $user_id);
$stmt->execute();
$stmt->close();

// Set session flag for pending deletion
$_SESSION['pending_account_deletion'] = true;

// Send verification code email
$emailNotifier = new EmailNotifier();
if ($emailNotifier->sendDeleteAccountCode($user['email'], $user['username'], $verification_code)) {
    $_SESSION['success_message'] = "A verification code has been sent to your email.";
} else {
    $_SESSION['success_message'] = "A verification code has been sent to your email.";
    // Log error but don't expose to user
    error_log("Failed to send delete account verification code to user ID: $user_id");
}

header("Location: verify_delete_account.php");
exit();

