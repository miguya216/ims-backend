<?php
session_start();

require_once __DIR__ . '/conn.php';
header('Content-Type: application/json');

// if (!isset($_SESSION['user']['user_ID'])) {
//     echo json_encode(['error' => 'Not logged in']);
//     exit;
// }

$user_ID = $_SESSION['user']['user_ID'] ?? null;

class DropdownOptions {
    private $pdo;
    private $user_ID;

    public function __construct($pdo, $user_ID) {
        $this->pdo = $pdo;
        $this->user_ID = $user_ID; 
    }

    public function fetchOptions() {
        return [
            'brands' => $this->getBrands(),
            'asset_types' => $this->getAssetTypes(),
            'acquisition_sources' => $this->getAcquisitionSources(),
            'transfer_type' => $this->getTransferType(),
            'ris_tag_type' => $this->getAllRIStype(),
            'admin_assets' => $this->getAssetsByUser1(), 
            'custodian_assets' => $this->getAssetsByLoggedInUser(),
            // 'user_assets' => $this->getAllUserAssets(),
            'rooms' => $this->getRooms(),
            'units' => $this->getUnits(),
            'users' => $this->getNonBorrowerUsers(),
            'roles' => $this->getRoles(),
            'asset_conditions' => $this->getAssetConditions(),
            'consumables' => $this->getConsumables() 
        ];
    }

     private function getConsumables() {
        $stmt = $this->pdo->prepare("
            SELECT 
                c.consumable_ID,
                c.kld_property_tag,
                c.consumable_name,
                c.unit_of_measure,
                c.description,
                c.total_quantity,
                c.price_amount,
                c.date_acquired
            FROM consumable c
            WHERE c.consumable_status = 'active'
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getBrands() {
        $stmt = $this->pdo->prepare("SELECT brand_ID, brand_name FROM brand WHERE brand_status = 'active'");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getAssetTypes() {
        $stmt = $this->pdo->prepare("SELECT asset_type_ID, asset_type FROM asset_type WHERE asset_type_status = 'active'");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getAllRIStype() {
        $stmt = $this->pdo->prepare("SELECT ris_tag_ID, ris_tag_name FROM ris_tag_type WHERE ris_tag_status = 'active'");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // private function getAllAssets() {
    //     $stmt = $this->pdo->prepare("SELECT a.kld_property_tag, at.asset_type, b.brand_name
    //                                 FROM asset a
    //                                 JOIN asset_type at ON a.asset_type_ID = at.asset_type_ID
    //                                 JOIN brand b ON a.brand_ID = b.brand_ID
    //                                 WHERE a.asset_status = 'active'");
    //     $stmt->execute();
    //     return $stmt->fetchAll(PDO::FETCH_ASSOC);
    // }

    // Assets with user_ID = 1
    private function getAssetsByUser1() {
        $stmt = $this->pdo->prepare("
            SELECT a.kld_property_tag, at.asset_type, b.brand_name
            FROM asset a
            JOIN asset_type at ON a.asset_type_ID = at.asset_type_ID
            JOIN brand b ON a.brand_ID = b.brand_ID
            WHERE a.asset_status = 'active'
            AND a.responsible_user_ID = 1
            AND NOT EXISTS (
                SELECT 1 
                FROM ris_assets ra
                JOIN requisition_and_issue ri ON ri.ris_ID = ra.ris_ID
                WHERE ra.asset_property_no = a.kld_property_tag
                    AND ri.ris_status = 'pending'
            )
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    // Assets with logged-in user
    private function getAssetsByLoggedInUser() {
        $stmt = $this->pdo->prepare("
            SELECT a.kld_property_tag, at.asset_type, b.brand_name
            FROM asset a
            JOIN asset_type at ON a.asset_type_ID = at.asset_type_ID
            JOIN brand b ON a.brand_ID = b.brand_ID
            WHERE a.asset_status = 'active'
            AND a.responsible_user_ID = :user_ID
            AND NOT EXISTS (
                SELECT 1 
                FROM ris_assets ra
                JOIN requisition_and_issue ri ON ri.ris_ID = ra.ris_ID
                WHERE ra.asset_property_no = a.kld_property_tag
                    AND ri.ris_status = 'pending'
            )
        ");
        $stmt->execute(['user_ID' => $this->user_ID]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    private function getAcquisitionSources() {
        $stmt = $this->pdo->prepare("SELECT a_source_ID, a_source_name FROM acquisition_source WHERE a_source_status = 'active'");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getTransferType() {
        $stmt = $this->pdo->prepare("SELECT transfer_type_ID, transfer_type_name FROM transfer_type WHERE transfer_type_status = 'active'");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getRooms() {
        $stmt = $this->pdo->prepare("SELECT room_ID, room_number FROM room WHERE room_status = 'active'");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getUnits() {
        $stmt = $this->pdo->prepare("SELECT unit_ID, unit_name FROM unit WHERE unit_status = 'active'");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getRoles() {
        $stmt = $this->pdo->prepare("SELECT role_ID, role_name FROM role WHERE role_status = 'active'");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getNonBorrowerUsers() {
        $stmt = $this->pdo->prepare("
            SELECT 
                u.user_ID,
                TRIM(CONCAT_WS(' ', u.f_name, u.m_name, u.l_name)) AS full_name,
                r.role_name
            FROM user u
            LEFT JOIN account a ON a.user_ID = u.user_ID
            LEFT JOIN role r ON r.role_ID = a.role_ID AND r.role_status = 'active'
            WHERE u.user_status = 'active'
            AND (a.role_ID <> 2 OR a.role_ID IS NULL)
            ORDER BY u.l_name, u.f_name;
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getAssetConditions() {
        $stmt = $this->pdo->prepare("SELECT asset_condition_ID, condition_name FROM asset_condition WHERE asset_condition_status = 'active'");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

try {
    $dropdown = new DropdownOptions($pdo, $user_ID);
    echo json_encode($dropdown->fetchOptions());
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Database error: " . $e->getMessage()]);
}
?>