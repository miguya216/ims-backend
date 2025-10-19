<?php
// api/Reservation-Borrowing-Handlers/cancel_brs.php
require_once __DIR__ . "/../conn.php";
require_once __DIR__ . "/../Notification-Handlers/notif_config.php";
session_start();
header("Content-Type: application/json; charset=UTF-8");

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(["success" => false, "error" => "Invalid request method"]);
        exit;
    }

    $input = json_decode(file_get_contents("php://input"), true);

    if (!isset($input['brs_ID']) || empty($input['brs_ID'])) {
        echo json_encode(["success" => false, "error" => "Missing reservation ID"]);
        exit;
    }

    $brs_ID = intval($input['brs_ID']);
    $user_ID = $_SESSION['user']['user_ID'] ?? null;

    if (!$user_ID) {
        echo json_encode(["success" => false, "error" => "User not logged in"]);
        exit;
    }

    // === STEP 1: Fetch brs_no ===
    $fetchStmt = $pdo->prepare("SELECT brs_no FROM reservation_borrowing WHERE brs_ID = :brs_ID");
    $fetchStmt->execute([':brs_ID' => $brs_ID]);
    $brs = $fetchStmt->fetch(PDO::FETCH_ASSOC);

    if (!$brs) {
        echo json_encode(["success" => false, "error" => "Reservation not found"]);
        exit;
    }

    $brs_no = $brs['brs_no'];

    // === STEP 2: Begin transaction ===
    $pdo->beginTransaction();

    // Update reservation status
    $stmt = $pdo->prepare("
        UPDATE reservation_borrowing 
        SET brs_status = 'cancelled' 
        WHERE brs_ID = :brs_ID
    ");
    $stmt->bindParam(':brs_ID', $brs_ID, PDO::PARAM_INT);
    $stmt->execute();

    // Update all assets under this reservation to 'active'
    $updateAssets = $pdo->prepare("
        UPDATE asset 
        SET asset_status = 'active'
        WHERE asset_ID IN (
            SELECT asset_ID FROM brs_asset WHERE brs_ID = :brs_ID
        )
    ");
    $updateAssets->bindParam(':brs_ID', $brs_ID, PDO::PARAM_INT);
    $updateAssets->execute();

    // Commit transaction
    $pdo->commit();

    // === STEP 3: Send Notification ===
    $adminQuery = $pdo->query("
        SELECT account_ID 
        FROM account a 
        JOIN role r ON a.role_ID = r.role_ID 
        WHERE (r.role_name = 'Super-Admin' OR r.role_name = 'Admin') 
        AND r.role_status = 'active'
    ");
    $admins = $adminQuery->fetchAll(PDO::FETCH_COLUMN);

    $title = "Borrowing Request Cancelled";
    $message = "Borrowing Request ({$brs_no}) has been cancelled by the user.";
    $module = "BRS";

    if (!empty($admins)) {
        foreach ($admins as $adminAccountID) {
            sendNotification($pdo, $title, $message, $adminAccountID, $user_ID, $module, $brs_no);
        }
    } else {
        sendNotification($pdo, $title, $message, null, $user_ID, $module, $brs_no);
    }

    echo json_encode([
        "success" => true,
        "message" => "Reservation ({$brs_no}) cancelled successfully and admins notified."
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}
?>
