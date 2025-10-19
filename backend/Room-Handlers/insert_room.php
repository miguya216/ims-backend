<?php
session_start();
header("Content-Type: application/json");

require_once __DIR__ . '/../conn.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../logActivity.php';

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['rooms']) || !is_array($data['rooms']) || count($data['rooms']) === 0) {
    echo json_encode(["success" => false, "message" => "No room data provided."]);
    exit;
}

try {
    $pdo->beginTransaction();

    $insertQr = $pdo->prepare("INSERT INTO qr_code (qr_image_path) VALUES (?)");
    $insertRoom = $pdo->prepare("
        INSERT INTO room (room_number, room_qr_value, room_qr_ID, room_status)
        VALUES (?, ?, ?, 'active')
    ");

    $account_ID = $_SESSION['user']['account_ID'] ?? null;

    foreach ($data['rooms'] as $room_number) {
        $room_number = trim($room_number);
        if ($room_number === "") continue;

        // Ensure unique room_number
        $check = $pdo->prepare("SELECT COUNT(*) FROM room WHERE room_number = ?");
        $check->execute([$room_number]);
        if ($check->fetchColumn() > 0) {
            throw new Exception("Room '$room_number' already exists.");
        }

        // Use posted room number as QR value
        $qr_value = $room_number;

        // --- Generate QR code image ---
        $qrCode = new QrCode($qr_value);
        $writer = new PngWriter();
        $qrImage = $writer->write($qrCode);

        $filename = 'qrcodes/room_' . preg_replace('/\s+/', '_', $room_number) . '_' . uniqid() . '.png';
        $fullPath = BASE_STORAGE_PATH . $filename;
        file_put_contents($fullPath, $qrImage->getString());

        // Insert into qr_code
        $insertQr->execute([$filename]);
        $qr_ID = $pdo->lastInsertId();

        // Insert into room
        $insertRoom->execute([$room_number, $qr_value, $qr_ID]);
        $room_ID = $pdo->lastInsertId();

        // Log activity
        logActivity(
            $pdo,
            $account_ID,
            "INSERT",
            "room",
            $room_ID,
            "Inserted new room '$room_number'"
        );
    }

    $pdo->commit();
    echo json_encode(["success" => true]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>