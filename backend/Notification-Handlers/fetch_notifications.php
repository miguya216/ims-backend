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
        SELECT 
            n.notification_ID,
            n.title,
            n.message,
            n.module,
            n.reference_ID,
            n.is_read,
            n.created_at,
            CONCAT(u.f_name, ' ', u.l_name) AS sender_name
        FROM notification n
        LEFT JOIN account s ON n.sender_account_ID = s.account_ID
        LEFT JOIN user u ON s.user_ID = u.user_ID
        WHERE n.recipient_account_ID = :acc_ID
           OR n.recipient_account_ID IS NULL
        ORDER BY n.created_at DESC
        LIMIT 10
    ");
    
    $stmt->execute([":acc_ID" => $account_ID]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(["success" => true, "notifications" => $notifications]);
} catch (Exception $e) {
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}
?>
