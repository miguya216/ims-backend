<?php
require_once __DIR__ . '/conn.php';

try {
    $stmt = $pdo->query("
        SELECT 
            al.activity_ID,
            al.action_type,
            al.module,
            al.description,
            al.created_at,
            CONCAT(u.f_name, ' ', COALESCE(u.m_name, ''), ' ', u.l_name) AS done_by
        FROM activity_log al
        LEFT JOIN account a ON al.account_ID = a.account_ID
        LEFT JOIN user u ON a.user_ID = u.user_ID
        ORDER BY al.created_at DESC
    ");

    $logs = $stmt->fetchAll();

    header('Content-Type: application/json');
    echo json_encode($logs);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}
?>