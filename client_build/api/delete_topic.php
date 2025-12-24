<?php
include 'config.php';
require_once __DIR__ . '/auth.php';
require_auth();

$data = json_decode(file_get_contents("php://input"));

if ($_SERVER['REQUEST_METHOD'] == 'POST' && !empty($data->id)) {
    try {
        $stmt = $conn->prepare("DELETE FROM content WHERE id = :id");
        $stmt->bindParam(':id', $data->id);

        if ($stmt->execute()) {
            echo json_encode(["message" => "Topic deleted successfully"]);
        } else {
            http_response_code(500);
            echo json_encode(["message" => "Failed to delete topic"]);
        }
    } catch(PDOException $e) {
        http_response_code(500);
        echo json_encode(["message" => "Db Error: " . $e->getMessage()]);
    }
} else {
    http_response_code(400);
    echo json_encode(["message" => "Invalid request"]);
}
?>
