<?php
//Latest Change 3 - Added email verification
// register_process.php
session_start();
require_once 'db_connect.php';
require_once 'includes/EmailNotifier.php';

// Check if data was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    $requestId = session_id() ?: bin2hex(random_bytes(16));
    // Validate CSRF token
    // if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    //     $errors[] = 'Invalid CSRF token.';
    //     error_log('CSRF token validation failed.');
    //     $_SESSION['errors'] = $errors;
    //     $form_data = $_POST;
    //     unset($form_data['csrf_token']);
    //     unset($form_data['password']);
    //     unset($form_data['confirm_password']);
    //     $_SESSION['form_data'] = $form_data;
    //     header('Location: register.php');
    //     exit();
    // }
    // Rotate token after successful validation
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    // 1. Retrieve and sanitize inputs
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Array to hold errors


    // 2. Validate inputs
    if (empty($username)) {
        $errors[] = "Username is required.";
    } elseif (strlen($username) > 50) {
        $errors[] = "Username must be less than 50 characters.";
    } else {
        // Check if username already exists
        try {
            $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
            if ($stmt) {
                $stmt->bind_param("s", $username);
                $stmt->execute();
                $stmt->store_result();
                if ($stmt->num_rows > 0) {
                    $errors[] = "Username already taken.";
                }
                $stmt->close();
            } else {
                throw new Exception("Failed to prepare username check statement: " . $conn->error);
            }
        } catch (Exception $e) {
            error_log('Database error (username check) [' . $requestId . ']: ' . $e->getMessage());
            $errors[] = 'An internal database error occurred. Please try again later.';
        }
    }

    if (empty($email)) {
        $errors[] = "Email is required.";
    } elseif (strlen($email) > 255) {
        $errors[] = "Email must be less than 255 characters.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    } else {
        // Check if email already exists
        try {
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            if ($stmt) {
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $stmt->store_result();
                if ($stmt->num_rows > 0) {
                    $errors[] = "This email is already registered.";
                }
                $stmt->close();
            } else {
                throw new Exception("Failed to prepare email check statement: " . $conn->error);
            }
        } catch (Exception $e) {
            error_log('Database error (email check) [' . $requestId . ']: ' . $e->getMessage());
            $errors[] = 'An internal database error occurred. Please try again later.';
        }
    }

    if (empty($password)) {
        $errors[] = "Password is required.";
    } elseif (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long.";
    }

    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }

    // 4. Insert user if no errors
    if (empty($errors)) {
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Generate 6-digit verification code
        $verification_code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $verification_code_expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));
        $email_verified = 0;

        try {
            $password_salt = ''; // PHP's password_hash() handles salting internally
            $stmt = $conn->prepare("INSERT INTO users (username, email, password_hash, password_salt, email_verified, verification_code, verification_code_expires) VALUES (?, ?, ?, ?, ?, ?, ?)");

            if (!$stmt) {
                throw new Exception("Failed to prepare insert statement: " . $conn->error);
            }

            $stmt->bind_param("sssssss", $username, $email, $hashed_password, $password_salt, $email_verified, $verification_code, $verification_code_expires);

            if ($stmt->execute()) {
                // Send verification code email
                try {
                    $emailNotifier = new EmailNotifier();
                    if ($emailNotifier->sendVerificationCode($email, $username, $verification_code)) {
                        // Store email in session for the verification page
                        $_SESSION['pending_verification_email'] = $email;
                        $_SESSION['success_message'] = "Registration successful! Please check your email for the verification code.";
                    } else {
                        $_SESSION['pending_verification_email'] = $email;
                        $_SESSION['success_message'] = "Registration successful! There was an issue sending the verification email. Please try resending.";
                        error_log("Failed to send verification code to: $email");
                    }
                } catch (Exception $e) {
                    $_SESSION['pending_verification_email'] = $email;
                    $_SESSION['success_message'] = "Registration successful! There was an issue sending the verification email. Please try resending.";
                    error_log("Exception sending verification code: " . $e->getMessage());
                }

                $stmt->close();
                header("Location: verify_code.php");
                exit();
            } else {
                throw new Exception("Execute failed: " . $stmt->error);
            }
        } catch (Exception $e) {
            // Ensure log directory exists
            $log_dir = __DIR__ . '/private_logs';
            $log_file = $log_dir . '/db_errors.log';
            if (!is_dir($log_dir)) {
                @mkdir($log_dir, 0750, true);
            }

            // Write generic redaction to file
            $redacted_message = "[" . date('Y-m-d H:i:s') . "] Request ID: " . $requestId . " | DB Error: " . $e->getMessage() . PHP_EOL;
            @file_put_contents($log_file, $redacted_message, FILE_APPEND);

            // Log detailed error to system log
            error_log("Database error (insert user) [" . $requestId . "]: " . $e->getMessage());
            $errors[] = "An internal error occurred. Please try again later.";

            if (isset($stmt) && $stmt) {
                $stmt->close();
            }
        }
    }

    // 5. Handle errors
    if (!empty($errors)) {
        // Store errors and form data in session to display them back on register.php
        $_SESSION['errors'] = $errors;
        $_SESSION['form_data'] = [
            'username' => $username,
            'email' => $email
        ];
        header("Location: register.php");
        exit();
    }

} else {
    // Not a POST request, redirect back
    header("Location: register.php");
    exit();
}
?>