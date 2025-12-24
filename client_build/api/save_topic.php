<?php
include 'config.php';
require_once __DIR__ . '/auth.php';
require_auth();

$data = json_decode(file_get_contents("php://input"));

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!empty($data->subjectId) && !empty($data->title) && !empty($data->type)) {
        
        try {
            // Check if subject exists in curriculum table? For now, we rely on the ID matching our static file or we just insert blindly.
            // Since we are moving to SQL, we should ideally verify, but for MVP we insert.
            // Note: The schema has subject_id as INT. Our frontend uses strings like 'sub-1'. 
            // We might need to adjust the schema or the frontend. 
            // PROPOSAL: Change schema subject_id to VARCHAR(50) to match static IDs or mapping.
            // Let's modify the schema instruction in setup.php or just use varchar here.
            // Wait, previous schema said INT. I will update schema in setup.php to VARCHAR to be safe with existing static data.
            
            $sql = "INSERT INTO content (subject_id, title, type, file_url, description, blog_content, youtube_id, quiz_data, created_at) VALUES (:subject, :title, :type, :file, :desc, :blog, :youtube, :quiz, NOW())";
            
            $stmt = $conn->prepare($sql);
            
            // Quiz data needs to be JSON encoded string
            $quizJson = isset($data->quiz) ? json_encode($data->quiz) : '[]';
            
            $stmt->bindParam(':subject', $data->subjectId);
            $stmt->bindParam(':title', $data->title);
            $stmt->bindParam(':type', $data->type);
            // Ensure file_url is a safe non-null value (empty string) if not provided
            $fileUrlSafe = (isset($data->fileUrl) && $data->fileUrl !== null) ? $data->fileUrl : '';
            $stmt->bindParam(':file', $fileUrlSafe); 
            $stmt->bindParam(':desc', $data->description); 
            $stmt->bindParam(':blog', $data->blogContent); // New
            $stmt->bindParam(':youtube', $data->youtubeId); // New
            $stmt->bindParam(':quiz', $quizJson);

            if ($stmt->execute()) {
                echo json_encode(["message" => "Topic saved successfully", "id" => $conn->lastInsertId()]);
            } else {
                http_response_code(500);
                echo json_encode(["message" => "Failed to save topic."]);
            }
        } catch(PDOException $e) {
             http_response_code(500);
             echo json_encode(["message" => "Db Error: " . $e->getMessage()]);
        }
    } else {
        http_response_code(400);
        echo json_encode(["message" => "Incomplete data."]);
    }
}
?>
