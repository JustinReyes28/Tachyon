<?php
// fetch_todos.php - AJAX endpoint to fetch user's todos
session_start();
require_once 'db_connect.php';

// Set JSON response header
header('Content-Type: application/json; charset=utf-8');

// Disable caching for dynamic content
// header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
// header('Pragma: no-cache');

// Security headers
header('X-Content-Type-Options: nosniff');

$requestId = session_id() ?: bin2hex(random_bytes(16));

/**
 * Helper function to send JSON response and exit
 */
function sendJsonResponse($success, $data = null, $message = '', $httpCode = 200)
{
    http_response_code($httpCode);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('c')
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}

/**
 * Secure logging function
 */
function secureLog($requestId, $message, $context = [])
{
    $log_dir = __DIR__ . '/private_logs';
    $log_file = $log_dir . '/api_errors.log';

    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0750, true);
    }

    $log_message = "[" . date('Y-m-d H:i:s') . "] Request ID: $requestId | $message";
    if (!empty($context)) {
        $log_message .= " | Context: " . json_encode($context);
    }
    $log_message .= PHP_EOL;

    file_put_contents($log_file, $log_message, FILE_APPEND);
    error_log("API error [$requestId]: $message");
}

// Session protection - check authentication
if (!isset($_SESSION['user_id'])) {
    sendJsonResponse(false, null, 'Unauthorized. Please log in.', 401);
}

// Only accept GET requests for fetching data
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJsonResponse(false, null, 'Method not allowed.', 405);
}

// Rate limiting (simple implementation - consider Redis for production)
$rate_limit_key = 'rate_limit_' . $_SESSION['user_id'];
$current_time = time();
$rate_limit_window = 60; // seconds
$max_requests = 60; // requests per window

if (!isset($_SESSION[$rate_limit_key])) {
    $_SESSION[$rate_limit_key] = [
        'count' => 0,
        'window_start' => $current_time
    ];
}

// Reset window if expired
if ($current_time - $_SESSION[$rate_limit_key]['window_start'] > $rate_limit_window) {
    $_SESSION[$rate_limit_key] = [
        'count' => 0,
        'window_start' => $current_time
    ];
}

$_SESSION[$rate_limit_key]['count']++;

if ($_SESSION[$rate_limit_key]['count'] > $max_requests) {
    secureLog($requestId, 'Rate limit exceeded', ['user_id' => $_SESSION['user_id']]);
    sendJsonResponse(false, null, 'Too many requests. Please try again later.', 429);
}

$user_id = $_SESSION['user_id'];

// Optional query parameters with validation
$status = isset($_GET['status']) ? trim($_GET['status']) : null;
$priority = isset($_GET['priority']) ? trim($_GET['priority']) : null;
$search = isset($_GET['search']) ? trim($_GET['search']) : null;
$limit = filter_input(INPUT_GET, 'limit', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1, 'max_range' => 100, 'default' => 50]
]);
$offset = filter_input(INPUT_GET, 'offset', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 0, 'default' => 0]
]);
$sort_by = isset($_GET['sort_by']) ? trim($_GET['sort_by']) : 'priority';
$sort_order = isset($_GET['sort_order']) ? strtoupper(trim($_GET['sort_order'])) : 'ASC';

// Validate enum values (whitelist approach - prevents SQL injection)
$allowed_statuses = ['pending', 'in_progress', 'completed', 'archived'];
$allowed_priorities = ['low', 'medium', 'high'];
$allowed_sort_fields = ['created_at', 'due_date', 'priority', 'status', 'task'];
$allowed_sort_orders = ['ASC', 'DESC'];

if ($status !== null && !in_array($status, $allowed_statuses)) {
    sendJsonResponse(false, null, 'Invalid status filter.', 400);
}

if ($priority !== null && !in_array($priority, $allowed_priorities)) {
    sendJsonResponse(false, null, 'Invalid priority filter.', 400);
}

if (!in_array($sort_by, $allowed_sort_fields)) {
    $sort_by = 'priority';
}

if (!in_array($sort_order, $allowed_sort_orders)) {
    $sort_order = 'ASC';
}

// Sanitize search query (limit length to prevent abuse)
if ($search !== null && strlen($search) > 200) {
    $search = substr($search, 0, 200);
}

// Build dynamic query safely
$sql = "SELECT id, task, description, status, priority, due_date, created_at, updated_at
        FROM todos
        WHERE user_id = ? AND is_trashed = 0";

$recurring_filter = isset($_GET['recurring']) ? $_GET['recurring'] : null;
if ($recurring_filter === 'none') {
    $sql .= " AND (recurring = 'none' OR recurring IS NULL)";
} elseif ($recurring_filter === 'only') {
    $sql .= " AND recurring != 'none' AND recurring IS NOT NULL";
}
$params = [$user_id];
$types = "i";

if ($status !== null) {
    $sql .= " AND status = ?";
    $params[] = $status;
    $types .= "s";
}

if ($priority !== null) {
    $sql .= " AND priority = ?";
    $params[] = $priority;
    $types .= "s";
}

if ($search !== null && $search !== '') {
    $sql .= " AND (task LIKE ? OR description LIKE ?)";
    $search_param = '%' . $search . '%';
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ss";
}

// Add sorting (using CASE for priority to maintain proper order)
if ($sort_by === 'priority') {
    $priority_order = $sort_order === 'ASC' ?
        "CASE priority WHEN 'high' THEN 1 WHEN 'medium' THEN 2 WHEN 'low' THEN 3 END" :
        "CASE priority WHEN 'low' THEN 1 WHEN 'medium' THEN 2 WHEN 'high' THEN 3 END";
    $sql .= " ORDER BY $priority_order, created_at DESC";
} else {
    // Use a mapping approach to ensure only allowed columns are used
    $allowed_sort_columns = [
        'created_at' => 'created_at',
        'due_date' => 'due_date',
        'priority' => 'priority',
        'status' => 'status',
        'task' => 'task'
    ];

    $safe_column = $allowed_sort_columns[$sort_by] ?? 'priority'; // fallback to priority
    $safe_order = $sort_order === 'DESC' ? 'DESC' : 'ASC'; // only allow ASC or DESC

    $sql .= " ORDER BY $safe_column $safe_order";
}

// Add pagination
$sql .= " LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

// Execute query
$stmt = $conn->prepare($sql);

if (!$stmt) {
    secureLog($requestId, 'Query prepare failed', ['sql' => $sql, 'error' => $conn->error]);
    sendJsonResponse(false, null, 'An internal error occurred.', 500);
}

// Bind parameters dynamically
$stmt->bind_param($types, ...$params);

if (!$stmt->execute()) {
    secureLog($requestId, 'Query execution failed', ['error' => $stmt->error]);
    $stmt->close();
    sendJsonResponse(false, null, 'An internal error occurred.', 500);
}

$result = $stmt->get_result();
$todos = [];

while ($row = $result->fetch_assoc()) {
    // Format dates for JSON
    $row['due_date'] = $row['due_date'] ? date('Y-m-d', strtotime($row['due_date'])) : null;
    $row['created_at'] = date('c', strtotime($row['created_at']));
    $row['updated_at'] = date('c', strtotime($row['updated_at']));

    // Ensure integer ID
    $row['id'] = (int) $row['id'];

    // Escape HTML in text fields to prevent XSS when rendered client-side
    $row['task'] = htmlspecialchars($row['task'], ENT_QUOTES, 'UTF-8');
    $row['description'] = $row['description'] ? htmlspecialchars($row['description'], ENT_QUOTES, 'UTF-8') : null;

    $todos[] = $row;
}

$stmt->close();

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM todos WHERE user_id = ? AND is_trashed = 0";
$count_params = [$user_id];
$count_types = "i";

if ($status !== null) {
    $count_sql .= " AND status = ?";
    $count_params[] = $status;
    $count_types .= "s";
}

if ($priority !== null) {
    $count_sql .= " AND priority = ?";
    $count_params[] = $priority;
    $count_types .= "s";
}

if ($search !== null && $search !== '') {
    $count_sql .= " AND (task LIKE ? OR description LIKE ?)";
    $count_params[] = $search_param;
    $count_params[] = $search_param;
    $count_types .= "ss";
}

$count_stmt = $conn->prepare($count_sql);

if ($count_stmt) {
    $count_stmt->bind_param($count_types, ...$count_params);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $total_count = $count_result->fetch_assoc()['total'];
    $count_stmt->close();
} else {
    $total_count = count($todos);
}

// Send successful response
sendJsonResponse(true, [
    'todos' => $todos,
    'pagination' => [
        'total' => (int) $total_count,
        'limit' => (int) $limit,
        'offset' => (int) $offset,
        'has_more' => ($offset + count($todos)) < $total_count
    ]
], 'Todos fetched successfully.');
?>