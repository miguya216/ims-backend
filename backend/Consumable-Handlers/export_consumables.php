<?php
require_once __DIR__ . '/../conn.php';

try {
    $sql = "
        SELECT 
            c.kld_property_tag AS kld_property_tag,
            c.consumable_name AS consumable_name,
            c.description AS description,
            c.unit_of_measure AS unit_of_measure,
            c.total_quantity AS total_quantity,
            c.price_amount AS price_amount,
            c.date_acquired AS date_acquired,
            bc.barcode_image_path AS barcode_path,
            qr.qr_image_path AS qr_code_path
        FROM consumable c
        INNER JOIN barcode bc ON c.barcode_ID = bc.barcode_ID
        INNER JOIN qr_code qr ON c.qr_ID = qr.qr_ID
        WHERE c.consumable_status = 'active'
    ";

    $stmt = $pdo->query($sql);
    $consumables = $stmt->fetchAll();

    header('Content-Type: application/json');
    echo json_encode($consumables);

} catch (Exception $e) {
    echo json_encode(["error" => $e->getMessage()]);
}
?>
