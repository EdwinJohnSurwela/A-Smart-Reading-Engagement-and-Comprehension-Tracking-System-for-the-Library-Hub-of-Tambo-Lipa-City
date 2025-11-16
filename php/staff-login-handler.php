<?php
require_once 'config.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Invalid Request'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize_input($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $staff_type = sanitize_input($_POST['staff_type'] ?? '');

    if (empty($email) || empty($password) || empty($staff_type)) {
        $response['message'] = 'Please fill in all fields.';
        echo json_encode($response);
        exit;
    }

    $valid_staff_types = ['admin', 'librarian', 'teacher'];
    if (!in_array($staff_type, $valid_staff_types)) {
        $response['message'] = 'Invalid staff type.';
        echo json_encode($response);
        exit;
    }

    $sql = "SELECT user_id, full_name, email, password_hash, user_type, status 
            FROM users 
            WHERE email = ? AND user_type = ? LIMIT 1";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        $response['message'] = 'Database error. Please try again.';
        echo json_encode($response);
        exit;
    }

    $stmt->bind_param("ss", $email, $staff_type);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        if ($user['status'] !== 'active') {
            $response['message'] = 'Your account is inactive or suspended. Please contact the administrator.';
            echo json_encode($response);
            $stmt->close();
            exit;
        }

        if (verify_password($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['user_type'] = $user['user_type'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['email'] = $user['email'];

            $response['success'] = true;
            $response['message'] = 'Login successful!';

            switch ($staff_type) {
                case 'admin':
                    $response['redirect'] = 'admin.php';
                    break;
                case 'librarian':
                    $response['redirect'] = 'librarian.php';
                    break;
                case 'teacher':
                    $response['redirect'] = 'teacher.php';
                    break;
                default:
                    $response['redirect'] = 'index.php';
                    break;
            }

            $log_sql = "INSERT INTO system_logs (user_id, action, description, ip_address) 
                       VALUES (?, 'LOGIN', 'Staff login successful', ?)";
            $log_stmt = $conn->prepare($log_sql);
            if ($log_stmt) {
                $ip_address = $_SERVER['REMOTE_ADDR'];
                $log_stmt->bind_param("is", $user['user_id'], $ip_address);
                $log_stmt->execute();
                $log_stmt->close();
            }
        } else {
            $response['message'] = 'Invalid email or password.';
        }
    } else {
        $response['message'] = 'Invalid email or password.';
    }

    $stmt->close();
}

echo json_encode($response);
exit;
?>
