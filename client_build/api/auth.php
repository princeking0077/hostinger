<?php
// Lightweight JWT HS256 implementation and helpers + security headers

if (!defined('JWT_SECRET')) {
    // Fallback for older configs; should be defined in config.php
    define('JWT_SECRET', 'change_this_default_secret');
}

// Send baseline security headers for API responses
if (!headers_sent()) {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer-when-downgrade');
    header("Permissions-Policy: camera=(), microphone=(), geolocation=()");
    // Relaxed CSP to avoid breaking existing inline scripts/styles; adjust as needed
    header("Content-Security-Policy: default-src 'self' https: data: blob: 'unsafe-inline' 'unsafe-eval'; img-src 'self' https: data: blob:; media-src 'self' https: data: blob:");
}

function base64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64url_decode($data) {
    $remainder = strlen($data) % 4;
    if ($remainder) {
        $data .= str_repeat('=', 4 - $remainder);
    }
    return base64_decode(strtr($data, '-_', '+/'));
}

function jwt_create(array $payload, $ttlSeconds = 604800) { // 7 days default
    $header = ['alg' => 'HS256', 'typ' => 'JWT'];
    $now = time();
    $payload = array_merge(['iat' => $now, 'exp' => $now + $ttlSeconds], $payload);

    $segments = [];
    $segments[] = base64url_encode(json_encode($header));
    $segments[] = base64url_encode(json_encode($payload));
    $signingInput = implode('.', $segments);
    $signature = hash_hmac('sha256', $signingInput, JWT_SECRET, true);
    $segments[] = base64url_encode($signature);

    return implode('.', $segments);
}

function jwt_verify($jwt) {
    if (!$jwt || substr_count($jwt, '.') !== 2) return false;
    list($h, $p, $s) = explode('.', $jwt);
    $signingInput = $h . '.' . $p;
    $expected = base64url_encode(hash_hmac('sha256', $signingInput, JWT_SECRET, true));
    if (!hash_equals($expected, $s)) return false;

    $payload = json_decode(base64url_decode($p), true);
    if (!is_array($payload)) return false;
    if (isset($payload['exp']) && time() >= (int)$payload['exp']) return false;
    return $payload;
}

function get_bearer_token() {
    $headers = [];
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
    } else {
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $key = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
                $headers[$key] = $value;
            }
        }
    }
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    if (stripos($authHeader, 'Bearer ') === 0) {
        return trim(substr($authHeader, 7));
    }
    return null;
}

function require_auth() {
    $token = get_bearer_token();
    $payload = jwt_verify($token);
    if ($payload === false) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['message' => 'Unauthorized']);
        exit;
    }
    return $payload;
}
