<?php
// create_note.php - Create new note with Quill editor (protected page)
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
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Note - Tachyon</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo asset_url('style.css'); ?>">
    <!-- Quill Editor CSS -->
    <link href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css" rel="stylesheet"
        integrity="sha384-ecIckRi4QlKYya/FQUbBUjS4qp65jF/J87Guw5uzTbO1C1Jfa/6kYmd6dXUF6D7i" crossorigin="anonymous">
    <style>
        /* Quill Editor Customization for Nothing OS Theme */
        .note-editor-container {
            background-color: var(--color-white);
            color: var(--color-black);
            padding: var(--space-xl);
            margin-bottom: var(--space-2xl);
        }

        .note-editor-container h2 {
            font-size: 0.875rem;
            letter-spacing: 0.15em;
            margin-bottom: var(--space-lg);
            color: var(--color-black);
        }

        #note-title {
            width: 100%;
            padding: 1rem;
            font-family: var(--font-sans);
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--color-black);
            background-color: transparent;
            border: none;
            border-bottom: 2px solid var(--color-black);
            margin-bottom: var(--space-lg);
            outline: none;
            transition: all var(--transition-normal);
        }

        #note-title::placeholder {
            color: var(--color-dim);
            opacity: 0.6;
        }

        #note-title:focus {
            background-color: rgba(0, 0, 0, 0.03);
        }

        /* Quill Editor Styling */
        #editor-container {
            min-height: 400px;
            background-color: var(--color-white);
            border: 2px solid var(--color-black);
            font-family: var(--font-sans);
            color: var(--color-black);
        }

        .ql-toolbar.ql-snow {
            border: 2px solid var(--color-black);
            border-bottom: none;
            background-color: var(--color-white);
            padding: var(--space-md);
        }

        .ql-container.ql-snow {
            border: 2px solid var(--color-black);
            font-size: 1rem;
        }

        .ql-editor {
            min-height: 400px;
            font-family: var(--font-sans);
            color: var(--color-black);
        }

        .ql-editor.ql-blank::before {
            color: var(--color-dim);
            opacity: 0.6;
            font-style: normal;
        }

        /* Toolbar buttons styling */
        .ql-snow .ql-stroke {
            stroke: var(--color-black);
        }

        .ql-snow .ql-fill {
            fill: var(--color-black);
        }

        .ql-snow .ql-picker-label {
            color: var(--color-black);
        }

        .ql-toolbar.ql-snow .ql-picker-label:hover,
        .ql-toolbar.ql-snow button:hover {
            color: var(--color-white);
            background-color: var(--color-black);
        }

        .ql-toolbar.ql-snow .ql-picker-label:hover .ql-stroke,
        .ql-toolbar.ql-snow button:hover .ql-stroke {
            stroke: var(--color-white);
        }

        .ql-toolbar.ql-snow .ql-picker-label:hover .ql-fill,
        .ql-toolbar.ql-snow button:hover .ql-fill {
            fill: var(--color-white);
        }

        .ql-snow.ql-toolbar button.ql-active,
        .ql-snow .ql-toolbar button.ql-active {
            background-color: var(--color-black);
        }

        .ql-snow.ql-toolbar button.ql-active .ql-stroke,
        .ql-snow .ql-toolbar button.ql-active .ql-stroke {
            stroke: var(--color-white);
        }

        .ql-snow.ql-toolbar button.ql-active .ql-fill,
        .ql-snow .ql-toolbar button.ql-active .ql-fill {
            fill: var(--color-white);
        }

        .editor-actions {
            display: flex;
            gap: var(--space-md);
            margin-top: var(--space-lg);
            justify-content: flex-end;
        }

        .char-count {
            font-size: 0.75rem;
            color: var(--color-dim);
            margin-top: var(--space-sm);
            text-align: right;
            font-family: var(--font-mono);
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

        <!-- Note Editor Card -->
        <div class="note-editor-container">
            <h2>+ NEW NOTE</h2>
            <form id="note-form" action="save_note.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                <!-- Note Title -->
                <input type="text" id="note-title" name="title" placeholder="Note Title" maxlength="255" required>

                <!-- Quill Editor Container -->
                <div id="editor-container"></div>

                <!-- Hidden input for note content -->
                <input type="hidden" name="content" id="note-content">

                <!-- Character count -->
                <div class="char-count">
                    <span id="char-count">0</span> characters
                </div>

                <!-- Action Buttons -->
                <div class="editor-actions">
                    <a href="dashboard.php" class="btn btn-ghost">Cancel</a>
                    <button type="submit" class="btn btn-primary">Save Note</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Quill Editor JS -->
    <script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js"
        integrity="sha384-utBUCeG4SYaCm4m7GQZYr8Hy8Fpy3V4KGjBZaf4WTKOcwhCYpt/0PfeEe3HNlwx8"
        crossorigin="anonymous"></script>
    <script>
        // Initialize Quill editor
        var quill = new Quill('#editor-container', {
            theme: 'snow',
            placeholder: 'Start writing your note...',
            modules: {
                toolbar: [
                    [{ 'header': [1, 2, 3, false] }],
                    ['bold', 'italic', 'underline', 'strike'],
                    [{ 'list': 'ordered' }, { 'list': 'bullet' }],
                    [{ 'indent': '-1' }, { 'indent': '+1' }],
                    [{ 'color': [] }, { 'background': [] }],
                    [{ 'align': [] }],
                    ['blockquote', 'code-block'],
                    ['link'],
                    ['clean']
                ]
            }
        });

        // Character counter
        quill.on('text-change', function () {
            var text = quill.getText();
            var length = text.trim().length;
            document.getElementById('char-count').textContent = length;
        });

        // Form submission
        document.getElementById('note-form').addEventListener('submit', function (e) {
            e.preventDefault();

            // Get the content from Quill
            var content = quill.root.innerHTML;

            // Validate title
            var title = document.getElementById('note-title').value.trim();
            if (!title) {
                alert('Please enter a note title');
                return false;
            }

            // Validate content (not empty)
            var text = quill.getText().trim();
            if (!text) {
                alert('Please write some content for your note');
                return false;
            }

            // Set the hidden input value
            document.getElementById('note-content').value = content;

            // Submit the form
            this.submit();
        });

        // Auto-focus on title field
        document.getElementById('note-title').focus();
    </script>
</body>

</html>