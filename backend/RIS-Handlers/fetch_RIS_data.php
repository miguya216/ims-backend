<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../conn.php';

header("Content-Type: application/json");

try {
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
        WHERE r.ris_status != 'inactive'
        ORDER by created_at DESC
    ");
    $stmt->execute();
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(["status" => "success", "data" => $result]);
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>