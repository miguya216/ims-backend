<?php
// api/Reservation-Borrowing-Handlers/fetch_brs_by_id.php
require_once __DIR__ . "/../conn.php";
header("Content-Type: application/json; charset=UTF-8");

try {
    if (!isset($_GET['brs_ID']) || empty($_GET['brs_ID'])) {
        echo json_encode([
            "success" => false,
            "error" => "Missing brs_ID parameter"
        ]);
        exit;
    }

    $brs_ID = intval($_GET['brs_ID']);

    // Main reservation_borrowing details with user info
    $stmt = $pdo->prepare("
        SELECT 
            rb.brs_ID,
            rb.brs_no,
            rb.date_of_use,
            rb.time_of_use,
            rb.date_of_return,
            rb.time_of_return,
            rb.purpose,
            rb.brs_status,
            rb.created_at,
            u.user_ID,
            CONCAT(u.f_name, ' ', IFNULL(u.m_name,''), ' ', u.l_name) AS full_name,
            un.unit_name
        FROM reservation_borrowing rb
        INNER JOIN user u ON rb.user_ID = u.user_ID
        LEFT JOIN unit un ON u.unit_ID = un.unit_ID
        WHERE rb.brs_ID = :brs_ID
        LIMIT 1
    ");
    $stmt->execute(['brs_ID' => $brs_ID]);
    $reservation = $stmt->fetch();

    if (!$reservation) {
        echo json_encode([
            "success" => false,
            "error" => "Reservation not found"
        ]);
        exit;
    }

    // Fetch associated assets
    $stmtAssets = $pdo->prepare("
        SELECT 
            ba.brs_asset_ID,
            ba.asset_ID,
            a.property_tag,
            a.kld_property_tag,
            ba.qty_brs,
            ba.UOM_brs,
            ba.is_available,
            ba.qty_issuance,
            ba.borrow_asset_remarks,
            ba.return_asset_remarks,
            at.asset_type,
            b.brand_name
        FROM brs_asset ba
        INNER JOIN asset a ON ba.asset_ID = a.asset_ID
        INNER JOIN asset_type at ON a.asset_type_ID = at.asset_type_ID
        INNER JOIN brand b ON a.brand_ID = b.brand_ID
        WHERE ba.brs_ID = :brs_ID
    ");
    $stmtAssets->execute(['brs_ID' => $brs_ID]);
    $assets = $stmtAssets->fetchAll();

    echo json_encode([
        "success" => true,
        "reservation" => $reservation,
        "assets" => $assets
    ]);
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "error" => $e->getMessage()
    ]);
}
?>