<?php
include 'config.php';
require_once __DIR__ . '/auth.php';
require_auth();

$data = json_decode(file_get_contents("php://input"), true);

if ($_SERVER['REQUEST_METHOD'] == 'POST' && !empty($data)) {
    try {
        $stmt = $conn->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (:key, :val) ON DUPLICATE KEY UPDATE setting_value = :val");

        foreach ($data as $key => $val) {
            // Whitelist keys to prevent garbage
            $allowed = ['google_analytics_id', 'google_search_console', 'google_adsense_code', 'ads_txt', 'ad_rpm_head', 'ad_rpm_body'];
            if (in_array($key, $allowed)) {
                $stmt->execute([':key' => $key, ':val' => $val]);
            }
        }

        echo json_encode(["message" => "Settings saved successfully"]);
    } catch(PDOException $e) {
        http_response_code(500);
        echo json_encode(["message" => "Db Error: " . $e->getMessage()]);
    }
} else {
    http_response_code(400);
    echo json_encode(["message" => "Invalid data"]);
}
?>
