<?php
require_once __DIR__ . "/../conn.php";
session_start();
header("Content-Type: application/json; charset=UTF-8");

try {
    if (!isset($_SESSION['user']['user_ID']) || empty($_SESSION['user']['user_ID'])) {
        echo json_encode([
            "success" => false,
            "error" => "User not logged in"
        ]);
        exit;
    }

    $user_ID = intval($_SESSION['user']['user_ID']);

    $stmt = $pdo->prepare("
        SELECT 
            brs_ID,
            brs_no,
            purpose,
            brs_status,
            created_at AS date_requested,
            date_of_use,
            time_of_use,
            date_of_return,
            time_of_return
        FROM reservation_borrowing
        WHERE user_ID = :user_ID
        ORDER BY created_at DESC
    ");
    $stmt->execute([':user_ID' => $user_ID]);
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