<?php
session_start();
require_once __DIR__ . '/../conn.php';

if (!isset($_GET['room_ID'])) {
    http_response_code(400);
    echo json_encode(["error" => "Missing room_ID"]);
    exit;
}

$room_ID = intval($_GET['room_ID']); // sanitize input

try {
    $sql = "
        SELECT 
            a.asset_ID,
            a.kld_property_tag,
            a.property_tag,
            b.brand_name,
            at.asset_type,
            a.price_amount,
            ac.condition_name
        FROM asset a
        LEFT JOIN brand b 
            ON a.brand_ID = b.brand_ID
        LEFT JOIN asset_type at 
            ON a.asset_type_ID = at.asset_type_ID
        LEFT JOIN asset_condition ac
            ON a.asset_condition_ID = ac.asset_condition_ID
        WHERE a.room_ID = :room_ID
          AND a.asset_status = 'active'
        ORDER BY a.asset_ID ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':room_ID' => $room_ID]);

    $assets = [];
    while ($row = $stmt->fetch()) {
        $assets[] = [
            "asset_ID"        => (int)$row["asset_ID"],
            "property_tag"    => $row["property_tag"],
            "kld_property_tag"=> $row["kld_property_tag"],
            "brand_name"      => $row["brand_name"],
            "asset_type"      => $row["asset_type"],
            "price_amount"    => number_format($row["price_amount"], 2),
            "condition"       => $row["condition_name"]
        ];
    }

    header("Content-Type: application/json");
    echo json_encode($assets);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}
?>