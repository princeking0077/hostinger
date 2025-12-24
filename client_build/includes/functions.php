<?php
// Common Functions for Installer

function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)));
}

function generate_salt($length = 32) {
    return bin2hex(random_bytes($length));
}

function check_requirements() {
    $apiDir = __DIR__ . '/../api';
    $uploadsDir = __DIR__ . '/../uploads';
    // Pre-create uploads dir to avoid later failure
    if (!is_dir($uploadsDir)) @mkdir($uploadsDir, 0777, true);

    return [
        'php_version' => version_compare(PHP_VERSION, '7.4.0', '>='),
        'extensions' => [
            'pdo_mysql' => extension_loaded('pdo_mysql'),
            'json' => extension_loaded('json'),
            'mbstring' => extension_loaded('mbstring'),
            'curl' => extension_loaded('curl')
        ],
        'writable' => [
            'api' => is_dir($apiDir) && is_writable($apiDir),
            'uploads' => is_dir($uploadsDir) && is_writable($uploadsDir)
        ]
    ];
}

function redirect($step) {
    header("Location: install.php?step=$step");
    exit;
}
?>
