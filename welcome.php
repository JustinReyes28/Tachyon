<?php
// welcome.php
session_start();
require_once 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['username'] ?? 'User';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <title>Welcome - Todo App</title>
    <?php include 'includes/head.php'; ?>
</head>

<body>
    <!-- Dot Matrix Background Pattern -->
    <div class="dot-pattern"></div>

    <div class="auth-body" style="flex: 1;">
        <div class="auth-container text-center">
            <h1 class="mb-4" style="font-size: 2.5rem; letter-spacing: 0.15em;">WELCOME</h1>
            <p class="mb-6" style="font-size: 1rem; opacity: 0.7;">
                Hello <strong><?php echo htmlspecialchars($username); ?></strong>, you have successfully logged in.
            </p>
            <div style="display: flex; gap: 1rem; justify-content: center;">
                <a href="dashboard.php">DASHBOARD</a>
                <a href="logout.php">LOGOUT</a>
            </div>
        </div>
    </div>
</body>

</html>