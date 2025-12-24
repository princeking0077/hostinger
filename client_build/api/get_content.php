<?php
include 'config.php';

$subjectId = isset($_GET['subject']) ? $_GET['subject'] : '';
$slug = isset($_GET['slug']) ? $_GET['slug'] : '';

if ($subjectId) {
    try {
        if (!empty($slug)) {
            // Fetch single topic by slug for deep link
            $query = "SELECT * FROM content WHERE subject_id = :sid AND slug = :slug LIMIT 1";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(":sid", $subjectId);
            $stmt->bindParam(":slug", $slug);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && isset($row['quiz_data'])) {
                $row['quiz_data'] = json_decode($row['quiz_data']);
            }
            echo json_encode($row ?: null);
            exit;
        } else {
            // Fetch list for sidebar
            $query = "SELECT * FROM content WHERE subject_id = :sid ORDER BY created_at DESC";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(":sid", $subjectId);
            $stmt->execute();
            
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
            // Decode quiz data for frontend convenience
            foreach ($results as &$row) {
                 if (isset($row['quiz_data'])) {
                     $row['quiz_data'] = json_decode($row['quiz_data']);
                 }
            }
            
            echo json_encode($results);
        }
    } catch(PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => $e->getMessage()]);
    }
} else {
    echo json_encode([]);
}
?>
