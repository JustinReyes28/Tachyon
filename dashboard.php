<?php
// dashboard.php - Main dashboard (protected page)
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

// Fetch active notes count
$total_notes = 0;
try {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM notes WHERE user_id = ? AND is_archived = 0 AND is_trashed = 0");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($total_notes);
    $stmt->fetch();
    $stmt->close();
} catch (Exception $e) {
    // Keep 0 if error
    error_log("Error counting notes: " . $e->getMessage());
}

// Fetch recent notes
$recent_notes = [];
try {
    $stmt = $conn->prepare("SELECT id, title, content, created_at, is_pinned FROM notes WHERE user_id = ? AND is_archived = 0 AND is_trashed = 0 ORDER BY is_pinned DESC, updated_at DESC LIMIT 3");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $recent_notes[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    error_log("Error fetching recent notes: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <title>Dashboard - Tachyon</title>
    <?php include 'includes/head.php'; ?>
    <style>
        .note-card {
            background-color: var(--color-white);
            color: var(--color-black);
            border: 2px solid var(--color-black);
            padding: var(--space-lg);
            cursor: pointer;
            transition: all var(--transition-normal);
            position: relative;
        }

        .note-card:hover {
            transform: translateY(-2px);
            box-shadow: 4px 4px 0 var(--color-black);
        }

        .note-card.pinned {
            border-color: var(--color-accent);
            border-width: 3px;
        }

        .note-card.pinned::before {
            content: "📌";
            position: absolute;
            top: 10px;
            right: 10px;
            font-size: 1.2rem;
        }

        .note-title {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: var(--space-md);
            color: var(--color-black);
            word-break: break-word;
        }

        .note-preview {
            font-size: 0.95rem;
            color: var(--color-dim);
            margin-bottom: var(--space-md);
            line-height: 1.6;
            max-height: 150px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: pre-line;
        }

        .note-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.75rem;
            color: var(--color-dim);
            margin-top: var(--space-md);
            font-family: var(--font-mono);
        }

        .note-date {
            font-size: 0.75rem;
            color: var(--color-dim);
            opacity: 1;
        }

        .note-actions {
            display: flex;
            gap: var(--space-sm);
            margin-top: var(--space-sm);
        }

        .note-action-btn {
            padding: 4px 8px;
            font-size: 0.75rem;
            border: 1px solid var(--color-black);
            background-color: transparent;
            cursor: pointer;
            transition: all var(--transition-fast);
        }

        .note-action-btn:hover {
            background-color: var(--color-black);
            color: var(--color-white);
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
                <a href="logout.php" class="btn btn-sm">Logout</a>
            </div>
        </header>

        <!-- Dashboard Navigation -->
        <section class="dashboard-nav-section">
            <h2 class="section-title">Dashboard Navigation</h2>
            <div class="nav-buttons">
                <a href="todos.php" class="nav-btn">[ToDos]</a>
                <a href="create_note.php" class="nav-btn">[Notes]</a>
                <a href="recurring_reminders.php" class="nav-btn">[Recurring Reminders]</a>
                <a href="profile.php" class="nav-btn">[Profile]</a>
                <a href="trash.php" class="nav-btn">
                    [Trash]
                    <?php
                    $trash_count = get_trash_count($conn, $user_id);
                    if ($trash_count > 0):
                        ?>
                        <span class="trash-badge"><?php echo $trash_count; ?></span>
                    <?php endif; ?>
                </a>
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



        <!-- Notes Grid -->
        <section class="notes-section">
            <div class="notes-grid">
                <?php if (empty($recent_notes)): ?>
                    <div class="note-card"
                        style="text-align: center; justify-content: center; align-items: center; display: flex; color: var(--color-dim);">
                        <p class="note-preview">No notes found. Create one!</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($recent_notes as $note): ?>
                        <div class="note-card <?php echo $note['is_pinned'] ? 'pinned' : ''; ?>"
                            data-note-id="<?php echo $note['id']; ?>">
                            <h3 class="note-title"><?php echo htmlspecialchars($note['title']); ?></h3>
                            <div class="note-preview">
                                <?php
                                $plainText = html_to_plain_text($note['content']);
                                $preview = mb_strlen($plainText) > 200 ? mb_substr($plainText, 0, 200) . '...' : $plainText;
                                echo htmlspecialchars($preview);
                                ?>
                            </div>
                            <div class="note-meta">
                                <span class="note-date">
                                    <?php
                                    try {
                                        $date = new DateTime($note['created_at']); // Using created_at for dashboard as it's "Recent Notes" but logic could swap to updated_at if preferred
                                        echo $date->format('M j, Y g:i A');
                                    } catch (Exception $e) {
                                        echo 'Invalid Date';
                                    }
                                    ?>
                                </span>
                            </div>
                            <div class="note-actions">
                                <button class="note-action-btn" onclick="viewNote(<?php echo $note['id']; ?>)">View</button>
                                <button class="note-action-btn" onclick="editNote(<?php echo $note['id']; ?>)">Edit</button>
                                <button class="note-action-btn" onclick="deleteNote(<?php echo $note['id']; ?>)">Delete</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    </div>
    <script src="<?php echo asset_url('script.js'); ?>"></script>
    <script>
        const csrfToken = "<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>";



        function viewNote(noteId) {
            window.location.href = `view_note.php?id=${noteId}`;
        }

        function editNote(noteId) {
            window.location.href = `edit_note.php?id=${noteId}`;
        }

        function deleteNote(noteId) {
            if (confirm('Are you sure you want to delete this note?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'delete_note.php';

                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'id';
                idInput.value = noteId;

                const csrfInput = document.createElement('input');
                csrfInput.type = 'hidden';
                csrfInput.name = 'csrf_token';
                csrfInput.value = csrfToken;

                form.appendChild(idInput);
                form.appendChild(csrfInput);
                document.body.appendChild(form);
                form.submit();
            }
        }

        document.querySelectorAll('.note-card').forEach(card => {
            card.addEventListener('click', function (e) {
                if (!e.target.classList.contains('note-action-btn')) {
                    const noteId = this.dataset.noteId;
                    viewNote(noteId);
                }
            });
        });
    </script>
</body>

</html>