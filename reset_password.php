<?php
session_start();
// Check if token is present
$token = $_GET['token'] ?? '';
if (empty($token) && empty($_POST['token'])) {
    die("Invalid request. No token provided.");
}

// Generate CSRF token if needed
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors = $_SESSION['errors'] ?? [];
unset($_SESSION['errors']);

// If token is in POST (from submission error), use that, otherwise GET
$tokenValue = $_POST['token'] ?? $token;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Todo App</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <div class="dot-pattern"></div>
    <div class="auth-body" style="flex: 1;">
        <div class="auth-container">
            <h2 class="text-center mb-6">NEW PASSWORD</h2>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <?php foreach ($errors as $err): ?>
                        <div><?php echo htmlspecialchars($err); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form action="process_reset_password.php" method="post">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($tokenValue); ?>">

                <div class="form-group">
                    <label for="password">New Password</label>
                    <input type="password" name="password" id="password" required>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" name="confirm_password" id="confirm_password" required>
                </div>

                <button type="submit" class="btn btn-primary btn-block">RESET PASSWORD</button>
            </form>
        </div>
    </div>
</body>

</html>