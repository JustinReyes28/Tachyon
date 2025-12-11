<?php
// register_process.php
session_start();
require_once 'db_connect.php';

// Check if data was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. Retrieve and sanitize inputs
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Array to hold errors
    $errors = [];

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
            $errors[] = "Database error: " . $conn->error;
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
            $errors[] = "Database error: " . $conn->error;
        }
    }

    if (empty($password)) {
        $errors[] = "Password is required.";
    } elseif (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters long.";
    }

    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }

    // 3. Check for existing users (optional but recommended)
    if (empty($errors)) {
        $stmt_check = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        if ($stmt_check) {
            $stmt_check->bind_param("ss", $username, $email);
            $stmt_check->execute();
            $stmt_check->store_result();
            if ($stmt_check->num_rows > 0) {
                $errors[] = "Username or Email already exists.";
            }
            $stmt_check->close();
        } else {
            $errors[] = "Database error: " . $conn->error;
        }
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
                $errors[] = "Error registering user: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $errors[] = "Database connect error: " . $conn->error;
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