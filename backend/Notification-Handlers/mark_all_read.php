<?php
session_start();
require_once __DIR__ . '/../conn.php';

if (!isset($_SESSION['user']['account_ID'])) {
    echo json_encode(["success" => false, "error" => "Unauthorized"]);
    exit;
}

$account_ID = $_SESSION['user']['account_ID'];

try {
    $stmt = $pdo->prepare("
        UPDATE notification 
        SET is_read = 1 
        WHERE (recipient_account_ID = :acc_ID OR recipient_account_ID IS NULL)
          AND is_read = 0
    ");
    $stmt->execute([":acc_ID" => $account_ID]);

    echo json_encode(["success" => true]);
} catch (Exception $e) {
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}
?>
