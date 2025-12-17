<?php
/**
 * export_backup.php - Manual backup export endpoint
 * 
 * Supports:
 * - JSON full backup with metadata
 * - CSV export for todos
 * - CSV export for notes
 * - File size calculation for preview
 * - Gzip compression for large files (>1MB)
 */

session_start();
require_once 'db_connect.php';
require_once 'includes/functions.php';

// Security: Require authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized. Please log in.']);
    exit();
}

// Rate limiting
$rate_limit_key = 'backup_rate_limit_' . $_SESSION['user_id'];
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
    echo json_encode(['error' => 'Too many requests. Please wait a moment.']);
    exit();
}

$user_id = $_SESSION['user_id'];
$export_type = isset($_GET['type']) ? strtolower(trim($_GET['type'])) : 'json';
$size_only = isset($_GET['size_only']) && $_GET['size_only'] === '1';

// App version for metadata
define('APP_VERSION', '1.0.0');

/**
 * Fetch all todos for user
 */
function fetchTodos($conn, $user_id)
{
    $todos = [];
    $stmt = $conn->prepare("SELECT id, task, description, status, priority, due_date, created_at, updated_at 
                            FROM todos 
                            WHERE user_id = ? AND is_trashed = 0 
                            ORDER BY created_at DESC");
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $todos[] = $row;
        }
        $stmt->close();
    }
    return $todos;
}

/**
 * Fetch all notes for user
 */
function fetchNotes($conn, $user_id)
{
    $notes = [];
    $stmt = $conn->prepare("SELECT id, title, content, color, is_pinned, is_archived, created_at, updated_at 
                            FROM notes 
                            WHERE user_id = ? AND is_trashed = 0 
                            ORDER BY created_at DESC");
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            // Add plain text version of content
            $row['content_plain'] = strip_tags(html_entity_decode($row['content'], ENT_QUOTES, 'UTF-8'));
            $notes[] = $row;
        }
        $stmt->close();
    }
    return $notes;
}

/**
 * Generate JSON backup with metadata
 */
function generateJsonBackup($conn, $user_id)
{
    $todos = fetchTodos($conn, $user_id);
    $notes = fetchNotes($conn, $user_id);

    $backup = [
        'metadata' => [
            'app_name' => 'Tachyon',
            'version' => APP_VERSION,
            'export_date' => date('c'),
            'user_id' => $user_id,
            'counts' => [
                'todos' => count($todos),
                'notes' => count($notes)
            ]
        ],
        'todos' => $todos,
        'notes' => $notes
    ];

    return json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

/**
 * Generate CSV for todos
 */
function generateTodosCsv($conn, $user_id)
{
    $todos = fetchTodos($conn, $user_id);

    $output = fopen('php://temp', 'r+');

    // Header row
    fputcsv($output, ['id', 'task', 'description', 'status', 'priority', 'due_date', 'created_at', 'updated_at']);

    // Data rows
    foreach ($todos as $todo) {
        fputcsv($output, [
            $todo['id'],
            $todo['task'],
            $todo['description'],
            $todo['status'],
            $todo['priority'],
            $todo['due_date'],
            $todo['created_at'],
            $todo['updated_at']
        ]);
    }

    rewind($output);
    $csv = stream_get_contents($output);
    fclose($output);

    return $csv;
}

/**
 * Generate CSV for notes
 */
function generateNotesCsv($conn, $user_id)
{
    $notes = fetchNotes($conn, $user_id);

    $output = fopen('php://temp', 'r+');

    // Header row
    fputcsv($output, ['id', 'title', 'content_plain_text', 'is_pinned', 'is_archived', 'created_at', 'updated_at']);

    // Data rows
    foreach ($notes as $note) {
        fputcsv($output, [
            $note['id'],
            $note['title'],
            $note['content_plain'],
            $note['is_pinned'],
            $note['is_archived'],
            $note['created_at'],
            $note['updated_at']
        ]);
    }

    rewind($output);
    $csv = stream_get_contents($output);
    fclose($output);

    return $csv;
}

/**
 * Format file size for display
 */
function formatFileSize($bytes)
{
    if ($bytes >= 1048576) {
        return round($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return round($bytes / 1024, 2) . ' KB';
    }
    return $bytes . ' bytes';
}

// Generate content based on export type
$content = '';
$filename = '';
$content_type = '';
$compress = false;

switch ($export_type) {
    case 'json':
        $content = generateJsonBackup($conn, $user_id);
        $timestamp = date('Y-m-d-His');
        $filename = "tachyon-backup-{$timestamp}.json";
        $content_type = 'application/json';

        // Compress if >1MB
        if (strlen($content) > 1048576 && function_exists('gzencode')) {
            $compress = true;
            $filename .= '.gz';
            $content_type = 'application/gzip';
        }
        break;

    case 'todos_csv':
        $content = generateTodosCsv($conn, $user_id);
        $timestamp = date('Y-m-d-His');
        $filename = "tachyon-todos-{$timestamp}.csv";
        $content_type = 'text/csv';
        break;

    case 'notes_csv':
        $content = generateNotesCsv($conn, $user_id);
        $timestamp = date('Y-m-d-His');
        $filename = "tachyon-notes-{$timestamp}.csv";
        $content_type = 'text/csv';
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid export type. Use: json, todos_csv, or notes_csv']);
        exit();
}

// Size calculation mode - return size info as JSON
if ($size_only) {
    $size = strlen($content);
    $will_compress = $export_type === 'json' && $size > 1048576 && function_exists('gzencode');

    // Estimate compressed size (roughly 10-20% of original for JSON)
    $estimated_size = $will_compress ? round($size * 0.15) : $size;

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'type' => $export_type,
        'size_bytes' => $estimated_size,
        'size_formatted' => formatFileSize($estimated_size),
        'will_compress' => $will_compress
    ]);
    exit();
}

// Apply compression if needed
if ($compress) {
    $content = gzencode($content, 9);
}

// Send file download headers
header('Content-Type: ' . $content_type);
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($content));
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

echo $content;
exit();
?>