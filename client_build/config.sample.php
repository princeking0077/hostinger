<?php
// Sample Configuration File
// Note: The app loads config from `api/config.php`.
// You can keep this as a reference or copy values into `api/config.php`.

define('DB_HOST', 'localhost');
define('DB_NAME', 'your_database_name');
define('DB_USER', 'your_database_user');
define('DB_PASS', 'your_database_password');

// JWT Secret (if used)
define('JWT_SECRET', 'change_this_to_a_random_string');

try {
    $conn = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->exec("set names utf8mb4");
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>
