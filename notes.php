<?php
// notes.php - Display all notes (protected page)
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

// Fetch all notes for the current user
$notes = [];
try {
    $stmt = $conn->prepare("SELECT id, title, content, color, is_pinned, is_archived, created_at, updated_at 
                            FROM notes 
                            WHERE user_id = ? AND is_archived = 0 
                            ORDER BY is_pinned DESC, updated_at DESC");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $notes[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    error_log("Error fetching notes: " . $e->getMessage());
    $error_message = "Failed to load notes. Please try again.";
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notes - Tachyon</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo asset_url('style.css'); ?>">
    <style>
        .notes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: var(--space-lg);
            margin-top: var(--space-xl);
        }

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

        .empty-state {
            text-align: center;
            padding: var(--space-2xl);
            color: var(--color-dim);
        }

        .empty-state-icon {
            font-size: 4rem;
            margin-bottom: var(--space-lg);
            opacity: 0.5;
        }

        .empty-state-text {
            font-size: 1.125rem;
            margin-bottom: var(--space-md);
        }

        .search-bar {
            margin-bottom: var(--space-xl);
        }

        .search-input {
            width: 100%;
            padding: var(--space-md);
            font-family: var(--font-sans);
            font-size: 1rem;
            border: 2px solid var(--color-black);
            background-color: var(--color-white);
            color: var(--color-black);
            outline: none;
            transition: all var(--transition-normal);
        }

        .search-input:focus {
            background-color: rgba(0, 0, 0, 0.03);
        }

        .action-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--space-xl);
            gap: var(--space-md);
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
                <a href="dashboard.php" class="btn btn-sm">Dashboard</a>
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

        <!-- Action Row -->
        <div class="action-row">
            <a href="create_note.php" class="btn btn-action-primary">[+ NEW NOTE]</a>
            <div class="search-bar" style="flex: 1; max-width: 400px;">
                <input type="text" id="search" placeholder="[Search notes...]" class="search-input">
            </div>
        </div>

        <!-- Notes Grid -->
        <section class="notes-section">
            <?php if (empty($notes)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📝</div>
                    <div class="empty-state-text">No notes yet</div>
                    <p>Create your first note to get started!</p>
                    <a href="create_note.php" class="btn btn-ghost" style="margin-top: var(--space-lg);">Create Note</a>
                </div>
            <?php else: ?>
                <div class="notes-grid" id="notes-grid">
                    <?php foreach ($notes as $note): ?>
                        <div class="note-card <?php echo $note['is_pinned'] ? 'pinned' : ''; ?>"
                            data-note-id="<?php echo $note['id']; ?>">
                            <h3 class="note-title"><?php echo htmlspecialchars($note['title']); ?></h3>
                            <div class="note-preview">
                                <?php
                                // Strip HTML tags and get plain text preview
                                $plainText = strip_tags($note['content']);
                                $preview = mb_strlen($plainText) > 200 ? mb_substr($plainText, 0, 200) . '...' : $plainText;
                                echo htmlspecialchars($preview);
                                ?>
                            </div>
                            <div class="note-meta">
                                <span class="note-date">
                                    <?php
                                    $date = new DateTime($note['updated_at']);
                                    echo $date->format('M j, Y g:i A');
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
                </div>
            <?php endif; ?>
        </section>
    </div>

    </div>

    <script>
        const csrfToken = "<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>";

        // Search functionality
        document.getElementById('search').addEventListener('input', function (e) {
            const searchTerm = e.target.value.toLowerCase();
            const noteCards = document.querySelectorAll('.note-card');

            noteCards.forEach(card => {
                const title = card.querySelector('.note-title').textContent.toLowerCase();
                const preview = card.querySelector('.note-preview').textContent.toLowerCase();

                if (title.includes(searchTerm) || preview.includes(searchTerm)) {
                    card.style.display = '';
                } else {
                    card.style.display = 'none';
                }
            });
        });

        // View note
        function viewNote(noteId) {
            window.location.href = `view_note.php?id=${noteId}`;
        }

        // Edit note
        function editNote(noteId) {
            window.location.href = `edit_note.php?id=${noteId}`;
        }

        // Delete note
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

        // Click on card to view
        document.querySelectorAll('.note-card').forEach(card => {
            card.addEventListener('click', function (e) {
                // Only trigger if clicking on the card itself, not the action buttons
                if (!e.target.classList.contains('note-action-btn')) {
                    const noteId = this.dataset.noteId;
                    viewNote(noteId);
                }
            });
        });
    </script>
</body>

</html>