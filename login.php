<?php
// login.php
session_start();

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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Todo App</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <div class="auth-body" style="flex: 1;">
        <div class="auth-container">
            <h2 class="text-center mb-6">Welcome Back</h2>

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
                <button type="submit" class="btn btn-primary btn-block" style="width: 100%;">Login</button>
            </form>
            <p class="text-center mt-4" style="margin-top: 1.5rem; color: var(--text-secondary); font-size: 0.9rem;">
                Don't have an account? <a href="register.php"
                    style="color: var(--primary-color); text-decoration: none; font-weight: 600;">Register here</a>
            </p>
        </div>
    </div>
    <script src="script.js"></script>
</body>

</html>