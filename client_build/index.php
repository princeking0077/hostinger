<?php
// Display errors
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 1. Check if Config Exists
if (!file_exists(__DIR__ . '/api/config.php')) {
    // If config is missing, check if installer exists
    if (file_exists(__DIR__ . '/install.php')) {
        header("Location: install.php");
        exit;
    } else {
        die("System not installed and install.php is missing. Please upload the installer.");
    }
}

// 2. Include Config
include __DIR__ . '/api/config.php';

// Response + security headers
header("Content-Type: text/html; charset=UTF-8");
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer-when-downgrade');
header("Permissions-Policy: camera=(), microphone=(), geolocation=()");
// Page CSP tuned to common integrations (Vite assets, GA, Adsense, YouTube). Adjust if needed.
$csp = "default-src 'self' https: data: blob:; " .
    "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://www.googletagmanager.com https://www.google-analytics.com https://pagead2.googlesyndication.com https://www.googletagservices.com; " .
    "style-src 'self' 'unsafe-inline' https: data:; " .
    "img-src 'self' https: data: blob:; " .
    "font-src 'self' https: data:; " .
    "connect-src 'self' https:; " .
    "frame-src https://www.youtube.com https://www.youtube-nocookie.com; " .
    "media-src 'self' https: data: blob:; ";
header("Content-Security-Policy: $csp");

// Prevent Caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Fetch Settings
$ga_id = '';
$gsc_tag = '';
$adsense_code = '';

if (isset($conn) && $conn instanceof PDO) {
    try {
        $stmt = $conn->prepare("SELECT setting_key, setting_value FROM site_settings");
        $stmt->execute();
        $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        $ga_id = $settings['google_analytics_id'] ?? '';
        $gsc_tag = $settings['google_search_console'] ?? '';
        $adsense_code = $settings['google_adsense_code'] ?? '';
    } catch(PDOException $e) {
        // Fail silently so the site still loads
        error_log("SEO Settings Fetch Error: " . $e->getMessage());
    }
}

// Get index.html content from public, fallback to project root
$htmlPath = __DIR__ . DIRECTORY_SEPARATOR . 'index.html';
if (!file_exists($htmlPath)) {
    $rootCandidate = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'index.html';
    if (file_exists($rootCandidate)) {
        $htmlPath = $rootCandidate;
    }
}
$html = @file_get_contents($htmlPath);
if ($html === false) {
    http_response_code(500);
    echo "<!doctype html><html><body><h1>Missing index.html</h1><p>Could not locate index.html in public/ or project root.</p></body></html>";
    exit;
}

// Prepare Injections
$injection = "";

// 1. Analytics
if (!empty($ga_id)) {
    $injection .= "
    <!-- Google Analytics -->
    <script async src='https://www.googletagmanager.com/gtag/js?id={$ga_id}'></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());
      gtag('config', '{$ga_id}');
    </script>
    ";
}

// 2. Search Console (Meta Tag)
if (!empty($gsc_tag)) {
    $injection .= $gsc_tag . "\n";
}

// 3. AdSense
if (!empty($adsense_code)) {
    $injection .= $adsense_code . "\n";
}

// Ensure viewport meta for mobile friendliness
if (stripos($html, 'name="viewport"') === false) {
    $injection = "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1, maximum-scale=1\">\n" . $injection;
}

// Inject into <head>
// We look for </head> and put our code right before it
$html = str_ireplace('</head>', $injection . '</head>', $html);

// --- SSR Lite: Dynamic Meta Tags based on URL ---
$requestUri = $_SERVER['REQUEST_URI'];
$metaTitle = "ApexApps | Pharmacy Notes & Animations";
$metaDesc = "Visual pharmacy education with 3D animations, interactive quizzes, and comprehensive B.Pharm notes.";
$metaImage = "https://apexapps.in/icon.png"; // Default image

// Check if visiting a Subject page
if (preg_match('/\/subject\/([a-zA-Z0-9-]+)/', $requestUri, $matches)) {
    $slug = $matches[1];
    // Convert "human-anatomy" -> "Human Anatomy"
    $readableTitle = ucwords(str_replace('-', ' ', $slug));
    
    $metaTitle = "$readableTitle | ApexApps";
    $metaDesc = "Study $readableTitle with interactive 3D animations and comprehensive notes on ApexApps.";
    // We could map specific images here if we had a list
}

// Replace standard tags in HTML with dynamic ones
// 1. Title
$html = preg_replace('/<title>(.*?)<\/title>/', "<title>$metaTitle</title>", $html);
// 2. Meta Description
$html = preg_replace('/<meta name="description" content="(.*?)" \/>/', "<meta name=\"description\" content=\"$metaDesc\" />", $html);
// 3. Open Graph Title (Add if missing, or replace)
if (strpos($html, 'og:title') !== false) {
    $html = preg_replace('/<meta property="og:title" content="(.*?)" \/>/', "<meta property=\"og:title\" content=\"$metaTitle\" />", $html);
} else {
    $html = str_replace('</head>', '<meta property="og:title" content="'.$metaTitle.'" /></head>', $html);
}
// 4. Open Graph Description
if (strpos($html, 'og:description') !== false) {
     $html = preg_replace('/<meta property="og:description" content="(.*?)" \/>/', "<meta property=\"og:description\" content=\"$metaDesc\" />", $html);
}

// Serve the modified HTML
echo "<!-- SERVED BY PHP + SSR LITE -->\n" . $html;
?>
