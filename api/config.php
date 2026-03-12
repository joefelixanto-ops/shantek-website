<?php
// ═══════════════════════════════════════════════════
//  config.php — Database Configuration
//  Upload to: public_html/shantek/api/config.php
// ═══════════════════════════════════════════════════

// ── DATABASE SETTINGS ──
// For XAMPP local:  host=localhost, user=root, pass=''
// For GoDaddy:      get these from cPanel → MySQL Databases
define('DB_HOST', 'localhost');
define('DB_NAME', 'shantek_db');
define('DB_USER', 'root');        // GoDaddy: your cPanel username_dbuser
define('DB_PASS', '');            // GoDaddy: your database password
define('DB_CHARSET', 'utf8mb4');

// ── ADMIN PASSWORD ──
define('ADMIN_PASSWORD', 'shantek123');  // Change this!

// ── SECURITY ──
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_MINUTES', 15);

// ── IMAGES ──
define('IMAGES_PATH', __DIR__ . '/../images/');
define('IMAGES_URL',  'images/');
define('MAX_IMAGE_MB', 5);

// ── DATABASE CONNECTION ──
function getDB() {
    static $pdo = null;
    if ($pdo) return $pdo;
    try {
        $dsn = "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=".DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        return $pdo;
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success'=>false,'message'=>'Database connection failed: '.$e->getMessage()]);
        exit;
    }
}

// ── CORS & HEADERS ──
function setHeaders() {
    header('Content-Type: application/json');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    $allowed = ['https://genzdgltech.com','http://genzdgltech.com','http://localhost','http://127.0.0.1'];
    $origin  = $_SERVER['HTTP_ORIGIN'] ?? '';
    if (in_array($origin, $allowed) || empty($origin)) {
        if (!empty($origin)) header("Access-Control-Allow-Origin: $origin");
    }
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
}

// ── RATE LIMITER ──
function checkRateLimit($ip) {
    $db  = getDB();
    $ago = date('Y-m-d H:i:s', strtotime('-'.LOCKOUT_MINUTES.' minutes'));
    // Clean old attempts
    $db->prepare("DELETE FROM login_attempts WHERE attempted_at < ?")->execute([$ago]);
    // Count recent
    $stmt = $db->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND attempted_at > ?");
    $stmt->execute([$ip, $ago]);
    return $stmt->fetchColumn() < MAX_LOGIN_ATTEMPTS;
}
function recordLoginAttempt($ip) {
    getDB()->prepare("INSERT INTO login_attempts (ip_address) VALUES (?)")->execute([$ip]);
}
function clearLoginAttempts($ip) {
    getDB()->prepare("DELETE FROM login_attempts WHERE ip_address = ?")->execute([$ip]);
}

// ── AUTH CHECK ──
function verifyPassword($password) {
    return hash_equals(hash('sha256', ADMIN_PASSWORD), hash('sha256', $password));
}

// ── JSON RESPONSE ──
function respond($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}
function error($msg, $code = 400) {
    respond(['success' => false, 'message' => $msg], $code);
}
function success($data = [], $msg = 'OK') {
    respond(array_merge(['success' => true, 'message' => $msg], $data));
}
?>
