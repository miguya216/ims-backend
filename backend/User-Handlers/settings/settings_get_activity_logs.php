<?php
session_start();
require_once __DIR__ . "/../../conn.php";

header("Content-Type: application/json");

// Ensure user is logged in
if (!isset($_SESSION['user']['user_ID'])) {
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

$user_ID = $_SESSION['user']['user_ID'];

try {
    // Fetch ALL logs for this user (no pagination)
    $stmt = $pdo->prepare("
        SELECT 
            al.activity_ID,
            al.action_type,
            al.module,
            al.record_ID,
            al.description,
            al.created_at,
            CONCAT(u.f_name, ' ', IFNULL(u.m_name, ''), ' ', u.l_name) AS account_name
        FROM activity_log al
        INNER JOIN account a ON al.account_ID = a.account_ID
        INNER JOIN user u ON a.user_ID = u.user_ID
        WHERE u.user_ID = :user_ID
        ORDER BY al.created_at DESC
    ");

    $stmt->bindValue(":user_ID", $user_ID, PDO::PARAM_INT);
    $stmt->execute();

    $logs = $stmt->fetchAll();

    echo json_encode([
        "logs" => $logs,
        "count" => count($logs), // 🔹 optional: useful for debugging
    ]);
} catch (Exception $e) {
    echo json_encode(["error" => $e->getMessage()]);
}
?>