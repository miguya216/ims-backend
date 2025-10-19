<?php
header("Content-Type: application/json");
require_once __DIR__ . "/../conn.php";

try {
    // Fetch assets that can be borrowed
    $stmt = $pdo->prepare("
        SELECT 
            a.asset_ID,
            a.kld_property_tag,
            a.property_tag,
            b.brand_name,
            at.asset_type,
            u.unit_name,
            ac.condition_name,
            a.asset_status
        FROM asset a
        LEFT JOIN brand b ON a.brand_ID = b.brand_ID
        LEFT JOIN asset_type at ON a.asset_type_ID = at.asset_type_ID
        LEFT JOIN unit u ON a.room_ID = u.unit_ID
        LEFT JOIN asset_condition ac ON a.asset_condition_ID = ac.asset_condition_ID
        WHERE a.asset_status = 'active' AND a.is_borrowable = 'yes'
    ");

    $stmt->execute();
    $assets = $stmt->fetchAll();

    echo json_encode([
        "success" => true,
        "data" => $assets
    ]);
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "error" => $e->getMessage()
    ]);
}
?>