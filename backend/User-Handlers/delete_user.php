<?php
session_start();
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["message" => "Only POST method is allowed"]);
    exit;
}

require_once __DIR__ . '/../conn.php';
require_once __DIR__ . '/../logActivity.php';

$userID = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($userID <= 0) {
    http_response_code(400);
    echo json_encode(["message" => "Invalid user ID"]);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE user SET user_status = 'inactive' WHERE user_ID = ? AND user_status = 'active'");
    $stmt->execute([$userID]);

    if ($stmt->rowCount() > 0) {
        // Log activity
        logActivity(
            $pdo,
            $_SESSION['user']['user_ID'] ?? null,
            "INACTIVATE",
            "user",
            $userID,
            "User ID $userID marked as inactive"
        );

        echo json_encode(["message" => "User successfully marked as inactive."]);
    } else {
        echo json_encode(["message" => "User not found or already inactive."]);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["message" => "Database error: " . $e->getMessage()]);
}
?>