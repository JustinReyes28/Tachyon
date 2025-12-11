<?php
// login_process.php
session_start();
require_once 'db_connect.php';

// Check if data was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    $requestId = session_id() ?: bin2hex(random_bytes(16));

    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $errors[] = 'Invalid CSRF token.';
        error_log('CSRF token validation failed (login).');
        $_SESSION['errors'] = $errors;
        header('Location: login.php');
        exit();
    }
    // Rotate token after successful validation
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    // 1. Retrieve inputs
    $username_email = trim($_POST['username_email'] ?? '');
    $password = $_POST['password'] ?? '';

    // 2. Validate inputs
    if (empty($username_email)) {
        $errors[] = "Username or Email is required.";
    }
    if (empty($password)) {
        $errors[] = "Password is required.";
    }

    // 3. Authenticate
    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT id, username, password FROM users WHERE username = ? OR email = ?");
        if ($stmt) {
            $stmt->bind_param("ss", $username_email, $username_email);

            if ($stmt->execute()) {
                $stmt->store_result();
                if ($stmt->num_rows === 1) {
                    $stmt->bind_result($id, $db_username, $db_password_hash);
                    $stmt->fetch();

                    if (password_verify($password, $db_password_hash)) {
                        // Success: Login
                        session_regenerate_id(true); // Prevent session fixation
                        $_SESSION['user_id'] = $id;
                        $_SESSION['username'] = $db_username;

                        // Redirect to dashboard/home
                        header("Location: index.php");
                        exit();
                    } else {
                        // Invalid password
                        $errors[] = "Invalid username or password.";
                    }
                } else {
                    // User not found
                    $errors[] = "Invalid username or password.";
                }
            } else {
                // DB Error (execute)
                // Ensure log directory exists
                $log_dir = __DIR__ . '/private_logs';
                $log_file = $log_dir . '/db_errors.log';
                if (!is_dir($log_dir)) {
                    if (!mkdir($log_dir, 0750, true)) {
                        error_log("Failed to create secure log directory: " . $log_dir);
                    }
                }

                // Write generic redaction to file
                $redacted_message = "[" . date('Y-m-d H:i:s') . "] Request ID: " . $requestId . " | DB Select Error (Login)" . PHP_EOL;
                if (file_put_contents($log_file, $redacted_message, FILE_APPEND) === false) {
                    error_log("Failed to write to secure log file: " . $log_file);
                }

                // Log detailed error to system log
                error_log("Database error (login select) [" . $requestId . "]: " . $stmt->error);
                $errors[] = "An internal error occurred. Please try again later.";
            }
            $stmt->close();
        } else {
            // DB Error (prepare)
            // Ensure log directory exists
            $log_dir = __DIR__ . '/private_logs';
            $log_file = $log_dir . '/db_errors.log';
            if (!is_dir($log_dir)) {
                if (!mkdir($log_dir, 0750, true)) {
                    error_log("Failed to create secure log directory: " . $log_dir);
                }
            }

            // Write generic redaction to file
            $redacted_message = "[" . date('Y-m-d H:i:s') . "] Request ID: " . $requestId . " | Convert Prepare Error (Login)" . PHP_EOL;
            if (file_put_contents($log_file, $redacted_message, FILE_APPEND) === false) {
                error_log("Failed to write to secure log file: " . $log_file);
            }

            // Log detailed error to system log
            error_log("Database error (login prepare) [" . $requestId . "]: " . $conn->error);
            $errors[] = "An internal error occurred. Please try again later.";
        }
    }

    // 4. Handle errors
    if (!empty($errors)) {
        $_SESSION['errors'] = $errors;
        $_SESSION['form_data'] = ['username_email' => $username_email];
        header("Location: login.php");
        exit();
    }

} else {
    // Not a POST request
    header("Location: login.php");
    exit();
}
?>