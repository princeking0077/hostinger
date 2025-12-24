<?php
include 'config.php';

try {
    $stmt = $conn->prepare("SELECT setting_key, setting_value FROM site_settings");
    $stmt->execute();
    $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR); // Returns [key => value]

    echo json_encode($settings);
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(["message" => "Db Error: " . $e->getMessage()]);
}
?>
