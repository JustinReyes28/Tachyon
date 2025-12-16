<?php
/**
 * resend_verification.php
 * 
 * Allows users to request a new verification code.
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
$redirect_to_verify = false;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Rotate token after successful validation
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    $email = trim($_POST['email'] ?? '');
    $redirect_to_verify = isset($_POST['redirect_to_verify']) && $_POST['redirect_to_verify'] === '1';

    // Validate email
    if (empty($email)) {
        $errors[] = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    } else {
        // Check if user exists and is not verified
        $stmt = $conn->prepare("SELECT id, username, email_verified, verification_code_expires, TIMESTAMPDIFF(SECOND, verification_code_expires, NOW()) as time_since_expire FROM users WHERE email = ?");
        if ($stmt) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                $stmt->close();

                if ($user['email_verified']) {
                    // Don't reveal if email is verified - use generic message
                    $success_message = "If an account exists with this email and is not verified, a verification code has been sent.";
                } else {
                    // Rate limiting: Check if code was generated in the last 30 seconds
                    // If verification_code_expires is set and hasn't expired more than 9.5 minutes ago
                    // (meaning the code was generated less than 30 seconds ago)
                    $canResend = true;
                    if (!empty($user['verification_code_expires'])) {
                        $expiresAt = strtotime($user['verification_code_expires']);
                        $generatedAt = $expiresAt - (10 * 60); // 10 minutes before expiration
                        $timeSinceGenerated = time() - $generatedAt;

                        if ($timeSinceGenerated < 30) {
                            $waitTime = 30 - $timeSinceGenerated;
                            $errors[] = "Please wait $waitTime seconds before requesting another code.";
                            $canResend = false;
                        }
                    }

                    if ($canResend) {
                        // Generate new 6-digit code
                        $newCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                        $newExpires = date('Y-m-d H:i:s', strtotime('+10 minutes'));

                        $updateStmt = $conn->prepare("UPDATE users SET verification_code = ?, verification_code_expires = ? WHERE id = ?");
                        if ($updateStmt) {
                            $updateStmt->bind_param("ssi", $newCode, $newExpires, $user['id']);

                            if ($updateStmt->execute()) {
                                // Send verification code email
                                try {
                                    $emailNotifier = new EmailNotifier();
                                    if ($emailNotifier->sendVerificationCode($email, $user['username'], $newCode)) {
                                        if ($redirect_to_verify) {
                                            $_SESSION['pending_verification_email'] = $email;
                                            $_SESSION['success_message'] = "A new verification code has been sent to your email.";
                                            $updateStmt->close();
                                            header("Location: verify_code.php");
                                            exit();
                                        } else {
                                            $success_message = "A new verification code has been sent. Please check your inbox.";
                                        }
                                    } else {
                                        $errors[] = "Failed to send verification code. Please try again later.";
                                        error_log("resend_verification: Failed to send code to $email");
                                    }
                                } catch (Exception $e) {
                                    $errors[] = "Failed to send verification code. Please try again later.";
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
                $success_message = "If an account exists with this email and is not verified, a verification code has been sent.";
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
                Enter your email address and we'll send you a new verification code.
            </p>

            <form action="resend_verification.php" method="post" class="needs-validation">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($email); ?>"
                        required>
                </div>
                <button type="submit" class="btn btn-primary btn-block">Send Verification Code</button>
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