<?php
// Start output buffering BEFORE any output
ob_start();

require_once 'config.php';

// Only accessible to admins
check_user_type(['admin']);

$test_results = [];

// Test 1: CSRF Token Generation
$csrf_token = generate_csrf_token();
$test_results['csrf'] = [
    'name' => 'CSRF Token Generation',
    'status' => !empty($csrf_token) ? 'PASS' : 'FAIL',
    'details' => "Token: " . substr($csrf_token, 0, 20) . "...",
    'description' => 'Generates unique tokens to prevent Cross-Site Request Forgery attacks'
];

// Test 2: Password Hashing
$test_password = 'TestPass123!';
$hashed = hash_password($test_password);
$verified = verify_password($test_password, $hashed);
$test_results['password'] = [
    'name' => 'Password Hashing & Verification',
    'status' => $verified ? 'PASS' : 'FAIL',
    'details' => "Bcrypt hash length: " . strlen($hashed),
    'description' => 'Uses bcrypt to securely hash passwords before storing'
];

// Test 3: SQL Injection Protection
$malicious_input = "'; DROP TABLE users; --";
$sanitized = sanitize_input($malicious_input);
$test_results['sql_injection'] = [
    'name' => 'SQL Injection Protection',
    'status' => $sanitized !== $malicious_input ? 'PASS' : 'FAIL',
    'details' => "Malicious input blocked: " . htmlspecialchars($sanitized),
    'description' => 'Uses prepared statements and input sanitization to prevent SQL injection'
];

// Test 4: XSS Protection
$xss_attack = "<script>alert('XSS')</script>";
$escaped = escape_output($xss_attack);
$test_results['xss'] = [
    'name' => 'XSS (Cross-Site Scripting) Protection',
    'status' => strpos($escaped, '<script>') === false ? 'PASS' : 'FAIL',
    'details' => "Output: " . $escaped,
    'description' => 'Escapes HTML entities to prevent malicious script injection'
];

// Test 5: Encryption/Decryption
$original_data = "Sensitive Information 123";
$encrypted = encrypt_data($original_data);
$decrypted = decrypt_data($encrypted);
$test_results['encryption'] = [
    'name' => 'AES-256 Data Encryption',
    'status' => $decrypted === $original_data ? 'PASS' : 'FAIL',
    'details' => "Encrypted length: " . strlen($encrypted),
    'description' => 'Encrypts sensitive data using AES-256-CBC algorithm'
];

// Test 6: Session Security
$test_results['session'] = [
    'name' => 'Session Hijacking Protection',
    'status' => isset($_SESSION['user_ip']) && isset($_SESSION['user_agent']) ? 'PASS' : 'FAIL',
    'details' => "IP: " . ($_SESSION['user_ip'] ?? 'Not set') . " | User-Agent tracked",
    'description' => 'Validates IP address and User-Agent to prevent session hijacking'
];

// Test 7: Rate Limiting Check
$test_identifier = 'test_user_' . time();
$rate_limit_ok = check_rate_limit($test_identifier, 5, 300);
$test_results['rate_limit'] = [
    'name' => 'Brute-Force Protection (Rate Limiting)',
    'status' => $rate_limit_ok ? 'PASS' : 'FAIL',
    'details' => "Limit: 5 attempts per 5 minutes",
    'description' => 'Blocks excessive login attempts to prevent brute-force attacks'
];

// Test 8: Password Policy
$weak_password = 'test';
$strong_password = 'StrongP@ss123!';
$weak_result = validate_password($weak_password);
$strong_result = validate_password($strong_password);
$test_results['password_policy'] = [
    'name' => 'Password Strength Validation',
    'status' => !$weak_result['valid'] && $strong_result['valid'] ? 'PASS' : 'FAIL',
    'details' => "Requires: 8+ chars, uppercase, lowercase, number, special char",
    'description' => 'Enforces strong password requirements'
];

// Test 9: Email Validation
$invalid_email = 'notanemail';
$valid_email = 'test@example.com';
$test_results['email_validation'] = [
    'name' => 'Email Format Validation',
    'status' => !validate_email($invalid_email) && validate_email($valid_email) ? 'PASS' : 'FAIL',
    'details' => "Invalid rejected, Valid accepted",
    'description' => 'Validates email format using PHP filter_var'
];

// Test 10: Database Connection Security
$test_results['db_security'] = [
    'name' => 'Database Connection Security',
    'status' => $conn->character_set_name() === 'utf8' ? 'PASS' : 'FAIL',
    'details' => "Charset: " . $conn->character_set_name(),
    'description' => 'Uses UTF-8 encoding to prevent encoding-based attacks'
];

// Test 11: Secure Cookie Management
set_secure_cookie('test_cookie', 'test_value', 1);
$cookie_retrieved = get_secure_cookie('test_cookie');
$test_results['secure_cookies'] = [
    'name' => 'Secure Cookie Management',
    'status' => $cookie_retrieved === 'test_value' ? 'PASS' : 'FAIL',
    'details' => "Encrypted cookie storage with HttpOnly flag",
    'description' => 'Encrypts cookies and sets secure flags (HttpOnly, SameSite)'
];

// Count results
$total_tests = count($test_results);
$passed_tests = count(array_filter($test_results, function($test) {
    return $test['status'] === 'PASS';
}));
$pass_percentage = round(($passed_tests / $total_tests) * 100);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Test Dashboard - Library Hub</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            min-height: 100vh;
        }
        .container { max-width: 1200px; margin: 0 auto; }
        .header {
            text-align: center;
            color: white;
            margin-bottom: 30px;
        }
        .header h1 { font-size: 2.5em; margin-bottom: 10px; }
        .card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            margin-bottom: 20px;
        }
        .summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .summary-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }
        .summary-number {
            font-size: 3em;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .summary-number.pass { color: #28a745; }
        .summary-number.fail { color: #dc3545; }
        .summary-number.percent { color: #667eea; }
        .test-result {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 15px;
            border-left: 5px solid #ddd;
        }
        .test-result.pass { border-left-color: #28a745; }
        .test-result.fail { border-left-color: #dc3545; }
        .test-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .test-name {
            font-size: 1.1em;
            font-weight: 600;
            color: #333;
        }
        .test-status {
            padding: 5px 15px;
            border-radius: 5px;
            font-weight: 600;
            font-size: 0.9em;
        }
        .test-status.pass {
            background: #d4edda;
            color: #155724;
        }
        .test-status.fail {
            background: #f8d7da;
            color: #721c24;
        }
        .test-details {
            margin-top: 10px;
            padding: 10px;
            background: white;
            border-radius: 5px;
            font-size: 0.9em;
            color: #666;
        }
        .test-description {
            margin-top: 5px;
            font-style: italic;
            color: #999;
            font-size: 0.85em;
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
            text-decoration: none;
            display: inline-block;
            transition: transform 0.3s;
        }
        .btn:hover { transform: translateY(-2px); }
        .back-link {
            text-align: center;
            margin-top: 20px;
        }
        @media print {
            body { background: white; }
            .btn, .back-link { display: none; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔒 Security Test Suite</h1>
            <p>Library Hub of Tambo, Lipa City - Security Validation</p>
        </div>

        <div class="card">
            <h2>📊 Test Summary</h2>
            <div class="summary">
                <div class="summary-card">
                    <div class="summary-number pass"><?php echo $passed_tests; ?></div>
                    <p>Tests Passed</p>
                </div>
                <div class="summary-card">
                    <div class="summary-number fail"><?php echo $total_tests - $passed_tests; ?></div>
                    <p>Tests Failed</p>
                </div>
                <div class="summary-card">
                    <div class="summary-number percent"><?php echo $pass_percentage; ?>%</div>
                    <p>Pass Rate</p>
                </div>
                <div class="summary-card">
                    <div class="summary-number"><?php echo $total_tests; ?></div>
                    <p>Total Tests</p>
                </div>
            </div>

            <h2>🔍 Detailed Test Results</h2>
            <?php foreach ($test_results as $key => $test): ?>
                <div class="test-result <?php echo strtolower($test['status']); ?>">
                    <div class="test-header">
                        <div class="test-name"><?php echo $test['name']; ?></div>
                        <div class="test-status <?php echo strtolower($test['status']); ?>">
                            <?php echo $test['status'] === 'PASS' ? '✅ PASS' : '❌ FAIL'; ?>
                        </div>
                    </div>
                    <div class="test-details">
                        <strong>Details:</strong> <?php echo $test['details']; ?>
                    </div>
                    <div class="test-description">
                        <?php echo $test['description']; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <div class="back-link">
                <a href="admin.php" class="btn">← Back to Admin Dashboard</a>
                <button onclick="window.print()" class="btn" style="margin-left: 10px;">🖨️ Print Report</button>
            </div>
        </div>
    </div>
</body>
</html>
<?php
// Flush output buffer
ob_end_flush();
?>