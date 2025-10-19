<?php
session_start();
require_once __DIR__ . '/../conn.php';
require_once __DIR__ . '/../Notification-Handlers/notif_config.php';


if (!isset($_SESSION['user']['account_ID'], $_SESSION['user']['user_ID'])) {
    echo json_encode(["success" => false, "error" => "Unauthorized"]);
    exit;
}

$account_ID       = $_SESSION['user']['account_ID'];
$officer_user_ID  = $_SESSION['user']['user_ID'];
$data             = json_decode(file_get_contents("php://input"), true);

if (!$data || empty($data['type']) || empty($data['items'])) {
    echo json_encode(["success" => false, "error" => "Invalid input"]);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Insert RIS header (placeholder ris_no)
    $stmt = $pdo->prepare("
        INSERT INTO requisition_and_issue (ris_no, account_ID, ris_tag_ID) 
        VALUES ('TEMP', :account_ID, :ris_tag_ID)
    ");
    $stmt->execute([
        ":account_ID" => $account_ID,
        ":ris_tag_ID" => $data['type']
    ]);
    $ris_ID = $pdo->lastInsertId();

    // 2. Generate RIS number
    $year   = date("y");
    $ris_no = "RIS-" . $year . "-" . str_pad($ris_ID, 6, "0", STR_PAD_LEFT);

    $stmt = $pdo->prepare("UPDATE requisition_and_issue SET ris_no = :ris_no WHERE ris_ID = :ris_ID");
    $stmt->execute([":ris_no" => $ris_no, ":ris_ID" => $ris_ID]);

    // 3. Get ris_tag_name (for remarks)
    $tagStmt = $pdo->prepare("SELECT ris_tag_name FROM ris_tag_type WHERE ris_tag_ID = :tag_ID LIMIT 1");
    $tagStmt->execute([":tag_ID" => $data['type']]);
    $risTagName = $tagStmt->fetchColumn() ?: 'RIS';

    // 4. Handle items
    if ($data['category'] === "Consumables") {
        // Prepare RIS consumables insert
        $stmt = $pdo->prepare("
            INSERT INTO ris_consumables 
            (ris_ID, consumable_ID, consumable_description, uom, quantity_requisition) 
            VALUES (:ris_ID, :consumable_ID, :description, :uom, :quantity)
        ");

        foreach ($data['items'] as $item) {
            $consumable_ID = (int) $item['description'];

            // Fetch consumable info
            $fetchStmt = $pdo->prepare("
                SELECT consumable_name, unit_of_measure, total_quantity 
                FROM consumable 
                WHERE consumable_ID = :cid 
                LIMIT 1
            ");
            $fetchStmt->execute([":cid" => $consumable_ID]);
            $consumable = $fetchStmt->fetch();

            if (!$consumable) {
                throw new Exception("Invalid consumable selected.");
            }

            if ($consumable['total_quantity'] < (int) $item['quantity']) {
                throw new Exception("Insufficient stock for " . $consumable['consumable_name']);
            }

            // Insert RIS consumables line
            $stmt->execute([
                ":ris_ID"        => $ris_ID,
                ":consumable_ID" => $consumable_ID,
                ":description"   => $consumable['consumable_name'],
                ":uom"           => $consumable['unit_of_measure'],
                ":quantity"      => (int) $item['quantity']
            ]);

            // Update consumable stock
            // $updateStock = $pdo->prepare("
            //     UPDATE consumable 
            //     SET total_quantity = total_quantity - :qty 
            //     WHERE consumable_ID = :cid
            // ");
            // $updateStock->execute([
            //     ":qty" => (int) $item['quantity'], 
            //     ":cid" => $consumable_ID
            // ]);

            // Ensure stock_card exists
            $checkCard = $pdo->prepare("
                SELECT stock_card_ID 
                FROM stock_card 
                WHERE consumable_ID = :cid
            ");
            $checkCard->execute([":cid" => $consumable_ID]);
            $stockCard = $checkCard->fetchColumn();

            if (!$stockCard) {
                $insertCard = $pdo->prepare("
                    INSERT INTO stock_card (consumable_ID) 
                    VALUES (:cid)
                ");
                $insertCard->execute([":cid" => $consumable_ID]);
                $stockCard = $pdo->lastInsertId();
            }

            // Get new balance
            $balStmt = $pdo->prepare("
                SELECT total_quantity 
                FROM consumable 
                WHERE consumable_ID = :cid
            ");
            $balStmt->execute([":cid" => $consumable_ID]);
            $balance = $balStmt->fetchColumn();

            // Insert stock_card_record
            $insertRecord = $pdo->prepare("
                INSERT INTO stock_card_record 
                (stock_card_ID, reference_type, reference_ID, officer_user_ID, quantity_in, quantity_out, balance, remarks) 
                VALUES (:stock_card_ID, 'RIS', :reference_ID, :officer_user_ID, 0, :qty_out, :balance, :remarks)
            ");
            $insertRecord->execute([
                ":stock_card_ID"    => $stockCard,
                ":reference_ID"     => $ris_no,
                ":officer_user_ID"  => $officer_user_ID,
                ":qty_out"          => 0,
                ":balance"          => $balance,
                ":remarks"          => $risTagName
            ]);
        }
    } else {
        // Prepare RIS assets insert
        $stmt = $pdo->prepare("
            INSERT INTO ris_assets (ris_ID, asset_property_no, asset_description, uom, quantity_requisition)
            VALUES (:ris_ID, :property_no, :description, :uom, :quantity)
        ");

        foreach ($data['items'] as $item) {
            $asset_ID     = null;
            $price_amount = 0.00;

            $property_no  = !empty($item['description']) ? $item['description'] : null;
            $description  = !empty($item['newDesc']) ? $item['newDesc'] : null;

            if ($description === null && $property_no !== null) {
                // Fetch asset info if property_no is provided
                $fetchStmt = $pdo->prepare("
                    SELECT a.asset_ID, a.price_amount, at.asset_type, b.brand_name
                    FROM asset a
                    JOIN asset_type at ON a.asset_type_ID = at.asset_type_ID
                    JOIN brand b ON a.brand_ID = b.brand_ID
                    WHERE a.property_tag = :property_no OR a.kld_property_tag = :property_no
                    LIMIT 1
                ");
                $fetchStmt->execute([":property_no" => $property_no]);
                $asset = $fetchStmt->fetch();

                if ($asset) {
                    $description   = $asset['asset_type'] . " - " . $asset['brand_name'];
                    $asset_ID      = $asset['asset_ID'];
                    $price_amount  = $asset['price_amount'];
                } else {
                    throw new Exception("Invalid Property No: " . $property_no);
                }
            }

            if ($description === null) {
                throw new Exception("Either Property No or New Description must be provided.");
            }

            // Insert RIS asset line
            $stmt->execute([
                ":ris_ID"      => $ris_ID,
                ":property_no" => $property_no,
                ":description" => $description,
                ":uom"         => $item['uom'],
                ":quantity"    => (int) $item['quantity']
            ]);

            // ---- Property card logic ----
            if (!empty($asset_ID)) {
                // Ensure property_card exists
                $checkCard = $pdo->prepare("SELECT property_card_ID FROM property_card WHERE asset_ID = :asset_ID");
                $checkCard->execute([":asset_ID" => $asset_ID]);
                $propertyCard = $checkCard->fetchColumn();

                if (!$propertyCard) {
                    $insertCard = $pdo->prepare("INSERT INTO property_card (asset_ID) VALUES (:asset_ID)");
                    $insertCard->execute([":asset_ID" => $asset_ID]);
                    $propertyCard = $pdo->lastInsertId();
                }

                // Insert record into property_card_record
                $insertRecord = $pdo->prepare("
                    INSERT INTO property_card_record 
                    (property_card_ID, reference_type, reference_ID, officer_user_ID, price_amount, remarks) 
                    VALUES (:property_card_ID, 'RIS', :reference_ID, :officer_user_ID, :price_amount, :remarks)
                ");
                $insertRecord->execute([
                    ":property_card_ID" => $propertyCard,
                    ":reference_ID"     => $ris_no,
                    ":officer_user_ID"  => $officer_user_ID,
                    ":price_amount"     => $price_amount,
                    ":remarks"          => $risTagName
                ]);
            }
        }
    }

    $pdo->commit();

    // Send notification (after successful commit)
    // Option A: Notify all admins (example)
    $adminQuery = $pdo->query("SELECT account_ID FROM account a 
                               JOIN role r ON a.role_ID = r.role_ID 
                               WHERE (r.role_name = 'Super-Admin' OR r.role_name = 'Admin') 
                               AND r.role_status = 'active'");
    $admins = $adminQuery->fetchAll(PDO::FETCH_COLUMN);

    $title = "New RIS Created";
    $message = "A new requisition and issue slip ({$ris_no}) has been submitted and is awaiting review.";
    $module = "RIS";

    if (!empty($admins)) {
        foreach ($admins as $adminAccountID) {
            sendNotification($pdo, $title, $message, $adminAccountID, $account_ID, $module, $ris_no);
        }
    } else {
        // fallback — broadcast if no admin accounts found
        sendNotification($pdo, $title, $message, null, $account_ID, $module, $ris_no);
    }

    echo json_encode(["success" => true, "ris_no" => $ris_no]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(["success" => false, "error" => $e->getMessage() ]);
}
?>
