<?php
// welcome.php
session_start();

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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome - Todo App</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
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
                <a href="dashboard.php" class="btn btn-primary">DASHBOARD</a>
                <a href="logout.php" class="btn btn-primary">LOGOUT</a>
            </div>
        </div>
    </div>
</body>

</html>