<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../conn.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid or missing ID"
    ]);
    exit;
}

$consumableId = intval($_GET['id']);

try {
    $sql = "
        SELECT 
            c.consumable_ID,
            c.kld_property_tag,
            c.consumable_name,
            c.description,
            c.unit_of_measure,
            c.total_quantity,
            c.price_amount,
            c.date_acquired,
            c.consumable_status,
            b.barcode_image_path,
            q.qr_image_path
        FROM consumable c
        LEFT JOIN barcode b ON c.barcode_ID = b.barcode_ID
        LEFT JOIN qr_code q ON c.qr_ID = q.qr_ID
        WHERE c.consumable_ID = :id
        LIMIT 1
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $consumableId]);
    $consumable = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($consumable) {
        echo json_encode([
            "status" => "success",
            "data" => $consumable
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "Consumable not found"
        ]);
    }
} catch (PDOException $e) {
    echo json_encode([
        "status" => "error",
        "message" => "Database error: " . $e->getMessage()
    ]);
}
?>