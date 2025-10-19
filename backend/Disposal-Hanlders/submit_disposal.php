<?php
session_start();
require_once __DIR__ . '/../conn.php';
header("Content-Type: application/json");

try {
    $data = json_decode(file_get_contents("php://input"), true);

    if (!$data || !isset($data['assets']) || empty($data['assets'])) {
        echo json_encode(["success" => false, "message" => "No assets provided."]);
        exit;
    }

    $user_ID = $_SESSION['user']['user_ID'];

    $pdo->beginTransaction();

    // Insert placeholder disposal
    $stmt = $pdo->prepare("INSERT INTO disposal (disposal_no, user_ID) VALUES (?, ?)");
    $stmt->execute(["TEMP", $user_ID]);
    $disposal_id = $pdo->lastInsertId();

    // Build disposal_no
    $yearTwoDigits = date("y");
    $disposal_no = "DSP-" . $yearTwoDigits . "-" . str_pad($disposal_id, 5, "0", STR_PAD_LEFT);

    // Update disposal_no
    $stmtUpdateNo = $pdo->prepare("UPDATE disposal SET disposal_no = ? WHERE disposal_id = ?");
    $stmtUpdateNo->execute([$disposal_no, $disposal_id]);

    // Prepare statements
    $stmtAsset = $pdo->prepare("INSERT INTO disposal_asset (disposal_id, asset_ID) VALUES (?, ?)");
    $stmtUpdateAsset = $pdo->prepare("UPDATE asset SET asset_status = 'inactive' WHERE asset_ID = ?");
    $stmtGetPropertyCard = $pdo->prepare("SELECT property_card_ID FROM property_card WHERE asset_ID = ?");
    $stmtGetAssetPrice = $pdo->prepare("SELECT price_amount FROM asset WHERE asset_ID = ?");
    $stmtInsertRecord = $pdo->prepare("
        INSERT INTO property_card_record 
        (property_card_ID, reference_type, reference_ID, officer_user_ID, price_amount, remarks)
        VALUES (?, 'DSP', ?, ?, ?, 'Disposed')
    ");

    foreach ($data['assets'] as $asset) {
        if (!isset($asset['asset_ID']) || empty($asset['asset_ID'])) continue;
        $asset_ID = $asset['asset_ID'];

        // Insert into disposal_asset
        $stmtAsset->execute([$disposal_id, $asset_ID]);

        // Mark asset inactive
        $stmtUpdateAsset->execute([$asset_ID]);

        // Get property card
        $stmtGetPropertyCard->execute([$asset_ID]);
        $propertyCard = $stmtGetPropertyCard->fetch(PDO::FETCH_ASSOC);

        if ($propertyCard) {
            $property_card_ID = $propertyCard['property_card_ID'];

            // Get asset price
            $stmtGetAssetPrice->execute([$asset_ID]);
            $assetData = $stmtGetAssetPrice->fetch(PDO::FETCH_ASSOC);
            $price_amount = $assetData ? $assetData['price_amount'] : 0;

            // Insert property card record
            $stmtInsertRecord->execute([$property_card_ID, $disposal_no, $user_ID, $price_amount]);
        }
    }

    $pdo->commit();

    echo json_encode([
        "success" => true,
        "message" => "Disposal request created successfully.",
        "disposal_no" => $disposal_no
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode([
        "success" => false,
        "message" => "Error: " . $e->getMessage()
    ]);
}
?>