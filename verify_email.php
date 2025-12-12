<?php
session_start();
require_once 'db_connect.php';

if (isset($_GET['token'])) {
    $token = $_GET['token'];

    // Prepare statement to find user with this token
    $stmt = $conn->prepare("SELECT id FROM users WHERE verification_token = ?");
    if ($stmt) {
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $stmt->bind_result($userId);
            $stmt->fetch();
            $stmt->close();

            // Update user to verified and clear/consume the token
            $updateStmt = $conn->prepare("UPDATE users SET is_verified = 1, verification_token = NULL WHERE id = ?");
            if ($updateStmt) {
                $updateStmt->bind_param("i", $userId);
                if ($updateStmt->execute()) {
                    $_SESSION['success_message'] = "Email verified successfully! You can now login.";
                } else {
                    $_SESSION['errors'] = ["Failed to update verification status."];
                }
                $updateStmt->close();
            } else {
                error_log("Database error (prepare update verified): " . $conn->error);
                $_SESSION['errors'] = ["An internal error occurred."];
            }

        } else {
            $_SESSION['errors'] = ["Invalid or expired verification token."];
            $stmt->close();
        }
    } else {
        error_log("Database error (prepare select token): " . $conn->error);
        $_SESSION['errors'] = ["An internal error occurred."];
    }
} else {
    $_SESSION['errors'] = ["No verification token provided."];
}

header("Location: login.php");
exit();
?>