<?php
session_start();
header("Content-Type: application/json");

if (!isset($_SESSION['user']['user_ID'])) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

require_once __DIR__ . '/../../conn.php';

try {
    $sql = "SELECT email_sender FROM settings_preferences WHERE setting_pref_ID = 1 LIMIT 1";
    $stmt = $pdo->query($sql);
    $row = $stmt->fetch();

    if ($row) {
        echo json_encode(["emailSender" => $row['email_sender']]);
    } else {
        echo json_encode(["error" => "No security settings found"]);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Database error: " . $e->getMessage()]);
}
?>