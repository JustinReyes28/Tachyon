<?php
session_start();
require_once 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Check
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['errors'] = ["Invalid CSRF token."];
        header("Location: forgot_password.php");
        exit();
    }

    $email = trim($_POST['email'] ?? '');

    if (empty($email)) {
        $_SESSION['errors'] = ["Email is required."];
        header("Location: forgot_password.php");
        exit();
    }

    // Check if email exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    if ($stmt) {
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $stmt->bind_result($userId);
            $stmt->fetch();
            $stmt->close();

            // Generate token and expiration (1 hour from now)
            $token = bin2hex(random_bytes(32));
            $expires_at = date('Y-m-d H:i:s', time() + 3600);

            $updateStmt = $conn->prepare("UPDATE users SET reset_token = ?, reset_token_expires = ? WHERE id = ?");
            if ($updateStmt) {
                $updateStmt->bind_param("ssi", $token, $expires_at, $userId);
                if ($updateStmt->execute()) {
                    // Send email using PHPMailer
                    require_once 'mailer.php';
                    $resetLink = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/reset_password.php?token=$token";

                    if (sendResetEmail($email, $resetLink)) {
                        $_SESSION['success_message'] = "If an account exists with that email, a password reset link has been sent.";
                    } else {
                        // Fallback logging if email fails (for debugging)
                        $log_dir = __DIR__ . '/private_logs';
                        if (!is_dir($log_dir))
                            mkdir($log_dir, 0750, true);
                        file_put_contents($log_dir . '/email_errors.log', "[" . date('Y-m-d H:i:s') . "] Failed to send to $email" . PHP_EOL, FILE_APPEND);
                        $_SESSION['success_message'] = "If an account exists with that email, a password reset link has been sent.";
                    }
                } else {
                    error_log("DB Error (update reset token): " . $conn->error);
                    $_SESSION['errors'] = ["An error occurred. Please try again."];
                }
                $updateStmt->close();
            } else {
                error_log("DB Error (prepare update reset token): " . $conn->error);
                $_SESSION['errors'] = ["An error occurred. Please try again."];
            }
        } else {
            // Email not found - for security, we show the same message
            $_SESSION['success_message'] = "If an account exists with that email, a password reset link has been sent.";
            $stmt->close();
        }
    } else {
        error_log("DB Error (check email): " . $conn->error);
        $_SESSION['errors'] = ["An error occurred. Please try again."];
    }
}

header("Location: forgot_password.php");
exit();
?>