<?php
require_once 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$email = sanitize_input($_POST['email'] ?? '');
$staff_type = sanitize_input($_POST['staff_type'] ?? '');

if (empty($email)) {
    echo json_encode(['success' => false, 'message' => 'Please enter your registered email.']);
    exit();
}

if (empty($staff_type)) {
    echo json_encode(['success' => false, 'message' => 'Invalid staff type.']);
    exit();
}

$sql = "SELECT user_id, full_name, email FROM users WHERE email = ? AND user_type = ? LIMIT 1";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Database error. Please try again.']);
    exit();
}

$stmt->bind_param("ss", $email, $staff_type);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'No account found with that email for this staff type.']);
    $stmt->close();
    exit();
}

$user = $result->fetch_assoc();
$stmt->close();

$token = bin2hex(random_bytes(32));
$expires = date("Y-m-d H:i:s", strtotime("+1 hour"));

$delete_old = $conn->prepare("DELETE FROM password_resets WHERE email = ?");
$delete_old->bind_param("s", $email);
$delete_old->execute();
$delete_old->close();

$insert = $conn->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");

if (!$insert) {
    echo json_encode(['success' => false, 'message' => 'Database error. Please try again.']);
    exit();
}

$insert->bind_param("sss", $email, $token, $expires);

if (!$insert->execute()) {
    echo json_encode(['success' => false, 'message' => 'Failed to generate reset link. Please try again.']);
    $insert->close();
    exit();
}

$insert->close();

$reset_link = "http://localhost/FP_SIA_SAD_WST/php/reset-password.php?token=" . $token;

// TODO: Send email with reset link
// mail($email, "Password Reset Request", "Click here to reset: " . $reset_link);

echo json_encode([
    'success' => true,
    'message' => 'A password reset link has been sent to your email. Please click here to reset now.',
    'reset_link' => $reset_link
]);
?>
