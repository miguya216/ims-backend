<?php
session_start();
require_once __DIR__ . '/../conn.php';
require_once __DIR__ . '/../../vendor/autoload.php';

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

$filePath = $_FILES['csvFile']['tmp_name'];
$importer = new ImportCSV();
$result = $importer->importFromCSV($filePath);
echo json_encode($result);

class ImportCSV {
    private $pdo;
    private $yearCounts = []; // cache counts per year

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }

    public function importFromCSV($csvFilePath) {
        $handle = fopen($csvFilePath, 'r');
        if (!$handle) {
            return [
                "summary" => "Failed to open file.",
                "errors" => []
            ];
        }

        $line = 1;
        $inserted = 0;
        $skipped = 0;
        $duplicates = 0;
        $errors = [];

        fgetcsv($handle); // skip header

        while (($row = fgetcsv($handle)) !== false) {
            $line++;
            $row = array_map('trim', $row);

            if (count($row) < 8) { 
                $skipped++;
                $errors[] = "Line {$line}: Incorrect column count.";
                continue;
            }

            [
                $property_tag,
                $brand,
                $asset_type,
                $acquisition_source,
                $date_acquired_raw,
                $serviceable_year_raw,
                $price_amount_raw,
                $ref_no
            ] = $row;

            if (!$property_tag) {
                $skipped++;
                $errors[] = "Line {$line}: Missing Property Tag.";
                continue;
            }

            try {
                // --- Generate new KLD property tag ---
                $date_acquired = !empty($date_acquired_raw) ? date('Y-m-d', strtotime($date_acquired_raw)) : date('Y-m-d');
                $kld_property_tag = $this->generateKLDTag($asset_type, $date_acquired);

                // duplicate check only by property_tag
                $stmt = $this->pdo->prepare("SELECT 1 FROM asset WHERE property_tag = ? LIMIT 1");
                $stmt->execute([$property_tag]);
                if ($stmt->rowCount() > 0) {
                    $duplicates++;
                    $errors[] = "Line {$line}: Duplicate Property Tag.";
                    continue;
                }

                // lookups
                $asset_type_id = $this->insertAndGetId("INSERT INTO asset_type (asset_type) VALUES (?)", 'asset_type', $asset_type);
                $brand_id = $this->insertAndGetId("INSERT INTO brand (brand_name, asset_type_ID) VALUES (?, ?)", 'brand', $brand, $asset_type_id);
                $a_source_id = $this->insertAndGetId("INSERT INTO acquisition_source (a_source_name) VALUES (?)", 'acquisition_source', $acquisition_source);

                // barcodes & QR
                $barcodePath = $this->generateBarcode($kld_property_tag);
                $qrPath = $this->generateQR($kld_property_tag);

                $stmt = $this->pdo->prepare("INSERT INTO barcode (barcode_image_path) VALUES (?)");
                $stmt->execute([$barcodePath]);
                $barcode_id = $this->pdo->lastInsertId();

                $stmt = $this->pdo->prepare("INSERT INTO qr_code (qr_image_path) VALUES (?)");
                $stmt->execute([$qrPath]);
                $qr_id = $this->pdo->lastInsertId();

                // values
                $serviceable_year = $serviceable_year_raw;
                $price_amount = is_numeric($price_amount_raw) ? number_format((float)$price_amount_raw, 2, '.', '') : '0.00';

                // insert asset
                $stmt = $this->pdo->prepare("
                    INSERT INTO asset 
                    (brand_ID, asset_type_ID, a_source_ID, kld_property_tag, property_tag, barcode_ID, qr_ID, date_acquired, serviceable_year, price_amount) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $brand_id,
                    $asset_type_id,
                    $a_source_id,
                    $kld_property_tag,
                    $property_tag,
                    $barcode_id,
                    $qr_id,
                    $date_acquired,
                    $serviceable_year,
                    $price_amount
                ]);

                $asset_id = $this->pdo->lastInsertId();
                $inserted++;

                // LOG: record import action
                require_once __DIR__ . '/../logActivity.php';
                $account_ID = $_SESSION['user']['account_ID'] ?? null;
                logActivity(
                    $this->pdo,
                    $account_ID,
                    "IMPORT",
                    "asset",
                    $asset_id,
                    "Imported asset via CSV (Property Tag: $property_tag)"
                );

                // property card
                $reference_type = str_starts_with($ref_no, 'RIS') ? 'RIS' : 'CSV';
                $remarks = str_starts_with($ref_no, 'RIS') ? 'Imported via RIS form' : 'CSV import';

                $stmt = $this->pdo->prepare("INSERT INTO property_card (asset_ID) VALUES (?)");
                $stmt->execute([$asset_id]);
                $property_card_id = $this->pdo->lastInsertId();

                $stmt = $this->pdo->prepare("
                    INSERT INTO property_card_record 
                    (property_card_ID, reference_type, reference_ID, price_amount, remarks)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$property_card_id, $reference_type, $ref_no, $price_amount, $remarks]);

            } catch (PDOException $e) {
                $skipped++;
                $errors[] = "Line {$line}: DB error - " . $e->getMessage();
            }
        }

        fclose($handle);

        return [
            "summary" => "Inserted: {$inserted}, Duplicates: {$duplicates}, Skipped: {$skipped}",
            "errors" => $errors
        ];
    }

    private function generateKLDTag($assetTypeName, $dateAcquired) {
        $yy = date('y', strtotime($dateAcquired));
        $mm = date('m', strtotime($dateAcquired));
        $dd = date('d', strtotime($dateAcquired));

        $assetTypeAcronym = $this->makeAcronym($assetTypeName);

        if (!isset($this->yearCounts[$yy])) {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM asset WHERE kld_property_tag LIKE ?");
            $stmt->execute(["KLD-$yy-%"]);
            $this->yearCounts[$yy] = (int)$stmt->fetchColumn();
        }

        $this->yearCounts[$yy]++;
        $counter = str_pad($this->yearCounts[$yy], 6, '0', STR_PAD_LEFT);

        return "KLD-$yy-$mm-$dd-$assetTypeAcronym-$counter";
    }

    private function makeAcronym($text) {
        $words = preg_split('/\s+/', trim($text));
        $acronym = '';
        foreach ($words as $w) {
            $acronym .= strtoupper(substr($w, 0, 1));
        }
        return strlen($acronym) < 2 ? strtoupper(substr(preg_replace('/\s+/', '', $text), 0, 3)) : $acronym;
    }

    private function insertAndGetId($query, $table, ...$params) {
        $value = strtolower(trim($params[0]));

        $nameColumn = match ($table) {
            'brand'              => 'brand_name',
            'asset_type'         => 'asset_type',
            'acquisition_source' => 'a_source_name',
            default              => "{$table}_name"
        };

        $idColumn = match ($table) {
            'brand'              => 'brand_ID',
            'asset_type'         => 'asset_type_ID',
            'acquisition_source' => 'a_source_ID',
            default              => "{$table}_ID"
        };

        $stmt = $this->pdo->prepare("SELECT {$idColumn} FROM {$table} WHERE LOWER(TRIM({$nameColumn})) = ? LIMIT 1");
        $stmt->execute([$value]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && isset($row[$idColumn])) {
            return $row[$idColumn];
        }

        $stmt = $this->pdo->prepare($query);
        $stmt->execute($params);
        return $this->pdo->lastInsertId();
    }

    private function generateBarcode($text) {
        $generator = new BarcodeGeneratorPNG();
        $barcodeData = $generator->getBarcode($text, $generator::TYPE_CODE_128);
        $filename = 'barcodes/' . uniqid('barcode_') . '.png';
        $fullPath = BASE_STORAGE_PATH . $filename;
        file_put_contents($fullPath, $barcodeData);
        return $filename;
    }

    private function generateQR($text) {
        $qrCode = new QrCode($text);
        $writer = new PngWriter();
        $qrImage = $writer->write($qrCode);
        $filename = 'qrcodes/' . uniqid('qr_') . '.png';
        $fullPath = BASE_STORAGE_PATH . $filename;
        file_put_contents($fullPath, $qrImage->getString());
        return $filename;
    }
}
?>
