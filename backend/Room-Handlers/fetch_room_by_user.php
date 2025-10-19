<?php
session_start();
require_once __DIR__ . '/../conn.php';

if (!isset($_SESSION['user']['user_ID'])) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

$user_ID = $_SESSION['user']['user_ID'];

try {
    $sql = "
        SELECT 
            r.room_ID,
            r.room_number,
            COUNT(a.asset_ID) AS total_assets,
            SUM(CASE WHEN ac.condition_name = 'Good' THEN 1 ELSE 0 END) AS good_assets,
            SUM(CASE WHEN ac.condition_name = 'Repair' THEN 1 ELSE 0 END) AS repair_assets,
            IFNULL(SUM(a.price_amount), 0) AS total_value
        FROM room r
        INNER JOIN asset a 
            ON r.room_ID = a.room_ID 
            AND a.asset_status = 'active'
            AND a.responsible_user_ID = :user_ID  
        LEFT JOIN asset_condition ac 
            ON a.asset_condition_ID = ac.asset_condition_ID
        WHERE r.room_status = 'active'
        GROUP BY r.room_ID, r.room_number
        ORDER BY r.room_number ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':user_ID' => $user_ID]);

    $rooms = [];
    while ($row = $stmt->fetch()) {
        $rooms[] = [
            "room_ID"       => $row['room_ID'],
            "room_number"   => $row["room_number"],
            "total_assets"  => (int)$row["total_assets"],
            "good_assets"   => (int)$row["good_assets"],
            "repair_assets" => (int)$row["repair_assets"],
            "total_value"   => number_format($row["total_value"], 2)
        ];
    }

    header("Content-Type: application/json");
    echo json_encode($rooms);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}
?>