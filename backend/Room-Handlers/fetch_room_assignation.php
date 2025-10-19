<?php
require_once __DIR__ . '/../conn.php';

header("Content-Type: application/json");

try {
    $sql = "
        SELECT 
            ra.room_assignation_ID,
            ra.room_assignation_no,
            fr.room_number AS from_room,
            tr.room_number AS to_room,
            CONCAT(u.f_name, ' ', u.l_name) AS moved_by,
            ra.moved_at
        FROM room_assignation ra
        LEFT JOIN room fr ON ra.from_room_ID = fr.room_ID
        INNER JOIN room tr ON ra.to_room_ID = tr.room_ID
        INNER JOIN user u ON ra.moved_by = u.user_ID
        WHERE ra.log_status = 'active'
        ORDER BY ra.moved_at DESC
    ";

    $stmt = $pdo->prepare($sql);   // use $pdo instead of $conn
    $stmt->execute();
    $result = $stmt->fetchAll();

    echo json_encode(["success" => true, "data" => $result]);
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>