<?php
require_once __DIR__ . '/../conn.php';

header("Content-Type: application/json");

try {
    $stmt = $pdo->prepare("
        SELECT 
            pt.ptr_ID,
            pt.ptr_no AS ptr_number,
            pt.created_at AS transfer_date,
            CONCAT(u_from.f_name, ' ', u_from.l_name) AS from_accounted,
            CONCAT(u_to.f_name, ' ', u_to.l_name) AS to_accounted
        FROM property_transfer pt
        INNER JOIN user u_from ON pt.from_accounted_user_ID = u_from.user_ID
        INNER JOIN user u_to ON pt.to_accounted_user_ID = u_to.user_ID
        ORDER BY pt.created_at DESC
    ");
    $stmt->execute();
    $transfers = $stmt->fetchAll();

    echo json_encode([
        "success" => true,
        "data" => $transfers
    ]);
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
?>