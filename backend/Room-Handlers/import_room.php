<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

// clear output buffers to avoid stray whitespace/BOM
if (ob_get_length()) ob_clean();

require_once __DIR__ . '/../conn.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../logActivity.php';

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

class RoomImporter {
    private $pdo;
    private $account_ID;

    public function __construct($pdo, $account_ID) {
        $this->pdo        = $pdo;
        $this->account_ID = $account_ID;
    }

    private function getOrCreateRoomWithQR($room_number) {
        $stmt = $this->pdo->prepare("SELECT room_ID FROM room WHERE room_number = ?");
        $stmt->execute([$room_number]);
        if ($stmt->rowCount() > 0) {
            return $stmt->fetch()['room_ID'];
        }

        // QR value is just the room number
        $roomValue = $room_number;

        $qrCode  = new QrCode($roomValue);
        $writer  = new PngWriter();
        $qrImage = $writer->write($qrCode);

        $qrFilename = 'qrcodes/' . uniqid('qr_room_') . '.png';
        $fullPath   = BASE_STORAGE_PATH . $qrFilename;
        if (!file_put_contents($fullPath, $qrImage->getString())) {
            throw new Exception("Failed to save QR image for room '$room_number'");
        }

        // Insert QR record
        $stmt = $this->pdo->prepare("INSERT INTO qr_code (qr_image_path) VALUES (?)");
        $stmt->execute([$qrFilename]);
        $qr_id = $this->pdo->lastInsertId();

        // Insert Room
        $stmt = $this->pdo->prepare("INSERT INTO room (room_number, room_qr_value, room_qr_ID) VALUES (?, ?, ?)");
        $stmt->execute([$room_number, $roomValue, $qr_id]);
        $room_id = $this->pdo->lastInsertId();

        // Log activity for room creation
        logActivity(
            $this->pdo,
            $this->account_ID,
            "INSERT",
            "room",
            $room_id,
            "Imported room '$room_number' via CSV"
        );

        return $room_id;
    }

    public function importFromCSV($csvFile) {
        $file = fopen($csvFile, 'r');
        if (!$file) {
            throw new Exception('Failed to open CSV file');
        }

        // Skip header row
        fgetcsv($file);

        $imported = 0;
        $errors   = [];

        while (($row = fgetcsv($file)) !== false) {
            $room_number = trim($row[0] ?? '');
            if ($room_number === '') {
                $errors[] = 'Empty room number skipped';
                continue;
            }

            try {
                $this->getOrCreateRoomWithQR($room_number);
                $imported++;
            } catch (Throwable $e) {
                $errors[] = "Room '$room_number': " . $e->getMessage();
            }
        }

        fclose($file);

        return [
            'status'   => 'success',
            'imported' => $imported,
            'errors'   => $errors
        ];
    }
}

// Main handler
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
        exit;
    }

    if (!isset($_FILES['csvFile']) || $_FILES['csvFile']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['status' => 'error', 'message' => 'CSV file upload failed']);
        exit;
    }

    $account_ID = $_SESSION['user']['account_ID'] ?? null;

    $importer = new RoomImporter($pdo, $account_ID);
    $result   = $importer->importFromCSV($_FILES['csvFile']['tmp_name']);

    echo json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
} catch (Throwable $e) {
    echo json_encode([
        'status'  => 'error',
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}
?>