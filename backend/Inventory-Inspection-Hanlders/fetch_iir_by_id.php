<?php
require_once __DIR__ . '/../conn.php';

header("Content-Type: application/json");

// Accept both ?iir_ID= and ?iir_id=
if (isset($_GET['iir_ID'])) {
    $iir_ID = intval($_GET['iir_ID']);
} elseif (isset($_GET['iir_id'])) {
    $iir_ID = intval($_GET['iir_id']);
}

if (!$iir_ID) {
    echo json_encode(["error" => "Missing iir_ID parameter"]);
    exit;
}

try {
   // Fetch IIR main details (role + unit, no room yet)
    $stmt = $pdo->prepare("
        SELECT 
            iir.iir_ID,
            iir.iir_no,
            iir.iir_status,
            iir.created_at,
            u.user_ID,
            CONCAT(u.f_name, ' ', IFNULL(u.m_name, ''), ' ', u.l_name) AS officer_name,
            r.role_name AS role,
            un.unit_name AS unit
        FROM inventory_inspection_report iir
        JOIN user u ON iir.user_ID = u.user_ID
        LEFT JOIN account acc ON acc.user_ID = u.user_ID
        LEFT JOIN role r ON acc.role_ID = r.role_ID
        LEFT JOIN unit un ON u.unit_ID = un.unit_ID
        WHERE iir.iir_ID = :iir_ID
    ");
    $stmt->execute([':iir_ID' => $iir_ID]);
    $report = $stmt->fetch();

    if (!$report) {
        echo json_encode(["error" => "Report not found"]);
        exit;
    }

    // Fetch assets (rooms are here)
    $stmtAssets = $pdo->prepare("
        SELECT 
            ia.iir_asset_ID,
            a.asset_ID,
            at.asset_type,
            b.brand_name,
            a.kld_property_tag,
            a.property_tag,
            a.date_acquired,
            a.price_amount AS unit_cost,
            r.room_number,
            ia.current_condition as condition_name,   -- use inspection-time condition
            ia.quantity,
            ia.total_cost,
            ia.accumulated_depreciation,
            ia.accumulated_impairment_losses,
            ia.carrying_amount,
            ia.sale,
            ia.transfer,
            ia.disposal,
            ia.damage,
            ia.others
        FROM iir_asset ia
        JOIN asset a ON ia.asset_ID = a.asset_ID
        LEFT JOIN asset_type at ON a.asset_type_ID = at.asset_type_ID
        LEFT JOIN brand b ON a.brand_ID = b.brand_ID
        LEFT JOIN room r ON a.room_ID = r.room_ID
        WHERE ia.iir_ID = :iir_ID
        ");

    $stmtAssets->execute([':iir_ID' => $iir_ID]);
    $assets = $stmtAssets->fetchAll();

    // Pick first room (or null)
    $report['room_no'] = $assets[0]['room_number'] ?? null;

    // Combine response
    echo json_encode([
        "iir" => $report,
        "assets" => $assets
    ]);


} catch (Exception $e) {
    echo json_encode(["error" => $e->getMessage()]);
}
?>