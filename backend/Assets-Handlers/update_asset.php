<?php
require_once __DIR__ . '/../conn.php';
require_once __DIR__ . '/../logActivity.php';

session_start();
$account_ID = $_SESSION['user']['account_ID'] ?? null;

$input = json_decode(file_get_contents("php://input"), true);

if (!$input || !isset($input['asset_ID'])) {
    http_response_code(400);
    echo json_encode(["message" => "Invalid input."]);
    exit();
}

$asset_ID = $input['asset_ID'];
$kld_property_tag = trim($input['kld_property_tag'] ?? '');
$property_tag = trim($input['property_tag'] ?? '');
$brand_ID = $input['brand_ID'] ?? null;
$asset_condition_ID = $input['asset_condition_ID'] ?? null;
$responsible_user_ID = $input['user_ID'] ?? null;
$a_source_ID = $input['a_source_ID'] ?? null;
$room_ID = $input['room_ID'] ?? null;
$date_acquired = $input['date_acquired'] ?? null;
$price_amount = $input['price_amount'] ?? null;
$serviceable_year = $input['serviceable_year'] ?? null;
$is_borrowable = $input['is_borrowable'] ?? null;

// Validate serviceable_year (must be 4-digit year)
if ($serviceable_year !== null) {
    if (!preg_match('/^\d{4}$/', $serviceable_year)) {
        http_response_code(400);
        echo json_encode(["message" => "Serviceable year must be a 4-digit year (e.g., 2025)."]);
        exit();
    }
    $year = (int)$serviceable_year;
    $currentYear = (int)date("Y");
    if ($year < 1900 || $year > $currentYear + 50) {
        http_response_code(400);
        echo json_encode([
            "message" => "Serviceable year must be between 1900 and " . ($currentYear + 50) . "."
        ]);
        exit();
    }
}

// ✅ Validate ENUMs (defensive)
$valid_borrowable = ['yes', 'no'];

if ($is_borrowable !== null && !in_array($is_borrowable, $valid_borrowable)) {
    http_response_code(400);
    echo json_encode(["message" => "Invalid value for is_borrowable."]);
    exit();
}


try {
    $stmt = $pdo->prepare("
        UPDATE asset SET
            kld_property_tag = ?,
            property_tag = ?,
            brand_ID = ?,
            asset_condition_ID = ?,
            responsible_user_ID = ?,
            a_source_ID = ?,
            room_ID = ?,
            date_acquired = ?,
            price_amount = ?,
            serviceable_year = ?,
            is_borrowable = COALESCE(?, is_borrowable)
        WHERE asset_ID = ?
    ");

    $stmt->execute([
        $kld_property_tag,
        $property_tag,
        $brand_ID,
        $asset_condition_ID,
        $responsible_user_ID,
        $a_source_ID,
        $room_ID,
        $date_acquired,
        $price_amount,
        $serviceable_year,
        $is_borrowable,
        $asset_ID
    ]);

    // Log activity
    logActivity(
        $pdo,
        $account_ID,
        "UPDATE",
        "asset",
        $asset_ID,
        "Updated asset with Inventory Tag: $kld_property_tag, Serial Number: $property_tag"
    );

    echo json_encode(["message" => "Asset updated successfully."]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["message" => "Database error: " . $e->getMessage()]);
}
?>
