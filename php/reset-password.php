<?php
require_once 'config.php';

$error = '';
$success = '';
$valid_token = false;
$email = '';
$user_type = '';

// Check if token is provided
if (!isset($_GET['token'])) {
    header("Location: login.php");
    exit();
}

$token = sanitize_input($_GET['token']);

// Verify token and get user type
$sql = "SELECT pr.email, pr.expires_at, u.user_type 
        FROM password_resets pr
        INNER JOIN users u ON pr.email = u.email
        WHERE pr.token = ? AND pr.expires_at > NOW()";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 1) {
    $valid_token = true;
    $reset_data = $result->fetch_assoc();
    $email = $reset_data['email'];
    $user_type = $reset_data['user_type'];
} else {
    $error = "Invalid or expired reset link. Please request a new password reset.";
}

$stmt->close();

// Handle password reset form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $valid_token) {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($new_password) || empty($confirm_password)) {
        $error = "Please fill in all fields.";
    } else if ($new_password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        // Enhanced password validation using validate_password() from config.php
        $password_check = validate_password($new_password);
        if (!$password_check['valid']) {
            $error = implode('<br>', $password_check['errors']);
        } else {
            // Update password
            $password_hash = hash_password($new_password);
            $update_sql = "UPDATE users SET password_hash = ? WHERE email = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("ss", $password_hash, $email);
            
            if ($update_stmt->execute()) {
                // Delete used token
                $delete_sql = "DELETE FROM password_resets WHERE email = ?";
                $delete_stmt = $conn->prepare($delete_sql);
                $delete_stmt->bind_param("s", $email);
                $delete_stmt->execute();
                $delete_stmt->close();
                
                $success = "Password reset successful! You can now login with your new password.";
                
                // Log the password reset
                $user_sql = "SELECT user_id FROM users WHERE email = ?";
                $user_stmt = $conn->prepare($user_sql);
                $user_stmt->bind_param("s", $email);
                $user_stmt->execute();
                $user_result = $user_stmt->get_result();
                if ($user_result->num_rows == 1) {
                    $user = $user_result->fetch_assoc();
                    $log_sql = "INSERT INTO system_logs (user_id, action, description, ip_address) 
                               VALUES (?, 'password_reset', 'Password reset successful', ?)";
                    $log_stmt = $conn->prepare($log_sql);
                    $ip = $_SERVER['REMOTE_ADDR'];
                    $log_stmt->bind_param("is", $user['user_id'], $ip);
                    $log_stmt->execute();
                    $log_stmt->close();
                }
                $user_stmt->close();
                
                // Redirect based on user type
                if ($user_type === 'student') {
                    header("refresh:3;url=login.php");
                } else {
                    // For staff (admin, teacher, librarian), redirect to index with modal trigger
                    header("refresh:3;url=index.php?login_modal=" . $user_type);
                }
            } else {
                $error = "Failed to reset password. Please try again.";
            }
            
            $update_stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Library Hub Tambo</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 500px;
            margin: 80px auto;
        }

        .header {
            text-align: center;
            color: white;
            margin-bottom: 30px;
        }

        .header h1 {
            font-size: 2.5em;
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
        }

        .btn:hover {
            opacity: 0.9;
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .alert-danger {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }

        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        .link {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }

        .link:hover {
            text-decoration: underline;
        }

        .text-center {
            text-align: center;
            margin-top: 15px;
        }

        .icon {
            font-size: 48px;
            text-align: center;
            margin-bottom: 20px;
        }

        .password-requirements {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .password-requirements h4 {
            margin-bottom: 10px;
            color: #333;
        }

        .password-requirements ul {
            margin: 10px 0 0 20px;
            color: #666;
        }

        .password-requirements li {
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📚 Library Hub Reading System</h1>
            <p>Reset Your Password</p>
        </div>

        <div class="card">
            <?php if (!$valid_token): ?>
                <div class="icon">⚠️</div>
                <h2>Invalid Reset Link</h2>
                
                <div class="alert alert-danger">
                    <?php echo $error; ?>
                </div>
                
                <div class="text-center">
                    <a href="forgot-password.php" class="link">Request New Reset Link</a> | 
                    <a href="login.php" class="link">Back to Login</a>
                </div>
                
            <?php elseif ($success): ?>
                <div class="icon">✅</div>
                <h2>Password Reset Successful!</h2>
                
                <div class="alert alert-success">
                    <?php echo $success; ?><br>
                    <small>Redirecting to login...</small>
                </div>
                
                <div class="text-center">
                    <?php if ($user_type === 'student'): ?>
                        <a href="login.php" class="link">Go to Login Page</a>
                    <?php else: ?>
                        <a href="index.php?login_modal=<?php echo $user_type; ?>" class="link">Go to Login</a>
                    <?php endif; ?>
                </div>
                
            <?php else: ?>
                <div class="icon">🔑</div>
                <h2>Create New Password</h2>
                <p style="color: #666; margin: 15px 0;">Enter your new password below.</p>
                
                <?php if ($error): ?>
                <div class="alert alert-danger">
                    <strong>❌ Error:</strong> <?php echo $error; ?>
                </div>
                <?php endif; ?>
                
                <div class="password-requirements">
                    <h4>Password Requirements:</h4>
                    <ul>
                        <li>At least 8 characters long</li>
                        <li>At least one uppercase letter (A-Z)</li>
                        <li>At least one lowercase letter (a-z)</li>
                        <li>At least one number (0-9)</li>
                        <li>At least one special character (!@#$%^&*)</li>
                        <li>Both password fields must match</li>
                    </ul>
                </div>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <label>New Password:</label>
                        <input type="password" name="new_password" placeholder="Enter new password" required autofocus>
                    </div>
                    <div class="form-group">
                        <label>Confirm New Password:</label>
                        <input type="password" name="confirm_password" placeholder="Confirm new password" required>
                    </div>
                    <button type="submit" class="btn">Reset Password</button>
                </form>
                
                <div class="text-center">
                    <?php if ($user_type === 'student'): ?>
                        <a href="login.php" class="link">← Back to Login</a>
                    <?php else: ?>
                        <a href="index.php" class="link">← Back to Home</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
