<?php
// register.php - User registration form and processing
require_once 'db_connect.php';

// Initialize variables
$username = $email = $password = $confirm_password = '';
$username_err = $email_err = $password_err = $confirm_err = $general_err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate username
    if (empty(trim($_POST['username']))) {
        $username_err = 'Please enter a username.';
    } else {
        $username = trim($_POST['username']);
    }

    // Validate email
    if (empty(trim($_POST['email']))) {
        $email_err = 'Please enter an email.';
    } elseif (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        $email_err = 'Invalid email format.';
    } else {
        $email = trim($_POST['email']);
    }

    // Validate password
    if (empty($_POST['password'])) {
        $password_err = 'Please enter a password.';
    } elseif (strlen($_POST['password']) < 6) {
        $password_err = 'Password must be at least 6 characters.';
    } else {
        $password = $_POST['password'];
    }

    // Validate confirm password
    if (empty($_POST['confirm_password'])) {
        $confirm_err = 'Please confirm your password.';
    } else {
        $confirm_password = $_POST['confirm_password'];
        if ($password !== $confirm_password) {
            $confirm_err = 'Passwords do not match.';
        }
    }

    // If no validation errors, insert into database
    if (empty($username_err) && empty($email_err) && empty($password_err) && empty($confirm_err)) {
        $stmt = $conn->prepare('INSERT INTO users (username, email, password) VALUES (?, ?, ?)');
        if ($stmt) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt->bind_param('sss', $username, $email, $hashed_password);
            if (!$stmt->execute()) {
                $general_err = 'Database error: ' . $stmt->error;
            } else {
                // Registration successful, redirect to login or home
                header('Location: login.php');
                exit();
            }
            $stmt->close();
        } else {
            $general_err = 'Prepare failed: ' . $conn->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Register</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f0f2f5;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }

        .container {
            background: #fff;
            padding: 20px 30px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            width: 350px;
        }

        h2 {
            text-align: center;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        input[type="text"],
        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }

        .error {
            color: #d93025;
            font-size: 0.9em;
        }

        button {
            width: 100%;
            padding: 10px;
            background: #4a90e2;
            color: #fff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1em;
        }

        button:hover {
            background: #357ab8;
        }
    </style>
</head>

<body>
    <div class="container">
        <h2>Create Account</h2>
        <?php if (!empty($general_err)): ?>
            <p class="error"><?php echo htmlspecialchars($general_err); ?></p>
        <?php endif; ?>
        <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" name="username" id="username" value="<?php echo htmlspecialchars($username); ?>"
                    required>
                <?php if (!empty($username_err)): ?><span
                        class="error"><?php echo $username_err; ?></span><?php endif; ?>
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($email); ?>" required>
                <?php if (!empty($email_err)): ?><span class="error"><?php echo $email_err; ?></span><?php endif; ?>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" name="password" id="password" required>
                <?php if (!empty($password_err)): ?><span
                        class="error"><?php echo $password_err; ?></span><?php endif; ?>
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" name="confirm_password" id="confirm_password" required>
                <?php if (!empty($confirm_err)): ?><span class="error"><?php echo $confirm_err; ?></span><?php endif; ?>
            </div>
            <button type="submit">Register</button>
        </form>
        <p style="margin-top:10px; text-align:center;">Already have an account? <a href="login.php">Login here</a></p>
    </div>
</body>

</html>