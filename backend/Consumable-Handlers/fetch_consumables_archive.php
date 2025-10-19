<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../conn.php';

try {
    $stmt = $pdo->prepare("
        SELECT 
            c.consumable_ID,
            c.kld_property_tag,
            c.consumable_name,
            c.description,
            c.unit_of_measure,
            c.total_quantity,
            c.price_amount,
            c.date_acquired
        FROM consumable c
        WHERE c.consumable_status = 'inactive'
        ORDER BY c.consumable_name ASC
    ");
    $stmt->execute();
    $consumables = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "status" => "success",
        "data" => $consumables
    ]);
} catch (PDOException $e) {
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
}
?>