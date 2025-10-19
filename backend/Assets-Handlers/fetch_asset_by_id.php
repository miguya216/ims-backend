<?php
require_once __DIR__ . '/../conn.php';

class Details {
    private $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }

    public function getAssetById($asset_ID) {
        $stmt = $this->pdo->prepare("
            SELECT 
                a.asset_ID,
                a.kld_property_tag,
                a.property_tag,
                a.asset_status,

                ac.asset_condition_ID,
                ac.condition_name AS asset_condition,

                b.brand_ID,
                b.brand_name,

                at.asset_type_ID,
                at.asset_type,

                u.user_ID,
                CONCAT(u.f_name, ' ', COALESCE(u.m_name, ''), ' ', u.l_name) AS responsible,

                un.unit_ID,
                un.unit_name AS responsible_unit,

                a_source.a_source_ID,
                a_source.a_source_name,

                r.room_ID,
                r.room_number,

                a.date_acquired,
                a.serviceable_year,
                a.price_amount,
                a.is_borrowable,

                bc.barcode_image_path,
                qr.qr_image_path

            FROM asset a
            JOIN brand b ON a.brand_ID = b.brand_ID
            JOIN asset_type at ON a.asset_type_ID = at.asset_type_ID
            JOIN user u ON a.responsible_user_ID = u.user_ID
            JOIN unit un ON u.unit_ID = un.unit_ID
            JOIN barcode bc ON a.barcode_ID = bc.barcode_ID
            JOIN qr_code qr ON a.qr_ID = qr.qr_ID
            JOIN asset_condition ac ON a.asset_condition_ID = ac.asset_condition_ID
            JOIN acquisition_source a_source ON a.a_source_ID = a_source.a_source_ID
            LEFT JOIN room r ON a.room_ID = r.room_ID 
            WHERE a.asset_ID = :asset_ID
        ");

        $stmt->execute(['asset_ID' => $asset_ID]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

// Handle request
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $details = new Details();
    $asset = $details->getAssetById($_GET['id']);
    
    if ($asset) {
        echo json_encode($asset);
    } else {
        echo json_encode(["message" => "Asset not found."]);
    }
} else {
    echo json_encode(["message" => "Invalid or missing asset ID."]);
}
?>