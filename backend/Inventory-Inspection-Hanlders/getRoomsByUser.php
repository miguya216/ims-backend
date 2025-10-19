<?php
require_once __DIR__ . '/../conn.php';

if (!isset($_GET['user'])) {
    echo json_encode([]);
    exit;
}

$userID = intval($_GET['user']);

$stmt = $pdo->prepare("
    SELECT DISTINCT 
        r.room_ID, 
        r.room_number,
        u.unit_name
    FROM asset a
    JOIN room r ON a.room_ID = r.room_ID
    JOIN user usr ON a.responsible_user_ID = usr.user_ID
    JOIN unit u ON usr.unit_ID = u.unit_ID
    WHERE a.responsible_user_ID = :userID
      AND a.asset_status = 'active'
      AND r.room_status = 'active'
");
$stmt->execute(['userID' => $userID]);
$rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($rooms);
?>
