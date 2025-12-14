<?php
// trash.php - Trash management page (protected)
session_start();
require_once 'db_connect.php';
require_once 'includes/functions.php';

// Session protection
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'User';

// CSRF check
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Auto-cleanup functionality (delete items older than 30 days)
$cleanup_count = 0;
// Note: We'll implement robust cleanup logic in cleanup_trash.php later if needed, 
// but for now we can do it inline or via include. Let's do it here for simplicity as per prompt.

$cleanup_days = 30;
$cutoff_date = date('Y-m-d H:i:s', strtotime("-$cleanup_days days"));

// Cleanup todos
$conn->query("DELETE FROM todos WHERE user_id = $user_id AND is_trashed = 1 AND trashed_at < '$cutoff_date'");
$cleanup_count += $conn->affected_rows;

// Cleanup notes
$conn->query("DELETE FROM notes WHERE user_id = $user_id AND is_trashed = 1 AND trashed_at < '$cutoff_date'");
$cleanup_count += $conn->affected_rows;

// Fetch Trashed Todos
$trashed_todos = [];
$stmt = $conn->prepare("SELECT id, task, description, priority, trashed_at FROM todos WHERE user_id = ? AND is_trashed = 1 ORDER BY trashed_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc())
    $trashed_todos[] = $row;
$stmt->close();

// Fetch Trashed Notes
$trashed_notes = [];
$stmt = $conn->prepare("SELECT id, title, content, trashed_at FROM notes WHERE user_id = ? AND is_trashed = 1 ORDER BY trashed_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc())
    $trashed_notes[] = $row;
$stmt->close();

$has_items = count($trashed_todos) > 0 || count($trashed_notes) > 0;

// Messages
$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

if ($cleanup_count > 0) {
    $cleanup_msg = "$cleanup_count item(s) were automatically permanently deleted because they were in trash for over 30 days.";
    if ($success_message)
        $success_message .= " " . $cleanup_msg;
    else
        $success_message = $cleanup_msg;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trash - Tachyon</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo asset_url('style.css'); ?>">
    <style>
        .trash-container {
            max-width: 1000px;
            margin: 0 auto;
            padding: var(--space-xl);
        }

        .trash-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--space-xl);
        }

        .trash-section {
            margin-bottom: var(--space-2xl);
        }

        .trash-section-title {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: var(--space-lg);
            border-bottom: 2px solid var(--color-border);
            padding-bottom: var(--space-sm);
        }

        .trash-item {
            background: var(--color-surface);
            border: 1px solid var(--color-border);
            padding: var(--space-md);
            margin-bottom: var(--space-md);
            display: flex;
            justify-content: space-between;
            align-items: center;
            opacity: 0.8;
            transition: opacity 0.2s;
        }

        .trash-item:hover {
            opacity: 1;
        }

        .trash-item-content {
            flex: 1;
        }

        .trash-item-title {
            font-weight: 600;
            text-decoration: line-through;
            color: var(--color-text-muted);
        }

        .trash-item-meta {
            font-size: 0.85rem;
            color: var(--color-text-muted);
            margin-top: 4px;
        }

        .trash-actions {
            display: flex;
            gap: var(--space-sm);
        }

        .btn-restore {
            color: var(--color-primary);
            background: transparent;
            border: 1px solid var(--color-primary);
            padding: 4px 8px;
            font-size: 0.8rem;
            cursor: pointer;
        }

        .btn-delete-permanent {
            color: var(--color-danger);
            background: transparent;
            border: 1px solid var(--color-danger);
            padding: 4px 8px;
            font-size: 0.8rem;
            cursor: pointer;
        }

        .btn-empty-trash {
            background-color: var(--color-danger);
            color: white;
            border: none;
            padding: 8px 16px;
            font-weight: 600;
            cursor: pointer;
        }

        .empty-state {
            text-align: center;
            color: var(--color-text-muted);
            padding: var(--space-xl);
        }
    </style>
</head>

<body>
    <div class="dot-pattern"></div>

    <div class="dashboard-container">
        <header class="app-header">
            <h1 class="app-title">TACHYON TRASH</h1>
            <div class="user-nav">
                <a href="dashboard.php" class="btn btn-sm">Dashboard</a>
                <a href="todos.php" class="btn btn-sm">Todos</a>
                <a href="notes.php" class="btn btn-sm">Notes</a>
                <a href="logout.php" class="btn btn-sm">Logout</a>
            </div>
        </header>

        <?php if ($success_message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <div class="trash-header">
            <h2>Trash Can</h2>
            <?php if ($has_items): ?>
                <form action="empty_trash.php" method="POST"
                    onsubmit="return confirm('Are you sure you want to permanently delete EVERYTHING in trash? This cannot be undone.');">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <button type="submit" class="btn-empty-trash">[Empty Trash]</button>
                </form>
            <?php endif; ?>
        </div>

        <?php if (!$has_items): ?>
            <div class="empty-state">
                <h3>Trash is empty</h3>
                <p>Relax, your space is clean.</p>
            </div>
        <?php else: ?>

            <?php if (!empty($trashed_todos)): ?>
                <section class="trash-section">
                    <h3 class="trash-section-title">Todos</h3>
                    <?php foreach ($trashed_todos as $todo): ?>
                        <div class="trash-item">
                            <div class="trash-item-content">
                                <div class="trash-item-title"><?php echo htmlspecialchars($todo['task']); ?></div>
                                <div class="trash-item-meta">Deleted:
                                    <?php echo date('M j, Y H:i', strtotime($todo['trashed_at'])); ?></div>
                            </div>
                            <div class="trash-actions">
                                <form action="restore_todo.php" method="POST">
                                    <input type="hidden" name="csrf_token"
                                        value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                    <input type="hidden" name="todo_id" value="<?php echo $todo['id']; ?>">
                                    <button type="submit" class="btn-restore">Restore</button>
                                </form>
                                <form action="permanent_delete_todo.php" method="POST"
                                    onsubmit="return confirm('Delete permanently?');">
                                    <input type="hidden" name="csrf_token"
                                        value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                    <input type="hidden" name="todo_id" value="<?php echo $todo['id']; ?>">
                                    <button type="submit" class="btn-delete-permanent">Delete</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </section>
            <?php endif; ?>

            <?php if (!empty($trashed_notes)): ?>
                <section class="trash-section">
                    <h3 class="trash-section-title">Notes</h3>
                    <?php foreach ($trashed_notes as $note): ?>
                        <div class="trash-item">
                            <div class="trash-item-content">
                                <div class="trash-item-title"><?php echo htmlspecialchars($note['title']); ?></div>
                                <div class="trash-item-meta">Deleted:
                                    <?php echo date('M j, Y H:i', strtotime($note['trashed_at'])); ?></div>
                            </div>
                            <div class="trash-actions">
                                <form action="restore_note.php" method="POST">
                                    <input type="hidden" name="csrf_token"
                                        value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                    <input type="hidden" name="note_id" value="<?php echo $note['id']; ?>">
                                    <button type="submit" class="btn-restore">Restore</button>
                                </form>
                                <form action="permanent_delete_note.php" method="POST"
                                    onsubmit="return confirm('Delete permanently?');">
                                    <input type="hidden" name="csrf_token"
                                        value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                    <input type="hidden" name="note_id" value="<?php echo $note['id']; ?>">
                                    <button type="submit" class="btn-delete-permanent">Delete</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </section>
            <?php endif; ?>

        <?php endif; ?>
    </div>
</body>

</html>