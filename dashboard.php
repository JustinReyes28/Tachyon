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
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
        }

        /* Header */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding: 20px 30px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
            backdrop-filter: blur(10px);
        }

        .header h1 {
            font-size: 1.8rem;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-info {
            color: #64748b;
            font-weight: 500;
        }

        .logout-btn {
            padding: 10px 20px;
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3);
        }

        .logout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(239, 68, 68, 0.4);
        }

        /* Messages */
        .alert {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-weight: 500;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-success {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
        }

        .alert-error {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3);
        }

        /* Add Todo Form */
        .add-todo-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 16px;
            padding: 25px 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
            backdrop-filter: blur(10px);
        }

        .add-todo-card h2 {
            font-size: 1.2rem;
            color: #1e293b;
            margin-bottom: 20px;
            font-weight: 600;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr auto auto auto;
            gap: 15px;
            align-items: end;
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .form-group label {
            font-size: 0.85rem;
            font-weight: 500;
            color: #64748b;
        }

        .form-group input,
        .form-group select {
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 0.95rem;
            font-family: inherit;
            transition: all 0.3s ease;
            background: #f8fafc;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.15);
        }

        .btn-add {
            padding: 12px 24px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.35);
            white-space: nowrap;
        }

        .btn-add:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 25px rgba(102, 126, 234, 0.45);
        }

        /* Todo List */
        .todo-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .todo-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 14px;
            padding: 20px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
        }

        .todo-card:hover {
            transform: translateX(5px);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
        }

        .todo-card.priority-high {
            border-left-color: #ef4444;
        }

        .todo-card.priority-medium {
            border-left-color: #f59e0b;
        }

        .todo-card.priority-low {
            border-left-color: #10b981;
        }

        .todo-card.completed {
            opacity: 0.7;
            background: rgba(241, 245, 249, 0.95);
        }

        .todo-card.completed .todo-task {
            text-decoration: line-through;
            color: #94a3b8;
        }

        .todo-info {
            flex: 1;
        }

        .todo-task {
            font-size: 1.1rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 8px;
        }

        .todo-meta {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .todo-meta span {
            font-size: 0.8rem;
            padding: 4px 10px;
            border-radius: 20px;
            font-weight: 500;
        }

        .status-badge {
            background: #e0e7ff;
            color: #4338ca;
        }

        .status-badge.completed {
            background: #d1fae5;
            color: #059669;
        }

        .status-badge.in_progress {
            background: #fef3c7;
            color: #d97706;
        }

        .priority-badge.high {
            background: #fee2e2;
            color: #dc2626;
        }

        .priority-badge.medium {
            background: #fef3c7;
            color: #d97706;
        }

        .priority-badge.low {
            background: #d1fae5;
            color: #059669;
        }

        .due-date {
            background: #f1f5f9;
            color: #64748b;
        }

        .todo-actions {
            display: flex;
            gap: 10px;
        }

        .btn-action {
            padding: 10px 18px;
            border: none;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-complete {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
        }

        .btn-complete:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);
        }

        .btn-delete {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3);
        }

        .btn-delete:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(239, 68, 68, 0.4);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
        }

        .empty-state h3 {
            font-size: 1.5rem;
            color: #1e293b;
            margin-bottom: 10px;
        }

        .empty-state p {
            color: #64748b;
            font-size: 1rem;
        }

        /* Stats */
        .stats-bar {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 25px;
        }

        @media (max-width: 600px) {
            .stats-bar {
                grid-template-columns: 1fr;
            }
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .stat-label {
            color: #64748b;
            font-size: 0.9rem;
            font-weight: 500;
            margin-top: 5px;
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- Header -->
        <header class="header">
            <h1>📋 My Tasks</h1>
            <div class="header-right">
                <span class="user-info">Welcome, <?php echo htmlspecialchars($username); ?>!</span>
                <a href="logout.php" class="logout-btn">Logout</a>
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
        <div class="stats-bar">
            <div class="stat-card">
                <div class="stat-number"><?php echo $total; ?></div>
                <div class="stat-label">Total Tasks</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $pending; ?></div>
                <div class="stat-label">Pending</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $completed; ?></div>
                <div class="stat-label">Completed</div>
            </div>
        </div>

        <!-- Add Todo Form -->
        <div class="add-todo-card">
            <h2>➕ Add New Task</h2>
            <form action="add_todo.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <div class="form-row">
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
                    <button type="submit" class="btn-add">Add Task</button>
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
                        class="todo-card priority-<?php echo htmlspecialchars($todo['priority']); ?> <?php echo $todo['status'] === 'completed' ? 'completed' : ''; ?>">
                        <div class="todo-info">
                            <div class="todo-task"><?php echo htmlspecialchars($todo['task']); ?></div>
                            <div class="todo-meta">
                                <span class="status-badge <?php echo htmlspecialchars($todo['status']); ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($todo['status']))); ?>
                                </span>
                                <span class="priority-badge <?php echo htmlspecialchars($todo['priority']); ?>">
                                    <?php echo ucfirst(htmlspecialchars($todo['priority'])); ?> Priority
                                </span>
                                <?php if ($todo['due_date']): ?>
                                    <span class="due-date">
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
                                    <button type="submit" class="btn-action btn-complete">Complete</button>
                                </form>
                            <?php endif; ?>
                            <form action="delete_todo.php" method="POST" style="display:inline;"
                                onsubmit="return confirm('Are you sure you want to delete this task?');">
                                <input type="hidden" name="csrf_token"
                                    value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                <input type="hidden" name="todo_id" value="<?php echo (int) $todo['id']; ?>">
                                <button type="submit" class="btn-action btn-delete">Delete</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>

</html>