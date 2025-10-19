<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../conn.php';

$asset_ID = $_GET['asset_ID'] ?? null;
if (!$asset_ID) {
    echo json_encode([]);
    exit;
}

try {
    $sql = "
        SELECT 
            pcr.record_ID,
            pcr.record_date,
            pcr.reference_type,
            pcr.reference_ID,
            CONCAT(officer.f_name, ' ', officer.l_name) AS officer_name,
            pcr.price_amount,
            pcr.remarks,
            CONCAT(responsible.f_name, ' ', responsible.l_name) AS responsible_user,
            u.unit_name,
            at.asset_type,
            b.brand_name,
            a.kld_property_tag
        FROM property_card_record pcr
        LEFT JOIN property_card pc ON pc.property_card_ID = pcr.property_card_ID
        LEFT JOIN user officer ON officer.user_ID = pcr.officer_user_ID
        LEFT JOIN asset a ON a.asset_ID = pc.asset_ID
        LEFT JOIN user responsible ON responsible.user_ID = a.responsible_user_ID
        LEFT JOIN unit u ON u.unit_ID = responsible.unit_ID
        LEFT JOIN asset_type at ON at.asset_type_ID = a.asset_type_ID
        LEFT JOIN brand b ON b.brand_ID = a.brand_ID
        WHERE pc.asset_ID = :asset_ID
        ORDER BY pcr.record_date ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['asset_ID' => $asset_ID]);
    $records = $stmt->fetchAll();

    echo json_encode($records);
} catch (PDOException $e) {
    echo json_encode([
        'error' => 'Failed to fetch records',
        'message' => $e->getMessage()
    ]);
}
?>
