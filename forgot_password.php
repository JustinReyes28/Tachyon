<?php
session_start();
require_once 'includes/functions.php';
// Database connection not required for form render; moved to process_forgot_password.php
// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$errors = $_SESSION['errors'] ?? [];
$success_message = $_SESSION['success_message'] ?? '';
unset($_SESSION['errors'], $_SESSION['success_message']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Todo App</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo asset_url('style.css'); ?>">
</head>

<body>
    <div class="dot-pattern"></div>
    <div class="auth-body" style="flex: 1;">
        <div class="auth-container">
            <h2 class="text-center mb-6">RESET PASSWORD</h2>

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

            <form action="process_forgot_password.php" method="post">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <div class="form-group">
                    <label for="email">Enter your email address</label>
                    <input type="email" name="email" id="email" required>
                </div>
                <button type="submit" class="btn btn-primary btn-block">SEND RESET LINK</button>
            </form>
            <p class="text-center" style="margin-top: 1.5rem; font-size: 0.875rem;">
                Remembered your password? <a href="login.php">Login</a>
            </p>
        </div>
    </div>
</body>

</html>