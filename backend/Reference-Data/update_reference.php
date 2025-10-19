<?php
// Preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

session_start();
require_once __DIR__ . '/../conn.php';
require_once __DIR__ . '/../logActivity.php';

$data = json_decode(file_get_contents("php://input"), true);

$selectedType   = trim($data['selectedType'] ?? '');
$newValue       = trim($data['newValue'] ?? '');
$referenceID    = $data['referenceID'] ?? null;
$asset_type_ID  = $data['asset_type_ID'] ?? null;

$account_ID = $_SESSION['user']['account_ID'] ?? null;

if (!$selectedType || !$newValue || !$referenceID) {
    echo json_encode(["success" => false, "message" => "Missing fields"]);
    exit;
}

$moduleName = ucwords(str_replace('_', ' ', $selectedType));

try {
    switch ($selectedType) {
        case 'acquisition_source':
            $check = $pdo->prepare("SELECT 1 FROM acquisition_source WHERE a_source_name = ? AND a_source_ID != ?");
            $check->execute([$newValue, $referenceID]);
            if ($check->fetch()) {
                echo json_encode(["success" => false, "message" => "Acquisition source already exists."]);
                exit;
            }
            $stmt = $pdo->prepare("UPDATE acquisition_source SET a_source_name = ? WHERE a_source_ID = ?");
            $stmt->execute([$newValue, $referenceID]);
            logActivity($pdo, $account_ID, "UPDATE", $moduleName, $referenceID, "Updated to: $newValue");
            break;

        case 'asset_condition':
            $check = $pdo->prepare("SELECT 1 FROM asset_condition WHERE condition_name = ? AND asset_condition_ID != ?");
            $check->execute([$newValue, $referenceID]);
            if ($check->fetch()) {
                echo json_encode(["success" => false, "message" => "Asset condition already exists."]);
                exit;
            }
            $stmt = $pdo->prepare("UPDATE asset_condition SET condition_name = ? WHERE asset_condition_ID = ?");
            $stmt->execute([$newValue, $referenceID]);
            logActivity($pdo, $account_ID, "UPDATE", $moduleName, $referenceID, "Updated to: $newValue");
            break;

        case 'role':
            $check = $pdo->prepare("SELECT 1 FROM role WHERE role_name = ? AND role_ID != ?");
            $check->execute([$newValue, $referenceID]);
            if ($check->fetch()) {
                echo json_encode(["success" => false, "message" => "Role already exists."]);
                exit;
            }
            $stmt = $pdo->prepare("UPDATE role SET role_name = ? WHERE role_ID = ?");
            $stmt->execute([$newValue, $referenceID]);
            logActivity($pdo, $account_ID, "UPDATE", $moduleName, $referenceID, "Updated to: $newValue");
            break;

        case 'room':
            // Check if the room number already exists for another record
            $check = $pdo->prepare("SELECT 1 FROM room WHERE room_number = ? AND room_ID != ?");
            $check->execute([$newValue, $referenceID]);
            if ($check->fetch()) {
                echo json_encode(["success" => false, "message" => "Room number already exists."]);
                exit;
            }

            // Update room number (or whatever property you’re editing)
            $stmt = $pdo->prepare("UPDATE room SET room_number = ? WHERE room_ID = ?");
            $stmt->execute([$newValue, $referenceID]);

            logActivity($pdo, $account_ID, "UPDATE", $moduleName, $referenceID, "Updated room number to: $newValue");
            break;


        case 'brand':
            if (!$asset_type_ID) {
                echo json_encode(["success" => false, "message" => "Missing asset type for brand."]);
                exit;
            }
            $check = $pdo->prepare("SELECT 1 FROM brand WHERE brand_name = ? AND asset_type_ID = ? AND brand_ID != ?");
            $check->execute([$newValue, $asset_type_ID, $referenceID]);
            if ($check->fetch()) {
                echo json_encode(["success" => false, "message" => "Brand already exists for this asset type."]);
                exit;
            }
            $stmt = $pdo->prepare("UPDATE brand SET brand_name = ?, asset_type_ID = ? WHERE brand_ID = ?");
            $stmt->execute([$newValue, $asset_type_ID, $referenceID]);
            logActivity($pdo, $account_ID, "UPDATE", $moduleName, $referenceID, "Updated to: $newValue (asset_type_ID=$asset_type_ID)");
            break;

        case 'unit':
            $check = $pdo->prepare("SELECT 1 FROM unit WHERE unit_name = ? AND unit_ID != ?");
            $check->execute([$newValue, $referenceID]);
            if ($check->fetch()) {
                echo json_encode(["success" => false, "message" => "Unit already exists."]);
                exit;
            }
            $stmt = $pdo->prepare("UPDATE unit SET unit_name = ? WHERE unit_ID = ?");
            $stmt->execute([$newValue, $referenceID]);
            logActivity($pdo, $account_ID, "UPDATE", $moduleName, $referenceID, "Updated to: $newValue");
            break;

        case 'asset_type':
            $check = $pdo->prepare("SELECT 1 FROM asset_type WHERE asset_type = ? AND asset_type_ID != ?");
            $check->execute([$newValue, $referenceID]);
            if ($check->fetch()) {
                echo json_encode(["success" => false, "message" => "Asset type already exists."]);
                exit;
            }
            $stmt = $pdo->prepare("UPDATE asset_type SET asset_type = ? WHERE asset_type_ID = ?");
            $stmt->execute([$newValue, $referenceID]);
            logActivity($pdo, $account_ID, "UPDATE", $moduleName, $referenceID, "Updated to: $newValue");
            break;

        case 'transfer_type':
            $check = $pdo->prepare("SELECT 1 FROM transfer_type WHERE transfer_type_name = ? AND transfer_type_ID != ?");
            $check->execute([$newValue, $referenceID]);
            if ($check->fetch()) {
                echo json_encode(["success" => false, "message" => "Transfer type already exists."]);
                exit;
            }
            $stmt = $pdo->prepare("UPDATE transfer_type SET transfer_type_name = ? WHERE transfer_type_ID = ?");
            $stmt->execute([$newValue, $referenceID]);
            logActivity($pdo, $account_ID, "UPDATE", $moduleName, $referenceID, "Updated to: $newValue");
            break;

        default:
            echo json_encode(["success" => false, "message" => "Invalid reference type"]);
            exit;
    }

    echo json_encode(["success" => true, "message" => ucfirst($selectedType) . " updated successfully."]);

} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
}
?>