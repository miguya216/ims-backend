<?php
header("Content-Type: application/json");
require_once __DIR__ . '/../conn.php';

try {
    $sql = "
        SELECT 
            a.asset_ID,
            a.kld_property_tag,
            a.property_tag,
            a.date_acquired,
            a.price_amount,
            a.serviceable_year,
            a.asset_status,
            at.asset_type,
            b.brand_name,
            u.f_name,
            u.l_name,
            ac.condition_name
        FROM asset a
        LEFT JOIN asset_type at ON a.asset_type_ID = at.asset_type_ID
        LEFT JOIN brand b ON a.brand_ID = b.brand_ID
        LEFT JOIN user u ON a.responsible_user_ID = u.user_ID
        LEFT JOIN asset_condition ac ON a.asset_condition_ID = ac.asset_condition_ID
        WHERE a.asset_status = 'active' and a.asset_condition_ID = 3
        ORDER BY a.asset_ID DESC
    ";

    $stmt = $pdo->query($sql);
    $assets = [];

    while ($row = $stmt->fetch()) {
        $assets[] = [
            "asset_ID"        => $row["asset_ID"],
            "kld_property_tag"=> $row["kld_property_tag"],
            "property_tag"    => $row["property_tag"],
            "asset_type"      => $row["asset_type"],
            "brand_name"      => $row["brand_name"],
            "responsible"     => trim($row["f_name"] . " " . $row["l_name"]),
            "condition"       => $row["condition_name"],
            "date_acquired"   => $row["date_acquired"],
            "price_amount"    => $row["price_amount"],
            "serviceable_year"=> $row["serviceable_year"],
            "asset_status"    => $row["asset_status"]
        ];
    }

    echo json_encode(["assets" => $assets]);

} catch (Exception $e) {
    echo json_encode(["error" => $e->getMessage()]);
}
