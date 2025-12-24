<?php
/* Enable error reporting for debugging (returns text, but visible in check.html) */
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 1. Check if config exists (Absolute Path)
$configPath = __DIR__ . '/config.php';

if (!file_exists($configPath)) {
    http_response_code(500);
    echo json_encode(["error" => "Configuration file missing at: $configPath"]);
    exit;
}

// 2. Include Config
try {
    require_once $configPath;
} catch (Throwable $t) {
    http_response_code(500);
    echo json_encode(["error" => "Error loading config.php: " . $t->getMessage()]);
    exit;
}

// 3. Check Database Connection
if (!isset($conn) || !($conn instanceof PDO)) {
    http_response_code(500);
    echo json_encode(["error" => "Database connection invalid. \$conn is not a PDO object."]);
    exit;
}

$query = isset($_GET['q']) ? trim($_GET['q']) : '';

if (empty($query)) {
    echo json_encode([]);
    exit;
}

try {
    // Prefer searching title/description (and blog_content if exists) with LIKE
    // First, attempt using slug column; if it doesn't exist, fallback without it.
     $sql = "SELECT id, subject_id, title, slug, description, 'topic' as type
                FROM content
                WHERE title LIKE :q
                    OR subject_id LIKE :q
                    OR description LIKE :q
                    OR blog_content LIKE :q
                ORDER BY created_at DESC
                LIMIT 20";

    $stmt = $conn->prepare($sql);
    $searchTerm = "%" . $query . "%";
    $stmt->bindParam(':q', $searchTerm);
    try {
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Fallback if slug column is missing in older databases
        if (stripos($e->getMessage(), 'Unknown column') !== false && stripos($e->getMessage(), 'slug') !== false) {
            $sql2 = "SELECT id, subject_id, title, description, 'topic' as type
                     FROM content
                     WHERE title LIKE :q OR subject_id LIKE :q OR description LIKE :q OR blog_content LIKE :q
                     ORDER BY created_at DESC
                     LIMIT 20";
            $stmt2 = $conn->prepare($sql2);
            $stmt2->bindParam(':q', $searchTerm);
            $stmt2->execute();
            $results = $stmt2->fetchAll(PDO::FETCH_ASSOC);
            // Compute a slug from title for navigation
            foreach ($results as &$r) {
                $r['slug'] = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $r['title']), '-'));
            }
        } else {
            throw $e;
        }
    }

    // Ensure types for frontend and safe defaults
    foreach ($results as &$row) {
        $row['id'] = isset($row['id']) ? (string)$row['id'] : '';
        $row['subject_id'] = isset($row['subject_id']) ? (string)$row['subject_id'] : '';
        if (!isset($row['slug']) || $row['slug'] === null || $row['slug'] === '') {
            $row['slug'] = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $row['title'] ?? ''), '-'));
        }
    }

    echo json_encode($results ?: []);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Database error: " . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "General error: " . $e->getMessage()]);
}
?>
