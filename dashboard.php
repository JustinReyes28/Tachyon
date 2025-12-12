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

// Fetch user's todos
$todos = [];
$stmt = $conn->prepare("SELECT id, task, description, status, priority, due_date, created_at FROM todos WHERE user_id = ? ORDER BY 
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
            $todos[] = $row;
        }
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Todo App</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <div class="dashboard-container">
        <!-- Header -->
        <header class="app-header">
            <h1 class="app-title">📋 My Tasks</h1>
            <div class="user-nav">
                <span class="user-welcome">Welcome, <?php echo htmlspecialchars($username); ?>!</span>
                <a href="logout.php" class="btn btn-danger btn-sm">Logout</a>
            </div>
        </header>

        <!-- Messages -->
        <?php if ($success_message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <!-- Stats -->
        <?php
        $total = count($todos);
        $completed = count(array_filter($todos, fn($t) => $t['status'] === 'completed'));
        $pending = $total - $completed;
        ?>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo $total; ?></div>
                <div class="stat-label">Total Tasks</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $pending; ?></div>
                <div class="stat-label">Pending</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $completed; ?></div>
                <div class="stat-label">Completed</div>
            </div>
        </div>

        <!-- Add Todo Form -->
        <div class="add-task-card">
            <h2>➕ Add New Task</h2>
            <form action="add_todo.php" method="POST" class="mt-4">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <div class="task-form-row">
                    <div class="form-group">
                        <label for="task">Task</label>
                        <input type="text" id="task" name="task" placeholder="What needs to be done?" required>
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
                        <label for="due_date">Due Date</label>
                        <input type="date" id="due_date" name="due_date">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <button type="submit" class="btn btn-primary">Add Task</button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Todo List -->
        <div class="todo-list">
            <?php if (empty($todos)): ?>
                <div class="empty-state">
                    <h3>No tasks yet!</h3>
                    <p>Add your first task above to get started.</p>
                </div>
            <?php else: ?>
                <?php foreach ($todos as $todo): ?>
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
                                <?php if ($todo['due_date']): ?>
                                    <span class="badge" style="background: #f1f5f9; color: #64748b;">
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
                                    <button type="submit" class="btn btn-success btn-sm">Complete</button>
                                </form>
                            <?php endif; ?>
                            <form action="delete_todo.php" method="POST" style="display:inline;">
                                <input type="hidden" name="csrf_token"
                                    value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                <input type="hidden" name="todo_id" value="<?php echo (int) $todo['id']; ?>">
                                <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <script src="script.js"></script>
</body>

</html>