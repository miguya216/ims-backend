<?php
header("Content-Type: application/json");
require_once __DIR__ . "/../conn.php";
require_once __DIR__ . "/../Notification-Handlers/notif_config.php";
session_start();

try {
    $data = json_decode(file_get_contents("php://input"), true);

    if (!$data || !isset($data['brs_ID'], $data['brs_status'], $data['assets'])) {
        echo json_encode(["success" => false, "message" => "Invalid input data."]);
        exit;
    }

    $brs_ID = intval($data['brs_ID']);
    $brs_status = $data['brs_status'];
    $assets = $data['assets'];
    $sender_account_ID = $_SESSION['user']['account_ID'] ?? null; // Admin or Super-Admin

    if (!$sender_account_ID) {
        echo json_encode(["success" => false, "message" => "User session missing or unauthorized."]);
        exit;
    }

    // === STEP 1: Fetch info for notification (brs_no & user_ID) ===
    $infoQuery = $pdo->prepare("SELECT brs_no, user_ID FROM reservation_borrowing WHERE brs_ID = :brs_ID");
    $infoQuery->execute([':brs_ID' => $brs_ID]);
    $brsInfo = $infoQuery->fetch(PDO::FETCH_ASSOC);

    if (!$brsInfo) {
        echo json_encode(["success" => false, "message" => "Reservation not found."]);
        exit;
    }

    $brs_no = $brsInfo['brs_no'];
    $recipient_user_ID = $brsInfo['user_ID']; // This is the form creator

    $pdo->beginTransaction();

    // === STEP 2: Update main reservation status ===
    $stmt = $pdo->prepare("UPDATE reservation_borrowing SET brs_status = ? WHERE brs_ID = ?");
    $stmt->execute([$brs_status, $brs_ID]);

    // === STEP 3: Update brs_asset and asset statuses ===
    $updateBrsAsset = $pdo->prepare("
        UPDATE brs_asset 
        SET 
            is_available = :is_available,
            qty_issuance = :qty_issuance,
            borrow_asset_remarks = :borrow_remarks,
            return_asset_remarks = :return_remarks
        WHERE brs_asset_ID = :brs_asset_ID
    ");

    $updateAssetStatus = $pdo->prepare("
        UPDATE asset 
        SET asset_status = :status 
        WHERE asset_ID = :asset_ID
    ");

    $fetchAssetID = $pdo->prepare("
        SELECT asset_ID 
        FROM brs_asset 
        WHERE brs_asset_ID = :brs_asset_ID
    ");

    foreach ($assets as $a) {
        $brs_asset_ID = $a['brs_asset_ID'];
        $qty_issuance = $a['qty_issuance'] ?? null;
        $is_available = $a['is_available'] ?? null;
        $borrow_remarks = $a['borrow_asset_remarks'] ?? null;
        $return_remarks = $a['return_asset_remarks'] ?? null;
        $enableReturnRemarks = $a['enableReturnRemarks'] ?? false;

        // Update brs_asset table
        $updateBrsAsset->execute([
            ':is_available'    => $is_available,
            ':qty_issuance'    => $qty_issuance,
            ':borrow_remarks'  => $borrow_remarks,
            ':return_remarks'  => $return_remarks,
            ':brs_asset_ID'    => $brs_asset_ID
        ]);

        // Fetch linked asset_ID
        $fetchAssetID->execute([':brs_asset_ID' => $brs_asset_ID]);
        $asset_ID = $fetchAssetID->fetchColumn();

        if ($asset_ID !== false) {
            $newStatus = null;

            if ($enableReturnRemarks && !empty($return_remarks)) {
                $newStatus = 'active';
            } elseif (!is_null($qty_issuance) && $qty_issuance !== "" && floatval($qty_issuance) > 0) {
                $newStatus = 'borrowed';
            } elseif (!is_null($qty_issuance) && floatval($qty_issuance) == 0) {
                $newStatus = 'active';
            }

            if ($newStatus) {
                $updateAssetStatus->execute([
                    ':status'   => $newStatus,
                    ':asset_ID' => $asset_ID
                ]);
            }
        }
    }

    $pdo->commit();

    // === STEP 4: Send notification to the user (form creator) ===
    $title = "Borrowing Request Updated";
    $message = "Your borrowing request ({$brs_no}) status has been updated to '{$brs_status}'.";
    $module = "BRS";

    sendNotification($pdo, $title, $message, $recipient_user_ID, $sender_account_ID, $module, $brs_no);

    echo json_encode([
        "success" => true,
        "message" => "Reservation and asset statuses updated successfully.",
        "notified_user" => $recipient_user_ID
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode([
        "success" => false,
        "message" => "Error updating reservation: " . $e->getMessage()
    ]);
}
?>
