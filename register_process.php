<?php
// register_process.php
session_start();
require_once 'db_connect.php';

// Check if data was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    $requestId = session_id() ?: bin2hex(random_bytes(16));
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $errors[] = 'Invalid CSRF token.';
        error_log('CSRF token validation failed.');
        $_SESSION['errors'] = $errors;
        $form_data = $_POST;
        unset($form_data['csrf_token']);
        unset($form_data['password']);
        unset($form_data['confirm_password']);
        $_SESSION['form_data'] = $form_data;
        header('Location: register.php');
        exit();
    }
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
    } else {
        // Check if username already exists
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
            error_log('Database error (username check): ' . $conn->error);
            $errors[] = 'An internal database error occurred. Please try again later.';
        }
    }

    if (empty($email)) {
        $errors[] = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    } else {
        // Check if email already exists
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
            error_log('Database error (email check): ' . $conn->error);
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

        // Prepare insert statement
        $stmt = $conn->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("sss", $username, $email, $hashed_password);

            if ($stmt->execute()) {
                // Success: Redirect to login page
                $_SESSION['success_message'] = "Registration successful! Please login.";
                header("Location: login.php");
                exit();
            } else {
                error_log('Database error (insert user) [' . $requestId . ']');
                $errors[] = "An internal error occurred. Please try again later.";
            }
            $stmt->close();
        } else {
            error_log('Database error (prepare insert) [' . $requestId . ']');
            $errors[] = "An internal error occurred. Please try again later.";
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