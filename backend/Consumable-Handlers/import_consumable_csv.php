<?php
session_start();
require_once __DIR__ . '/../conn.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../logActivity.php';

use Picqer\Barcode\BarcodeGeneratorPNG;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

if (!isset($_FILES['csvFile']) || $_FILES['csvFile']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(["message" => "CSV file upload failed."]);
    exit();
}

$ext = pathinfo($_FILES['csvFile']['name'], PATHINFO_EXTENSION);
if (strtolower($ext) !== 'csv') {
    echo json_encode(["message" => "Only CSV files are allowed."]);
    exit();
}

$account_ID = $_SESSION['user']['account_ID'] ?? null;
$officerId  = $_SESSION['user']['user_ID'] ?? null;
$filePath   = $_FILES['csvFile']['tmp_name'];

$importer = new ImportConsumableCSV($officerId, $account_ID);
$result   = $importer->importFromCSV($filePath);
echo json_encode($result);

class ImportConsumableCSV {
    private $pdo;
    private $yearCounts = [];
    private $officerId;
    private $account_ID;

    public function __construct($officerId, $account_ID) {
        global $pdo;
        $this->pdo        = $pdo;
        $this->officerId  = $officerId;
        $this->account_ID = $account_ID;
    }

    public function importFromCSV($csvFilePath) {
        $handle = fopen($csvFilePath, 'r');
        if (!$handle) {
            return [
                "summary" => "Failed to open file.",
                "errors" => []
            ];
        }

        $line     = 1;
        $inserted = 0;
        $updated  = 0;
        $skipped  = 0;
        $errors   = [];

        fgetcsv($handle); // skip header

        while (($row = fgetcsv($handle)) !== false) {
            $line++;
            $row = array_map('trim', $row);

            if (count($row) < 7) { 
                $skipped++;
                continue;
            }

            [
                $consumable_name,
                $description,
                $unit_of_measure,
                $quantity_raw,
                $price_amount_raw,
                $date_acquired_raw,
                $ref_no
            ] = $row;

            if (!$consumable_name || !$unit_of_measure) {
                $skipped++;
                continue;
            }

            $quantity = is_numeric($quantity_raw) ? (int)$quantity_raw : 0;
            if ($quantity <= 0) {
                $skipped++;
                $errors[] = "Line {$line}: Invalid quantity.";
                continue;
            }

            try {
                $date_acquired = !empty($date_acquired_raw) ? date('Y-m-d', strtotime($date_acquired_raw)) : date('Y-m-d');
                $price_amount  = is_numeric($price_amount_raw) ? number_format((float)$price_amount_raw, 2, '.', '') : '0.00';

                $kld_property_tag = $this->generateKLDTag($consumable_name, $date_acquired);

                // Check if consumable exists
                $stmt = $this->pdo->prepare("SELECT consumable_ID, total_quantity 
                                             FROM consumable 
                                             WHERE LOWER(TRIM(consumable_name)) = ? 
                                             AND LOWER(TRIM(unit_of_measure)) = ? 
                                             LIMIT 1");
                $stmt->execute([strtolower($consumable_name), strtolower($unit_of_measure)]);
                $existing = $stmt->fetch();

                if ($existing) {
                    $newQuantity = $existing['total_quantity'] + $quantity;

                    $stmt = $this->pdo->prepare("UPDATE consumable 
                                                 SET total_quantity = ?, price_amount = ?, date_acquired = ?, kld_property_tag = ? 
                                                 WHERE consumable_ID = ?");
                    $stmt->execute([$newQuantity, $price_amount, $date_acquired, $kld_property_tag, $existing['consumable_ID']]);

                    $stockCardId = $this->getStockCardId($existing['consumable_ID']);
                    $this->insertStockCardRecord($stockCardId, 'CSV', $ref_no, $quantity, 'Additional stock from CSV');

                    // Log update
                    logActivity(
                        $this->pdo,
                        $this->account_ID,
                        "UPDATE",
                        "consumable",
                        $existing['consumable_ID'],
                        "Updated via CSV import: +$quantity units"
                    );

                    $updated++;
                } else {
                    $barcodePath = $this->generateBarcode($kld_property_tag);
                    $qrPath      = $this->generateQR($kld_property_tag);

                    $stmt = $this->pdo->prepare("INSERT INTO barcode (barcode_image_path) VALUES (?)");
                    $stmt->execute([$barcodePath]);
                    $barcode_id = $this->pdo->lastInsertId();

                    $stmt = $this->pdo->prepare("INSERT INTO qr_code (qr_image_path) VALUES (?)");
                    $stmt->execute([$qrPath]);
                    $qr_id = $this->pdo->lastInsertId();

                    $stmt = $this->pdo->prepare("INSERT INTO consumable 
                        (kld_property_tag, consumable_name, description, unit_of_measure, total_quantity, barcode_ID, qr_ID, price_amount, date_acquired) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$kld_property_tag, $consumable_name, $description, $unit_of_measure, $quantity, $barcode_id, $qr_id, $price_amount, $date_acquired]);

                    $consumableId = $this->pdo->lastInsertId();

                    $stmt = $this->pdo->prepare("INSERT INTO stock_card (consumable_ID) VALUES (?)");
                    $stmt->execute([$consumableId]);
                    $stockCardId = $this->pdo->lastInsertId();

                    $this->insertStockCardRecord($stockCardId, 'CSV', $ref_no, $quantity, 'Initial stock from CSV');

                    // Log insert
                    logActivity(
                        $this->pdo,
                        $this->account_ID,
                        "INSERT",
                        "consumable",
                        $consumableId,
                        "Inserted via CSV import: $consumable_name ($quantity units)"
                    );

                    $inserted++;
                }

            } catch (PDOException $e) {
                $skipped++;
                $errors[] = "Line {$line}: DB error - " . $e->getMessage();
            }
        }

        fclose($handle);

        return [
            "summary" => "Inserted: {$inserted}, Updated: {$updated}, Skipped: {$skipped}",
            "errors" => $errors
        ];
    }

    private function generateKLDTag($name, $dateAcquired) {
        $yy = date('y', strtotime($dateAcquired));
        $mm = date('m', strtotime($dateAcquired));
        $dd = date('d', strtotime($dateAcquired));

        $acronym = $this->makeAcronym($name);

        if (!isset($this->yearCounts[$yy])) {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM consumable WHERE kld_property_tag LIKE ?");
            $stmt->execute(["KLD-$yy-%"]);
            $this->yearCounts[$yy] = (int)$stmt->fetchColumn();
        }

        $this->yearCounts[$yy]++;
        $counter = str_pad($this->yearCounts[$yy], 6, '0', STR_PAD_LEFT);

        return "KLD-$yy-$mm-$dd-$acronym-$counter";
    }

    private function makeAcronym($text) {
        $words = preg_split('/\s+/', trim($text));
        $acronym = '';
        foreach ($words as $w) {
            $acronym .= strtoupper(substr($w, 0, 1));
        }
        return strlen($acronym) < 2 ? strtoupper(substr(preg_replace('/\s+/', '', $text), 0, 3)) : $acronym;
    }

    private function getStockCardId($consumableId) {
        $stmt = $this->pdo->prepare("SELECT stock_card_ID FROM stock_card WHERE consumable_ID = ? LIMIT 1");
        $stmt->execute([$consumableId]);
        $row = $stmt->fetch();
        return $row ? $row['stock_card_ID'] : null;
    }

    private function insertStockCardRecord($stockCardId, $refType, $refNo, $qtyIn, $remarks) {
        // Get latest balance
        $stmt = $this->pdo->prepare("SELECT balance FROM stock_card_record 
                                    WHERE stock_card_ID = ? 
                                    ORDER BY record_date DESC 
                                    LIMIT 1");
        $stmt->execute([$stockCardId]);
        $lastRecord = $stmt->fetch();

        $previousBalance = $lastRecord ? (int)$lastRecord['balance'] : 0;

        // Only add qtyIn for CSV imports
        $newBalance = $previousBalance + $qtyIn;

        $stmt = $this->pdo->prepare("INSERT INTO stock_card_record 
            (stock_card_ID, reference_type, reference_ID, officer_user_ID, quantity_in, quantity_out, balance, remarks) 
            VALUES (?, ?, ?, ?, ?, 0, ?, ?)");
        $stmt->execute([
            $stockCardId,
            $refType,
            $refNo,
            $this->officerId,
            $qtyIn,
            $newBalance,
            $remarks
        ]);
    }


    private function generateBarcode($text) {
        $generator = new BarcodeGeneratorPNG();
        $barcodeData = $generator->getBarcode($text, $generator::TYPE_CODE_128);
        $filename = 'barcodes/' . uniqid('consumable_barcode_') . '.png';
        $fullPath = BASE_STORAGE_PATH . $filename;
        file_put_contents($fullPath, $barcodeData);
        return $filename;
    }

    private function generateQR($text) {
        $qrCode = new QrCode($text);
        $writer = new PngWriter();
        $qrImage = $writer->write($qrCode);
        $filename = 'qrcodes/' . uniqid('consumable_qr_') . '.png';
        $fullPath = BASE_STORAGE_PATH . $filename;
        file_put_contents($fullPath, $qrImage->getString());
        return $filename;
    }
}
?>
