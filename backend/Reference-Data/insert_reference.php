<?php
session_start();

// Preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

header("Content-Type: application/json");

require_once __DIR__ . '/../conn.php'; 
require_once __DIR__ . '/../logActivity.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

$data = json_decode(file_get_contents("php://input"), true);

$selectedType = trim($data['selectedType'] ?? '');
$newValue = trim($data['newValue'] ?? '');
$asset_type_ID = $data['asset_type_ID'] ?? null;

if (!$selectedType || !$newValue) {
    echo json_encode(["success" => false, "message" => "Missing fields"]);
    exit;
}

$moduleName = ucwords(str_replace('_', ' ', $selectedType));

try {
    $record_ID = null;

    switch ($selectedType) {
        case 'asset_condition':
            $check = $pdo->prepare("SELECT 1 FROM asset_condition WHERE condition_name = ?");
            $check->execute([$newValue]);
            if ($check->fetch()) {
                echo json_encode(["success" => false, "message" => "Condition already exists."]);
                exit;
            }
            $stmt = $pdo->prepare("INSERT INTO asset_condition (condition_name) VALUES (?)");
            $stmt->execute([$newValue]);
            $record_ID = $pdo->lastInsertId();
            break;

        case 'acquisition_source':
            $check = $pdo->prepare("SELECT 1 FROM acquisition_source WHERE a_source_name = ?");
            $check->execute([$newValue]);
            if ($check->fetch()) {
                echo json_encode(["success" => false, "message" => "Acquisition source already exists."]);
                exit;
            }
            $stmt = $pdo->prepare("INSERT INTO acquisition_source (a_source_name) VALUES (?)");
            $stmt->execute([$newValue]);
            $record_ID = $pdo->lastInsertId();
            break;
        // case 'role':
        //     $check = $pdo->prepare("SELECT 1 FROM role WHERE role_name = ?");
        //     $check->execute([$newValue]);
        //     if ($check->fetch()) {
        //         echo json_encode(["success" => false, "message" => "Role already exists."]);
        //         exit;
        //     }
        //     $stmt = $pdo->prepare("INSERT INTO role (role_name) VALUES (?)");
        //     $stmt->execute([$newValue]);
        //     break;

        case 'brand':
            if (!$asset_type_ID) {
                echo json_encode(["success" => false, "message" => "Missing asset type for brand."]);
                exit;
            }
            $check = $pdo->prepare("SELECT 1 FROM brand WHERE brand_name = ? AND asset_type_ID = ?");
            $check->execute([$newValue, $asset_type_ID]);
            if ($check->fetch()) {
                echo json_encode(["success" => false, "message" => "Brand already exists for this asset type."]);
                exit;
            }
            $stmt = $pdo->prepare("INSERT INTO brand (brand_name, asset_type_ID) VALUES (?, ?)");
            $stmt->execute([$newValue, $asset_type_ID]);
            $record_ID = $pdo->lastInsertId();
            break;

        case 'unit':
            $check = $pdo->prepare("SELECT 1 FROM unit WHERE unit_name = ?");
            $check->execute([$newValue]);
            if ($check->fetch()) {
                echo json_encode(["success" => false, "message" => "Unit already exists."]);
                exit;
            }
            $stmt = $pdo->prepare("INSERT INTO unit (unit_name) VALUES (?)");
            $stmt->execute([$newValue]);
            $record_ID = $pdo->lastInsertId();
            break;

        case 'asset_type':
            $check = $pdo->prepare("SELECT 1 FROM asset_type WHERE asset_type = ?");
            $check->execute([$newValue]);
            if ($check->fetch()) {
                echo json_encode(["success" => false, "message" => "Asset type already exists."]);
                exit;
            }
            $stmt = $pdo->prepare("INSERT INTO asset_type (asset_type) VALUES (?)");
            $stmt->execute([$newValue]);
            $record_ID = $pdo->lastInsertId();
            break;

        case 'transfer_type':
            $check = $pdo->prepare("SELECT 1 FROM transfer_type WHERE transfer_type_name = ?");
            $check->execute([$newValue]);
            if ($check->fetch()) {
                echo json_encode(["success" => false, "message" => "Transfer type already exists."]);
                exit;
            }
            $stmt = $pdo->prepare("INSERT INTO transfer_type (transfer_type_name) VALUES (?)");
            $stmt->execute([$newValue]);
            $record_ID = $pdo->lastInsertId();
            break;

        case 'room':
            $roomNumber = strtoupper(trim($newValue));
            $check = $pdo->prepare("SELECT room_ID FROM room WHERE room_number = ? AND room_status = 'active'");
            $check->execute([$roomNumber]);
            if ($check->fetch()) {
                echo json_encode(["success" => false, "message" => "Room already exists."]);
                exit;
            }

            // Use room number as QR value
            $qr_value = $roomNumber;

            // Generate QR code
            $qrCode = new QrCode($qr_value);
            $writer = new PngWriter();
            $qrImage = $writer->write($qrCode);

            $filename = 'qrcodes/room_' . preg_replace('/\s+/', '_', $roomNumber) . '_' . uniqid() . '.png';
            $fullPath = BASE_STORAGE_PATH . $filename;
            file_put_contents($fullPath, $qrImage->getString());

            // Insert into qr_code
            $stmt = $pdo->prepare("INSERT INTO qr_code (qr_image_path) VALUES (?)");
            $stmt->execute([$filename]);
            $qr_ID = $pdo->lastInsertId();

            // Insert room
            $stmt = $pdo->prepare("INSERT INTO room (room_number, room_qr_value, room_qr_ID, room_status) VALUES (?, ?, ?, 'active')");
            $stmt->execute([$roomNumber, $qr_value, $qr_ID]);
            $record_ID = $pdo->lastInsertId();
            break;

        default:
            echo json_encode(["success" => false, "message" => "Invalid reference type"]);
            exit;
    }

    // Log the insertion
    logActivity(
        $pdo,
        $_SESSION['user']['user_ID'] ?? null,
        "INSERT",
        $moduleName,
        $record_ID,
        "$moduleName '$newValue' inserted"
    );

    echo json_encode(["success" => true, "message" => ucfirst($selectedType) . " added successfully."]);

} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Error: " . $e->getMessage()]);
}
?>