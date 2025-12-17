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
    <title>Profile - Tachyon</title>
    <?php include 'includes/head.php'; ?>
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
                    <a href="change_password.php" class="btn btn-primary">Change Password</a>
                    <a href="delete_account.php" class="btn btn-danger">Delete Account</a>
                </div>

                <!-- Data Backup Section -->
                <div class="backup-section">
                    <h3>Data Backup</h3>
                    <p class="backup-description">Export your data for safekeeping. Downloads will start automatically.
                    </p>

                    <div class="backup-size-info">
                        <div class="size-row">
                            <span>📦 Full Backup (JSON):</span>
                            <span id="json-size" class="size-value">Calculating...</span>
                        </div>
                        <div class="size-row">
                            <span>📋 Todos (CSV):</span>
                            <span id="todos-size" class="size-value">Calculating...</span>
                        </div>
                        <div class="size-row">
                            <span>📝 Notes (CSV):</span>
                            <span id="notes-size" class="size-value">Calculating...</span>
                        </div>
                    </div>

                    <div class="backup-buttons">
                        <button id="export-json-btn" class="btn btn-primary" onclick="exportBackup('json')">
                            [Export Full Backup]
                        </button>
                        <button id="export-todos-btn" class="btn btn-secondary" onclick="exportBackup('todos_csv')">
                            [Export Todos CSV]
                        </button>
                        <button id="export-notes-btn" class="btn btn-secondary" onclick="exportBackup('notes_csv')">
                            [Export Notes CSV]
                        </button>
                    </div>
                </div>

                <!-- Import Data Section -->
                <div class="import-section">
                    <h3>Import Data</h3>
                    <p class="backup-description">Restore your data from backups. Supported formats: JSON (Full Backup)
                        and CSV.</p>

                    <!-- Hidden File Input -->
                    <input type="file" id="import-file-input" style="display: none;">

                    <div class="import-alerts">
                        <div class="alert">
                            <strong>JSON Import:</strong> Restores full note formatting, fonts, and colors.
                        </div>
                        <div class="alert">
                            <strong>CSV Import:</strong> Restores text only. Original fonts/styling are NOT preserved.
                        </div>
                    </div>

                    <div class="backup-buttons">
                        <button id="import-json-btn" class="btn btn-primary" onclick="triggerImport('json')">
                            [Import JSON Backup]
                        </button>
                        <button id="import-todos-btn" class="btn btn-secondary" onclick="triggerImport('todos_csv')">
                            [Import Todos CSV]
                        </button>
                        <button id="import-notes-btn" class="btn btn-secondary" onclick="triggerImport('notes_csv')">
                            [Import Notes CSV]
                        </button>
                    </div>
                </div>
            </div>
        </section>
    </div>
    <script src="<?php echo asset_url('script.js'); ?>"></script>
    <script>
        // Calculate and display file sizes on page load
        async function calculateSizes() {
            const types = ['json', 'todos_csv', 'notes_csv'];
            const elementIds = {
                'json': 'json-size',
                'todos_csv': 'todos-size',
                'notes_csv': 'notes-size'
            };

            for (const type of types) {
                try {
                    const response = await fetch(`export_backup.php?type=${type}&size_only=1`);
                    const data = await response.json();

                    if (data.success) {
                        let sizeText = data.size_formatted;
                        if (data.will_compress) {
                            sizeText += ' (compressed)';
                        }
                        document.getElementById(elementIds[type]).textContent = sizeText;
                    } else {
                        document.getElementById(elementIds[type]).textContent = 'Error';
                    }
                } catch (error) {
                    console.error(`Error calculating ${type} size:`, error);
                    document.getElementById(elementIds[type]).textContent = 'Error';
                }
            }
        }

        // Export backup function
        function exportBackup(type) {
            const btn = document.querySelector(`#export-${type.replace('_csv', '')}-btn`);
            const originalText = btn.textContent;

            btn.textContent = '[Downloading...]';
            btn.disabled = true;

            // Create hidden iframe for download
            const iframe = document.createElement('iframe');
            iframe.style.display = 'none';
            iframe.src = `export_backup.php?type=${type}`;
            document.body.appendChild(iframe);

            // Re-enable button after a delay
            setTimeout(() => {
                btn.textContent = originalText;
                btn.disabled = false;
                // Remove iframe after download starts
                setTimeout(() => {
                    document.body.removeChild(iframe);
                }, 5000);
            }, 2000);
        }

        // Calculate sizes when page loads
        document.addEventListener('DOMContentLoaded', calculateSizes);

        // Import Backup Functions
        function triggerImport(type) {
            const fileInput = document.getElementById('import-file-input');
            fileInput.setAttribute('accept', type === 'json' ? '.json,.gz' : '.csv');
            fileInput.dataset.importType = type;
            fileInput.click();
        }

        document.getElementById('import-file-input').addEventListener('change', async function (e) {
            if (!this.files || this.files.length === 0) return;

            const file = this.files[0];
            const type = this.dataset.importType;
            const formData = new FormData();
            formData.append('backup_file', file);
            formData.append('type', type);

            // Show loading state
            const btnId = `import-${type.replace('_csv', '')}-btn`;
            const btn = document.getElementById(btnId);
            const originalText = btn.textContent;
            btn.textContent = '[Importing...]';
            btn.disabled = true;

            try {
                const response = await fetch('import_backup.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    alert('Success: ' + result.message);
                    // Reload to show new data
                    window.location.reload();
                } else {
                    alert('Error: ' + result.error);
                }
            } catch (error) {
                console.error('Import error:', error);
                alert('An error occurred during import.');
            } finally {
                // Reset button
                btn.textContent = originalText;
                btn.disabled = false;
                this.value = ''; // Clear input
            }
        });
    </script>
</body>

</html>