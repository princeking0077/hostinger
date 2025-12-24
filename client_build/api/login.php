<?php
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

$data = json_decode(file_get_contents('php://input')) ?: (object) [];

$username = '';
$password = '';

if (isset($data->email)) {
    $username = trim($data->email);
}
if (isset($data->username)) {
    // Allow either field, prefer explicit username if provided
    $username = trim($data->username);
}
if (isset($data->password)) {
    $password = (string) $data->password;
}

// Simple brute-force protection
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$lockMinutes = 15;
$maxAttempts = 5;

function is_locked($conn, $username, $ip, $maxAttempts, $lockMinutes) {
    $stmt = $conn->prepare("SELECT attempts, TIMESTAMPDIFF(MINUTE, last_attempt, NOW()) AS mins FROM login_attempts WHERE (username = :u OR (username IS NULL AND ip = :ip)) ORDER BY last_attempt DESC LIMIT 1");
    $u = $username !== '' ? $username : null;
    $stmt->execute([':u' => $u, ':ip' => $ip]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return false;
    if ((int)$row['attempts'] >= $maxAttempts && (int)$row['mins'] < $lockMinutes) {
        return true;
    }
    return false;
}

function record_attempt($conn, $username, $ip, $success) {
    if ($success) {
        // Reset on success
        $stmt = $conn->prepare("DELETE FROM login_attempts WHERE (username = :u OR (username IS NULL AND ip = :ip))");
        $u = $username !== '' ? $username : null;
        $stmt->execute([':u' => $u, ':ip' => $ip]);
        return;
    }
    // Increment or insert
    $u = $username !== '' ? $username : null;
    $stmt = $conn->prepare("SELECT id, attempts FROM login_attempts WHERE (username = :u OR (username IS NULL AND ip = :ip)) ORDER BY last_attempt DESC LIMIT 1");
    $stmt->execute([':u' => $u, ':ip' => $ip]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $stmt = $conn->prepare("UPDATE login_attempts SET attempts = attempts + 1, last_attempt = NOW() WHERE id = :id");
        $stmt->execute([':id' => $row['id']]);
    } else {
        $stmt = $conn->prepare("INSERT INTO login_attempts (username, ip, attempts, last_attempt) VALUES (:u, :ip, 1, NOW())");
        $stmt->execute([':u' => $u, ':ip' => $ip]);
    }
}

if ($username !== '' && $password !== '') {
    try {
        if (is_locked($conn, $username, $ip, $maxAttempts, $lockMinutes)) {
            http_response_code(429);
            echo json_encode(['message' => 'Too many attempts. Try again later.']);
            exit;
        }

        // Constant small delay to reduce brute force speed
        usleep(300000); // 300ms

        $query = 'SELECT id, username, password_hash FROM admins WHERE username = :username LIMIT 1';
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && password_verify($password, $row['password_hash'])) {
            record_attempt($conn, $username, $ip, true);
            $token = jwt_create(['sub' => $row['id'], 'username' => $row['username']]);
            echo json_encode([
                'message' => 'Login successful',
                'token'   => $token,
                'user'    => $row['username']
            ]);
            exit;
        }

        record_attempt($conn, $username, $ip, false);
        http_response_code(401);
        echo json_encode(['message' => 'Invalid username or password.']);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['message' => 'Server error', 'error' => $e->getMessage()]);
    }
} else {
    http_response_code(400);
    echo json_encode(['message' => 'Email/username and password are required.']);
}
?>
