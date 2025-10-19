<?php
header("Content-Type: application/json");
require_once __DIR__ . "/../conn.php";

if (!isset($_GET['disposal_id']) || empty($_GET['disposal_id'])) {
    echo json_encode([
        "status" => "error",
        "message" => "Missing disposal_id parameter"
    ]);
    exit;
}

$disposal_id = intval($_GET['disposal_id']);

try {
    // Fetch main disposal record with user, kld email, and unit
    $stmt = $pdo->prepare("
        SELECT d.disposal_id, d.disposal_no, d.created_at, d.disposal_status,
               u.user_ID, CONCAT(u.f_name, ' ', u.l_name) AS full_name,
               k.kld_email, un.unit_name
        FROM disposal d
        INNER JOIN user u ON d.user_ID = u.user_ID
        LEFT JOIN kld k ON u.kld_ID = k.kld_ID
        LEFT JOIN unit un ON u.unit_ID = un.unit_ID
        WHERE d.disposal_id = ?
    ");
    $stmt->execute([$disposal_id]);
    $disposal = $stmt->fetch();

    if (!$disposal) {
        echo json_encode([
            "status" => "error",
            "message" => "Disposal record not found"
        ]);
        exit;
    }

    // Fetch linked assets
    $stmtAssets = $pdo->prepare("
        SELECT a.asset_ID, a.kld_property_tag, a.property_tag,
               at.asset_type, b.brand_name
        FROM disposal_asset da
        INNER JOIN asset a ON da.asset_ID = a.asset_ID
        INNER JOIN brand b ON a.brand_ID = b.brand_ID
        INNER JOIN asset_type at ON a.asset_type_ID = at.asset_type_ID
        WHERE da.disposal_id = ?
    ");
    $stmtAssets->execute([$disposal_id]);
    $assets = $stmtAssets->fetchAll();

    echo json_encode([
        "status" => "success",
        "header" => [
            "disposal_no" => $disposal['disposal_no'],
            "date"        => $disposal['created_at'],
            "full_name"   => $disposal['full_name'],
            "kld_email"   => $disposal['kld_email'],
            "unit_name"   => $disposal['unit_name']
        ],
        "items" => array_map(function($row) {
            return [
                "kld_property_tag" => $row['kld_property_tag'],
                "property_tag"     => $row['property_tag'],
                "asset_type"       => $row['asset_type'],
                "brand"            => $row['brand_name']
            ];
        }, $assets)
    ]);

} catch (Exception $e) {
    echo json_encode([
        "status" => "error",
        "message" => "Database query failed: " . $e->getMessage()
    ]);
}
?>
