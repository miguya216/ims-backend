<?php
session_start();
require_once __DIR__ . '/../conn.php';
require_once __DIR__ . '/../../vendor/autoload.php';

// Decode incoming JSON
$input = json_decode(file_get_contents("php://input"), true);

if (!$input) {
    echo json_encode(["message" => "Invalid JSON input."]);
    exit();
}

// Map variables from JSON payload
$asset = new Asset();

$response = $asset->insertAsset($input);


// Send response
if ($response === true) {
    echo json_encode(["success" => true, "message" => "Asset added successfully."]);
} elseif ($response === "duplicate") {
    echo json_encode(["success" => false, "message" => "Asset already exists (duplicate inventory tag or serial number)."]);
} else {
    echo json_encode(["success" => false, "message" => $response]);
}

use Picqer\Barcode\BarcodeGeneratorPNG;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

class Asset {
    private $pdo;
    private $yearCounts = []; // cache counts per year

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }

    public function insertAsset($data) {
        try {
            $serial_num = $data['property_tag'];
            $log_user = $data['response_for_this_log'] ?? 'Unknown';

            // ✅ Validate serviceable_year (must be 4-digit number)
            if (empty($data['serviceable_year']) || !preg_match('/^\d{4}$/', $data['serviceable_year'])) {
                return "Serviceable year must be a 4-digit year (e.g., 2025).";
            }

            // (Optional) also check if within a sensible range
            $year = (int)$data['serviceable_year'];
            $currentYear = (int)date("Y");
            if ($year < 1900 || $year > $currentYear + 50) {
                return "Serviceable year must be between 1900 and " . ($currentYear + 50) . ".";
            }

            // 🟢 Step 1: Generate KLD property tag (mimic CSV logic)
            $date_acquired = !empty($data['date_acquired']) 
                ? date('Y-m-d', strtotime($data['date_acquired'])) 
                : date('Y-m-d');

        // STEP 2: Resolve Brand + Asset Type BEFORE KLD tag
        $brand_data = $data['brand'];
        $asset_type_data = $data['asset_type'];

        $brand_id = null;
        $asset_type_id = null;
        $asset_type_name = '';

        if (is_numeric($brand_data['existing_id'])) {
            // 🟢 CASE 1: Existing brand → fetch asset_type
            $stmt = $this->pdo->prepare("
                SELECT b.asset_type_ID, at.asset_type 
                FROM brand b
                JOIN asset_type at ON b.asset_type_ID = at.asset_type_ID
                WHERE b.brand_ID = ?
            ");
            $stmt->execute([$brand_data['existing_id']]);
            $row = $stmt->fetch();
            if ($row) {
                $brand_id = $brand_data['existing_id'];
                $asset_type_id = $row['asset_type_ID'];
                $asset_type_name = $row['asset_type'];
            } else {
                return "Selected brand does not exist.";
            }
        } else {
            // 🟢 CASE 2/3: New brand
            $new_brand = trim($brand_data['new_value']);

            if (is_numeric($asset_type_data['existing_id'])) {
                // Case 2: New brand + existing asset type
                $asset_type_id = $asset_type_data['existing_id'];
                $stmt = $this->pdo->prepare("SELECT asset_type FROM asset_type WHERE asset_type_ID = ?");
                $stmt->execute([$asset_type_id]);
                $row = $stmt->fetch();
                if ($row) $asset_type_name = $row['asset_type'];
            } else {
                // Case 3: New brand + new asset type
                $new_asset_type = trim($asset_type_data['new_value']);
                if (empty($new_asset_type)) {
                    return "Asset type is required when creating a new brand.";
                }

                // Insert asset type if not exists
                $stmt = $this->pdo->prepare("SELECT asset_type_ID FROM asset_type WHERE asset_type = ?");
                $stmt->execute([$new_asset_type]);
                $row = $stmt->fetch();
                if ($row) {
                    $asset_type_id = $row['asset_type_ID'];
                } else {
                    $stmt = $this->pdo->prepare("INSERT INTO asset_type (asset_type) VALUES (?)");
                    $stmt->execute([$new_asset_type]);
                    $asset_type_id = $this->pdo->lastInsertId();
                }
                $asset_type_name = $new_asset_type;
            }

            if (empty($new_brand)) {
                return "Brand name is required.";
            }

            // Insert or fetch brand
            $stmt = $this->pdo->prepare("SELECT brand_ID FROM brand WHERE brand_name = ? AND asset_type_ID = ?");
            $stmt->execute([$new_brand, $asset_type_id]);
            $row = $stmt->fetch();
            if ($row) {
                $brand_id = $row['brand_ID'];
            } else {
                $stmt = $this->pdo->prepare("INSERT INTO brand (brand_name, asset_type_ID) VALUES (?, ?)");
                $stmt->execute([$new_brand, $asset_type_id]);
                $brand_id = $this->pdo->lastInsertId();
            }
        }
        // STEP 3: Now generate KLD tag (safe because we know $asset_type_name)
        $kld_property_tag = $this->generateKLDTag($asset_type_name, $date_acquired);

        // Step 4: Duplicate check
        $checkStmt = $this->pdo->prepare("SELECT 1 FROM asset WHERE kld_property_tag = ? OR property_tag = ? LIMIT 1");
        $checkStmt->execute([$kld_property_tag, $serial_num]);
        if ($checkStmt->rowCount() > 0) return "duplicate";

            // 6. Handle Responsible (User) and Unit
            $responsible = $data['responsible'];
            $responsible_id = null;
            $unit_id = null;

            if (is_numeric($responsible['existing_id'])) {
                // Case A: Existing user
                $responsible_id = $responsible['existing_id'];
            } else {
                // New user - get names
                $first = trim($responsible['new_value']['first_name']);
                $middle = trim($responsible['new_value']['middle_name']);
                $last = trim($responsible['new_value']['last_name']);

                if (empty($first) || empty($last)) {
                    return "Responsible person's first and last name are required.";
                }

                // Handle unit
                $unit = $data['unit'];
                if (is_numeric($unit['existing_id'])) {
                    // Case B: Unit exists
                    $unit_id = $unit['existing_id'];
                } elseif (!empty($unit['new_value'])) {
                    // Case C: New unit
                    $new_unit = trim($unit['new_value']);

                    // Check for duplicate unit
                    $stmt = $this->pdo->prepare("SELECT unit_ID FROM unit WHERE unit_name = ?");
                    $stmt->execute([$new_unit]);
                    $row = $stmt->fetch();
                    if ($row) {
                        $unit_id = $row['unit_ID'];
                    } else {
                        $stmt = $this->pdo->prepare("INSERT INTO unit (unit_name) VALUES (?)");
                        $stmt->execute([$new_unit]);
                        $unit_id = $this->pdo->lastInsertId();
                    }
                } else {
                    return "Unit is required when creating a new responsible user.";
                }

                // Now insert user
                $stmt = $this->pdo->prepare("
                    INSERT INTO user (f_name, m_name, l_name, unit_ID)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$first, $middle, $last, $unit_id]);
                $responsible_id = $this->pdo->lastInsertId();
            }


        // 7. Handle room
        $room_data = $data['room'];
        if (is_numeric($room_data['existing_id'])) {
            $room_id = $room_data['existing_id'];
        } else {
            $roomNumber = strtoupper(trim($room_data['new_value']));
            $stmt = $this->pdo->prepare("SELECT room_ID FROM room WHERE room_number = ? AND room_status = 'active'");
            $stmt->execute([$roomNumber]);
            $existingRoom = $stmt->fetch();
            if ($existingRoom) {
                $room_id = $existingRoom['room_ID'];
            } else {
                // Inside else for new room creation
                $stmt = $this->pdo->prepare("INSERT INTO qr_code (qr_image_path) VALUES ('pending')");
                $stmt->execute();
                $qr_code_id = $this->pdo->lastInsertId();

                // Generate room_qr_value first
                $qr_value = $roomNumber . strtoupper(bin2hex(random_bytes(2)));

                // Save room with generated qr_value and placeholder qr_code_ID
                $stmt = $this->pdo->prepare("INSERT INTO room (room_number, room_qr_value, room_qr_ID) VALUES (?, ?, ?)");
                $stmt->execute([$roomNumber, $qr_value, $qr_code_id]);
                $room_id = $this->pdo->lastInsertId();

                // Now generate QR image using room_qr_value (not ROOM_ID)
                $qr = new QrCode($qr_value); // ✅ QR encodes the correct value
                $writer = new PngWriter();
                $qrFilename = uniqid("qr_room_") . ".png";
                $qrPath = "qrcodes/$qrFilename";
                file_put_contents($_SERVER['DOCUMENT_ROOT'] . "/IMS-REACT/frontend/public/" . $qrPath, $writer->write($qr)->getString());

                // Update the qr_code table with correct image path
                $stmt = $this->pdo->prepare("UPDATE qr_code SET qr_image_path = ? WHERE qr_ID = ?");
                $stmt->execute([$qrPath, $qr_code_id]);

            }
        }

        // 9. Handle asset condition
        $cond_data = $data['asset_condition'];
        if (is_numeric($cond_data['existing_id'])) {
            $asset_condition_id = $cond_data['existing_id'];
        } else {
            $stmt = $this->pdo->prepare("SELECT asset_condition_ID FROM asset_condition WHERE condition_name = ?");
            $stmt->execute([$cond_data['new_value']]);
            $row = $stmt->fetch();
            if ($row) {
                $asset_condition_id = $row['asset_condition_ID'];
            } else {
                $stmt = $this->pdo->prepare("INSERT INTO asset_condition (condition_name) VALUES (?)");
                $stmt->execute([$cond_data['new_value']]);
                $asset_condition_id = $this->pdo->lastInsertId();
            }
        }

        // 10. Handle acquisition source
        $source_data = $data['acquisition_source'];
        $a_source_id = null;

        if (is_numeric($source_data['existing_id'])) {
            // Existing acquisition source
            $a_source_id = $source_data['existing_id'];
        } else {
            // New acquisition source
            $new_source = trim($source_data['new_value']);
            if (empty($new_source)) {
                return "Acquisition source is required.";
            }
            // Check if already exists
            $stmt = $this->pdo->prepare("SELECT a_source_ID FROM acquisition_source WHERE a_source_name = ?");
            $stmt->execute([$new_source]);
            $row = $stmt->fetch();
            if ($row) {
                $a_source_id = $row['a_source_ID'];
            } else {
                $stmt = $this->pdo->prepare("INSERT INTO acquisition_source (a_source_name) VALUES (?)");
                $stmt->execute([$new_source]);
                $a_source_id = $this->pdo->lastInsertId();
            }
        }
        
        // 11. Barcode & QR
        $barcode = new BarcodeGeneratorPNG();
        $barcodeData = $barcode->getBarcode($kld_property_tag, $barcode::TYPE_CODE_128);
        $barcodeFilename = uniqid("barcode_") . ".png";
        $barcodePath = "barcodes/$barcodeFilename";
        file_put_contents(BASE_STORAGE_PATH . $barcodePath, $barcodeData);
        // file_put_contents($_SERVER['DOCUMENT_ROOT'] . "/IMS-REACT/frontend/public/" . $barcodePath, $barcodeData);
        $stmt = $this->pdo->prepare("INSERT INTO barcode (barcode_image_path) VALUES (?)");
        $stmt->execute([$barcodePath]);
        $barcode_id = $this->pdo->lastInsertId();

        $qr = new QrCode($kld_property_tag);
        $writer = new PngWriter();
        $qrFilename = uniqid("qr_asset_") . ".png";
        $qrPath = "qrcodes/$qrFilename";
        file_put_contents(BASE_STORAGE_PATH . $qrPath, $writer->write($qr)->getString());
        // file_put_contents($_SERVER['DOCUMENT_ROOT'] . "/IMS-REACT/frontend/public/" . $qrPath, $writer->write($qr)->getString());
        $stmt = $this->pdo->prepare("INSERT INTO qr_code (qr_image_path) VALUES (?)");
        $stmt->execute([$qrPath]);
        $qr_id = $this->pdo->lastInsertId();

        // 12. Final asset insert
        $stmt = $this->pdo->prepare("
            INSERT INTO asset (
                brand_ID, asset_type_ID, kld_property_tag, property_tag,
                responsible_user_ID, barcode_ID, qr_ID, room_ID,
                asset_condition_ID, a_source_ID, date_acquired, serviceable_year, price_amount
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $brand_id, $asset_type_id, $kld_property_tag, $serial_num,
            $responsible_id, $barcode_id, $qr_id, $room_id, $asset_condition_id,
            $a_source_id, $data['date_acquired'], $data['serviceable_year'], $data['price_amount']
        ]);

        $asset_id = $this->pdo->lastInsertId();

        

        // 12.1 Create property card linked to this asset
        $stmt = $this->pdo->prepare("INSERT INTO property_card (asset_ID) VALUES (?)");
        $stmt->execute([$asset_id]);
        $property_card_id = $this->pdo->lastInsertId();

        // 12.2 Insert initial record into property_card_record
        $stmt = $this->pdo->prepare("
            INSERT INTO property_card_record (
                property_card_ID, reference_type, reference_ID,
                officer_user_ID, price_amount, remarks
            ) VALUES (?, ?, ?, ?, ?, ?)
        ");

        // You can adjust reference_type/ID depending on your workflow. 
        // Here we’ll log it as 'CSV' (newly added from CSV/encoding) with the asset tag as reference.
        $stmt->execute([
            $property_card_id,
            'Manual Input',                 // initial reference type
            'REF-INIT-1',                // use inventory tag as reference_ID
            $responsible_id,                  // officer in charge
            $data['price_amount'],            // same price amount as acquisition
            'Initial record upon asset creation'
        ]);

        // 13. Logging
        require_once __DIR__ . '/../logActivity.php';
        $account_ID = $_SESSION['user']['account_ID'] ?? null;

        logActivity(
            $this->pdo,
            $account_ID,
            "INSERT",                    // action_type
            "asset",                     // module
            $asset_id,                   // record_ID
            "Added asset with Inventory Tag: $kld_property_tag, Serial Number: $serial_num"
        );

        return true;
    } catch (PDOException $e) {
        return $e->getMessage();
    }
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
        return strlen($acronym) < 2 
            ? strtoupper(substr(preg_replace('/\s+/', '', $text), 0, 3)) 
            : $acronym;
    }
}

?>