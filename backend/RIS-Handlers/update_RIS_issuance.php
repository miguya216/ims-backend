<?php
// update_RIS_issuance.php
header("Content-Type: application/json");
require_once __DIR__ . '/../conn.php';
require_once __DIR__ . '/../Notification-Handlers/notif_config.php';
session_start();

try {
    $data = json_decode(file_get_contents("php://input"), true);

    if (!$data || !isset($data["ris_ID"])) {
        echo json_encode(["status" => "error", "message" => "Invalid input"]);
        exit;
    }

    $ris_ID = intval($data["ris_ID"]);
    $items = $data["items"] ?? [];
    $consumables = $data["consumables"] ?? [];
    $ris_status = isset($data["ris_status"]) ? trim($data["ris_status"]) : null;

    //Get RIS info (creator + ris_no)
    $stmt = $pdo->prepare("SELECT ris_no, account_ID FROM requisition_and_issue WHERE ris_ID = ?");
    $stmt->execute([$ris_ID]);
    $risInfo = $stmt->fetch();

    if (!$risInfo) {
        echo json_encode(["status" => "error", "message" => "RIS not found"]);
        exit;
    }

    $ris_no = $risInfo["ris_no"];
    $creatorID = $risInfo["account_ID"];
    $adminID = $_SESSION["account_ID"] ?? null; // optional, for logging only

    // Update ris_assets
    if (!empty($items)) {
        $stmt = $pdo->prepare("
            UPDATE ris_assets
            SET quantity_issuance = :quantity_issuance, ris_remarks = :ris_remarks
            WHERE ris_asset_ID = :ris_asset_ID AND ris_ID = :ris_ID
        ");

        foreach ($items as $item) {
            $stmt->execute([
                ":quantity_issuance" => $item["quantity_issuance"] ?? null,
                ":ris_remarks" => $item["ris_remarks"] ?? null,
                ":ris_asset_ID" => $item["item_id"],
                ":ris_ID" => $ris_ID
            ]);
        }
    }

    // Update ris_consumables + stock_card_record
    if (!empty($consumables)) {
        $stmtUpdateRIS = $pdo->prepare("
            UPDATE ris_consumables
            SET quantity_issuance = :quantity_issuance, ris_remarks = :ris_remarks
            WHERE ris_consumable_ID = :ris_consumable_ID AND ris_ID = :ris_ID
        ");

        foreach ($consumables as $c) {
            $stmtUpdateRIS->execute([
                ":quantity_issuance" => $c["quantity_issuance"] ?? 0,
                ":ris_remarks" => $c["ris_remarks"] ?? null,
                ":ris_consumable_ID" => $c["item_id"],
                ":ris_ID" => $ris_ID
            ]);
        }
    }

    // Update RIS status (if provided)
    if ($ris_status) {
        $stmt = $pdo->prepare("UPDATE requisition_and_issue SET ris_status = :status WHERE ris_ID = :ris_ID");
        $stmt->execute([":status" => $ris_status, ":ris_ID" => $ris_ID]);
    }

    // Notify creator (NOT admin)
    $title = "RIS Updated";
    $message = "Your Requisition and Issue Slip ({$ris_no}) has been updated. Status: " . strtoupper($ris_status ?: "unchanged") . ".";
    $module = "RIS";

    // Only send to creator (the one who made the RIS)
    sendNotification($pdo, $title, $message, $creatorID, $adminID, $module, $ris_no);

    echo json_encode([
        "status" => "success",
        "message" => "Issuance updated successfully (Status: " . ($ris_status ?: "unchanged") . ")"
    ]);

} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => "Server error: " . $e->getMessage()]);
}
?>
