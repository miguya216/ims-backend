<?php
require_once __DIR__ . '/../conn.php';

if (!isset($_GET['user_ID'])) {
    echo json_encode([]);
    exit;
}

$user_ID = intval($_GET['user_ID']);

try {
    $stmt = $pdo->prepare("
        SELECT 
            a.asset_ID,
            a.kld_property_tag, 
            CONCAT(at.asset_type, ' - ', b.brand_name) AS description,
            ac.condition_name,
            a.date_acquired,
            a.price_amount,
            tt.transfer_type_name
        FROM asset a
        JOIN asset_type at 
            ON a.asset_type_ID = at.asset_type_ID
        JOIN brand b 
            ON a.brand_ID = b.brand_ID
        JOIN asset_condition ac 
            ON a.asset_condition_ID = ac.asset_condition_ID
        LEFT JOIN transfer_type tt 
            ON a.transfer_type_ID = tt.transfer_type_ID
        WHERE a.responsible_user_ID = :user_ID
        AND a.asset_status = 'active';
    ");
    $stmt->execute(['user_ID' => $user_ID]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Database error: " . $e->getMessage()]);
}
?>
