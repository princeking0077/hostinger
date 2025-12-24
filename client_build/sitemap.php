<?php
header("Content-Type: application/xml; charset=utf-8");
include 'api/config.php';

$baseUrl = "https://apexapps.in";

echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <!-- Static Pages -->
    <url>
        <loc><?php echo $baseUrl; ?>/</loc>
        <changefreq>daily</changefreq>
        <priority>1.0</priority>
    </url>
    <url>
        <loc><?php echo $baseUrl; ?>/about</loc>
        <changefreq>monthly</changefreq>
        <priority>0.5</priority>
    </url>
    <url>
        <loc><?php echo $baseUrl; ?>/contact</loc>
        <changefreq>monthly</changefreq>
        <priority>0.5</priority>
    </url>

    <!-- Dynamic Subject Pages from Content -->
    <?php
    try {
        // We fetch subjects that have content or exist in our system
        // Since we don't have a populated 'subjects' table, we use 'content' to find active subjects
        // Fetch Subjects
        $stmt = $conn->query("SELECT DISTINCT subject_id FROM content");
        $subjects = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($subjects as $subId) {
            echo "<url>";
            echo "<loc>{$baseUrl}/subject/" . htmlspecialchars($subId) . "</loc>";
            echo "<changefreq>weekly</changefreq>";
            echo "<priority>0.8</priority>";
            echo "</url>";
        }

        // Fetch Individual Topics (Deep Indexing)
        $stmtTopics = $conn->query("SELECT subject_id, slug, updated_at FROM content WHERE slug IS NOT NULL AND slug != ''");
        while ($row = $stmtTopics->fetch(PDO::FETCH_ASSOC)) {
            echo "<url>";
            echo "<loc>{$baseUrl}/subject/" . htmlspecialchars($row['subject_id']) . "/" . htmlspecialchars($row['slug']) . "</loc>";
            echo "<lastmod>" . date('Y-m-d', strtotime($row['updated_at'])) . "</lastmod>";
            echo "<changefreq>monthly</changefreq>";
            echo "<priority>0.6</priority>";
            echo "</url>";
        }
    } catch (PDOException $e) {
        // Silently fail or log error
    }
    ?>
</urlset>
