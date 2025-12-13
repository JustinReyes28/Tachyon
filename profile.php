<?php
// profile.php - User Profile Page (protected page)
session_start();
require_once 'db_connect.php';
require_once 'includes/functions.php';

// Session protection - redirect if not authenticated
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'User';

// Fetch user email from database
$email = '';
try {
    $stmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user_data = $result->fetch_assoc();
    $email = $user_data['email'] ?? '';
    $stmt->close();
} catch (Exception $e) {
    error_log("Error fetching user email: " . $e->getMessage());
    $email = '';
}

// CSRF token generation
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Retrieve messages from session
$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - Tachyon</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo asset_url('style.css'); ?>">
</head>

<body>
    <!-- Dot Matrix Background Pattern -->
    <div class="dot-pattern"></div>

    <div class="dashboard-container">
        <!-- Header -->
        <header class="app-header">
            <h1 class="app-title">PROFILE</h1>
            <div class="user-nav">
                <span class="user-welcome"><?php echo htmlspecialchars($username); ?></span>
                <a href="logout.php" class="btn btn-sm">Logout</a>
            </div>
        </header>

        <!-- Profile Navigation -->
        <section class="dashboard-nav-section">
            <h2 class="section-title">Profile Navigation</h2>
            <div class="nav-buttons">
                <a href="dashboard.php" class="nav-btn">[Dashboard]</a>
                <a href="todos.php" class="nav-btn">[ToDos]</a>
                <a href="create_note.php" class="nav-btn">[Notes]</a>
            </div>
        </section>

        <!-- Messages -->
        <?php if ($success_message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <!-- Profile Content Section -->
        <section class="profile-section">
            <div class="profile-content">
                <h3>User Information</h3>

                <div class="profile-info" style="margin-bottom: 1.5rem;">
                    <div class="info-item"
                        style="display: flex; justify-content: space-between; margin-bottom: 0.5rem; padding: 0.5rem 0;">
                        <label style="color: #6c757d; font-weight: 500;">Username:</label>
                        <span><?php echo htmlspecialchars($username); ?></span>
                    </div>
                    <div class="info-item"
                        style="display: flex; justify-content: space-between; margin-bottom: 0.5rem; padding: 0.5rem 0;">
                        <label style="color: #6c757d; font-weight: 500;">User ID:</label>
                        <span><?php echo htmlspecialchars($user_id); ?></span>
                    </div>
                    <div class="info-item"
                        style="display: flex; justify-content: space-between; margin-bottom: 0.5rem; padding: 0.5rem 0;">
                        <label style="color: #6c757d; font-weight: 500;">Email Address:</label>
                        <span><?php echo htmlspecialchars($email); ?></span>
                    </div>
                </div>

                <div class="profile-actions" style="margin-top: 2.5rem;">
                    <h3 style="margin-bottom: 1rem;">Profile Actions</h3>
                    <button class="btn btn-primary">Change Password</button>
                    <button class="btn btn-danger">Delete Account</button>
                </div>
            </div>
        </section>
    </div>
    <script src="<?php echo asset_url('script.js'); ?>"></script>
</body>

</html>