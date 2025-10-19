<?php
require_once __DIR__ . '/../conn.php';

header("Content-Type: application/json");

// get consumable_ID from fetch request (GET or POST)
$consumableId = isset($_GET['consumable_ID']) ? intval($_GET['consumable_ID']) : 0;

if ($consumableId <= 0) {
    echo json_encode(["error" => "Invalid consumable_ID"]);
    exit;
}

try {
    $sql = "
        SELECT 
            c.consumable_ID,
            c.kld_property_tag,
            c.consumable_name,
            c.description,
            sc.stock_card_ID,
            scr.record_ID,
            scr.record_date,
            scr.reference_type,
            scr.reference_ID,
            scr.officer_user_ID,
            CONCAT(u.f_name, ' ', IFNULL(u.m_name, ''), ' ', u.l_name) AS officer_name,
            scr.quantity_in,
            scr.quantity_out,
            scr.balance,
            scr.remarks
        FROM consumable c
        INNER JOIN stock_card sc 
            ON c.consumable_ID = sc.consumable_ID
        LEFT JOIN stock_card_record scr 
            ON sc.stock_card_ID = scr.stock_card_ID
        LEFT JOIN user u
            ON scr.officer_user_ID = u.user_ID
        WHERE c.consumable_ID = :consumable_ID
        ORDER BY scr.record_date ASC, scr.record_ID ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['consumable_ID' => $consumableId]);
    $rows = $stmt->fetchAll();

    echo json_encode([
        "success" => true,
        "consumable_ID" => $consumableId,
        "data" => $rows
    ]);

} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "error" => $e->getMessage()
    ]);
}
?>
