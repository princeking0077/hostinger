<?php
// Aggressive Error Reporting for Debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/functions.php';
require_once 'includes/database.php';

// Prevent reinstall if config exists
$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;

// Allow access to Step 5 (Success) even if config exists
if (file_exists('api/config.php') && $step != 5) {
    die("Application is already installed. Default config found in api/config.php. Please delete it to reinstall.");
}
$error = '';
$success = '';

// Handling Form Submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'step2': // DB Config
                $_SESSION['db_host'] = sanitize($_POST['db_host']);
                $_SESSION['db_user'] = sanitize($_POST['db_user']);
                $_SESSION['db_pass'] = $_POST['db_pass'];
                $_SESSION['db_name'] = sanitize($_POST['db_name']);

                $db = new Database();
                if ($db->connect($_SESSION['db_host'], $_SESSION['db_user'], $_SESSION['db_pass'], $_SESSION['db_name'])) {
                    redirect(3);
                } else {
                    $error = "Connection failed: " . $db->getError();
                }
                break;

            case 'step3': // Run SQL
                $db = new Database();
                if ($db->connect($_SESSION['db_host'], $_SESSION['db_user'], $_SESSION['db_pass'], $_SESSION['db_name'])) {
                    $conn = $db->getConnection();
                    
                    // Comprehensive Schema
                    $sql = "
                    CREATE TABLE IF NOT EXISTS admins (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        username VARCHAR(100) NOT NULL UNIQUE,
                        password_hash VARCHAR(255) NOT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    );

                    CREATE TABLE IF NOT EXISTS curriculum (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        type ENUM('year', 'semester', 'subject') NOT NULL,
                        title VARCHAR(100) NOT NULL,
                        parent_id INT DEFAULT NULL,
                        slug VARCHAR(100) NOT NULL,
                        ordering INT DEFAULT 0
                    );

                    CREATE TABLE IF NOT EXISTS content (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        subject_id VARCHAR(50) NOT NULL,
                        title VARCHAR(200) NOT NULL,
                        type ENUM('animation', 'note', 'video') NOT NULL,
                        file_url VARCHAR(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT '',
                        description TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
                        blog_content LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
                        youtube_id VARCHAR(50),
                        quiz_data JSON, 
                        slug VARCHAR(200) NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        INDEX idx_slug (slug),
                        INDEX idx_subject (subject_id)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                    
                    CREATE TABLE IF NOT EXISTS site_settings (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        setting_key VARCHAR(100) NOT NULL UNIQUE,
                        setting_value LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                    );

                    CREATE TABLE IF NOT EXISTS login_attempts (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        username VARCHAR(100) DEFAULT NULL,
                        ip VARCHAR(45) NOT NULL,
                        attempts INT NOT NULL DEFAULT 0,
                        last_attempt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        INDEX idx_user (username),
                        INDEX idx_ip (ip)
                    );
                    ";

                    try {
                        // 1. Create Tables
                        $conn->exec($sql);
                        
                        // 2. Insert Default Settings
                        $default_settings = [
                            'google_analytics_id'   => '',
                            'google_search_console' => '',
                            'google_adsense_code'   => '',
                            'ads_txt'               => '',
                            'ad_rpm_head'           => '',
                            'ad_rpm_body'           => ''
                        ];
                        $stmt_set = $conn->prepare("INSERT IGNORE INTO site_settings (setting_key, setting_value) VALUES (?, ?)");
                        foreach ($default_settings as $key => $val) {
                            $stmt_set->execute([$key, $val]);
                        }

                        // 3. Insert Demo Content (So Search works immediately)
                        $demo_title = "Introduction to Pharmacy";
                        $demo_slug = "intro-to-pharmacy";
                        $stmt_check = $conn->prepare("SELECT COUNT(*) FROM content WHERE slug = ?");
                        $stmt_check->execute([$demo_slug]);
                        if ($stmt_check->fetchColumn() == 0) {
                             $sql_demo = "INSERT INTO content (subject_id, title, type, slug, description, file_url, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())";
                             $stmt_demo = $conn->prepare($sql_demo);
                             $stmt_demo->execute([
                                 '1st Year', 
                                 $demo_title, 
                                 'note', 
                                 $demo_slug, 
                                 'This is a sample topic to test the search functionality.',
                                 ''
                             ]);
                        }
                        
                        // Ensure uploads directory exists for admin uploads
                        $uploadsDir = __DIR__ . '/uploads';
                        if (!is_dir($uploadsDir)) {
                            @mkdir($uploadsDir, 0777, true);
                        }

                        // Optional: Add FULLTEXT index for faster search
                        try {
                            $conn->exec("ALTER TABLE content ADD FULLTEXT INDEX ft_content (title, description, blog_content)");
                        } catch (PDOException $e) { /* ignore if not supported */ }

                        redirect(4);
                    } catch (PDOException $e) {
                        $error = "Database setup failed: " . $e->getMessage();
                    }
                }
                break;

            case 'step4': // Admin Creation
                $email = sanitize($_POST['email']);
                $pass = $_POST['password'];

                if (strlen($pass) < 8) {
                    $error = "Password must be at least 8 characters.";
                } else {
                    $hash = password_hash($pass, PASSWORD_DEFAULT);
                    $db = new Database();
                    $db->connect($_SESSION['db_host'], $_SESSION['db_user'], $_SESSION['db_pass'], $_SESSION['db_name']);
                    $conn = $db->getConnection();

                    try {
                        $stmt = $conn->prepare("INSERT INTO admins (username, password_hash) VALUES (?, ?)");
                        $stmt->execute([$email, $hash]);
                        
                        // Also create api/config.php
                        $jwt_secret = bin2hex(random_bytes(32));
                        $config_content = "<?php\n";
                        $config_content .= "// Auto-generated Config\n";
                        $config_content .= "define('DB_HOST', '" . $_SESSION['db_host'] . "');\n";
                        $config_content .= "define('DB_USER', '" . $_SESSION['db_user'] . "');\n";
                        $config_content .= "define('DB_PASS', '" . addslashes($_SESSION['db_pass']) . "');\n";
                        $config_content .= "define('DB_NAME', '" . $_SESSION['db_name'] . "');\n";
                        $config_content .= "define('JWT_SECRET', '" . $jwt_secret . "');\n\n";
                        $config_content .= "try {\n";
                        $config_content .= "    \$conn = new PDO(\"mysql:host=\" . DB_HOST . \";dbname=\" . DB_NAME, DB_USER, DB_PASS);\n";
                        $config_content .= "    \$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);\n";
                        $config_content .= "    \$conn->exec(\"set names utf8mb4\");\n";
                        $config_content .= "} catch(PDOException \$e) {\n";
                        $config_content .= "    die(\"Connection failed: \" . \$e->getMessage());\n";
                        $config_content .= "}\n";
                        $config_content .= "?>";

                        // Write to api/config.php
                        file_put_contents('api/config.php', $config_content);
                        
                        redirect(5);
                    } catch (Exception $e) {
                        $error = "Failed to create admin: " . $e->getMessage();
                    }
                }
                break;
                
            case 'step5': // Finalizing (Optional Sample Data)
                 // This block can be used for any post-install cleanup if needed
                 break; 
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>App Installer</title>
    <style>
        body { font-family: sans-serif; background: #f0f2f5; display: flex; justify-content: center; padding-top: 50px; }
        .container { background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); width: 500px; }
        .step-indicator { display: flex; justify-content: space-between; margin-bottom: 2rem; color: #aaa; font-size: 0.9rem; }
        .step-indicator .active { color: #007bff; font-weight: bold; }
        h1 { margin-top: 0; color: #333; }
        .form-group { margin-bottom: 1rem; }
        label { display: block; margin-bottom: 0.5rem; font-weight: 500; }
        input[type="text"], input[type="password"], input[type="email"] { width: 100%; padding: 0.8rem; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        .btn { background: #007bff; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; width: 100%; font-size: 1rem; }
        .btn:hover { background: #0056b3; }
        .error { color: red; background: #fff0f0; padding: 10px; border-radius: 4px; margin-bottom: 1rem; border: 1px solid #ffcccc; }
        .checklist { list-style: none; padding: 0; }
        .checklist li { margin-bottom: 0.5rem; }
        .check-pass { color: green; }
        .check-fail { color: red; }
    </style>
</head>
<body>

<div class="container">
    <div class="step-indicator">
        <span class="<?php echo $step == 1 ? 'active' : ''; ?>">1. Requirements</span>
        <span class="<?php echo $step == 2 ? 'active' : ''; ?>">2. Database</span>
        <span class="<?php echo $step == 3 ? 'active' : ''; ?>">3. Install</span>
        <span class="<?php echo $step == 4 ? 'active' : ''; ?>">4. Admin</span>
        <span class="<?php echo $step == 5 ? 'active' : ''; ?>">5. Done</span>
    </div>

    <?php if ($error): ?>
        <div class="error"><?php echo $error; ?></div>
    <?php endif; ?>

    <!-- STEP 1: REQUIREMENTS -->
    <?php if ($step == 1): ?>
        <h1>System Check</h1>
        <?php $reqs = check_requirements(); ?>
        <ul class="checklist">
            <li>PHP Version >= 7.4: <span class="<?php echo $reqs['php_version'] ? 'check-pass' : 'check-fail'; ?>"><?php echo $reqs['php_version'] ? '✓' : '✗'; ?></span></li>
            <li>PDO MySQL: <span class="<?php echo $reqs['extensions']['pdo_mysql'] ? 'check-pass' : 'check-fail'; ?>"><?php echo $reqs['extensions']['pdo_mysql'] ? '✓' : '✗'; ?></span></li>
            <li>`public/api` writable: <span class="<?php echo $reqs['writable']['api'] ? 'check-pass' : 'check-fail'; ?>"><?php echo $reqs['writable']['api'] ? '✓' : '✗'; ?></span></li>
            <li>`public/uploads` writable: <span class="<?php echo $reqs['writable']['uploads'] ? 'check-pass' : 'check-fail'; ?>"><?php echo $reqs['writable']['uploads'] ? '✓' : '✗'; ?></span></li>
        </ul>
        <?php if ($reqs['php_version'] && $reqs['extensions']['pdo_mysql'] && $reqs['writable']['api'] && $reqs['writable']['uploads']): ?>
            <a href="?step=2" class="btn" style="text-decoration:none; display:block; text-align:center;">Next Step</a>
        <?php else: ?>
            <p style="color:red">Please fix requirements to proceed.</p>
        <?php endif; ?>
    <?php endif; ?>

    <!-- STEP 2: DATABASE CONFIG -->
    <?php if ($step == 2): ?>
        <h1>Database Config</h1>
        <form method="POST">
            <input type="hidden" name="action" value="step2">
            <div class="form-group">
                <label>Database Host</label>
                <input type="text" name="db_host" value="localhost" required>
            </div>
            <div class="form-group">
                <label>Database Name</label>
                <input type="text" name="db_name" required placeholder="e.g. u12345_myapp">
            </div>
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="db_user" required placeholder="e.g. u12345_admin">
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="db_pass">
            </div>
            <button type="submit" class="btn">Test Connection</button>
        </form>
    <?php endif; ?>

    <!-- STEP 3: INSTALLATION -->
    <?php if ($step == 3): ?>
        <h1>Ready to Install</h1>
        <p>Database connection successful.</p>
        <p>We are ready to create the necessary tables.</p>
        <form method="POST">
            <input type="hidden" name="action" value="step3">
            <button type="submit" class="btn">Run Installation</button>
        </form>
    <?php endif; ?>

    <!-- STEP 4: CREATE ADMIN -->
    <?php if ($step == 4): ?>
        <h1>Create Admin</h1>
        <form method="POST">
            <input type="hidden" name="action" value="step4">
            <div class="form-group">
                <label>Admin Email</label>
                <input type="email" name="email" required>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required minlength="8">
            </div>
            <button type="submit" class="btn">Create Account</button>
        </form>
    <?php endif; ?>

    <!-- STEP 5: SUCCESS -->
    <?php if ($step == 5): ?>
        <h1 style="color:green">Installation Complete!</h1>
        <p>Your application is now installed.</p>
        <div style="background:#e8f5e9; padding:15px; border-radius:4px; border:1px solid #c8e6c9;">
            <strong>Next Steps:</strong>
            <ul style="margin-top:5px; padding-left:20px;">
                <li>Delete <code>install.php</code> from your server immediately.</li>
                <li>Go to the <a href="/admin">Admin Dashboard</a> to log in.</li>
            </ul>
        </div>
    <?php endif; ?>

</div>

</body>
</html>
