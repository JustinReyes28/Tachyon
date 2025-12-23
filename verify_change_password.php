<?php
// verify_change_password.php - Verify code and update password
session_start();
require_once 'db_connect.php';
require_once 'includes/functions.php';

// Session protection
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Check if there's a pending password change
if (!isset($_SESSION['pending_password_hash'])) {
    $_SESSION['errors'] = ["No pending password change. Please start again."];
    header("Location: change_password.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'User';

// CSRF token generation
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Check
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['errors'] = ["Invalid CSRF token."];
        header("Location: verify_change_password.php");
        exit();
    }

    $code = trim($_POST['verification_code'] ?? '');

    if (empty($code)) {
        $_SESSION['errors'] = ["Verification code is required."];
        header("Location: verify_change_password.php");
        exit();
    }

    // Verify code from database
    $stmt = $conn->prepare("SELECT password_change_token, password_change_token_expires FROM users WHERE id = ? AND password_change_token_expires > NOW()");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if (!$user || !password_verify($code, $user['password_change_token'])) {
        $_SESSION['errors'] = ["Invalid or expired verification code."];
        header("Location: verify_change_password.php");
        exit();
    }

    // Update password
    $new_password_hash = $_SESSION['pending_password_hash'];
    $stmt = $conn->prepare("UPDATE users SET password_hash = ?, password_changed_at = NOW(), password_change_token = NULL, password_change_token_expires = NULL WHERE id = ?");
    $stmt->bind_param("si", $new_password_hash, $user_id);
    
    if ($stmt->execute()) {
        // Clear session data
        unset($_SESSION['pending_password_hash']);
        $_SESSION['success_message'] = "Your password has been changed successfully.";
        header("Location: profile.php");
        exit();
    } else {
        $_SESSION['errors'] = ["Failed to update password. Please try again."];
        header("Location: verify_change_password.php");
        exit();
    }
}

// Retrieve messages from session
$errors = $_SESSION['errors'] ?? [];
$success_message = $_SESSION['success_message'] ?? '';
unset($_SESSION['errors'], $_SESSION['success_message']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <title>Verify Code - Tachyon</title>
    <?php include 'includes/head.php'; ?>
</head>

<body>
    <!-- Dot Matrix Background Pattern -->
    <div class="dot-pattern"></div>

    <div class="auth-body" style="flex: 1;">
        <div class="auth-container">
            <h2 class="text-center mb-6">VERIFY CODE</h2>

            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <?php foreach ($errors as $err): ?>
                        <div><?php echo htmlspecialchars($err); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <p style="text-align: center; margin-bottom: 1.5rem; color: #6c757d;">
                Enter the 6-digit verification code sent to your email.
            </p>

            <form action="verify_change_password.php" method="post">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                
                <div class="form-group">
                    <label for="verification_code">Verification Code</label>
                    <input type="text" name="verification_code" id="verification_code" required 
                           maxlength="6" pattern="[0-9]{6}" placeholder="000000"
                           style="text-align: center; letter-spacing: 0.5rem; font-size: 1.5rem;">
                </div>

                <button type="submit" class="btn btn-primary btn-block">VERIFY & CHANGE PASSWORD</button>
            </form>

            <p class="text-center" style="margin-top: 1.5rem; font-size: 0.875rem;">
                <a href="change_password.php">Start Over</a>
            </p>
        </div>
    </div>
    <script src="<?php echo asset_url('script.js'); ?>"></script>
</body>

</html>








