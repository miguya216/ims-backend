<?php
require_once __DIR__ . '/../conn.php';

if (!isset($_GET['user']) || !isset($_GET['room'])) {
    echo json_encode([]);
    exit;
}

$userID = intval($_GET['user']);
$roomID = intval($_GET['room']);

$stmt = $pdo->prepare("
    SELECT 
        a.date_acquired,
        at.asset_type,
        b.brand_name,
        a.kld_property_tag,
        a.price_amount AS unit_cost,
        ac.asset_condition_ID,
        ac.condition_name AS asset_condition
    FROM asset a
    JOIN asset_type at ON a.asset_type_ID = at.asset_type_ID
    JOIN brand b ON a.brand_ID = b.brand_ID
    JOIN asset_condition ac ON a.asset_condition_ID = ac.asset_condition_ID
    WHERE a.responsible_user_ID = :userID
      AND a.room_ID = :roomID
      AND a.asset_status = 'active'
");
$stmt->execute([
    'userID' => $userID,
    'roomID' => $roomID
]);

$assets = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($assets);
?>
