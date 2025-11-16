<?php
require_once 'config.php';

// If already logged in, redirect based on user type
if (is_logged_in()) {
    switch($_SESSION['user_type']) {
        case 'student':
            header("Location: quiz.php");
            break;
        case 'teacher':
            header("Location: teacher.php");
            break;
        case 'librarian':
            header("Location: librarian.php");
            break;
        case 'admin':
            header("Location: admin.php");
            break;
    }
    exit();
}

$error = '';
$success = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // CSRF Protection
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $error = "Invalid security token. Please try again.";
    } else {
        $username = sanitize_input($_POST['username']);
        $password = $_POST['password'];
        
        // Rate limiting
        if (!check_rate_limit($username, 5, 300)) {
            $error = "Too many failed login attempts. Please try again in 5 minutes.";
            log_failed_attempt($username, 'login_rate_limit');
        } else if (empty($username) || empty($password)) {
            $error = "Please fill in all fields.";
        } else {
            // Query user from database
            $sql = "SELECT user_id, full_name, email, password_hash, user_type, status, student_id, grade_level 
                    FROM users 
                    WHERE (email = ? OR student_id = ?) 
                    LIMIT 1";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $username, $username);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows == 1) {
                $user = $result->fetch_assoc();
                
                // Check if account is active
                if ($user['status'] != 'active') {
                    $error = "Your account has been suspended. Please contact the administrator.";
                    log_failed_attempt($username, 'login_suspended');
                } 
                // Verify password
                else if (verify_password($password, $user['password_hash'])) {
                    // Set session variables
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['user_type'] = $user['user_type'];
                    $_SESSION['student_id'] = $user['student_id'];
                    $_SESSION['grade_level'] = $user['grade_level'];
                    
                    // Regenerate session ID on login
                    session_regenerate_id(true);
                    
                    // Log the login
                    $log_sql = "INSERT INTO system_logs (user_id, action, description, ip_address) 
                               VALUES (?, 'login', 'User logged in', ?)";
                    $log_stmt = $conn->prepare($log_sql);
                    $ip = $_SERVER['REMOTE_ADDR'];
                    $log_stmt->bind_param("is", $user['user_id'], $ip);
                    $log_stmt->execute();
                    
                    // Redirect based on user type
                    switch($user['user_type']) {
                        case 'student':
                            header("Location: quiz.php");
                            break;
                        case 'teacher':
                            header("Location: teacher.php");
                            break;
                        case 'librarian':
                            header("Location: librarian.php");
                            break;
                        case 'admin':
                            header("Location: admin.php");
                            break;
                    }
                    exit();
                } else {
                    $error = "Invalid username or password.";
                    log_failed_attempt($username, 'login_failed');
                }
            } else {
                $error = "Invalid username or password.";
                log_failed_attempt($username, 'login_not_found');
            }
            
            $stmt->close();
        }
    }
}

// Get scanned book info if coming from QR scan
$scanned_book = '';
if (isset($_SESSION['scanned_book'])) {
    $book = $_SESSION['scanned_book'];
    $scanned_book = "Selected Book: " . $book['title'] . " by " . $book['author'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Library Hub Tambo</title>
    <!-- Add favicon for book icon -->
    <link rel="icon" type="image/svg+xml" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.4.2/svgs/solid/book.svg">
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
            margin: 50px auto;
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
            margin-bottom: 10px;
        }

        .btn:hover {
            opacity: 0.9;
        }

        .btn-secondary {
            background: #6c757d;
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .alert-info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
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

        .divider {
            margin: 20px 0;
            text-align: center;
            color: #999;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📚 Library Hub Reading System</h1>
            <p>Smart Reading Engagement & Comprehension Tracking</p>
            <p><small>Tambo, Lipa City</small></p>
        </div>

        <div class="card">
            <h2>🔑 Login</h2>
            
            <?php if ($scanned_book): ?>
            <div class="alert alert-info">
                <strong>📖 <?php echo $scanned_book; ?></strong>
            </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
            <div class="alert alert-danger">
                <strong>❌ Error:</strong> <?php echo $error; ?>
            </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
            <div class="alert alert-success">
                <strong>✅ Success:</strong> <?php echo $success; ?>
            </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <?php echo csrf_field(); ?>
                <div class="form-group">
                    <label>Email or Student ID:</label>
                    <input type="text" name="username" placeholder="Enter your email or student ID" required autofocus>
                </div>
                <div class="form-group">
                    <label>Password:</label>
                    <input type="password" name="password" placeholder="Enter your password" required>
                </div>
                <button type="submit" class="btn">Login</button>
            </form>

            <div class="divider">OR</div>

            <a href="signup.php" class="btn btn-secondary" style="display: block; text-align: center; text-decoration: none;">
                Create Student Account
            </a>
            
            <div class="text-center">
                <a href="forgot-password.php" class="link">Forgot Password?</a>
            </div>

            <div class="divider">───</div>
            
            <div class="text-center">
                <a href="index.php" class="link">← Back to QR Scanner</a>
            </div>
        </div>
    </div>
</body>
</html>
