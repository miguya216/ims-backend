<?php
require_once __DIR__ . '/../conn.php';

// Check if ptr_ID is passed
if (!isset($_GET['ptr_id'])) {
    http_response_code(400);
    echo json_encode(["error" => "Missing ptr_id parameter"]);
    exit;
}

$ptr_id = intval($_GET['ptr_id']);

try {
    // 1. Fetch the main property transfer details
    $stmt = $pdo->prepare("
        SELECT 
            pt.ptr_ID,
            pt.ptr_no,
            pt.created_at,
            pt.ptr_status,
            u_from.user_ID AS from_user_ID,
            CONCAT(u_from.f_name, ' ', COALESCE(u_from.m_name, ''), ' ', u_from.l_name) AS from_user_name,
            u_to.user_ID AS to_user_ID,
            CONCAT(u_to.f_name, ' ', COALESCE(u_to.m_name, ''), ' ', u_to.l_name) AS to_user_name
        FROM property_transfer pt
        INNER JOIN user u_from ON pt.from_accounted_user_ID = u_from.user_ID
        INNER JOIN user u_to ON pt.to_accounted_user_ID = u_to.user_ID
        WHERE pt.ptr_ID = :ptr_id
    ");
    $stmt->execute(['ptr_id' => $ptr_id]);
    $transfer = $stmt->fetch();

    if (!$transfer) {
        http_response_code(404);
        echo json_encode(["error" => "Property transfer not found"]);
        exit;
    }

    // 2. Fetch all assets linked to this transfer
    $stmtAssets = $pdo->prepare("
        SELECT 
            a.asset_ID,
            a.kld_property_tag,
            a.property_tag,
            at.asset_type,
            b.brand_name,
            CONCAT(at.asset_type, ' - ', b.brand_name) AS description,
            tt.transfer_type_name,
            r.room_number,
            pa.current_condition AS condition_name, -- <-- use recorded condition at transfer time
            a.date_acquired,
            a.price_amount,
            a.serviceable_year
        FROM ptr_asset pa
        INNER JOIN asset a ON pa.asset_ID = a.asset_ID
        LEFT JOIN brand b ON a.brand_ID = b.brand_ID
        LEFT JOIN asset_type at ON a.asset_type_ID = at.asset_type_ID
        LEFT JOIN transfer_type tt ON a.transfer_type_ID = tt.transfer_type_ID
        LEFT JOIN room r ON a.room_ID = r.room_ID
        WHERE pa.ptr_ID = :ptr_id
    ");
    $stmtAssets->execute(['ptr_id' => $ptr_id]);
    $assets = $stmtAssets->fetchAll();



    // Combine results
    $response = [
        "transfer" => $transfer,
        "assets" => $assets
    ];

    header('Content-Type: application/json');
    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Server error: " . $e->getMessage()]);
}
?>