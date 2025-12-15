<?php
/**
 * verify_email.php
 * 
 * Handles email verification when user clicks the link from their email.
 * Displays a styled page with success or error message.
 */
session_start();
require_once 'db_connect.php';
require_once 'includes/functions.php';

$page_title = "Email Verification";
$status = 'error';
$message = '';
$show_login_link = false;
$show_resend_link = false;

if (isset($_GET['token']) && !empty($_GET['token'])) {
    $token = trim($_GET['token']);
    
    // Validate token format (should be 64 hex characters)
    if (!preg_match('/^[a-f0-9]{64}$/i', $token)) {
        $message = "Invalid verification token format.";
    } else {
        // Prepare statement to find user with this token
        $stmt = $conn->prepare("SELECT id, email, email_verified FROM users WHERE verification_token = ?");
        if ($stmt) {
            $stmt->bind_param("s", $token);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows > 0) {
                $stmt->bind_result($userId, $userEmail, $alreadyVerified);
                $stmt->fetch();
                $stmt->close();

                if ($alreadyVerified) {
                    $status = 'info';
                    $message = "Your email has already been verified. You can login to your account.";
                    $show_login_link = true;
                } else {
                    // Update user to verified and clear/consume the token
                    $updateStmt = $conn->prepare("UPDATE users SET email_verified = 1, verification_token = NULL WHERE id = ?");
                    if ($updateStmt) {
                        $updateStmt->bind_param("i", $userId);
                        if ($updateStmt->execute()) {
                            $status = 'success';
                            $message = "Your email has been verified successfully! You can now login to your account.";
                            $show_login_link = true;
                        } else {
                            $message = "Failed to update verification status. Please try again.";
                            error_log("verify_email: Failed to update user $userId - " . $updateStmt->error);
                        }
                        $updateStmt->close();
                    } else {
                        error_log("verify_email: Prepare update failed - " . $conn->error);
                        $message = "An internal error occurred. Please try again later.";
                    }
                }
            } else {
                $message = "Invalid or expired verification token. Please request a new verification email.";
                $show_resend_link = true;
                $stmt->close();
            }
        } else {
            error_log("verify_email: Prepare select failed - " . $conn->error);
            $message = "An internal error occurred. Please try again later.";
        }
    }
} else {
    $message = "No verification token provided. Please use the link from your verification email.";
    $show_resend_link = true;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <title><?php echo htmlspecialchars($page_title); ?> - Tachyon</title>
    <?php include 'includes/head.php'; ?>
    <style>
        .verification-container {
            max-width: 500px;
            margin: 0 auto;
            padding: 2rem;
            text-align: center;
        }

        .verification-icon {
            font-size: 4rem;
            margin-bottom: 1.5rem;
        }

        .verification-icon.success {
            color: #10B981;
        }

        .verification-icon.error {
            color: #EF4444;
        }

        .verification-icon.info {
            color: #3B82F6;
        }

        .verification-title {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: var(--color-black);
        }

        .verification-message {
            font-size: 1rem;
            color: #666;
            line-height: 1.6;
            margin-bottom: 2rem;
        }

        .verification-actions {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            align-items: center;
        }

        .verification-actions .btn {
            min-width: 200px;
        }
    </style>
</head>

<body>
    <!-- Dot Matrix Background Pattern -->
    <div class="dot-pattern"></div>

    <div class="auth-body" style="flex: 1;">
        <div class="auth-container verification-container">
            <?php if ($status === 'success'): ?>
                <div class="verification-icon success">✓</div>
                <h1 class="verification-title">Email Verified!</h1>
            <?php elseif ($status === 'info'): ?>
                <div class="verification-icon info">ℹ</div>
                <h1 class="verification-title">Already Verified</h1>
            <?php else: ?>
                <div class="verification-icon error">✕</div>
                <h1 class="verification-title">Verification Failed</h1>
            <?php endif; ?>

            <p class="verification-message"><?php echo htmlspecialchars($message); ?></p>

            <div class="verification-actions">
                <?php if ($show_login_link): ?>
                    <a href="login.php" class="btn btn-primary">Go to Login</a>
                <?php endif; ?>

                <?php if ($show_resend_link): ?>
                    <a href="resend_verification.php" class="btn btn-primary">Resend Verification Email</a>
                <?php endif; ?>

                <a href="index.php" class="btn" style="background: transparent; border: 2px solid var(--color-black);">Back to Home</a>
            </div>
        </div>
    </div>

    <script src="<?php echo asset_url('script.js'); ?>"></script>
</body>

</html>