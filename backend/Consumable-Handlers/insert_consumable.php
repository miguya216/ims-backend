<?php
session_start();
require_once __DIR__ . '/../conn.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../logActivity.php';

use Picqer\Barcode\BarcodeGeneratorPNG;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["success" => false, "message" => "Invalid request method."]);
    exit();
}

$account_ID = $_SESSION['user']['account_ID'] ?? null;
$officerId  = $_SESSION['user']['user_ID'] ?? null;

$consumable_name = trim($_POST['consumable_name'] ?? '');
$description     = trim($_POST['description'] ?? '');
$uom             = trim($_POST['uom'] ?? '');
$quantity        = intval($_POST['quantity'] ?? 0);
$price_amount    = number_format((float)($_POST['price_amount'] ?? 0), 2, '.', '');
$date_acquired   = !empty($_POST['date_acquired']) ? $_POST['date_acquired'] : date('Y-m-d');

if (!$consumable_name || !$uom || $quantity <= 0) {
    echo json_encode(["success" => false, "message" => "Invalid input data."]);
    exit();
}

try {
    // Generate KLD tag
    $kld_property_tag = generateKLDTag($pdo, $consumable_name, $date_acquired);

    // Check if consumable already exists
    $stmt = $pdo->prepare("SELECT consumable_ID, total_quantity FROM consumable 
                           WHERE LOWER(TRIM(consumable_name)) = ? AND LOWER(TRIM(unit_of_measure)) = ? LIMIT 1");
    $stmt->execute([strtolower($consumable_name), strtolower($uom)]);
    $existing = $stmt->fetch();

    if ($existing) {
        // Update quantity
        $newQuantity = $existing['total_quantity'] + $quantity;
        $stmt = $pdo->prepare("UPDATE consumable 
                               SET total_quantity = ?, price_amount = ?, date_acquired = ?, kld_property_tag = ? 
                               WHERE consumable_ID = ?");
        $stmt->execute([$newQuantity, $price_amount, $date_acquired, $kld_property_tag, $existing['consumable_ID']]);

        $stockCardId = getStockCardId($pdo, $existing['consumable_ID']);
        insertStockCardRecord($pdo, $stockCardId, 'MANUAL', 'FORM', $officerId, $quantity, "Added manually from form");

        // Log update
        logActivity(
            $pdo,
            $account_ID,
            "UPDATE",
            "consumable",
            $existing['consumable_ID'],
            "Updated via manual form: +$quantity units"
        );

        echo json_encode(["success" => true, "message" => "Consumable updated successfully."]);
    } else {
        // Generate barcode + QR
        $barcodePath = generateBarcode($kld_property_tag);
        $qrPath      = generateQR($kld_property_tag);

        $stmt = $pdo->prepare("INSERT INTO barcode (barcode_image_path) VALUES (?)");
        $stmt->execute([$barcodePath]);
        $barcode_id = $pdo->lastInsertId();

        $stmt = $pdo->prepare("INSERT INTO qr_code (qr_image_path) VALUES (?)");
        $stmt->execute([$qrPath]);
        $qr_id = $pdo->lastInsertId();

        // Insert consumable
        $stmt = $pdo->prepare("INSERT INTO consumable 
            (kld_property_tag, consumable_name, description, unit_of_measure, total_quantity, barcode_ID, qr_ID, price_amount, date_acquired) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$kld_property_tag, $consumable_name, $description, $uom, $quantity, $barcode_id, $qr_id, $price_amount, $date_acquired]);
        $consumableId = $pdo->lastInsertId();

        // Create stock card
        $stmt = $pdo->prepare("INSERT INTO stock_card (consumable_ID) VALUES (?)");
        $stmt->execute([$consumableId]);
        $stockCardId = $pdo->lastInsertId();

        insertStockCardRecord($pdo, $stockCardId, 'MANUAL', 'FORM', $officerId, $quantity, "Initial stock from form");

        // Log insert
        logActivity(
            $pdo,
            $account_ID,
            "INSERT",
            "consumable",
            $consumableId,
            "Inserted via manual form: $consumable_name ($quantity units)"
        );

        echo json_encode(["success" => true, "message" => "Consumable added successfully."]);
    }

} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
}


function generateKLDTag($pdo, $name, $dateAcquired) {
    $yy = date('y', strtotime($dateAcquired));
    $mm = date('m', strtotime($dateAcquired));
    $dd = date('d', strtotime($dateAcquired));

    $acronym = makeAcronym($name);

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM consumable WHERE kld_property_tag LIKE ?");
    $stmt->execute(["KLD-$yy-%"]);
    $count = (int)$stmt->fetchColumn() + 1;

    $counter = str_pad($count, 6, '0', STR_PAD_LEFT);

    return "KLD-$yy-$mm-$dd-$acronym-$counter";
}

function makeAcronym($text) {
    $words = preg_split('/\s+/', trim($text));
    $acronym = '';
    foreach ($words as $w) {
        $acronym .= strtoupper(substr($w, 0, 1));
    }
    return strlen($acronym) < 2 ? strtoupper(substr(preg_replace('/\s+/', '', $text), 0, 3)) : $acronym;
}

function getStockCardId($pdo, $consumableId) {
    $stmt = $pdo->prepare("SELECT stock_card_ID FROM stock_card WHERE consumable_ID = ? LIMIT 1");
    $stmt->execute([$consumableId]);
    $row = $stmt->fetch();
    return $row ? $row['stock_card_ID'] : null;
}

function insertStockCardRecord($pdo, $stockCardId, $refType, $refNo, $officerId, $qtyIn, $remarks) {
    $stmt = $pdo->prepare("SELECT balance FROM stock_card_record WHERE stock_card_ID = ? ORDER BY record_date DESC LIMIT 1");
    $stmt->execute([$stockCardId]);
    $lastRecord = $stmt->fetch();
    $previousBalance = $lastRecord ? (int)$lastRecord['balance'] : 0;

    $newBalance = $previousBalance + $qtyIn;

    $stmt = $pdo->prepare("INSERT INTO stock_card_record 
        (stock_card_ID, reference_type, reference_ID, officer_user_ID, quantity_in, quantity_out, balance, remarks) 
        VALUES (?, ?, ?, ?, ?, 0, ?, ?)");
    $stmt->execute([$stockCardId, $refType, $refNo, $officerId, $qtyIn, $newBalance, $remarks]);
}

function generateBarcode($text) {
    $generator = new BarcodeGeneratorPNG();
    $barcodeData = $generator->getBarcode($text, $generator::TYPE_CODE_128);
    $filename = 'barcodes/' . uniqid('consumable_barcode_') . '.png';
    $fullPath = BASE_STORAGE_PATH . $filename;
    file_put_contents($fullPath, $barcodeData);
    return $filename;
}

function generateQR($text) {
    $qrCode = new QrCode($text);
    $writer = new PngWriter();
    $qrImage = $writer->write($qrCode);
    $filename = 'qrcodes/' . uniqid('consumable_qr_') . '.png';
    $fullPath = BASE_STORAGE_PATH . $filename;
    file_put_contents($fullPath, $qrImage->getString());
    return $filename;
}
?>