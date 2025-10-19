<?php
require_once __DIR__ . '/../conn.php';

try {
    $sql = "
        SELECT 
            a.property_tag,
            a.kld_property_tag AS kld_property_tag,
            s.a_source_name AS acquisition_source,
            CONCAT(b.brand_name, ' / ', t.asset_type) AS brand_asset_type,
            a.date_acquired AS date_acquired,
            a.price_amount AS price_amount,
            CONCAT(u.f_name, ' ', IFNULL(u.m_name, ''), ' ', u.l_name) AS accounted_to,
            bc.barcode_image_path AS barcode_path,
            qr.qr_image_path AS qr_code_path
        FROM asset a
        INNER JOIN acquisition_source s ON a.a_source_ID = s.a_source_ID
        INNER JOIN brand b ON a.brand_ID = b.brand_ID
        INNER JOIN asset_type t ON a.asset_type_ID = t.asset_type_ID
        INNER JOIN user u ON a.responsible_user_ID = u.user_ID
        INNER JOIN barcode bc ON a.barcode_ID = bc.barcode_ID
        INNER JOIN qr_code qr ON a.qr_ID = qr.qr_ID
        WHERE a.asset_status = 'active'
    ";

    $stmt = $pdo->query($sql);
    $assets = $stmt->fetchAll();

    header('Content-Type: application/json');
    echo json_encode($assets);

} catch (Exception $e) {
    echo json_encode(["error" => $e->getMessage()]);
}
?>