<?php
/**
 * =====================================================
 * SECURITY UTILITIES
 * Additional security helper functions
 * =====================================================
 */

// Prevent direct access
if (!defined('DB_HOST')) {
    die('Direct access not permitted');
}

// =====================================================
// SQL INJECTION ADVANCED PROTECTION
// =====================================================
class SecureQuery {
    private $conn;
    
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    public function select($table, $columns = '*', $where = [], $order = null, $limit = null) {
        $sql = "SELECT $columns FROM $table";
        
        if (!empty($where)) {
            $conditions = [];
            foreach ($where as $key => $value) {
                $conditions[] = "$key = ?";
            }
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }
        
        if ($order) $sql .= " ORDER BY $order";
        if ($limit) $sql .= " LIMIT $limit";
        
        $stmt = $this->conn->prepare($sql);
        
        if (!empty($where)) {
            $types = str_repeat('s', count($where));
            $stmt->bind_param($types, ...array_values($where));
        }
        
        $stmt->execute();
        return $stmt->get_result();
    }
}

// =====================================================
// SECURITY HEADERS
// =====================================================
function set_security_headers() {
    // Prevent clickjacking
    header('X-Frame-Options: DENY');
    
    // XSS Protection
    header('X-XSS-Protection: 1; mode=block');
    
    // Prevent MIME sniffing
    header('X-Content-Type-Options: nosniff');
    
    // Referrer Policy
    header('Referrer-Policy: strict-origin-when-cross-origin');
    
    // Content Security Policy
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://unpkg.com https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; img-src 'self' data:;");
    
    // HTTPS Strict Transport Security (if using HTTPS)
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

// Apply security headers
set_security_headers();

// =====================================================
// IP WHITELIST/BLACKLIST
// =====================================================
function is_ip_blocked($ip) {
    global $conn;
    
    $sql = "SELECT COUNT(*) as count FROM ip_blacklist WHERE ip_address = ? AND is_active = TRUE";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $ip);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    return $row['count'] > 0;
}

// =====================================================
// TWO-FACTOR AUTHENTICATION HELPERS
// =====================================================
function generate_2fa_code() {
    return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

function verify_2fa_code($user_id, $code) {
    global $conn;
    
    $sql = "SELECT code, expires_at FROM two_factor_codes 
            WHERE user_id = ? AND code = ? AND used = FALSE 
            AND expires_at > NOW() LIMIT 1";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $user_id, $code);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        // Mark as used
        $update = $conn->prepare("UPDATE two_factor_codes SET used = TRUE WHERE user_id = ? AND code = ?");
        $update->bind_param("is", $user_id, $code);
        $update->execute();
        $update->close();
        
        $stmt->close();
        return true;
    }
    
    $stmt->close();
    return false;
}
?>
