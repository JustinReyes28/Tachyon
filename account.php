<?php
// account.php - Account / Profile hub
session_start();
require_once 'includes/functions.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <title>Account - Tachyon</title>
    <?php include 'includes/head.php'; ?>
</head>

<body>
    <div class="dot-pattern"></div>

    <div class="dashboard-container">
        <header class="app-header">
            <h1 class="app-title">ACCOUNT</h1>
            <div class="user-nav">
                <a href="index.php" class="btn btn-sm">Home</a>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="dashboard.php" class="btn btn-sm">Dashboard</a>
                    <a href="logout.php" class="btn btn-sm">Logout</a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-sm">Login</a>
                    <a href="register.php" class="btn btn-sm">Register</a>
                <?php endif; ?>
            </div>
        </header>

        <section class="profile-section">
            <div class="profile-content">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <h2 class="section-title">You are signed in</h2>
                    <p class="mb-4">
                        Access your full Tachyon experience: synced todos, rich notes, backups, and profile controls.
                    </p>
                    <div class="nav-buttons" style="margin-top: var(--space-lg);">
                        <a href="dashboard.php" class="nav-btn">[Dashboard]</a>
                        <a href="todos.php" class="nav-btn">[ToDos]</a>
                        <a href="create_note.php" class="nav-btn">[Notes]</a>
                        <a href="profile.php" class="nav-btn">[Profile]</a>
                    </div>
                <?php else: ?>
                    <h2 class="section-title">Upgrade from guest mode</h2>
                    <p class="mb-4">
                        You are currently using Tachyon as a guest. Create an account to sync your data across devices,
                        access email features, and manage backups.
                    </p>
                    <div class="nav-buttons" style="margin-top: var(--space-lg);">
                        <a href="login.php" class="nav-btn">[Login]</a>
                        <a href="register.php" class="nav-btn">[Create Account]</a>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </div>
    <script src="<?php echo asset_url('script.js'); ?>"></script>
</body>

</html>








