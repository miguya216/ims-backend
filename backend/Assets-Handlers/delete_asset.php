<?php
session_start(); // Needed to get account_ID
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["message" => "Only POST method is allowed"]);
    exit;
}

require_once __DIR__ . '/../conn.php';
require_once __DIR__ . '/../logActivity.php';

// Get asset_ID from query string
$assetID = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($assetID <= 0) {
    http_response_code(400);
    echo json_encode(["message" => "Invalid asset ID"]);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE asset SET asset_status = 'inactive' 
                           WHERE asset_ID = ? AND asset_status = 'active'");
    $stmt->execute([$assetID]);

    if ($stmt->rowCount() > 0) {
        // Log the action
        $account_ID = $_SESSION['user']['account_ID'] ?? null;
        $description = "Marked asset ID $assetID as inactive.";
        logActivity($pdo, $account_ID, "INACTIVATE", "asset", $assetID, $description);

        echo json_encode(["message" => "Asset successfully marked as inactive."]);
    } else {
        echo json_encode(["message" => "Asset not found or currently borrowed or already inactive."]);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["message" => "Database error: " . $e->getMessage()]);
}
?>