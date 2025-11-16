<?php
/**
 * =====================================================
 * DATABASE CONFIGURATION
 * Library Hub of Tambo, Lipa City
 * =====================================================
 */

// Start session with proper configuration
if (session_status() === PHP_SESSION_NONE) {
    // Session configuration for better cookie management
    ini_set('session.cookie_lifetime', 0); // Session ends when browser closes
    ini_set('session.gc_maxlifetime', 3600); // 1 hour
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_samesite', 'Lax');
    
    session_start();
    
    // Clear any error-related session data on new page load
    if (isset($_SESSION['_clear_on_next_load'])) {
        session_unset();
        session_destroy();
        session_start();
        unset($_SESSION['_clear_on_next_load']);
    }
}

// Database Configuration
define('DB_HOST', 'sql302.infinityfree.com');
define('DB_USER', 'if0_40429281');
define('DB_PASS', '1sG1hRhT1oDwHs');
define('DB_NAME', 'if0_40429281_library_reading_system');

// Create database connection
try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    // Set charset to utf8
    $conn->set_charset("utf8");
    
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// Timezone
date_default_timezone_set('Asia/Manila');

// Security Functions
function sanitize_input($data) {
    global $conn;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $conn->real_escape_string($data);
}

function sanitize_array($array) {
    $sanitized = [];
    foreach ($array as $key => $value) {
        $sanitized[$key] = is_array($value) ? sanitize_array($value) : sanitize_input($value);
    }
    return $sanitized;
}

function hash_password($password) {
    return password_hash($password, PASSWORD_BCRYPT);
}

function verify_password($password, $hash) {
    return password_verify($password, $hash);
}

// Check if user is logged in
function is_logged_in() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_type']);
}

// Check user type
function check_user_type($allowed_types) {
    if (!is_logged_in()) {
        header("Location: login.php");
        exit();
    }
    
    if (!in_array($_SESSION['user_type'], $allowed_types)) {
        header("Location: unauthorized.php");
        exit();
    }
}

// Logout function
function logout() {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit();
}

// Generate random token for password reset
function generate_token() {
    return bin2hex(random_bytes(32));
}

// Send email (configure with your SMTP settings)
function send_email($to, $subject, $message) {
    // Basic mail function - configure with PHPMailer for production
    $headers = "From: Library Hub <noreply@libraryhub.com>\r\n";
    $headers .= "Reply-To: noreply@libraryhub.com\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    
    return mail($to, $subject, $message, $headers);
}

// Error logging
function log_error($message) {
    $log_file = 'logs/error_log.txt';
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[$timestamp] $message\n";
    file_put_contents($log_file, $log_message, FILE_APPEND);
}

// Success response with session clearing on error
function json_response($success, $message, $data = null) {
    // Clean any output buffer
    if (ob_get_level()) {
        ob_clean();
    }
    
    // If error, mark session for clearing on next load
    if (!$success) {
        $_SESSION['_clear_on_next_load'] = true;
    }
    
    // Ensure we're sending JSON
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
    
    $response = [
        'success' => $success,
        'message' => $message
    ];
    
    if ($data !== null) {
        $response['data'] = $data;
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}

// =====================================================
// CSRF PROTECTION
// =====================================================
function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function csrf_field() {
    $token = generate_csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
}

// =====================================================
// ENCRYPTION & DECRYPTION (AES-256-CBC)
// =====================================================
define('ENCRYPTION_KEY', 'your-32-character-secret-key-here-change-this!!'); // CHANGE IN PRODUCTION
define('ENCRYPTION_METHOD', 'AES-256-CBC');

function encrypt_data($data) {
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(ENCRYPTION_METHOD));
    $encrypted = openssl_encrypt($data, ENCRYPTION_METHOD, ENCRYPTION_KEY, 0, $iv);
    return base64_encode($encrypted . '::' . $iv);
}

function decrypt_data($encrypted_data) {
    list($encrypted, $iv) = explode('::', base64_decode($encrypted_data), 2);
    return openssl_decrypt($encrypted, ENCRYPTION_METHOD, ENCRYPTION_KEY, 0, $iv);
}

// =====================================================
// SESSION SECURITY
// =====================================================
function secure_session_start() {
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
        ini_set('session.use_strict_mode', 1);
        ini_set('session.cookie_samesite', 'Strict');
        session_start();
    }
    
    // Session hijacking protection
    if (!isset($_SESSION['user_ip'])) {
        $_SESSION['user_ip'] = $_SERVER['REMOTE_ADDR'];
        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
    } else {
        if ($_SESSION['user_ip'] !== $_SERVER['REMOTE_ADDR'] || 
            $_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
            session_unset();
            session_destroy();
            // Only redirect if headers not sent
            if (!headers_sent()) {
                header("Location: login.php?session_hijack=1");
                exit();
            }
        }
    }
    
    // Session regeneration for login - only if headers not sent
    if (isset($_SESSION['user_id']) && !isset($_SESSION['session_regenerated'])) {
        if (!headers_sent()) {
            session_regenerate_id(true);
            $_SESSION['session_regenerated'] = true;
        }
    }
}

// Replace the existing session_start() call
secure_session_start();

// =====================================================
// RATE LIMITING (Brute-Force Protection)
// =====================================================
function check_rate_limit($identifier, $max_attempts = 5, $time_window = 300) {
    global $conn;
    
    $sql = "SELECT COUNT(*) as attempts FROM system_logs 
            WHERE description LIKE CONCAT('%', ?, '%') 
            AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $identifier, $time_window);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    return $row['attempts'] < $max_attempts;
}

function log_failed_attempt($identifier, $action) {
    global $conn;
    
    $ip = $_SERVER['REMOTE_ADDR'];
    $sql = "INSERT INTO system_logs (action, description, ip_address) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $action, $identifier, $ip);
    $stmt->execute();
    $stmt->close();
}

// =====================================================
// FILE UPLOAD SECURITY
// =====================================================
function secure_file_upload($file, $allowed_types = ['image/jpeg', 'image/png', 'application/pdf']) {
    $upload_dir = '../uploads/';
    
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Validate file
    if (!isset($file['error']) || is_array($file['error'])) {
        return ['success' => false, 'message' => 'Invalid file upload'];
    }
    
    // Check upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Upload error: ' . $file['error']];
    }
    
    // File size limit (5MB)
    if ($file['size'] > 5242880) {
        return ['success' => false, 'message' => 'File size exceeds 5MB limit'];
    }
    
    // Check MIME type
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    
    if (!in_array($mime, $allowed_types)) {
        return ['success' => false, 'message' => 'Invalid file type'];
    }
    
    // Generate secure filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = bin2hex(random_bytes(16)) . '.' . $extension;
    $filepath = $upload_dir . $filename;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['success' => false, 'message' => 'Failed to save file'];
    }
    
    return ['success' => true, 'filename' => $filename, 'filepath' => $filepath];
}

// =====================================================
// SECURE COOKIE MANAGEMENT
// =====================================================
function set_secure_cookie($name, $value, $days = 30) {
    $encrypted_value = encrypt_data($value);
    
    setcookie(
        $name,
        $encrypted_value,
        [
            'expires' => time() + ($days * 86400),
            'path' => '/',
            'domain' => $_SERVER['HTTP_HOST'],
            'secure' => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Strict'
        ]
    );
}

function get_secure_cookie($name) {
    if (isset($_COOKIE[$name])) {
        return decrypt_data($_COOKIE[$name]);
    }
    return null;
}

// =====================================================
// PASSWORD POLICY VALIDATION
// =====================================================
function validate_password($password) {
    $errors = [];
    
    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters";
    }
    
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "Password must contain at least one uppercase letter";
    }
    
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = "Password must contain at least one lowercase letter";
    }
    
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = "Password must contain at least one number";
    }
    
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = "Password must contain at least one special character";
    }
    
    return empty($errors) ? ['valid' => true] : ['valid' => false, 'errors' => $errors];
}

// =====================================================
// EMAIL VALIDATION
// =====================================================
function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// XSS PROTECTION
function escape_output($data) {
    return htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function clean_html($html) {
    return htmlspecialchars(strip_tags($html), ENT_QUOTES, 'UTF-8');
}
?>
