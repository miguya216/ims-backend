<?php
session_start();
require_once __DIR__ . '/../conn.php';
require_once __DIR__ . '/../logActivity.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents("php://input"), true);

if (!$input || !isset($input['ids'], $input['field'], $input['newValue'])) {
    echo json_encode(["success" => false, "message" => "Invalid request data."]);
    exit;
}

$ids = $input['ids'];           
$field = $input['field'];       
$newValue = $input['newValue']; 

if (!is_array($ids) || empty($ids)) {
    echo json_encode(["success" => false, "message" => "No asset IDs provided."]);
    exit;
}

// Map frontend field to DB column
$fieldMap = [
    "brand" => "brand_ID",
    "acquisition_source" => "a_source_ID",
    "asset_condition" => "asset_condition_ID",
    "is_borrowable" => "is_borrowable" // ✅ NEW FIELD
];

// Validate selected field
if (!array_key_exists($field, $fieldMap)) {
    echo json_encode(["success" => false, "message" => "Invalid field selected."]);
    exit;
}

$column = $fieldMap[$field];

// ✅ Optional: Validate ENUM values for is_borrowable
if ($field === "is_borrowable" && !in_array($newValue, ['yes', 'no'])) {
    echo json_encode(["success" => false, "message" => "Invalid value for Borrowable field."]);
    exit;
}

try {
    $pdo->beginTransaction();

    $placeholders = implode(",", array_fill(0, count($ids), "?"));
    $sql = "UPDATE asset SET $column = ? WHERE asset_ID IN ($placeholders)";
    $stmt = $pdo->prepare($sql);

    $params = array_merge([$newValue], $ids);
    $stmt->execute($params);

    $affected = $stmt->rowCount();

    $pdo->commit();

    // Log each asset separately
    $account_ID = $_SESSION['user']['account_ID'] ?? null;
    foreach ($ids as $id) {
        $description = "Updated field '$field' to '$newValue' for asset ID $id";
        logActivity($pdo, $account_ID, "UPDATE", "asset", $id, $description);
    }

    echo json_encode([
        "success" => true,
        "message" => "Updated $affected assets successfully."
    ]);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Database error: " . $e->getMessage()
    ]);
}
?>
