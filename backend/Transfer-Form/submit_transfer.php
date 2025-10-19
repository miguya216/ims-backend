<?php
header("Content-Type: application/json");
require_once __DIR__ . '/../conn.php';
require_once __DIR__ . '/../Notification-Handlers/notif_config.php';

$conn = $pdo;

// Read raw JSON input
$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['fromUser'], $data['toUser'], $data['assets'], $data['transferType']) || !is_array($data['assets'])) {
    echo json_encode(["success" => false, "message" => "Invalid request data."]);
    exit;
}

$fromUser = intval($data['fromUser']);
$toUser   = intval($data['toUser']);
$transferType = intval($data['transferType']);
$assets   = $data['assets'];

// Validation
if ($fromUser === $toUser) {
    echo json_encode(["success" => false, "message" => "From and To users cannot be the same."]);
    exit;
}
if (count($assets) === 0) {
    echo json_encode(["success" => false, "message" => "No assets selected for transfer."]);
    exit;
}
if ($transferType === 0) {
    echo json_encode(["success" => false, "message" => "Transfer type must be selected."]);
    exit;
}

try {
    $conn->beginTransaction();

    // ensure session available to identify admin who initiated (if you store it in session)
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    //  1. Insert into property_transfer
    $sqlInsertTransfer = "INSERT INTO property_transfer (from_accounted_user_ID, to_accounted_user_ID) 
                          VALUES (?, ?)";
    $stmtInsert = $conn->prepare($sqlInsertTransfer);
    $stmtInsert->execute([$fromUser, $toUser]);
    $ptrID = $conn->lastInsertId();

    // 2. Generate ptr_no: PTR-YY-000000ptr_ID
    $year = date('y'); // last two digits of the year
    $ptrNo = 'PTR-' . $year . '-' . str_pad($ptrID, 6, '0', STR_PAD_LEFT);

    $sqlUpdateNo = "UPDATE property_transfer SET ptr_no = ? WHERE ptr_ID = ?";
    $stmtUpdateNo = $conn->prepare($sqlUpdateNo);
    $stmtUpdateNo->execute([$ptrNo, $ptrID]);


    //  3. Fetch transfer type name
    $stmtTransferType = $conn->prepare("SELECT transfer_type_name FROM transfer_type WHERE transfer_type_ID = ?");
    $stmtTransferType->execute([$transferType]);
    $transferTypeName = $stmtTransferType->fetchColumn();
    if (!$transferTypeName) {
        throw new Exception("Invalid transfer type.");
    }

    //  4. Insert into ptr_asset for each asset
    $sqlInsertPtrAsset = "INSERT INTO ptr_asset (ptr_ID, asset_ID, current_condition) 
                        VALUES (?, ?, ?)";
    $stmtPtrAsset = $conn->prepare($sqlInsertPtrAsset);

    // For property_card_record insertion
    $sqlGetAssetDetails = "SELECT a.asset_ID, a.price_amount, c.condition_name
                        FROM asset a
                        JOIN asset_condition c ON a.asset_condition_ID = c.asset_condition_ID
                        WHERE a.kld_property_tag = ? AND a.responsible_user_ID = ?";
    $stmtGetAsset = $conn->prepare($sqlGetAssetDetails);

    $sqlInsertRecord = "INSERT INTO property_card_record 
        (property_card_ID, reference_type, reference_ID, officer_user_ID, price_amount, remarks) 
        VALUES (?, 'PTR', ?, ?, ?, ?)";
    $stmtInsertRecord = $conn->prepare($sqlInsertRecord);

    foreach ($assets as $tag) {
        // Get asset details including condition
        $stmtGetAsset->execute([$tag, $fromUser]);
        $asset = $stmtGetAsset->fetch(PDO::FETCH_ASSOC);

        if ($asset) {
            $assetID = $asset['asset_ID'];
            $priceAmount = $asset['price_amount'];
            $conditionName = $asset['condition_name'];

            // Insert into ptr_asset with condition
            $stmtPtrAsset->execute([$ptrID, $assetID, $conditionName]);

            // Get property_card_ID
            $stmtCard = $conn->prepare("SELECT property_card_ID FROM property_card WHERE asset_ID = ?");
            $stmtCard->execute([$assetID]);
            $propertyCardID = $stmtCard->fetchColumn();

            if ($propertyCardID) {
                // Insert record into property_card_record
                $stmtInsertRecord->execute([$propertyCardID, $ptrNo, $toUser, $priceAmount, $transferTypeName]);
            }
        }
    }

    //  5. Update asset table
    $placeholders = implode(',', array_fill(0, count($assets), '?'));
    $sqlUpdateAsset = "UPDATE asset 
                       SET responsible_user_ID = ?, transfer_type_ID = ? 
                       WHERE kld_property_tag IN ($placeholders) 
                       AND responsible_user_ID = ?";
    $stmtUpdateAsset = $conn->prepare($sqlUpdateAsset);
    $params = array_merge([$toUser, $transferType], $assets, [$fromUser]);
    $stmtUpdateAsset->execute($params);

    if ($stmtUpdateAsset->rowCount() === 0) {
        $conn->rollBack();
        echo json_encode(["success" => false, "message" => "No matching assets found or transfer not needed."]);
        exit;
    }

    $conn->commit();
    // --- after $conn->commit(); ---

    // Get admin/sender account ID from session (if available)
    $adminAccountID = null;
    if (isset($_SESSION['user']['account_ID'])) {
        $adminAccountID = intval($_SESSION['user']['account_ID']);
    }

    // Fetch proper full names (your user table has f_name and l_name)
    $fromUserStmt = $conn->prepare("SELECT CONCAT(f_name, ' ', l_name) AS full_name FROM user WHERE user_ID = ?");
    $fromUserStmt->execute([$fromUser]);
    $fromName = $fromUserStmt->fetchColumn();
    $fromName = $fromName ?: "User";

    $toUserStmt = $conn->prepare("SELECT CONCAT(f_name, ' ', l_name) AS full_name FROM user WHERE user_ID = ?");
    $toUserStmt->execute([$toUser]);
    $toName = $toUserStmt->fetchColumn();
    $toName = $toName ?: "User";

    // Prepare message pieces
    $title = "Property Transfer Notification";
    $module = "PTR";
    $assetCount = count($assets);

    // Message for recipient (toUser)
    $messageRecipient = "{$fromName} has transferred {$assetCount} asset(s) to you via {$transferTypeName} (PTR No. {$ptrNo}). Please check your assets.";

    // Message for original owner (fromUser) — informing them assets were moved/removed
    $messageFromUser = "Your {$assetCount} asset(s) were transferred to {$toName} via {$transferTypeName} (PTR No. {$ptrNo}). If this is incorrect, contact your administrator.";

    // Resolve account IDs for recipient and original owner
    $getAccountStmt = $conn->prepare("SELECT account_ID FROM account WHERE user_ID = ? LIMIT 1");

    // recipient account id
    $getAccountStmt->execute([$toUser]);
    $recipientAccountID = $getAccountStmt->fetchColumn();

    // fromUser's account id (original owner)
    $getAccountStmt->execute([$fromUser]);
    $fromUserAccountID = $getAccountStmt->fetchColumn();

    // Send notification to recipient (toUser)
    if ($recipientAccountID) {
        sendNotification(
            $conn,
            $title,
            $messageRecipient,
            intval($recipientAccountID),
            $adminAccountID,   // sender (admin) if available, else NULL
            $module,
            $ptrNo
        );
    } else {
        error_log("PTR Notification: recipient account not found for user_ID={$toUser} (ptr_no={$ptrNo})");
    }

    // Send notification to original owner (fromUser)
    if ($fromUserAccountID) {
        sendNotification(
            $conn,
            $title,
            $messageFromUser,
            intval($fromUserAccountID),
            $adminAccountID,
            $module,
            $ptrNo
        );
    } else {
        error_log("PTR Notification: from-user account not found for user_ID={$fromUser} (ptr_no={$ptrNo})");
    }

    echo json_encode([
        "success" => true,
        "message" => "Assets transferred successfully.",
        "ptr_no" => $ptrNo
    ]);

} catch (Exception $e) {
    $conn->rollBack();
    echo json_encode(["success" => false, "message" => "Error: " . $e->getMessage()]);
}
?>
