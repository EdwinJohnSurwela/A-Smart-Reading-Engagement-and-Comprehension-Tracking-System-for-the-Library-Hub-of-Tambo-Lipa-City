<?php
require_once 'config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['forgot_password'])) {
    $email = sanitize_input($_POST['email']);

    if (empty($email)) {
        $error = "Please enter your registered email.";
    } else {
        $sql = "SELECT user_id, full_name, email FROM users WHERE email = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            $token = bin2hex(random_bytes(32));
            $expires = date("Y-m-d H:i:s", strtotime("+1 hour"));

            $insert = $conn->prepare("INSERT INTO password_resets (email, token, expires_at) 
                                      VALUES (?, ?, ?) 
                                      ON DUPLICATE KEY UPDATE token=?, expires_at=?");
            $insert->bind_param("sssss", $email, $token, $expires, $token, $expires);
            $insert->execute();

            $reset_link = "http://localhost/FP_SIA_SAD_WST/php/reset-password.php?token=" . $token;
            $success = "A password reset link has been sent to your email: 
                        <strong>{$email}</strong><br>
                        <small>(For testing: <a href='$reset_link' target='_blank'>Click here to reset now</a>)</small>";
        } else {
            $error = "No account found with that email.";
        }
        $stmt->close();
    }
}
?>
