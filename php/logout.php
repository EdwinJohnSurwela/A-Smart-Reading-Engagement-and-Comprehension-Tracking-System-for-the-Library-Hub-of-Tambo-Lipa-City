<?php
require_once 'config.php';

// Log the logout action
if (is_logged_in()) {
    $user_id = $_SESSION['user_id'];
    $log_sql = "INSERT INTO system_logs (user_id, action, description, ip_address) 
               VALUES (?, 'logout', 'User logged out', ?)";
    $log_stmt = $conn->prepare($log_sql);
    $ip = $_SERVER['REMOTE_ADDR'];
    $log_stmt->bind_param("is", $user_id, $ip);
    $log_stmt->execute();
    $log_stmt->close();
}

// Clear all session data
session_unset();
session_destroy();

// Redirect to landing page
header("Location: index.php");
exit();
?>
