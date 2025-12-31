<?php
// recurring_reminders.php - Display recurring reminders (protected page)
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

// CSRF token generation
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Retrieve messages from session
$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

// Fetch user's recurring todos
$recurring_todos = [];
$stmt = $conn->prepare("SELECT id, task, description, status, priority, due_date, created_at, recurring FROM todos WHERE user_id = ? AND is_trashed = 0 AND recurring != 'none' ORDER BY
    CASE priority
        WHEN 'high' THEN 1
        WHEN 'medium' THEN 2
        WHEN 'low' THEN 3
    END,
    created_at DESC");

if ($stmt) {
    $stmt->bind_param("i", $user_id);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $recurring_todos[] = $row;
        }
    }
    $stmt->close();
}

// Calculate stats for recurring tasks
$total_recurring = count($recurring_todos);
$completed_recurring = count(array_filter($recurring_todos, function ($t) {
    return $t['status'] === 'completed';
}));
$pending_recurring = $total_recurring - $completed_recurring;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <title>Recurring Reminders - Tachyon</title>
    <?php include 'includes/head.php'; ?>
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
                <a href="todos.php" class="btn btn-sm">[ToDos]</a>
                <a href="logout.php" class="btn btn-sm">Logout</a>
            </div>
        </header>

        <!-- Messages -->
        <?php if ($success_message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <!-- Page Title -->
        <h2 style="margin-bottom: var(--space-xl); text-align: center;">[Recurring Reminders]</h2>

        <!-- Add Recurring Reminder Form -->
        <div class="add-task-card" style="margin-bottom: var(--space-xl);">
            <h2>[+ NEW RECURRING REMINDER]</h2>
            <form action="add_recurring.php" method="POST" class="mt-4">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <div class="task-form-row">
                    <div class="form-group">
                        <label for="task">Reminder</label>
                        <input type="text" id="task" name="task" placeholder="What needs to be reminded?" required>
                    </div>
                    <div class="form-group">
                        <label for="priority">Priority</label>
                        <select id="priority" name="priority">
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                            <option value="low">Low</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="due_date">Start Date</label>
                        <input type="date" id="due_date" name="due_date">
                    </div>
                    <div class="form-group">
                        <label for="recurring">Frequency</label>
                        <select id="recurring" name="recurring" required>
                            <option value="daily">Daily</option>
                            <option value="weekly">Weekly</option>
                            <option value="monthly">Monthly</option>
                            <option value="yearly">Yearly</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <button type="submit" class="btn btn-primary">[Add Reminder]</button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo $total_recurring; ?></div>
                <div class="stat-label">Total Recurring</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $pending_recurring; ?></div>
                <div class="stat-label">Pending</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $completed_recurring; ?></div>
                <div class="stat-label">Completed</div>
            </div>
        </div>

        <!-- Recurring Todo List -->
        <div class="todo-list">
            <?php if (empty($recurring_todos)): ?>
                <div class="empty-state">
                    <h3>No recurring reminders yet!</h3>
                    <p>Create a recurring task to see it here.</p>
                </div>
            <?php else: ?>
                <?php foreach ($recurring_todos as $todo): ?>
                    <div
                        class="todo-item priority-<?php echo htmlspecialchars($todo['priority']); ?> <?php echo $todo['status'] === 'completed' ? 'completed' : ''; ?>">
                        <div class="todo-content">
                            <div class="todo-title"><?php echo htmlspecialchars($todo['task']); ?></div>
                            <div class="todo-meta">
                                <span class="badge badge-status <?php echo htmlspecialchars($todo['status']); ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($todo['status']))); ?>
                                </span>
                                <span class="badge badge-priority <?php echo htmlspecialchars($todo['priority']); ?>">
                                    <?php echo ucfirst(htmlspecialchars($todo['priority'])); ?> Priority
                                </span>
                                <span class="badge badge-recurring">
                                    <?php echo ucfirst(htmlspecialchars($todo['recurring'])); ?> Recurring
                                </span>
                                <?php if ($todo['due_date']): ?>
                                    <span class="badge badge-due">
                                        Due: <?php echo date('M j, Y', strtotime($todo['due_date'])); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="todo-actions">
                            <?php if ($todo['status'] !== 'completed'): ?>
                                <form action="complete_todo.php" method="POST" style="display:inline;">
                                    <input type="hidden" name="csrf_token"
                                        value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                    <input type="hidden" name="todo_id" value="<?php echo (int) $todo['id']; ?>">
                                    <input type="hidden" name="return_url" value="recurring_reminders.php">
                                    <button type="submit" class="btn btn-success btn-sm">[Complete]</button>
                                </form>
                            <?php endif; ?>
                            <form action="delete_todo.php" method="POST" style="display:inline;">
                                <input type="hidden" name="csrf_token"
                                    value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                <input type="hidden" name="todo_id" value="<?php echo (int) $todo['id']; ?>">
                                <input type="hidden" name="return_url" value="recurring_reminders.php">
                                <button type="submit" class="btn btn-danger btn-sm">[Delete]</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <script src="<?php echo asset_url('script.js'); ?>"></script>
</body>

</html>