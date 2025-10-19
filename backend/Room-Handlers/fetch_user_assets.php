<?php
session_start();
require_once __DIR__ . '/../conn.php';

if (!isset($_SESSION['user']['user_ID'])) {
    echo json_encode(["error" => "Unauthorized access"]);
    exit;
}

$user_ID = $_SESSION['user']['user_ID'];

$sql = "
    SELECT 
        a.asset_ID,
        a.kld_property_tag AS kld_property_tag,
        r.room_number AS room,
        b.brand_name AS brand,
        at.asset_type AS asset_type,
        a.price_amount AS price_amount,
        ac.condition_name AS asset_condition
    FROM asset a
    LEFT JOIN room r 
        ON a.room_ID = r.room_ID
    LEFT JOIN brand b 
        ON a.brand_ID = b.brand_ID
    LEFT JOIN asset_type at 
        ON a.asset_type_ID = at.asset_type_ID
    LEFT JOIN asset_condition ac 
        ON a.asset_condition_ID = ac.asset_condition_ID
    WHERE a.asset_status = 'active'
      AND a.responsible_user_ID = :user_ID
";

$stmt = $pdo->prepare($sql);
$stmt->execute(['user_ID' => $user_ID]);
$assets = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode($assets);
?>