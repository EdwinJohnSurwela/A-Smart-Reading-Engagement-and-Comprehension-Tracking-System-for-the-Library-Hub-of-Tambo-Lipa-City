<?php
require_once 'config.php';

// Redirect if already logged in
if (is_logged_in()) {
    switch($_SESSION['user_type']) {
        case 'student': header("Location: quiz.php"); break;
        case 'teacher': header("Location: teacher.php"); break;
        case 'librarian': header("Location: librarian.php"); break;
        case 'admin': header("Location: admin.php"); break;
    }
    exit();
}

$error = '';
$success = '';

// Handle forgot password form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize_input($_POST['email']);

    if (empty($email)) {
        $error = "Please enter your registered email.";
    } else {
        // Check if email exists
        $sql = "SELECT user_id, full_name, email FROM users WHERE email = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            // Generate reset token
            $token = bin2hex(random_bytes(32));
            $expires = date("Y-m-d H:i:s", strtotime("+1 hour"));

            // Store token in password_resets table
            $insert = $conn->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?) 
                                      ON DUPLICATE KEY UPDATE token=?, expires_at=?");
            $insert->bind_param("sssss", $email, $token, $expires, $token, $expires);
            $insert->execute();

            // Send reset link (You can replace this with PHPMailer for production)
            $reset_link = "http://localhost/FP_SIA_SAD_WST/php/reset-password.php?token=" . $token;
            $success = "A password reset link has been sent to your email: <strong>{$email}</strong><br>
                        <small>(For testing: <a href='$reset_link' target='_blank'>Click here to reset now</a>)</small>";
        } else {
            $error = "No account found with that email.";
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Library Hub Tambo</title>
    <style>
        * {
            margin: 0; padding: 0; box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 500px;
            margin: 50px auto;
        }
        .header {
            text-align: center;
            color: white;
            margin-bottom: 30px;
        }
        .header h1 {
            font-size: 2.3em;
            margin-bottom: 10px;
        }
        .card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e1e1e1;
            border-radius: 8px;
            font-size: 16px;
        }
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }
        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            width: 100%;
            margin-bottom: 10px;
        }
        .btn:hover { opacity: 0.9; }
        .btn-secondary { background: #6c757d; }
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .alert-danger {
            background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24;
        }
        .alert-success {
            background: #d4edda; border: 1px solid #c3e6cb; color: #155724;
        }
        .link {
            color: #667eea; text-decoration: none; font-weight: 600;
        }
        .link:hover { text-decoration: underline; }
        .text-center {
            text-align: center; margin-top: 15px;
        }
        .divider {
            margin: 20px 0; text-align: center; color: #999;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔒 Forgot Password</h1>
            <p>Library Hub Reading System - Tambo, Lipa City</p>
        </div>

        <div class="card">
            <h2>Reset Your Password</h2>

            <?php if ($error): ?>
            <div class="alert alert-danger">
                <strong>❌ Error:</strong> <?= $error; ?>
            </div>
            <?php endif; ?>

            <?php if ($success): ?>
            <div class="alert alert-success">
                <?= $success; ?>
            </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label>Email Address:</label>
                    <input type="email" name="email" placeholder="Enter your registered email" required autofocus>
                </div>
                <button type="submit" class="btn">Send Reset Link</button>
            </form>

            <div class="divider">───</div>

            <div class="text-center">
                <a href="login.php" class="link">← Back to Login</a>
            </div>
        </div>
    </div>
</body>
</html>
