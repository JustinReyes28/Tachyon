<?php
// view_note.php - View a single note (protected page)
session_start();
require_once 'db_connect.php';

// Include helper functions
require_once 'includes/functions.php';

// Session protection - redirect if not authenticated
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'User';

// Ensure CSRF token exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Content Security Policy
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.quilljs.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.quilljs.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: https:;");

// Get note ID from URL
$note_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($note_id <= 0) {
    $_SESSION['error_message'] = "Invalid note ID.";
    header("Location: notes.php");
    exit();
}

// Fetch the note
$note = null;
try {
    $stmt = $conn->prepare("SELECT id, title, content, color, is_pinned, is_archived, created_at, updated_at 
                            FROM notes 
                            WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $note_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $_SESSION['error_message'] = "Note not found.";
        header("Location: notes.php");
        exit();
    }

    $note = $result->fetch_assoc();
    $stmt->close();
} catch (Exception $e) {
    error_log("Error fetching note: " . $e->getMessage());
    $_SESSION['error_message'] = "Failed to load note.";
    header("Location: notes.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($note['title']); ?> - Tachyon</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .note-view-container {
            background-color: var(--color-white);
            color: var(--color-black);
            padding: var(--space-2xl);
            margin-bottom: var(--space-2xl);
            border: 2px solid var(--color-black);
        }

        .note-view-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: var(--space-lg);
            color: var(--color-black);
            word-break: break-word;
        }

        .note-view-meta {
            display: flex;
            gap: var(--space-lg);
            font-size: 0.875rem;
            color: var(--color-dim);
            margin-bottom: var(--space-xl);
            padding-bottom: var(--space-md);
            border-bottom: 1px solid var(--color-dim);
            font-family: var(--font-mono);
        }

        .note-view-content {
            font-size: 1rem;
            line-height: 1.8;
            color: var(--color-black);
            min-height: 200px;
        }

        .note-view-content p {
            margin-bottom: var(--space-md);
        }

        .note-actions-bar {
            display: flex;
            gap: var(--space-md);
            margin-top: var(--space-xl);
            padding-top: var(--space-lg);
            border-top: 2px solid var(--color-black);
        }
    </style>
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
                <a href="dashboard.php" class="btn btn-sm">Dashboard</a>
                <a href="logout.php" class="btn btn-sm">Logout</a>
            </div>
        </header>

        <!-- Note View Card -->
        <div class="note-view-container">
            <h1 class="note-view-title"><?php echo htmlspecialchars($note['title']); ?></h1>

            <div class="note-view-meta">
                <span>Created: <?php
                $created = new DateTime($note['created_at']);
                echo $created->format('M j, Y g:i A');
                ?></span>
                <span>Updated: <?php
                $updated = new DateTime($note['updated_at']);
                echo $updated->format('M j, Y g:i A');
                ?></span>
                <?php if ($note['is_pinned']): ?>
                    <span>📌 Pinned</span>
                <?php endif; ?>
            </div>

            <div class="note-view-content">
                <?php echo sanitize_html($note['content']); ?>
            </div>

            <form id="delete-note-form" action="delete_note.php" method="POST" style="display: none;">
                <input type="hidden" name="id" value="<?php echo $note['id']; ?>">
                <input type="hidden" name="csrf_token"
                    value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
            </form>

            <div class="note-actions-bar">
                <a href="notes.php" class="btn btn-ghost">← Back to Notes</a>
                <a href="edit_note.php?id=<?php echo $note['id']; ?>" class="btn btn-primary">Edit Note</a>
                <button class="btn btn-ghost"
                    onclick="if(confirm('Are you sure you want to delete this note?')) document.getElementById('delete-note-form').submit();">Delete
                    Note</button>
            </div>
        </div>
    </div>
</body>

</html>