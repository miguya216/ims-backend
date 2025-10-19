<?php

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../conn.php';

class RefData {
    private $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }

    public function getAllRoles($status) {
        $stmt = $this->pdo->prepare("SELECT role_ID, role_name, role_status FROM role WHERE role_status = :status");
        $stmt->execute(['status' => $status]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    public function getAllUnits($status) {
        $stmt = $this->pdo->prepare("SELECT unit_ID, unit_name, unit_status FROM unit WHERE unit_status = :status");
        $stmt->execute(['status' => $status]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    public function getAllBrands($status) {
        $stmt = $this->pdo->prepare("
            SELECT brand.brand_ID, brand.brand_name, brand.asset_type_ID, asset_type.asset_type 
            FROM brand 
            JOIN asset_type ON brand.asset_type_ID = asset_type.asset_type_ID
            WHERE brand.brand_status = :status AND asset_type.asset_type_status = 'active'
        ");
        $stmt->execute(['status' => $status]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    public function getAllAssetTypes($status) {
        $stmt = $this->pdo->prepare("SELECT asset_type_ID, asset_type, asset_type_status FROM asset_type WHERE asset_type_status = :status");
        $stmt->execute(['status' => $status]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    public function getAllRooms($status) {
        $stmt = $this->pdo->prepare("SELECT room_ID, room_number, room_qr_value, room_qr_ID, room_status FROM room WHERE room_status = :status");
        $stmt->execute(['status' => $status]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAllAssetConditions($status) {
        $stmt = $this->pdo->prepare("SELECT asset_condition_ID, condition_name, asset_condition_status FROM asset_condition WHERE asset_condition_status = :status");
        $stmt->execute(['status' => $status]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAllAcquisitionSource($status) {
        $stmt = $this->pdo->prepare("SELECT a_source_ID, a_source_name, a_source_status FROM acquisition_source WHERE a_source_status = :status");
        $stmt->execute(['status' => $status]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAllTransferType($status) {
        $stmt = $this->pdo->prepare("SELECT transfer_type_ID, transfer_type_name, transfer_type_status FROM transfer_type WHERE transfer_type_status = :status");
        $stmt->execute(['status' => $status]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAllLogs() {
        $stmt = $this->pdo->prepare("SELECT log_ID, log_content FROM logs ORDER BY log_ID DESC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// === Dispatcher Logic ===
$refData = new RefData();
$action = $_GET['action'] ?? '';
$status = $_GET['status'] ?? 'active'; 

switch ($action) {
    case 'roles':
        echo json_encode($refData->getAllRoles($status));
        break;
    case 'units':
        echo json_encode($refData->getAllUnits($status));
        break;
    case 'brands':
        echo json_encode($refData->getAllBrands($status));
        break;
    case 'asset_types':
        echo json_encode($refData->getAllAssetTypes($status));
        break;
    case 'rooms':
        echo json_encode($refData->getAllRooms($status));
        break;
    case 'asset_conditions':
        echo json_encode($refData->getAllAssetConditions($status));
        break;
    case 'acquisition_sources':
        echo json_encode($refData->getAllAcquisitionSource($status));
        break;
    case 'transfer_types':
        echo json_encode($refData->getAllTransferType($status));
        break;
    case 'logs':
        echo json_encode($refData->getAllLogs());
        break;
    default:
        echo json_encode(['error' => 'Invalid action']);
        break;
}

?>
