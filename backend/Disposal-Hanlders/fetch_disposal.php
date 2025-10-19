<?php
require_once __DIR__ . '/../conn.php';

try {
    $sql = "
        SELECT 
            d.disposal_id,
            d.disposal_no,
            DATE(d.created_at) AS date,
            CONCAT(u.f_name, ' ', COALESCE(u.m_name, ''), ' ', u.l_name) AS full_name,
            k.kld_email,
            un.unit_name
        FROM disposal d
        INNER JOIN user u ON d.user_ID = u.user_ID
        LEFT JOIN kld k ON u.kld_ID = k.kld_ID
        LEFT JOIN unit un ON u.unit_ID = un.unit_ID
        ORDER BY d.disposal_id DESC
    ";

    $stmt = $pdo->query($sql);
    $disposals = $stmt->fetchAll();

    echo json_encode([
        "status" => "success",
        "data" => $disposals
    ]);
} catch (Exception $e) {
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
}
?>
