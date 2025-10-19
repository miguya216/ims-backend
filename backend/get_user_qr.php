<?php
session_start();
require_once __DIR__ . '/conn.php';

if (!isset($_SESSION['user']['account_ID'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

$account_ID = $_SESSION['user']['account_ID'];

// Get qr_image_path by joining account → qr_code
$stmt = $pdo->prepare("
    SELECT qc.qr_image_path
    FROM account a
    JOIN qr_code qc ON a.qr_ID = qc.qr_ID
    WHERE a.account_ID = ?
");
$stmt->execute([$account_ID]);
$qr = $stmt->fetch(PDO::FETCH_ASSOC);

if ($qr) {
    echo json_encode(['qrPath' => $qr['qr_image_path']]);
} else {
    echo json_encode(['error' => 'QR code not found']);
}
?>
