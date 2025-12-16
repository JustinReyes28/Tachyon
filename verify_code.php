<?php
/**
 * verify_code.php
 * 
 * Page where users enter their 6-digit verification code after registration.
 */
session_start();
require_once 'db_connect.php';
require_once 'includes/functions.php';
require_once 'includes/EmailNotifier.php';

// CSRF token generation
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];
$success_message = '';
$email = $_SESSION['pending_verification_email'] ?? $_GET['email'] ?? '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Rotate CSRF token
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    $code = trim($_POST['code'] ?? '');
    $email = trim($_POST['email'] ?? '');

    // Validate inputs
    if (empty($email)) {
        $errors[] = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }

    if (empty($code)) {
        $errors[] = "Verification code is required.";
    } elseif (!preg_match('/^\d{6}$/', $code)) {
        $errors[] = "Invalid code format. Please enter a 6-digit code.";
    }

    if (empty($errors)) {
        // Look up user by email
        $stmt = $conn->prepare("SELECT id, username, email_verified, verification_code, verification_code_expires FROM users WHERE email = ?");
        if ($stmt) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                $stmt->close();

                if ($user['email_verified']) {
                    $success_message = "Your email is already verified. You can login to your account.";
                } elseif (empty($user['verification_code'])) {
                    $errors[] = "No verification code found. Please request a new code.";
                } elseif ($user['verification_code'] !== $code) {
                    $errors[] = "Invalid verification code. Please try again.";
                } elseif (strtotime($user['verification_code_expires']) < time()) {
                    $errors[] = "Verification code has expired. Please request a new code.";
                } else {
                    // Code is valid - verify user
                    $updateStmt = $conn->prepare("UPDATE users SET email_verified = 1, verification_code = NULL, verification_code_expires = NULL WHERE id = ?");
                    if ($updateStmt) {
                        $updateStmt->bind_param("i", $user['id']);
                        if ($updateStmt->execute()) {
                            // Clear session data
                            unset($_SESSION['pending_verification_email']);

                            // Set success message for login page
                            $_SESSION['success_message'] = "Email verified successfully! You can now login to your account.";
                            $updateStmt->close();
                            header("Location: login.php");
                            exit();
                        } else {
                            $errors[] = "Failed to verify email. Please try again.";
                            error_log("verify_code: Update failed - " . $updateStmt->error);
                        }
                        $updateStmt->close();
                    } else {
                        $errors[] = "An internal error occurred.";
                        error_log("verify_code: Prepare update failed - " . $conn->error);
                    }
                }
            } else {
                $errors[] = "No account found with this email address.";
                $stmt->close();
            }
        } else {
            $errors[] = "An internal error occurred.";
            error_log("verify_code: Prepare select failed - " . $conn->error);
        }
    }
}

// Get success message from session if redirected from register
$session_message = $_SESSION['success_message'] ?? '';
unset($_SESSION['success_message']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <title>Verify Your Email - Tachyon</title>
    <?php include 'includes/head.php'; ?>
    <style>
        .code-input-container {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-bottom: 1.5rem;
        }

        .code-input {
            width: 50px;
            height: 60px;
            text-align: center;
            font-size: 1.75rem;
            font-weight: 600;
            border: 2px solid var(--color-gray);
            border-radius: 8px;
            background-color: #fff;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .code-input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.2);
        }

        .code-input::-webkit-outer-spin-button,
        .code-input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        .timer-text {
            text-align: center;
            color: #888;
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }

        .timer-text.expired {
            color: #dc3545;
        }

        .resend-section {
            text-align: center;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #eee;
        }
    </style>
</head>

<body>
    <!-- Dot Matrix Background Pattern -->
    <div class="dot-pattern"></div>

    <div class="auth-body" style="flex: 1;">
        <div class="auth-container">
            <h2 class="text-center mb-6">VERIFY YOUR EMAIL</h2>

            <?php if (!empty($session_message)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($session_message); ?></div>
            <?php endif; ?>

            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
                <div style="text-align: center; margin-top: 1rem;">
                    <a href="login.php" class="btn btn-primary">Go to Login</a>
                </div>
            <?php else: ?>

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

                <p style="text-align: center; color: #666; margin-bottom: 1.5rem; font-size: 0.95rem;">
                    We've sent a 6-digit verification code to<br>
                    <strong><?php echo htmlspecialchars($email ?: 'your email'); ?></strong>
                </p>

                <form action="verify_code.php" method="post" id="verifyForm">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">

                    <!-- Single hidden input for the combined code -->
                    <input type="hidden" name="code" id="combinedCode" value="">

                    <div class="code-input-container">
                        <input type="text" class="code-input" maxlength="1" pattern="[0-9]" inputmode="numeric"
                            data-index="0" autofocus>
                        <input type="text" class="code-input" maxlength="1" pattern="[0-9]" inputmode="numeric"
                            data-index="1">
                        <input type="text" class="code-input" maxlength="1" pattern="[0-9]" inputmode="numeric"
                            data-index="2">
                        <input type="text" class="code-input" maxlength="1" pattern="[0-9]" inputmode="numeric"
                            data-index="3">
                        <input type="text" class="code-input" maxlength="1" pattern="[0-9]" inputmode="numeric"
                            data-index="4">
                        <input type="text" class="code-input" maxlength="1" pattern="[0-9]" inputmode="numeric"
                            data-index="5">
                    </div>

                    <p class="timer-text">Code expires in <span id="countdown">10:00</span></p>

                    <button type="submit" class="btn btn-primary btn-block">VERIFY CODE</button>
                </form>

                <div class="resend-section">
                    <p style="color: #666; font-size: 0.9rem; margin-bottom: 0.75rem;">Didn't receive the code?</p>
                    <form action="resend_verification.php" method="post" style="display: inline;">
                        <input type="hidden" name="csrf_token"
                            value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                        <input type="hidden" name="redirect_to_verify" value="1">
                        <button type="submit" class="btn btn-secondary">
                            Resend Code
                        </button>
                    </form>
                </div>

            <?php endif; ?>

            <p class="text-center" style="margin-top: 1.5rem; font-size: 0.875rem; letter-spacing: 0.05em;">
                <a href="login.php">Back to Login</a>
            </p>
        </div>
    </div>

    <script>
        // Code input handling
        const codeInputs = document.querySelectorAll('.code-input');
        const combinedCodeInput = document.getElementById('combinedCode');
        const form = document.getElementById('verifyForm');

        // Update combined code value
        function updateCombinedCode() {
            let code = '';
            codeInputs.forEach(input => {
                code += input.value;
            });
            combinedCodeInput.value = code;
        }

        codeInputs.forEach((input, index) => {
            // Handle input
            input.addEventListener('input', (e) => {
                const value = e.target.value;

                // Only allow digits
                if (!/^\d*$/.test(value)) {
                    e.target.value = '';
                    return;
                }

                // Move to next input
                if (value && index < codeInputs.length - 1) {
                    codeInputs[index + 1].focus();
                }

                updateCombinedCode();

                // Auto-submit when all fields are filled
                if (combinedCodeInput.value.length === 6) {
                    // Small delay for better UX
                    setTimeout(() => {
                        form.submit();
                    }, 100);
                }
            });

            // Handle backspace
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Backspace' && !e.target.value && index > 0) {
                    codeInputs[index - 1].focus();
                }
            });

            // Handle paste
            input.addEventListener('paste', (e) => {
                e.preventDefault();
                const pastedData = e.clipboardData.getData('text').replace(/\D/g, '').slice(0, 6);

                pastedData.split('').forEach((char, i) => {
                    if (codeInputs[i]) {
                        codeInputs[i].value = char;
                    }
                });

                // Focus the next empty input or the last one
                const nextEmptyIndex = Math.min(pastedData.length, codeInputs.length - 1);
                codeInputs[nextEmptyIndex].focus();

                updateCombinedCode();

                if (pastedData.length === 6) {
                    setTimeout(() => {
                        form.submit();
                    }, 100);
                }
            });
        });

        // Countdown timer (10 minutes from page load)
        const countdownEl = document.getElementById('countdown');
        let timeLeft = 10 * 60; // 10 minutes in seconds

        function updateCountdown() {
            const minutes = Math.floor(timeLeft / 60);
            const seconds = timeLeft % 60;
            countdownEl.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;

            if (timeLeft <= 0) {
                countdownEl.textContent = 'Expired';
                countdownEl.parentElement.classList.add('expired');
                clearInterval(timerInterval);
            } else {
                timeLeft--;
            }
        }

        const timerInterval = setInterval(updateCountdown, 1000);
        updateCountdown();
    </script>
    <script src="<?php echo asset_url('script.js'); ?>"></script>
</body>

</html>