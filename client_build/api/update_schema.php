<?php
header("Content-Type: application/json; charset=UTF-8");
include_once 'config.php';
require_once __DIR__ . '/auth.php';
require_auth();

try {
    // Add blog_content column
    try {
        $conn->exec("ALTER TABLE content ADD COLUMN blog_content LONGTEXT");
        echo "Added blog_content column. ";
    } catch (PDOException $e) {
        if ($e->getCode() != '42S21') { // Duplicate column error code for some drivers, though PDO might throw generic
             // safely ignore if it exists, or check metadata first. 
             // simpler to just try/catch generic for "duplicate column" logic
        }
    }

    // Add youtube_id column
    try {
        $conn->exec("ALTER TABLE content ADD COLUMN youtube_id VARCHAR(50)");
        echo "Added youtube_id column. ";
    } catch (PDOException $e) {
         // Ignore
    }
    
    // Add image_url column (User asked for images, though blog_content handles inline, a main hero image might be good. 
    // The previous code had file_url for notes, maybe add main_image_url?)
    try {
         $conn->exec("ALTER TABLE content ADD COLUMN main_image VARCHAR(255)");
         echo "Added main_image column. ";
    } catch (PDOException $e) {
        // Ignore
    }

    // Ensure file_url column allows NULL or safe default (avoid NOT NULL insert errors)
    try {
        $conn->exec("ALTER TABLE content MODIFY COLUMN file_url VARCHAR(255) NULL DEFAULT ''");
        echo "Relaxed file_url constraint. ";
    } catch (PDOException $e) {
        // Ignore if column doesn't exist; older schemas may differ
    }

    // Add slug column to content if missing and backfill from title
    try {
        $conn->exec("ALTER TABLE content ADD COLUMN slug VARCHAR(200) NULL");
        // Backfill existing rows with slugified titles
        $stmt = $conn->query("SELECT id, title FROM content WHERE slug IS NULL OR slug='' ");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $upd = $conn->prepare("UPDATE content SET slug = :slug WHERE id = :id");
        foreach ($rows as $r) {
            $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $r['title']), '-'));
            if ($slug === '') { $slug = (string)$r['id']; }
            $upd->execute([':slug' => $slug, ':id' => $r['id']]);
        }
        echo "Added slug column and backfilled. ";
    } catch (PDOException $e) {
        // Column likely exists; ignore
    }

    // Optional FULLTEXT index for faster search (title/description/blog_content)
    try {
        $conn->exec("ALTER TABLE content ADD FULLTEXT INDEX ft_content (title, description, blog_content)");
        echo "Added FULLTEXT index. ";
    } catch (PDOException $e) { /* ignore if exists or unsupported */ }

    echo json_encode(["message" => "Schema updated successfully."]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["message" => "Database error: " . $e->getMessage()]);
}
?>
