<?php
// dashboard.php - Main dashboard (protected page)
session_start();
require_once 'db_connect.php';

// Session protection - redirect if not authenticated
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'User';

// CSRF token generation
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Retrieve messages from session
$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

// Fetch active notes count
$total_notes = 0;
try {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM notes WHERE user_id = ? AND is_archived = 0");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($total_notes);
    $stmt->fetch();
    $stmt->close();
} catch (Exception $e) {
    // Keep 0 if error
    error_log("Error counting notes: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Tachyon</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <!-- Dot Matrix Background Pattern -->
    <div class="dot-pattern"></div>

    <div class="dashboard-container">
        <!-- Header -->
        <header class="app-header">
            <h1 class="app-title">TACHYON</h1>
            <div class="user-nav">
                <span class="user-welcome"><?php echo htmlspecialchars($username); ?></span>
                <a href="logout.php" class="btn btn-sm">Logout</a>
            </div>
        </header>

        <!-- Dashboard Navigation -->
        <section class="dashboard-nav-section">
            <h2 class="section-title">Dashboard Navigation</h2>
            <div class="nav-buttons">
                <a href="todos.php" class="nav-btn">[ToDos]</a>
                <a href="create_note.php" class="nav-btn">[Notes]</a>
                <a href="profile.php" class="nav-btn">[Profile]</a>
            </div>
        </section>

        <!-- Messages -->
        <?php if ($success_message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <!-- Stats & Actions Grid -->
        <div class="stats-grid">
            <!-- Total Notes Card -->
            <div class="stat-card">
                <div class="stat-value"><?php echo $total_notes; ?></div>
                <div class="stat-label">Total Notes</div>
            </div>

            <!-- New Note Card -->
            <a href="create_note.php" class="stat-card" style="text-decoration: none;">
                <div class="stat-value">+</div>
                <div class="stat-label">New Note</div>
            </a>

            <!-- View All Notes Card -->
            <a href="notes.php" class="stat-card" style="text-decoration: none;">
                <div class="stat-value">ALL</div>
                <div class="stat-label">View Notes</div>
            </a>
        </div>

        <!-- Search Section -->
        <div class="search-section" style="margin-bottom: var(--space-xl);">
            <div class="search-field">
                <input type="text" id="search" name="search" placeholder="[Search...]" class="search-input">
            </div>
        </div>

        <!-- Notes Grid -->
        <section class="notes-section">
            <div class="notes-grid">
                <div class="note-card">
                    <h3 class="note-title">Sample Note 1</h3>
                    <p class="note-preview">This is a preview of your first note content...</p>
                    <div class="note-meta">
                        <span class="note-date">Dec 13, 2024</span>
                    </div>
                </div>
                <div class="note-card">
                    <h3 class="note-title">Sample Note 2</h3>
                    <p class="note-preview">This is a preview of your second note content...</p>
                    <div class="note-meta">
                        <span class="note-date">Dec 12, 2024</span>
                    </div>
                </div>
                <div class="note-card">
                    <h3 class="note-title">Sample Note 3</h3>
                    <p class="note-preview">This is a preview of your third note content...</p>
                    <div class="note-meta">
                        <span class="note-date">Dec 11, 2024</span>
                    </div>
                </div>
            </div>
        </section>
    </div>
    <script src="script.js"></script>
</body>

</html>