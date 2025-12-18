<?php
/**
 * import_backup.php - Manual backup import endpoint
 * 
 * Supports:
 * - JSON full backup
 * - CSV export for todos
 * - CSV export for notes
 */

session_start();
require_once 'db_connect.php';
require_once 'includes/functions.php';

// Security: Require authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized. Please log in.']);
    exit();
}

// Rate limiting
$rate_limit_key = 'import_rate_limit_' . $_SESSION['user_id'];
$current_time = time();
$rate_limit_window = 60; // seconds
$max_requests = 10; // requests per window

if (!isset($_SESSION[$rate_limit_key])) {
    $_SESSION[$rate_limit_key] = ['count' => 0, 'window_start' => $current_time];
}

if ($current_time - $_SESSION[$rate_limit_key]['window_start'] > $rate_limit_window) {
    $_SESSION[$rate_limit_key] = ['count' => 0, 'window_start' => $current_time];
}

$_SESSION[$rate_limit_key]['count']++;

if ($_SESSION[$rate_limit_key]['count'] > $max_requests) {
    http_response_code(429);
    echo json_encode(['success' => false, 'error' => 'Too many requests. Please wait a moment.']);
    exit();
}

$user_id = $_SESSION['user_id'];
$import_type = isset($_POST['type']) ? strtolower(trim($_POST['type'])) : '';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit();
}

if (!isset($_FILES['backup_file']) || $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'error' => 'File upload failed or no file selected.']);
    exit();
}

$file_tmp = $_FILES['backup_file']['tmp_name'];
$file_ext = strtolower(pathinfo($_FILES['backup_file']['name'], PATHINFO_EXTENSION));

/**
 * process JSON Import
 */
function processJsonImport($conn, $user_id, $file_path)
{
    $content = file_get_contents($file_path);
    $data = json_decode($content, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Invalid JSON file.");
    }

    if (!isset($data['todos']) && !isset($data['notes'])) {
        throw new Exception("Invalid backup format: Missing data sections.");
    }

    $imported_todos = 0;
    $imported_notes = 0;

    // Import Todos
    if (isset($data['todos']) && is_array($data['todos'])) {
        $stmt = $conn->prepare("INSERT INTO todos (user_id, created_by, task, description, status, priority, due_date) VALUES (?, ?, ?, ?, ?, ?, ?)");
        foreach ($data['todos'] as $todo) {
            $task = $todo['task'] ?? 'Untitled Task';
            $desc = $todo['description'] ?? '';
            $status = $todo['status'] ?? 'pending';
            $priority = $todo['priority'] ?? 'medium';
            $due = !empty($todo['due_date']) ? $todo['due_date'] : null;

            $stmt->bind_param("iisssss", $user_id, $user_id, $task, $desc, $status, $priority, $due);
            if ($stmt->execute()) {
                $imported_todos++;
            }
        }
        $stmt->close();
    }

    // Import Notes
    if (isset($data['notes']) && is_array($data['notes'])) {
        $stmt = $conn->prepare("INSERT INTO notes (user_id, title, content, color, is_pinned, is_archived) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($data['notes'] as $note) {
            $title = $note['title'] ?? 'Untitled Note';
            $content = $note['content'] ?? '';
            $color = $note['color'] ?? '#ffffff';
            $pinned = isset($note['is_pinned']) ? (int) $note['is_pinned'] : 0;
            $archived = isset($note['is_archived']) ? (int) $note['is_archived'] : 0;

            $stmt->bind_param("isssii", $user_id, $title, $content, $color, $pinned, $archived);
            if ($stmt->execute()) {
                $imported_notes++;
            }
        }
        $stmt->close();
    }

    return "Import setup completed: {$imported_todos} todos and {$imported_notes} notes imported.";
}

/**
 * Process CSV Import for Todos
 */
function processTodosCsvImport($conn, $user_id, $file_path)
{
    $handle = fopen($file_path, "r");
    if ($handle === FALSE) {
        throw new Exception("Could not open CSV file.");
    }

    $headers = fgetcsv($handle); // Read header row
    // Basic validation of headers could be added here

    $imported = 0;
    $stmt = $conn->prepare("INSERT INTO todos (user_id, created_by, task, description, status, priority, due_date) VALUES (?, ?, ?, ?, ?, ?, ?)");

    while (($row = fgetcsv($handle)) !== FALSE) {
        // Assume standard export format: id, task, description, status, priority, due_date, ...
        // We will map based on standard column indices from export_backup.php
        // Indexes: 1=task, 2=description, 3=status, 4=priority, 5=due_date

        if (count($row) < 6)
            continue; // Skip malformed rows

        $task = $row[1] ?? 'Imported Task';
        $desc = $row[2] ?? '';
        $status = $row[3] ?? 'pending';
        $priority = $row[4] ?? 'medium';
        $due = !empty($row[5]) && $row[5] !== 'null' ? $row[5] : null;

        $stmt->bind_param("iisssss", $user_id, $user_id, $task, $desc, $status, $priority, $due);
        if ($stmt->execute()) {
            $imported++;
        }
    }

    fclose($handle);
    $stmt->close();

    return "Imported {$imported} todos from CSV.";
}

/**
 * Process CSV Import for Notes
 */
function processNotesCsvImport($conn, $user_id, $file_path)
{
    $handle = fopen($file_path, "r");
    if ($handle === FALSE) {
        throw new Exception("Could not open CSV file.");
    }

    $headers = fgetcsv($handle);

    $imported = 0;
    $stmt = $conn->prepare("INSERT INTO notes (user_id, title, content, is_pinned, is_archived) VALUES (?, ?, ?, ?, ?)");

    while (($row = fgetcsv($handle)) !== FALSE) {
        // Indexes from export: 1=title, 2=content_plain, 3=is_pinned, 4=is_archived

        if (count($row) < 5)
            continue;

        $title = $row[1] ?? 'Imported Note';
        $plain_content = $row[2] ?? '';

        // Convert plain text to basic HTML for Quill
        $html_content = '<p>' . nl2br(htmlspecialchars($plain_content)) . '</p>';

        $pinned = isset($row[3]) ? (int) $row[3] : 0;
        $archived = isset($row[4]) ? (int) $row[4] : 0;

        $stmt->bind_param("issii", $user_id, $title, $html_content, $pinned, $archived);
        if ($stmt->execute()) {
            $imported++;
        }
    }

    fclose($handle);
    $stmt->close();

    return "Imported {$imported} notes from CSV (formatting limited).";
}

try {
    $message = "";

    // Support .gz for json
    if ($file_ext === 'gz') {
        // Decompress if needed, for now just reject if we don't want to handle complexity or rely on php extensions
        // But the plan mentioned preserving what export does. export can do .gz.
        // Let's stick to simple first. If it's .json or .csv.
        // IF user uploads .gz, we might need to decompress. 
        // For current scope execution, let's assume they extract first or upload regular.
        // But wait, export makes .gz. It's nice to support it.
        // Implementation:
        $is_gzip = str_ends_with($_FILES['backup_file']['name'], '.gz');
        if ($is_gzip) {
            $content = gzdecode(file_get_contents($file_tmp));
            file_put_contents($file_tmp, $content); // Overwrite with decompressed
            // Remove .gz from name to check inner extension
            $orig_name = $_FILES['backup_file']['name'];
            $inner_name = substr($orig_name, 0, -3);
            $import_type = str_ends_with($inner_name, '.json') ? 'json' : $import_type;
        }
    }

    switch ($import_type) {
        case 'json':
            $message = processJsonImport($conn, $user_id, $file_tmp);
            break;
        case 'todos_csv':
            $message = processTodosCsvImport($conn, $user_id, $file_tmp);
            break;
        case 'notes_csv':
            $message = processNotesCsvImport($conn, $user_id, $file_tmp);
            break;
        default:
            // Auto-detect attempt if type matches extension roughly
            if ($file_ext === 'json') {
                $message = processJsonImport($conn, $user_id, $file_tmp);
            } else {
                throw new Exception("Invalid or unspecified import type.");
            }
    }

    echo json_encode(['success' => true, 'message' => $message]);

} catch (Exception $e) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>