<?php
// login.php
session_start();
require_once 'includes/functions.php';

// CSRF token generation
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Retrieve errors or old data from session if they exist
$errors = $_SESSION['errors'] ?? [];
$form_data = $_SESSION['form_data'] ?? [];
$success_message = $_SESSION['success_message'] ?? '';

// Clear session variables so they don't persist on refresh
unset($_SESSION['errors'], $_SESSION['form_data'], $_SESSION['success_message']);

$username_email = $form_data['username_email'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <title>Login - Todo App</title>
    <?php include 'includes/head.php'; ?>
</head>

<body>
    <!-- Dot Matrix Background Pattern -->
    <div class="dot-pattern"></div>

    <div class="auth-body" style="flex: 1;">
        <div class="auth-container">
            <h1 class="text-center"
                style="font-size: 2.75rem; font-weight: 1000; letter-spacing: 0.1em; text-transform: uppercase; color: var(--color-black); margin-bottom: 30px;">
                Welcome to Tachyon</h1>
            <!-- <h2 class="text-center mb-6">LOGIN</h2> -->

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

            <form action="login_process.php" method="post" class="needs-validation">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <div class="form-group">
                    <label for="username_email">Username or Email</label>
                    <input type="text" name="username_email" id="username_email"
                        value="<?php echo htmlspecialchars($username_email); ?>" required>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" name="password" id="password" required>
                </div>
                <button type="submit" class="btn btn-primary btn-block">LOGIN</button>
            </form>
            <p class="text-center" style="margin-top: 1rem; font-size: 0.875rem;">
                <a href="forgot_password.php">Forgot Password?</a>
            </p>
            <p class="text-center" style="margin-top: 0.5rem; font-size: 0.875rem; letter-spacing: 0.05em;">
                Don't have an account? <a href="register.php">Register</a>
            </p>
        </div>
    </div>
    <script src="<?php echo asset_url('script.js'); ?>"></script>
</body>

</html>