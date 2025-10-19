<?php
header("Content-Type: application/json");
require_once __DIR__ . '/../conn.php';

if (!isset($_GET['room_assignation_id'])) {
    echo json_encode(["success" => false, "error" => "Missing room_assignation_id"]);
    exit;
}

$room_assignation_id = intval($_GET['room_assignation_id']);

try {
    // ===== 1. Fetch Assignation Header =====
    $sql = "
        SELECT 
            ra.room_assignation_ID,
            ra.room_assignation_no,
            fr.room_number AS from_room,
            tr.room_number AS to_room,
            CONCAT(u.f_name, ' ', u.l_name) AS moved_by,
            ra.moved_at
        FROM room_assignation ra
        LEFT JOIN room fr ON ra.from_room_ID = fr.room_ID
        INNER JOIN room tr ON ra.to_room_ID = tr.room_ID
        INNER JOIN user u ON ra.moved_by = u.user_ID
        WHERE ra.room_assignation_ID = ?
          AND ra.log_status = 'active'
        LIMIT 1
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$room_assignation_id]);
    $assignation = $stmt->fetch();

    if (!$assignation) {
        echo json_encode(["success" => false, "error" => "Room assignation not found"]);
        exit;
    }

    // ===== 2. Fetch Assets =====
    $sqlAssets = "
        SELECT 
            a.asset_ID,
            a.price_amount,
            a.kld_property_tag,
            CONCAT(b.brand_name, ' ', at.asset_type) AS description,
            ra.current_asset_conditon AS condition_name
        FROM room_a_asset ra
        INNER JOIN asset a ON ra.asset_ID = a.asset_ID
        LEFT JOIN brand b ON a.brand_ID = b.brand_ID
        LEFT JOIN asset_type at ON a.asset_type_ID = at.asset_type_ID
        WHERE ra.room_assignation_ID = ?
    ";
    $stmtAssets = $pdo->prepare($sqlAssets);
    $stmtAssets->execute([$room_assignation_id]);
    $assets = $stmtAssets->fetchAll();

    // ===== Response =====
    echo json_encode([
        "success" => true,
        "assignation" => $assignation,
        "assets" => $assets
    ]);
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "error" => $e->getMessage()
    ]);
}
?>
