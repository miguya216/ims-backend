<?php
session_start();

require_once __DIR__ . '/../conn.php';

header("Content-Type: application/json");

if (!isset($_SESSION['user']['account_ID'])) {
    die("Unauthorized access");
}

$account_ID = $_SESSION['user']['account_ID'];

try {
    // Get user_ID from account_ID
    $stmt = $pdo->prepare("SELECT user_ID FROM account WHERE account_ID = :account_ID");
    $stmt->execute(['account_ID' => $account_ID]);
    $result = $stmt->fetch();

    if ($result) {
        $user_ID = $result['user_ID'];
    } else {
        die("User not found for account_ID: " . $account_ID);
    }

    // Query requisition_and_issue with user_ID
    $stmt = $pdo->prepare("
        SELECT 
            r.ris_ID,
            r.ris_no AS ris_number,
            r.ris_status,
            u.unit_name AS office_unit,
            CONCAT(usr.f_name, ' ', usr.l_name) AS employee_name,
            rt.ris_tag_name AS ris_type
        FROM requisition_and_issue r
        JOIN account a ON r.account_ID = a.account_ID
        JOIN user usr ON a.user_ID = usr.user_ID
        JOIN unit u ON usr.unit_ID = u.unit_ID
        JOIN ris_tag_type rt ON r.ris_tag_ID = rt.ris_tag_ID
        WHERE usr.user_ID = :user_ID
        ORDER by created_at DESC
    ");
    $stmt->execute(['user_ID' => $user_ID]);
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(["status" => "success", "data" => $result]);
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
