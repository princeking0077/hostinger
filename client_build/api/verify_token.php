<?php
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

$token = get_bearer_token();
$payload = jwt_verify($token);

if ($payload === false) {
    http_response_code(401);
    echo json_encode(['valid' => false]);
    exit;
}

echo json_encode(['valid' => true, 'user' => $payload['username'] ?? null]);
