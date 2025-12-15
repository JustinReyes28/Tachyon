<?php
/**
 * resend_verification.php
 * 
 * Allows users to request a new verification email.
 * Includes rate limiting to prevent abuse.
 */
session_start();
require_once 'db_connect.php';
require_once 'includes/EmailNotifier.php';
require_once 'includes/functions.php';

// CSRF token generation
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];
$success_message = '';
$email = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    // if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    //     $errors[] = 'Invalid CSRF token.';
    // } else {
    // Rotate token after successful validation
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    $email = trim($_POST['email'] ?? '');

    // Validate email
    if (empty($email)) {
        $errors[] = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    } else {
        // Check if user exists and is not verified
        // Use TIMESTAMPDIFF to let the database handle time calculations (avoids timezone mismatches between PHP and DB)
        $stmt = $conn->prepare("SELECT id, username, email_verified, verification_token, TIMESTAMPDIFF(SECOND, updated_at, NOW()) as time_since_update FROM users WHERE email = ?");
        if ($stmt) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                $stmt->close();

                if ($user['email_verified']) {
                    // Don't reveal if email is verified - use generic message
                    $success_message = "If an account exists with this email and is not verified, a verification email has been sent.";
                } else {
                    // Rate limiting: Check if last update was less than 30 seconds ago
                    // We use the DB-calculated difference
                    $timeDiff = (int) $user['time_since_update'];

                    if ($timeDiff >= 0 && $timeDiff < 30 && !empty($user['verification_token'])) {
                        // Rate limited
                        $waitTime = 30 - $timeDiff;
                        $errors[] = "Please wait $waitTime seconds before requesting another verification email.";
                    } else {
                        // Generate new token
                        $newToken = bin2hex(random_bytes(32));

                        $updateStmt = $conn->prepare("UPDATE users SET verification_token = ?, updated_at = NOW() WHERE id = ?");
                        if ($updateStmt) {
                            $updateStmt->bind_param("si", $newToken, $user['id']);

                            if ($updateStmt->execute()) {
                                // Send verification email
                                $baseUrl = defined('APP_URL') ? APP_URL : "https://tachyon.rf.gd";
                                $verifyLink = "$baseUrl/verify_email.php?token=$newToken";

                                try {
                                    $emailNotifier = new EmailNotifier();
                                    if ($emailNotifier->sendVerificationEmail($email, $user['username'], $verifyLink)) {
                                        $success_message = "A new verification email has been sent. Please check your inbox.";
                                    } else {
                                        $errors[] = "Failed to send verification email. Please try again later.";
                                        error_log("resend_verification: Failed to send email to $email");
                                    }
                                } catch (Exception $e) {
                                    $errors[] = "Failed to send verification email. Please try again later.";
                                    error_log("resend_verification: Exception - " . $e->getMessage());
                                }
                            } else {
                                $errors[] = "An error occurred. Please try again.";
                                error_log("resend_verification: Update failed - " . $updateStmt->error);
                            }
                            $updateStmt->close();
                        } else {
                            $errors[] = "An internal error occurred.";
                            error_log("resend_verification: Prepare update failed - " . $conn->error);
                        }
                    }
                }
            } else {
                // User not found - use generic message to prevent email enumeration
                $success_message = "If an account exists with this email and is not verified, a verification email has been sent.";
                $stmt->close();
            }
        } else {
            $errors[] = "An internal error occurred.";
            error_log("resend_verification: Prepare select failed - " . $conn->error);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <title>Resend Verification - Tachyon</title>
    <?php include 'includes/head.php'; ?>
</head>

<body>
    <!-- Dot Matrix Background Pattern -->
    <div class="dot-pattern"></div>

    <div class="auth-body" style="flex: 1;">
        <div class="auth-container">
            <h2 class="text-center mb-6">RESEND VERIFICATION</h2>

            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <?php if (count($errors) === 1): ?>
                        <?php echo htmlspecialchars($errors[0]); ?>
                    <?php else: ?>
                        <ul style="list-style: none;">
                            <?php foreach ($errors as $err): ?>
                                <li><?php echo htmlspecialchars($err); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <p style="text-align: center; color: #666; margin-bottom: 1.5rem; font-size: 0.9rem;">
                Enter your email address and we'll send you a new verification link.
            </p>

            <form action="resend_verification.php" method="post" class="needs-validation">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($email); ?>"
                        required>
                </div>
                <button type="submit" class="btn btn-primary btn-block">Send Verification Email</button>
            </form>

            <p class="text-center" style="margin-top: 1.5rem; font-size: 0.875rem; letter-spacing: 0.05em;">
                Already verified? <a href="login.php">Login</a>
            </p>
            <p class="text-center" style="margin-top: 0.5rem; font-size: 0.875rem; letter-spacing: 0.05em;">
                Don't have an account? <a href="register.php">Register</a>
            </p>
        </div>
    </div>
    <script src="<?php echo asset_url('script.js'); ?>"></script>
</body>

</html>