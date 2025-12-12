<?php
session_start();
require_once 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("Invalid CSRF token.");
    }

    $token = $_POST['token'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    $errors = [];

    if (empty($token)) {
        $errors[] = "Missing reset token.";
    }
    if (empty($password) || strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters.";
    }
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }

    if (!empty($errors)) {
        $_SESSION['errors'] = $errors;
        // Redirect back to reset page with the token
        header("Location: reset_password.php?token=" . urlencode($token));
        exit();
    }

    // Verify token and expiration
    // Debug: Log the token being checked
    $log_dir = __DIR__ . '/private_logs';
    if (!is_dir($log_dir))
        mkdir($log_dir, 0750, true);
    file_put_contents($log_dir . '/reset_debug.log', "[" . date('Y-m-d H:i:s') . "] Checking token: " . substr($token, 0, 10) . "...\n", FILE_APPEND);

    // Debug: Check if token exists at all (without expiration check)
    $debugStmt = $conn->prepare("SELECT id, reset_token, reset_token_expires FROM users WHERE reset_token = ?");
    $debugStmt->bind_param("s", $token);
    $debugStmt->execute();
    $debugResult = $debugStmt->get_result();
    if ($debugResult->num_rows > 0) {
        $debugRow = $debugResult->fetch_assoc();
        // Also get MySQL's NOW() to compare
        $nowResult = $conn->query("SELECT NOW() as mysql_now");
        $mysqlNow = $nowResult->fetch_assoc()['mysql_now'];
        file_put_contents($log_dir . '/reset_debug.log', "[" . date('Y-m-d H:i:s') . "] Token EXISTS for user ID: " . $debugRow['id'] . ", Expires: " . $debugRow['reset_token_expires'] . ", PHP NOW: " . date('Y-m-d H:i:s') . ", MySQL NOW: " . $mysqlNow . "\n", FILE_APPEND);
    } else {
        file_put_contents($log_dir . '/reset_debug.log', "[" . date('Y-m-d H:i:s') . "] Token NOT FOUND in database\n", FILE_APPEND);
    }
    $debugStmt->close();

    $stmt = $conn->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_token_expires > NOW()");
    if ($stmt) {
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $stmt->store_result();

        // Debug: Log the result
        file_put_contents($log_dir . '/reset_debug.log', "[" . date('Y-m-d H:i:s') . "] With expiration check - Found rows: " . $stmt->num_rows . "\n", FILE_APPEND);

        if ($stmt->num_rows > 0) {
            $stmt->bind_result($userId);
            $stmt->fetch();
            $stmt->close();

            // Hash new password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Update password and clear token
            $updateStmt = $conn->prepare("UPDATE users SET password_hash = ?, reset_token = NULL, reset_token_expires = NULL WHERE id = ?");
            if ($updateStmt) {
                $updateStmt->bind_param("si", $hashed_password, $userId);
                if ($updateStmt->execute()) {
                    $_SESSION['success_message'] = "Password reset successful! You can now login.";
                    header("Location: login.php");
                    exit();
                } else {
                    error_log("DB Error (update password): " . $conn->error);
                    die("An error occurred updating your password.");
                }
            } else {
                error_log("DB Error (prepare update password): " . $conn->error);
                die("An internal error occurred.");
            }
        } else {
            $stmt->close();
            $_SESSION['errors'] = ["Invalid or expired reset token."];
            // Since token is invalid, maybe redirect to forgot password? Or back to reset page to show error
            header("Location: reset_password.php?token=" . urlencode($token));
            exit();
        }
    } else {
        error_log("DB Error (select token): " . $conn->error);
        die("An internal error occurred.");
    }

} else {
    header("Location: login.php");
    exit();
}
?>