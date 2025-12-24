<?php
include 'config.php';
require_once __DIR__ . '/auth.php';
require_auth();

// Ensure uploads directory exists
$uploadDir = realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;
if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0777, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $file = $_FILES['file'];

    if (!isset($file['error']) || is_array($file['error'])) {
        http_response_code(400);
        echo json_encode(["message" => "Invalid upload parameters."]);
        exit;
    }

    // Handle PHP upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(["message" => "Upload error code: " . $file['error']]);
        exit;
    }

    // Limit size to ~15MB by default (adjust as needed)
    $maxBytes = 15 * 1024 * 1024;
    if ($file['size'] > $maxBytes) {
        http_response_code(413);
        echo json_encode(["message" => "File too large (max 15MB)."]);
        exit;
    }

    // Validate MIME type and extension mapping (safe types only)
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $allowed = [
        'pdf'  => 'application/pdf',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        // Allow code assets for topics
        'html' => 'text/html',
        'css'  => 'text/css',
        // JS can report different MIME types depending on server/os
        'js'   => 'application/javascript'
    ];

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    // Validate by extension first, then allow common alternate MIME types
    if (!isset($allowed[$ext])) {
        http_response_code(400);
        echo json_encode(["message" => "Invalid or unsupported file type."]);
        exit;
    }

    $expectedMime = $allowed[$ext];
    $mimeOk = ($mime === $expectedMime);
    // Some environments report JS as text/javascript
    if (!$mimeOk && $ext === 'js' && ($mime === 'text/javascript')) {
        $mimeOk = true;
    }
    // Some environments report HTML as application/octet-stream when zipped/uploaded via certain clients
    if (!$mimeOk && $ext === 'html' && ($mime === 'application/octet-stream')) {
        $mimeOk = true;
    }
    if (!$mimeOk) {
        http_response_code(400);
        echo json_encode(["message" => "Invalid or unexpected MIME type: $mime"]);
        exit;
    }

    // Generate a safe random filename preserving extension
    $safeName = bin2hex(random_bytes(8)) . '.' . $ext;
    $targetPath = $uploadDir . $safeName;

    if (!is_uploaded_file($file['tmp_name'])) {
        http_response_code(400);
        echo json_encode(["message" => "Invalid upload source."]);
        exit;
    }

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        // Public URL resolves from /uploads/
        $publicUrl = '/uploads/' . $safeName;
        echo json_encode(["message" => "Upload successful", "url" => $publicUrl]);
    } else {
        http_response_code(500);
        echo json_encode(["message" => "Failed to move uploaded file."]);
    }
} else {
    http_response_code(400);
    echo json_encode(["message" => "No file uploaded."]);
}
?>
