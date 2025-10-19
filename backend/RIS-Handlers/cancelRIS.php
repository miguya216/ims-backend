<?php
// cancelRIS.php
header("Content-Type: application/json");
require_once __DIR__ . '/../conn.php';
require_once __DIR__ . '/../Notification-Handlers/notif_config.php';

session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Invalid request method."]);
    exit;
}

if (empty($_POST['ris_ID'])) {
    echo json_encode(["status" => "error", "message" => "Missing ris_ID."]);
    exit;
}

$ris_ID = intval($_POST['ris_ID']);
$userID = $_SESSION['user']['account_ID'] ?? null;

try {
    // === STEP 1: Fetch the ris_no (for message use) ===
    $fetchStmt = $pdo->prepare("
        SELECT ris_no 
        FROM requisition_and_issue 
        WHERE ris_ID = :ris_ID
    ");
    $fetchStmt->execute([':ris_ID' => $ris_ID]);
    $ris = $fetchStmt->fetch(PDO::FETCH_ASSOC);

    if (!$ris) {
        echo json_encode(["status" => "error", "message" => "RIS record not found."]);
        exit;
    }

    $ris_no = $ris['ris_no'];

    // === STEP 2: Cancel the RIS ===
    $updateStmt = $pdo->prepare("
        UPDATE requisition_and_issue 
        SET ris_status = 'cancelled' 
        WHERE ris_ID = :ris_ID
    ");
    $updateStmt->execute([':ris_ID' => $ris_ID]);

    if ($updateStmt->rowCount() === 0) {
        echo json_encode(["status" => "error", "message" => "Failed to cancel RIS."]);
        exit;
    }

    // === STEP 3: Notify Admins and Super-Admins ===
    $adminQuery = $pdo->query("
        SELECT a.account_ID 
        FROM account a
        JOIN role r ON a.role_ID = r.role_ID
        WHERE (r.role_name = 'Super-Admin' OR r.role_name = 'Admin')
          AND r.role_status = 'active'
    ");
    $admins = $adminQuery->fetchAll(PDO::FETCH_COLUMN);

    $title = "RIS Cancelled";
    $message = "Requisition and Issue Slip ({$ris_no}) has been cancelled.";
    $module = "RIS";

    if (!empty($admins)) {
        foreach ($admins as $adminAccountID) {
            sendNotification($pdo, $title, $message, $adminAccountID, $userID, $module, $ris_no);
        }
    } else {
        // fallback — broadcast if no admin accounts found
        sendNotification($pdo, $title, $message, null, $userID, $module, $ris_no);
    }

    echo json_encode([
        "status" => "success",
        "message" => "RIS #{$ris_no} cancelled successfully and notifications sent."
    ]);

} catch (PDOException $e) {
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
}
?>
