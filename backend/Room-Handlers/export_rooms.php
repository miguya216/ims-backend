<?php
header("Content-Type: application/json");
require_once __DIR__ . '/../conn.php';

try {
    // Query to join room + qr_code
    $stmt = $pdo->query("
        SELECT 
            r.room_ID,
            r.room_number,
            q.qr_image_path
        FROM room r
        INNER JOIN qr_code q ON r.room_qr_ID = q.qr_ID
        WHERE r.room_status = 'active'
        ORDER BY r.room_number ASC
    ");

    $rooms = $stmt->fetchAll();

    foreach ($rooms as &$room) {
        if (!empty($room['qr_image_path'])) {
            // Always make sure it starts with a forward slash so React can serve it
            $room['qr_code_path'] = '/' . ltrim($room['qr_image_path'], '/');
        } else {
            $room['qr_code_path'] = null;
        }
    }

    echo json_encode($rooms);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "error" => true,
        "message" => "Failed to fetch rooms: " . $e->getMessage()
    ]);
}
?>