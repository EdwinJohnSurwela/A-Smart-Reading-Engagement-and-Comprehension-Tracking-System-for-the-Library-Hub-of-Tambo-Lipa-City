<?php
require_once 'config.php';

// If already logged in, redirect
if (is_logged_in()) {
    header("Location: quiz.php");
    exit();
}

$error = '';
$success = '';

// Handle registration form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // CSRF Protection
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $error = "Invalid security token. Please try again.";
    } else {
        $full_name = sanitize_input($_POST['full_name']);
        $student_id = sanitize_input($_POST['student_id']);
        $email = sanitize_input($_POST['email']);
        $grade_level = sanitize_input($_POST['grade_level']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Validation
        if (empty($full_name) || empty($student_id) || empty($email) || empty($grade_level) || empty($password)) {
            $error = "Please fill in all fields.";
        } else if (!validate_email($email)) {
            $error = "Invalid email address.";
        } else if ($password !== $confirm_password) {
            $error = "Passwords do not match.";
        } else {
            // Enhanced password validation
            $password_check = validate_password($password);
            if (!$password_check['valid']) {
                $error = implode('<br>', $password_check['errors']);
            } else {
                // Check if email or student_id already exists
                $check_sql = "SELECT user_id FROM users WHERE email = ? OR student_id = ?";
                $check_stmt = $conn->prepare($check_sql);
                $check_stmt->bind_param("ss", $email, $student_id);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                
                if ($check_result->num_rows > 0) {
                    $error = "Email or Student ID already registered.";
                } else {
                    // Insert new user
                    $password_hash = hash_password($password);
                    $sql = "INSERT INTO users (student_id, full_name, email, password_hash, user_type, grade_level, status) 
                            VALUES (?, ?, ?, ?, 'student', ?, 'active')";
                    
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("ssssi", $student_id, $full_name, $email, $password_hash, $grade_level);
                    
                    if ($stmt->execute()) {
                        $success = "Registration successful! You can now login.";
                        // Redirect to login after 2 seconds
                        header("refresh:2;url=login.php");
                    } else {
                        $error = "Registration failed. Please try again.";
                    }
                    
                    $stmt->close();
                }
                
                $check_stmt->close();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - Library Hub Tambo</title>
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
            margin: 30px auto;
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

        .form-group input, .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e1e1e1;
            border-radius: 8px;
            font-size: 16px;
        }

        .form-group input:focus, .form-group select:focus {
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

        .password-requirements {
            background: #f8f9fa;
            padding: 12px 15px;
            border-radius: 8px;
            margin-top: 8px;
            font-size: 13px;
        }

        .password-requirements h4 {
            margin-bottom: 8px;
            color: #333;
            font-size: 14px;
        }

        .password-requirements ul {
            margin: 5px 0 0 20px;
            color: #666;
        }

        .password-requirements li {
            margin-bottom: 3px;
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
            <h2>📝 Student Registration</h2>
            
            <?php if ($error): ?>
            <div class="alert alert-danger">
                <strong>❌ Error:</strong> <?php echo $error; ?>
            </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
            <div class="alert alert-success">
                <strong>✅ Success:</strong> <?php echo $success; ?><br>
                <small>Redirecting to login...</small>
            </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <?php echo csrf_field(); ?>
                <div class="form-group">
                    <label>Full Name: *</label>
                    <input type="text" name="full_name" placeholder="Enter your full name" required>
                </div>
                <div class="form-group">
                    <label>Student ID: *</label>
                    <input type="text" name="student_id" placeholder="Enter your student ID" required>
                </div>
                <div class="form-group">
                    <label>Email: *</label>
                    <input type="email" name="email" placeholder="Enter your email" required>
                </div>
                <div class="form-group">
                    <label>Grade Level: *</label>
                    <select name="grade_level" required>
                        <option value="">Select Grade Level</option>
                        <option value="1">Grade 1</option>
                        <option value="2">Grade 2</option>
                        <option value="3">Grade 3</option>
                        <option value="4">Grade 4</option>
                        <option value="5">Grade 5</option>
                        <option value="6">Grade 6</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Password: *</label>
                    <input type="password" name="password" placeholder="Create a strong password" required>
                    <div class="password-requirements">
                        <h4>Password must contain:</h4>
                        <ul>
                            <li>At least 8 characters</li>
                            <li>One uppercase letter (A-Z)</li>
                            <li>One lowercase letter (a-z)</li>
                            <li>One number (0-9)</li>
                            <li>One special character (!@#$%^&*)</li>
                        </ul>
                    </div>
                </div>
                <div class="form-group">
                    <label>Confirm Password: *</label>
                    <input type="password" name="confirm_password" placeholder="Confirm your password" required>
                </div>
                <button type="submit" class="btn">Register</button>
                <a href="login.php" class="btn btn-secondary" style="display: block; text-align: center; text-decoration: none;">
                    Already Have an Account?
                </a>
            </form>
            
            <div class="text-center">
                <a href="index.php" class="link">← Back to QR Scanner</a>
            </div>
        </div>
    </div>
</body>
</html>
