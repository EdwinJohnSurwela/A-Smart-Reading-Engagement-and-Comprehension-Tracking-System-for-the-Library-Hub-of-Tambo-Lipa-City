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

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Scanner - Library Hub Tambo</title>
    <!-- Add favicon for book icon -->
    <link rel="icon" type="image/svg+xml" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.4.2/svgs/solid/book.svg">
    <script src="https://unpkg.com/html5-qrcode"></script>
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
            overflow-x: hidden;
        }

        /* Boot Animation Styles */
        #bootLoader {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            opacity: 1;
            transition: opacity 0.8s ease-out;
        }

        #bootLoader.fade-out {
            opacity: 0;
            pointer-events: none;
        }

        .boot-logo {
            position: relative;
            width: 200px;
            height: 200px;
            margin-bottom: 30px;
        }

        .boot-icon {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 120px;
            animation: iconPulse 2s ease-in-out infinite;
        }

        .spinner-ring {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border: 6px solid transparent;
            border-top-color: rgba(255, 255, 255, 0.8);
            border-right-color: rgba(255, 255, 255, 0.6);
            border-radius: 50%;
            animation: spin 1.5s linear infinite;
        }

        .spinner-ring:nth-child(2) {
            border-top-color: rgba(255, 255, 255, 0.6);
            border-right-color: rgba(255, 255, 255, 0.4);
            animation: spin 2s linear infinite reverse;
            width: 85%;
            height: 85%;
            top: 7.5%;
            left: 7.5%;
        }

        .spinner-ring:nth-child(3) {
            border-top-color: rgba(255, 255, 255, 0.4);
            border-right-color: rgba(255, 255, 255, 0.2);
            animation: spin 2.5s linear infinite;
            width: 70%;
            height: 70%;
            top: 15%;
            left: 15%;
        }

        .boot-text {
            color: white;
            font-size: 28px;
            font-weight: bold;
            text-align: center;
            animation: textFade 1.5s ease-in-out infinite;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
        }

        .boot-subtitle {
            color: rgba(255, 255, 255, 0.9);
            font-size: 16px;
            margin-top: 10px;
            text-align: center;
            animation: textFade 1.5s ease-in-out infinite 0.3s;
        }

        .loading-dots {
            display: inline-block;
            width: 40px;
            text-align: left;
        }

        .loading-dots::after {
            content: '';
            animation: dots 1.5s steps(4, end) infinite;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
                filter: blur(0px);
            }
            50% {
                filter: blur(2px);
            }
            100% {
                transform: rotate(360deg);
                filter: blur(0px);
            }
        }

        @keyframes iconPulse {
            0%, 100% {
                transform: translate(-50%, -50%) scale(1);
                filter: drop-shadow(0 0 20px rgba(255, 255, 255, 0.5));
            }
            50% {
                transform: translate(-50%, -50%) scale(1.1);
                filter: drop-shadow(0 0 30px rgba(255, 255, 255, 0.8));
            }
        }

        @keyframes textFade {
            0%, 100% {
                opacity: 1;
            }
            50% {
                opacity: 0.7;
            }
        }

        @keyframes dots {
            0%, 20% { content: ''; }
            40% { content: '.'; }
            60% { content: '..'; }
            80%, 100% { content: '...'; }
        }

        /* Main Content - Initially Hidden */
        #mainContent {
            opacity: 0;
            transform: scale(0.95);
            transition: opacity 0.8s ease-out, transform 0.8s ease-out;
        }

        #mainContent.show {
            opacity: 1;
            transform: scale(1);
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
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

        .header p {
            font-size: 1.2em;
            opacity: 0.9;
        }

        .card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            margin-bottom: 20px;
        }

        .qr-scanner {
            text-align: center;
            padding: 40px;
            background: #f8f9fa;
            border-radius: 10px;
        }

        .qr-placeholder {
            width: 100%;
            max-width: 400px;
            height: 300px;
            border: 4px solid #ffffff;
            border-radius: 20px;
            margin: 20px auto;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
            background: #000;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
        }

        .qr-placeholder.qr-detected {
            border-color: #28a745;
            box-shadow: 0 4px 20px rgba(40, 167, 69, 0.4);
        }

        .qr-placeholder.qr-error {
            border-color: #dc3545;
            box-shadow: 0 4px 20px rgba(220, 53, 69, 0.4);
        }

        #qr-reader {
            width: 100%;
            height: 100%;
        }

        #qr-reader video {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        #scanStatus {
            position: absolute;
            top: 10px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 8px 15px;
            border-radius: 5px;
            font-size: 14px;
            font-weight: 600;
            z-index: 10;
            transition: all 0.3s ease;
        }

        #scanStatus.analyzing {
            background: rgba(255, 193, 7, 0.9);
            color: #333;
        }

        #scanStatus.success {
            background: rgba(40, 167, 69, 0.9);
            color: white;
        }

        #scanStatus.error {
            background: rgba(220, 53, 69, 0.9);
            color: white;
        }

        .camera-controls {
            margin-top: 20px;
            display: flex;
            gap: 10px;
            justify-content: center;
            flex-wrap: wrap;
            align-items: center;
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
            transition: transform 0.3s;
        }

        .btn:hover {
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: #6c757d;
        }

        #cameraSelector {
            padding: 12px 20px;
            font-size: 16px;
            max-width: 300px;
            width: 100%;
            border: 2px solid #667eea;
            border-radius: 8px;
            background: white;
            color: #333;
            cursor: pointer;
            font-weight: 600;
        }

        #cameraSelector:focus {
            outline: none;
            border-color: #764ba2;
        }

        .link {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }

        .link:hover {
            text-decoration: underline;
        }

        .admin-links {
            text-align: center;
            margin-top: 30px;
            padding: 20px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
        }

        .admin-links p {
            color: white;
            margin-bottom: 10px;
            font-weight: 600;
        }

        .admin-links a {
            color: white;
            text-decoration: none;
            padding: 8px 15px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 5px;
            margin: 0 5px;
            transition: all 0.3s;
            display: inline-block;
            cursor: pointer;
        }

        .admin-links a:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            animation: fadeIn 0.3s;
        }

        .modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: white;
            border-radius: 15px;
            padding: 30px;
            max-width: 450px;
            width: 90%;
            box-shadow: 0 10px 50px rgba(0,0,0,0.3);
            animation: slideDown 0.3s;
            max-height: 90vh;
            overflow-y: auto;
            /* Classic Mac OS Modal Animation */
            animation: modalPopIn 0.5s cubic-bezier(.68,-0.55,.27,1.55);
        }
        .modal.hide-anim .modal-content {
            animation: modalPopOut 0.4s cubic-bezier(.68,-0.55,.27,1.55);
        }
        @keyframes modalPopIn {
            0% {
                opacity: 0;
                transform: scale(0.7) translateY(60px);
                filter: blur(8px);
            }
            60% {
                opacity: 1;
                transform: scale(1.05) translateY(-8px);
                filter: blur(0px);
            }
            80% {
                transform: scale(0.98) translateY(0px);
            }
            100% {
                opacity: 1;
                transform: scale(1) translateY(0px);
                filter: blur(0px);
            }
        }
        @keyframes modalPopOut {
            0% {
                opacity: 1;
                transform: scale(1) translateY(0px);
                filter: blur(0px);
            }
            60% {
                opacity: 0.7;
                transform: scale(0.7) translateY(60px);
                filter: blur(8px);
            }
            100% {
                opacity: 0;
                transform: scale(0.6) translateY(100px);
                filter: blur(12px);
            }
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .modal-header h2 {
            color: #333;
            font-size: 1.5em;
        }

        .close-modal {
            font-size: 28px;
            font-weight: bold;
            color: #999;
            cursor: pointer;
            background: none;
            border: none;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .close-modal:hover {
            color: #333;
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

        .alert {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-size: 14px;
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

        .modal-footer {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e1e1e1;
            text-align: center;
        }

        .text-link {
            color: #667eea;
            cursor: pointer;
            text-decoration: none;
        }

        .text-link:hover {
            text-decoration: underline;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideDown {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        /* Portal Animation Styles */
        #portalOverlay {
            position: fixed;
            top: 0; left: 0;
            width: 100vw; height: 100vh;
            z-index: 99999;
            display: none;
            align-items: center;
            justify-content: center;
            background: rgba(102,126,234,0.1);
            pointer-events: none;
        }
        .portal-circle {
            width: 180px;
            height: 180px;
            border-radius: 50%;
            background: radial-gradient(circle at 60% 40%, #fff 0%, #667eea 60%, #764ba2 100%);
            box-shadow: 0 0 60px 20px #764ba2, 0 0 120px 40px #667eea;
            position: relative;
            animation: portalSpin 2.2s cubic-bezier(.68,-0.55,.27,1.55) forwards;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .portal-icon {
            font-size: 60px;
            color: #fff;
            filter: drop-shadow(0 0 10px #764ba2);
            animation: portalIconFade 2.2s forwards;
        }
        @keyframes portalSpin {
            0% { transform: scale(0.2) rotate(0deg); opacity: 0.2; }
            60% { transform: scale(1.1) rotate(360deg); opacity: 1; }
            100% { transform: scale(1) rotate(720deg); opacity: 1; }
        }
        @keyframes portalIconFade {
            0% { opacity: 0; }
            60% { opacity: 1; }
            100% { opacity: 1; }
        }
        .portal-text {
            position: absolute;
            bottom: -40px;
            width: 100%;
            text-align: center;
            color: #fff;
            font-size: 1.3em;
            font-weight: bold;
            text-shadow: 0 2px 10px #764ba2;
            animation: portalTextFade 2.2s forwards;
        }
        @keyframes portalTextFade {
            0% { opacity: 0; }
            60% { opacity: 1; }
            100% { opacity: 1; }
        }
    </style>
</head>
<body>
    <!-- Boot Loader Animation -->
    <div id="bootLoader">
        <div class="boot-logo">
            <div class="spinner-ring"></div>
            <div class="spinner-ring"></div>
            <div class="spinner-ring"></div>
            <div class="boot-icon">📚</div>
        </div>
        <div class="boot-text">
            Library Hub
        </div>
        <div class="boot-subtitle">
            Loading<span class="loading-dots"></span>
        </div>
    </div>

    <!-- Main Content -->
    <div id="mainContent">
        <div class="container">
            <div class="header">
                <h1>📚 Library Hub Reading System</h1>
                <p>Smart Reading Engagement & Comprehension Tracking</p>
                <p><small>Tambo, Lipa City</small></p>
            </div>

            <div class="card">
                <h2>📱 Scan Book QR Code</h2>
                <div class="qr-scanner">
                    <h3>📷 QR Code Scanner</h3>
                    <p>Point your camera at the QR code on the book</p>

                    <div class="qr-placeholder" id="qrPlaceholder">
                        <div id="qr-reader"></div>
                        <div id="scanStatus">Click "Start Scanner" to begin</div>
                    </div>

                    <div class="camera-controls">
                        <button class="btn" id="startButton">📷 Start Scanner</button>
                        <button class="btn btn-secondary" id="stopButton" style="display: none;">⏹️ Stop Scanner</button>
                        <select id="cameraSelector" style="display: none;">
                            <option value="">Select Camera</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="admin-links">
                <p>Staff Access:</p>
                <a onclick="openStaffLogin('admin')">Admin Dashboard</a> |
                <a onclick="openStaffLogin('librarian')">Librarian Dashboard</a> |
                <a onclick="openStaffLogin('teacher')">Teacher Dashboard</a>
            </div>
        </div>

        <!-- Staff Login Modal -->
        <div id="staffLoginModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 id="modalTitle">Staff Login</h2>
                    <button class="close-modal" onclick="closeModal('staffLoginModal')">&times;</button>
                </div>
                <div id="modalMessage"></div>
                <form id="staffLoginForm" onsubmit="handleStaffLogin(event)">
                    <input type="hidden" id="staffType" name="staff_type">
                    <div class="form-group">
                        <label>Email:</label>
                        <input type="email" name="email" id="staffEmail" placeholder="Enter your email" required>
                    </div>
                    <div class="form-group">
                        <label>Password:</label>
                        <input type="password" name="password" id="staffPassword" placeholder="Enter your password" required>
                    </div>
                    <button type="submit" class="btn" style="width: 100%;">Login</button>
                </form>
                <div class="modal-footer">
                    <a href="#" class="forgot-password-link">Forgot Password?</a>
                </div>
            </div>
        </div>

        <!-- Forgot Password Modal -->
        <div id="forgotPasswordModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>🔒 Forgot Password</h2>
                    <button class="close-modal" onclick="closeForgotPasswordModal()">&times;</button>
                </div>
                <div id="forgotPasswordMessage"></div>
                <form id="forgotPasswordForm" onsubmit="handleForgotPassword(event)">
                    <input type="hidden" id="forgotStaffType" name="staff_type">
                    <h6 style="text-align: center; margin-bottom: 20px; color: #666;">Reset Your Password</h6>
                    <div class="form-group">
                        <label>Email Address:</label>
                        <input type="email" name="email" id="forgotPasswordEmail" placeholder="Enter your registered email" required>
                    </div>
                    <button type="submit" class="btn" style="width: 100%;">Send Reset Link</button>
                </form>
                <div class="modal-footer">
                    <a href="#" class="text-link" onclick="backToLogin(event)">← Back to Login</a>
                </div>
            </div>
        </div>

        <!-- Bootstrap 5 (if not already included) -->
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

        <script>
            // Boot Animation Script
            window.addEventListener('load', function() {
                const bootLoader = document.getElementById('bootLoader');
                const mainContent = document.getElementById('mainContent');
                
                // Minimum loading time for smooth animation (2 seconds)
                const minLoadingTime = 2000;
                const startTime = Date.now();
                
                function hideBootLoader() {
                    const elapsedTime = Date.now() - startTime;
                    const remainingTime = Math.max(0, minLoadingTime - elapsedTime);
                    
                    setTimeout(() => {
                        // Fade out boot loader
                        bootLoader.classList.add('fade-out');
                        
                        // Show main content
                        setTimeout(() => {
                            bootLoader.style.display = 'none';
                            mainContent.classList.add('show');
                        }, 800); // Wait for fade-out animation
                    }, remainingTime);
                }
                
                // Start hiding boot loader
                hideBootLoader();
            });

            // Prevent accidental navigation during boot
            let bootComplete = false;
            setTimeout(() => {
                bootComplete = true;
            }, 3000);

            function openStaffLogin(type) {
                const modal = document.getElementById('staffLoginModal');
                const title = document.getElementById('modalTitle');
                const staffType = document.getElementById('staffType');

                const titles = {
                    'admin': '👨‍💼 Admin Login',
                    'teacher': '👩‍🏫 Teacher Login',
                    'librarian': '📚 Librarian Login'
                };

                title.textContent = titles[type] || 'Staff Login';
                staffType.value = type;
                modal.classList.add('active');

                document.getElementById('staffLoginForm').reset();
                document.getElementById('staffEmail').value = '';
                document.getElementById('staffPassword').value = '';
                document.getElementById('modalMessage').innerHTML = '';
            }

            function openForgotPasswordModal() {
                const staffType = document.getElementById('staffType').value;
                
                closeModal('staffLoginModal');
                
                setTimeout(() => {
                    const forgotModal = document.getElementById('forgotPasswordModal');
                    document.getElementById('forgotStaffType').value = staffType;
                    document.getElementById('forgotPasswordEmail').value = '';
                    document.getElementById('forgotPasswordForm').reset();
                    document.getElementById('forgotPasswordMessage').innerHTML = '';
                    showModal('forgotPasswordModal');
                }, 300);
            }

            function backToLogin(event) {
                event.preventDefault();
                const staffType = document.getElementById('forgotStaffType').value;
                
                closeForgotPasswordModal();
                
                setTimeout(() => {
                    openStaffLogin(staffType);
                }, 300);
            }

            function closeForgotPasswordModal() {
                hideModal('forgotPasswordModal');
                document.getElementById('forgotPasswordEmail').value = '';
                document.getElementById('forgotPasswordForm').reset();
                document.getElementById('forgotPasswordMessage').innerHTML = '';
            }

            function closeModal(modalId) {
                hideModal(modalId);
            }

            window.onclick = function(event) {
                const staffModal = document.getElementById('staffLoginModal');
                const forgotModal = document.getElementById('forgotPasswordModal');
                if (event.target == staffModal) {
                    hideModal('staffLoginModal');
                }
                if (event.target == forgotModal) {
                    hideModal('forgotPasswordModal');
                }
            }

            async function handleStaffLogin(event) {
                event.preventDefault();

                const form = event.target;
                const formData = new FormData(form);

                try {
                    const response = await fetch('staff-login-handler.php', {
                        method: 'POST',
                        body: formData
                    });

                    const data = await response.json();

                    if (data.success) {
                        showModalMessage('modalMessage', 'Login successful! Redirecting...', 'success');
                        setTimeout(() => {
                            window.location.href = data.redirect;
                        }, 1000);
                    } else {
                        showModalMessage('modalMessage', data.message || 'Login failed. Please try again.', 'danger');
                    }
                } catch (error) {
                    console.error('Login error:', error);
                    showModalMessage('modalMessage', 'Login failed. Please try again.', 'danger');
                }
            }

            async function handleForgotPassword(event) {
                event.preventDefault();

                const form = event.target;
                const formData = new FormData(form);

                try {
                    const response = await fetch('forgot-password-handler.php', {
                        method: 'POST',
                        body: formData
                    });

                    const data = await response.json();

                    if (data.success) {
                        const messageHTML = `
                            <div class="alert alert-success">
                                A password reset link has been sent to your email. Please 
                                <a href="${data.reset_link}" target="_blank" style="text-decoration: underline; font-weight: 600;">click here to reset now</a>.
                            </div>
                        `;
                        document.getElementById('forgotPasswordMessage').innerHTML = messageHTML;
                        document.getElementById('forgotPasswordEmail').value = '';
                        form.reset();
                    } else {
                        showModalMessage('forgotPasswordMessage', data.message || 'Failed to send reset link. Please try again.', 'danger');
                    }
                } catch (error) {
                    console.error('Forgot password error:', error);
                    showModalMessage('forgotPasswordMessage', 'Failed to send reset link. Please try again.', 'danger');
                }
            }

            function showModalMessage(elementId, message, type) {
                const messageDiv = document.getElementById(elementId);
                messageDiv.innerHTML = `<div class="alert alert-${type}">${message}</div>`;
            }

            // Auto-open login modal if redirected from password reset
            document.addEventListener('DOMContentLoaded', () => {
                const urlParams = new URLSearchParams(window.location.search);
                const loginModal = urlParams.get('login_modal');
                
                if (loginModal && ['admin', 'teacher', 'librarian'].includes(loginModal)) {
                    openStaffLogin(loginModal);
                }

                document.querySelectorAll('.forgot-password-link').forEach(link => {
                    link.addEventListener('click', function(e) {
                        e.preventDefault();
                        openForgotPasswordModal();
                    });
                });
            });

            const VALID_QR_CODES = ['QR001', 'QR002', 'QR003', 'QR004', 'QR005', 'QR006', 'QR007', 'QR008', 'QR009', 'QR010'];

            const statusDiv = document.getElementById('scanStatus');
            const qrPlaceholder = document.getElementById('qrPlaceholder');
            const startButton = document.getElementById('startButton');
            const stopButton = document.getElementById('stopButton');
            const cameraSelector = document.getElementById('cameraSelector');

            let html5QrcodeScanner = null;
            let isProcessing = false;
            let availableCameras = [];
            let currentCameraId = null;

            async function loadAvailableCameras() {
                try {
                    const devices = await Html5Qrcode.getCameras();
                    availableCameras = devices;

                    if (devices && devices.length > 0) {
                        cameraSelector.innerHTML = '<option value="">Select Camera</option>';
                        devices.forEach((device, index) => {
                            const option = document.createElement('option');
                            option.value = device.id;
                            option.text = device.label || `Camera ${index + 1}`;
                            cameraSelector.appendChild(option);
                        });

                        if (devices.length > 1) {
                            cameraSelector.style.display = 'inline-block';
                        }
                    }
                } catch (err) {
                    console.error('Error loading cameras:', err);
                }
            }

            async function startScanner(cameraId = null) {
                try {
                    if (html5QrcodeScanner) {
                        await html5QrcodeScanner.stop();
                        html5QrcodeScanner.clear();
                        html5QrcodeScanner = null;
                        await new Promise(resolve => setTimeout(resolve, 300));
                    }

                    html5QrcodeScanner = new Html5Qrcode("qr-reader");

                    const config = {
                        fps: 10,
                        qrbox: { width: 250, height: 250 },
                        aspectRatio: 1.0,
                        disableFlip: false
                    };

                    const cameraConfig = cameraId ? { deviceId: { exact: cameraId } } : { facingMode: "environment" };

                    await html5QrcodeScanner.start(
                        cameraConfig,
                        config,
                        onScanSuccess,
                        onScanError
                    );

                    currentCameraId = cameraId;
                    startButton.style.display = 'none';
                    stopButton.style.display = 'inline-block';

                    statusDiv.textContent = '📷 Scanner active';
                    statusDiv.className = '';
                    qrPlaceholder.className = 'qr-placeholder';
                    isProcessing = false;

                } catch (err) {
                    console.error('Camera error:', err);
                    statusDiv.textContent = '❌ Camera access denied';
                    statusDiv.className = 'error';
                    qrPlaceholder.className = 'qr-placeholder qr-error';
                    alert('Unable to access camera. Please allow camera permissions and try again.');

                    html5QrcodeScanner = null;
                    startButton.style.display = 'inline-block';
                    stopButton.style.display = 'none';
                }
            }

            async function stopScanner() {
                try {
                    if (html5QrcodeScanner) {
                        await html5QrcodeScanner.stop();
                        html5QrcodeScanner.clear();
                        html5QrcodeScanner = null;
                    }

                    startButton.style.display = 'inline-block';
                    stopButton.style.display = 'none';
                    cameraSelector.value = '';
                    statusDiv.textContent = 'Scanner stopped';
                    statusDiv.className = '';
                    qrPlaceholder.className = 'qr-placeholder';
                    isProcessing = false;
                    currentCameraId = null;
                } catch (err) {
                    console.error('Error stopping scanner:', err);
                }
            }

            startButton.addEventListener('click', async () => {
                await loadAvailableCameras();
                await startScanner();
            });

            stopButton.addEventListener('click', async () => {
                await stopScanner();
            });

            cameraSelector.addEventListener('change', async (e) => {
                const selectedCamera = e.target.value;
                if (selectedCamera && html5QrcodeScanner) {
                    isProcessing = false;
                    await startScanner(selectedCamera);
                }
            });

            function onScanSuccess(decodedText, decodedResult) {
                if (isProcessing) return;
                isProcessing = true;

                const qrCode = decodedText.trim();

                console.log('[Scanner] QR Code Detected:', qrCode);

                statusDiv.textContent = '⏳ Verifying book...';
                statusDiv.className = 'analyzing';
                qrPlaceholder.className = 'qr-placeholder qr-detected';

                // Fetch book from database
                fetch(`get-book-by-qr.php?qr_code=${encodeURIComponent(qrCode)}`)
                    .then(response => response.json())
                    .then(data => {
                        console.log('[Scanner] Database response:', data);
                        
                        if (data.success && data.book) {
                            const bookInfo = data.book;
                            
                            console.log('[Scanner] Book Found:', bookInfo.title);

                            statusDiv.textContent = '✅ Book recognized! Redirecting...';
                            statusDiv.className = 'success';

                            // Save book to session
                            return fetch('save-scanned-book.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json'
                            },
                            body: JSON.stringify(bookInfo)
                            });
                        } else {
                            throw new Error(data.message || 'Book not found in database');
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        console.log('[Scanner] Save response:', data);
                        if (data.success) {
                            // Show portal animation and redirect
                            showPortalAndRedirect('login.php');
                        } else {
                            throw new Error(data.message || 'Failed to save book');
                        }
                    })
                    .catch(error => {
                        console.error('[Scanner] Error:', error);
                        statusDiv.textContent = '❌ Book not found!';
                        statusDiv.className = 'error';
                        qrPlaceholder.className = 'qr-placeholder qr-error';

                        alert(`❌ ERROR: Book Not Found!\n\nScanned QR Code: "${qrCode}"\n\n${error.message}\n\nPlease ensure:\n• The book exists in the system\n• The QR code matches exactly\n• The book is marked as available`);

                        setTimeout(() => {
                            statusDiv.textContent = '📷 Scanner active';
                            statusDiv.className = '';
                            qrPlaceholder.className = 'qr-placeholder';
                            isProcessing = false;
                        }, 3000);
                    });
            }

            function onScanError(error) {
                if (error && !error.toString().includes('No QR code found')) {
                    console.log('[Scanner] Scan error:', error);
                }
            }

            // Portal Animation Function
            function showPortalAndRedirect(url) {
                const portal = document.getElementById('portalOverlay');
                portal.style.display = 'flex';
                setTimeout(() => {
                    window.location.href = url;
                }, 2200); // Match animation duration (2.2s)
            }

            // Utility to show modal with animation
            function showModal(modalId) {
                const modal = document.getElementById(modalId);
                if (!modal) return;
                modal.classList.remove('hide-anim');
                modal.classList.add('active');
            }

            // Utility to hide modal with animation
            function hideModal(modalId) {
                const modal = document.getElementById(modalId);
                if (!modal) return;
                modal.classList.add('hide-anim');
                setTimeout(() => {
                    modal.classList.remove('active');
                    modal.classList.remove('hide-anim');
                }, 400); // Match modalPopOut duration
            }
        </script>

    <!-- Portal Animation Overlay -->
    <div id="portalOverlay">
        <div class="portal-circle">
            <span class="portal-icon">📖</span>
            <div class="portal-text">Entering Library Portal...</div>
        </div>
    </div>
</body>
</html>
