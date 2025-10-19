<?php
require_once __DIR__ . "/../conn.php";
session_start();
header("Content-Type: application/json; charset=UTF-8");

try {
    // Fetch ALL reservations, no user filter
    $stmt = $pdo->prepare("
        SELECT 
            rb.brs_ID,
            rb.brs_no,
            CONCAT(u.f_name, ' ', 
                   COALESCE(CONCAT(u.m_name, ' '), ''), 
                   u.l_name) AS full_name,
            rb.created_at AS date_requested,
            rb.date_of_use,
            rb.time_of_use,
            rb.date_of_return,
            rb.time_of_return,
            rb.brs_status
        FROM reservation_borrowing rb
        INNER JOIN user u ON rb.user_ID = u.user_ID
        ORDER BY rb.created_at DESC
    ");
    
    $stmt->execute();
    $reservations = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'data' => $reservations
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "error" => $e->getMessage()
    ]);
}
?>
