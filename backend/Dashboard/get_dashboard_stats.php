<?php
require_once __DIR__ . '/../conn.php';
header("Content-Type: application/json; charset=UTF-8");

try {
    // Total Assets
    $stmt = $pdo->query("SELECT COUNT(*) AS total_assets FROM asset WHERE asset_status != 'inactive'");
    $totalAssets = $stmt->fetchColumn();

    // Total Consumables
    $stmt = $pdo->query("SELECT COUNT(*) AS total_consumables FROM consumable WHERE consumable_status = 'active'");
    $totalConsumables = $stmt->fetchColumn();

    // Total Users
    $stmt = $pdo->query("SELECT COUNT(*) AS total_users FROM user WHERE user_status = 'active'");
    $totalUsers = $stmt->fetchColumn();

    // Requisition and Issue counts
    $reqCounts = $pdo->query("
        SELECT 
            SUM(ris_status = 'pending') AS pending,
            SUM(ris_status = 'cancelled') AS cancelled,
            SUM(ris_status = 'issuing') AS issuing
        FROM requisition_and_issue
    ")->fetch(PDO::FETCH_ASSOC);

    // Reservation and Borrowing counts
    $brsCounts = $pdo->query("
        SELECT 
            SUM(brs_status = 'pending') AS pending,
            SUM(brs_status = 'cancelled') AS cancelled,
            SUM(brs_status = 'issuing') AS issuing
        FROM reservation_borrowing
    ")->fetch(PDO::FETCH_ASSOC);

    // Asset by Status
    $assetByStatus = $pdo->query("
        SELECT asset_status, COUNT(*) AS count 
        FROM asset 
        GROUP BY asset_status
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Assets by Type
    $assetByType = $pdo->query("
        SELECT at.asset_type, COUNT(a.asset_ID) AS count
        FROM asset a
        JOIN asset_type at ON a.asset_type_ID = at.asset_type_ID
        GROUP BY at.asset_type
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Assets by Brand
    $assetByBrand = $pdo->query("
        SELECT b.brand_name, COUNT(a.asset_ID) AS count
        FROM asset a
        JOIN brand b ON a.brand_ID = b.brand_ID
        GROUP BY b.brand_name
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Consumables by Status
    $consumablesByStatus = $pdo->query("
        SELECT consumable_status, COUNT(*) AS count 
        FROM consumable 
        GROUP BY consumable_status
    ")->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "summary" => [
            "totalAssets" => $totalAssets,
            "totalConsumables" => $totalConsumables,
            "totalUsers" => $totalUsers
        ],
        "requisition" => $reqCounts,
        "borrowing" => $brsCounts,
        "assetByStatus" => $assetByStatus,
        "assetByType" => $assetByType,
        "assetByBrand" => $assetByBrand,
        "consumablesByStatus" => $consumablesByStatus
    ]);

} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "error" => $e->getMessage()
    ]);
}
?>
