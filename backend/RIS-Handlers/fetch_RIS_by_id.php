<?php
require_once __DIR__ . '/../conn.php';

if (!isset($_GET['ris_ID'])) {
    echo json_encode(["status" => "error", "message" => "Missing ris_ID"]);
    exit;
}

$ris_ID = $_GET['ris_ID'];

try {
    // Get RIS header info
    $stmt = $pdo->prepare("
        SELECT 
            r.ris_ID,
            r.ris_no, 
            rt.ris_tag_name AS ris_type, 
            u.unit_name AS office_unit,
            r.ris_status,
            r.created_at
        FROM requisition_and_issue r
        JOIN ris_tag_type rt ON r.ris_tag_ID = rt.ris_tag_ID
        JOIN account a ON r.account_ID = a.account_ID
        JOIN user us ON a.user_ID = us.user_ID
        JOIN unit u ON us.unit_ID = u.unit_ID
        WHERE r.ris_ID = ?
    ");
    $stmt->execute([$ris_ID]);
    $header = $stmt->fetch();

    // Get RIS assets
    $stmt2 = $pdo->prepare("
        SELECT ris_asset_ID, asset_property_no, UOM, asset_description, 
               quantity_requisition, quantity_issuance, ris_remarks
        FROM ris_assets
        WHERE ris_ID = ?
    ");
    $stmt2->execute([$ris_ID]);
    $items = $stmt2->fetchAll();

    // Get RIS consumables
    $stmt3 = $pdo->prepare("
        SELECT rc.ris_consumable_ID, c.kld_property_tag, c.consumable_name, 
               rc.consumable_description, rc.UOM, 
               rc.quantity_requisition, rc.quantity_issuance, rc.ris_remarks
        FROM ris_consumables rc
        JOIN consumable c ON rc.consumable_ID = c.consumable_ID
        WHERE rc.ris_ID = ?
    ");
    $stmt3->execute([$ris_ID]);
    $consumables = $stmt3->fetchAll();

    // Echo only once
    echo json_encode([
        "status" => "success",
        "header" => $header,
        "items"  => $items,
        "consumables" => $consumables
    ]);

} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>
