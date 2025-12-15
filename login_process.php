<?php
// login_process.php
session_start();
require_once 'db_connect.php';

// Check if data was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    $requestId = session_id() ?: bin2hex(random_bytes(16));

    // Validate CSRF token
    // if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    //     $errors[] = 'Invalid CSRF token.';
    //     error_log('CSRF token validation failed (login).');
    //     $_SESSION['errors'] = $errors;
    //     header('Location: login.php');
    //     exit();
    // }
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
        $stmt = $conn->prepare("SELECT id, username, email, password_hash, failed_login_attempts, locked_until, email_verified FROM users WHERE username = ? OR email = ?");
        if ($stmt) {
            $stmt->bind_param("ss", $username_email, $username_email);

            if ($stmt->execute()) {
                $stmt->store_result();
                if ($stmt->num_rows === 1) {
                    $stmt->bind_result($id, $db_username, $db_email, $db_password_hash, $failed_login_attempts, $locked_until, $email_verified);
                    $stmt->fetch();

                    // Check if account is locked
                    if ($locked_until && new DateTime($locked_until) > new DateTime()) {
                        $lock_time = new DateTime($locked_until);
                        $now = new DateTime();
                        $interval = $now->diff($lock_time);
                        $minutes = $interval->i + ($interval->h * 60) + 1; // Round up
                        $errors[] = "Account is locked due to too many failed attempts. Please try again in {$minutes} minutes.";
                    } else {
                        if (password_verify($password, $db_password_hash)) {
                            // Check if email is verified
                            if (!$email_verified) {
                                $errors[] = "Please verify your email before logging in. Check your inbox for the verification link.";
                                $_SESSION['unverified_email'] = $db_email;
                                $_SESSION['show_resend_link'] = true;
                            } else {
                                // Success: Login

                                // Reset failed attempts
                                $reset_stmt = $conn->prepare("UPDATE users SET failed_login_attempts = 0, locked_until = NULL WHERE id = ?");
                                $reset_stmt->bind_param("i", $id);
                                $reset_stmt->execute();
                                $reset_stmt->close();

                                session_regenerate_id(true); // Prevent session fixation
                                $_SESSION['user_id'] = $id;
                                $_SESSION['username'] = $db_username;

                                // Redirect to dashboard/home
                                // Redirect to welcome page
                                header("Location: welcome.php");
                                exit();
                            }
                        } else {
                            // Invalid password
                            $failed_login_attempts++;
                            $new_locked_until = null;

                            // Lock out logic (e.g., 5 attempts = 15 minute lock)
                            if ($failed_login_attempts >= 5) {
                                $new_locked_until = date('Y-m-d H:i:s', strtotime('+15 minutes'));
                                $errors[] = "Too many failed attempts. Account locked for 15 minutes.";
                            } else {
                                $remaining = 5 - $failed_login_attempts;
                                $errors[] = "Invalid username or password. {$remaining} attempts remaining.";
                            }

                            // Update user with new failed attempts count
                            $update_stmt = $conn->prepare("UPDATE users SET failed_login_attempts = ?, locked_until = ? WHERE id = ?");
                            $update_stmt->bind_param("isi", $failed_login_attempts, $new_locked_until, $id);
                            $update_stmt->execute();
                            $update_stmt->close();
                        }
                    }
                } else {
                    // User not found
                    // To prevent user enumeration, we generally shouldn't distinguish, 
                    // but for rate limiting valid users, we only track existing accounts here.
                    // If we wanted to rate limit by IP for non-existent users, that would require a separate table.
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