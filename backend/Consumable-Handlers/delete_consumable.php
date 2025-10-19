<?php
session_start();
header("Content-Type: application/json");

if($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["message" => $_SERVER['REQUEST_METHOD']]);
    exit;
}

require_once __DIR__ . '/../conn.php';
require_once __DIR__ . '/../logActivity.php';

$account_ID   = $_SESSION['user']['account_ID'] ?? null;
$consumableID = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($consumableID <= 0) {
    http_response_code(400);
    echo json_encode(["message" => "Invalid consumable ID"]);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE consumable 
                           SET consumable_status = 'inactive' 
                           WHERE consumable_ID = ? AND consumable_status = 'active'");
    $stmt->execute([$consumableID]);

    if ($stmt->rowCount() > 0) {
        // ✅ Log activity
        logActivity(
            $pdo,
            $account_ID,
            "INACTIVATE",
            "consumable",
            $consumableID,
            "Consumable marked as inactive"
        );

        echo json_encode(["message" => "Consumable successfully marked as inactive."]);
    } else {
        echo json_encode(["message" => "Consumable not found or already inactive."]);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["message" => "Database error: " . $e->getMessage()]);
}
?>