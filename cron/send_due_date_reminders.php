<?php
/**
 * Cron Job: Send Due Date Reminders
 * 
 * This script should be run daily (e.g., via cron or Task Scheduler)
 * to send email reminders for tasks due within the next day.
 * 
 * Example cron entry (run daily at 8 AM):
 * 0 8 * * * /usr/bin/php /path/to/Tachyon/cron/send_due_date_reminders.php
 * 
 * For Windows Task Scheduler:
 * Program: C:\xampp\php\php.exe
 * Arguments: C:\xampp\htdocs\Tachyon\cron\send_due_date_reminders.php
 */

// Ensure script is run from CLI only (security measure)
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('This script can only be run from the command line.');
}

// Set timezone
date_default_timezone_set('Asia/Manila');

// Error reporting for CLI
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Define log file
$logDir = dirname(__DIR__) . '/private_logs';
$logFile = $logDir . '/cron_reminders.log';

// Ensure log directory exists
if (!is_dir($logDir)) {
    mkdir($logDir, 0750, true);
}

/**
 * Log a message to the cron log file
 */
function cronLog($message)
{
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message" . PHP_EOL;
    file_put_contents($logFile, $logMessage, FILE_APPEND);
    echo $logMessage; // Also output to console
}

cronLog("=== Starting due date reminder job ===");

try {
    // Include required files
    require_once dirname(__DIR__) . '/db_connect.php';
    require_once dirname(__DIR__) . '/includes/EmailNotifier.php';

    cronLog("Dependencies loaded successfully");

    // Calculate the target date range (tasks due tomorrow)
    $tomorrow = date('Y-m-d', strtotime('+1 day'));

    cronLog("Looking for tasks due on: $tomorrow");

    // Query for pending tasks due tomorrow with user email
    $sql = "SELECT 
                t.id AS task_id,
                t.task AS task_name,
                t.due_date,
                u.id AS user_id,
                u.username,
                u.email
            FROM todos t
            INNER JOIN users u ON t.user_id = u.id
            WHERE t.status = 'pending'
              AND t.due_date = ?
              AND u.email_verified = 1
            ORDER BY u.id, t.due_date";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        cronLog("ERROR: Failed to prepare statement - " . $conn->error);
        exit(1);
    }

    $stmt->bind_param("s", $tomorrow);
    $stmt->execute();
    $result = $stmt->get_result();

    $totalTasks = $result->num_rows;
    cronLog("Found $totalTasks tasks due tomorrow");

    if ($totalTasks === 0) {
        cronLog("No reminders to send. Job completed.");
        $stmt->close();
        $conn->close();
        exit(0);
    }

    // Initialize the email notifier
    $notifier = new EmailNotifier();

    // Counters
    $sent = 0;
    $failed = 0;

    // Process each task
    while ($row = $result->fetch_assoc()) {
        $taskId = $row['task_id'];
        $taskName = $row['task_name'];
        $dueDate = $row['due_date'];
        $username = $row['username'];
        $email = $row['email'];

        cronLog("Processing: Task #$taskId - \"$taskName\" for user \"$username\" ($email)");

        // Send the reminder
        $success = $notifier->sendDueDateReminder($email, $username, $taskName, $dueDate);

        if ($success) {
            $sent++;
            cronLog("  ✓ Email sent successfully");
        } else {
            $failed++;
            cronLog("  ✗ Failed to send email");
        }

        // Small delay to avoid rate limiting
        usleep(500000); // 0.5 second delay
    }

    $stmt->close();
    $conn->close();

    cronLog("=== Job completed ===");
    cronLog("Summary: $sent sent, $failed failed out of $totalTasks total");

} catch (Exception $e) {
    cronLog("FATAL ERROR: " . $e->getMessage());
    cronLog("Stack trace: " . $e->getTraceAsString());
    exit(1);
}

exit(0);
?>