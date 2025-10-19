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

// Read JSON body
$input = json_decode(file_get_contents("php://input"), true);

if (!isset($input['ids']) || !is_array($input['ids']) || count($input['ids']) === 0) {
    http_response_code(400);
    echo json_encode(["message" => "No valid asset IDs provided."]);
    exit;
}

// Sanitize IDs
$assetIDs = array_filter(array_map('intval', $input['ids']), fn($id) => $id > 0);

if (empty($assetIDs)) {
    http_response_code(400);
    echo json_encode(["message" => "No valid asset IDs after sanitization."]);
    exit;
}

try {
    $placeholders = implode(',', array_fill(0, count($assetIDs), '?'));
    $stmt = $pdo->prepare("UPDATE asset 
                           SET asset_status = 'active' 
                           WHERE asset_ID IN ($placeholders) 
                           AND asset_status = 'inactive'");
    $stmt->execute($assetIDs);

    $affected = $stmt->rowCount();

    if ($affected > 0) {
        $account_ID = $_SESSION['user']['account_ID'] ?? null;

        // Log each asset individually
        foreach ($assetIDs as $id) {
            logActivity(
                $pdo,
                $account_ID,
                "UPDATE",
                "asset",
                $id,
                "Asset ID $id marked as active."
            );
        }

        echo json_encode([
            "message" => "$affected asset(s) successfully marked as active."
        ]);
    } else {
        echo json_encode([
            "message" => "No assets updated. They may already be active."
        ]);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["message" => "Database error: " . $e->getMessage()]);
}
?>