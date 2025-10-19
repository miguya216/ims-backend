<?php
session_start();
header("Content-Type: application/json");

if (!isset($_SESSION['user']['user_ID'])) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

require_once __DIR__ . '/../../conn.php';
require_once __DIR__ . '/../../logActivity.php';

// Get input
$input = json_decode(file_get_contents("php://input"), true);
if (!$input || empty(trim($input['emailSender'] ?? ""))) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid email sender"]);
    exit;
}

$emailSender = trim($input['emailSender']);
$user_ID = $_SESSION['user']['user_ID'];

try {
    $sql = "UPDATE settings_preferences
            SET email_sender = :emailSender
            WHERE setting_pref_ID = 1";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([":emailSender" => $emailSender]);

    // Log the activity
    logActivity(
        $pdo,
        $user_ID,                                // who did it
        "UPDATE",                                // action type
        "settings_preferences",                  // module
        1,                                       // record affected (fixed row)
        "Changed email sender to: $emailSender"  // description
    );

    echo json_encode(["success" => true, "emailSender" => $emailSender]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Database error: " . $e->getMessage()]);
}
?>