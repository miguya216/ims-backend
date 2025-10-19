<?php
header("Content-Type: application/json");
require_once __DIR__ . '/../conn.php';

try {
    if (isset($pdo)) {
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    $input = json_decode(file_get_contents("php://input"), true);

    if (!$input || !isset($input['user_ID']) || !isset($input['assets']) || !is_array($input['assets'])) {
        echo json_encode(["success" => false, "message" => "Invalid input"]);
        exit;
    }

    $user_ID = intval($input['user_ID']);
    $assets  = $input['assets'];

    $pdo->beginTransaction();

    // Generate IIR number
    $currentYear = date("y"); // last two digits, e.g., 25 for 2025

    // Count existing reports for this year only
    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS count
        FROM inventory_inspection_report
        WHERE YEAR(created_at) = YEAR(CURDATE())
    ");
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    $count = $row ? intval($row['count']) + 1 : 1;

    // Format: IIR-25-000001
    $iir_no = "IIR-" . $currentYear . "-" . str_pad($count, 6, "0", STR_PAD_LEFT);


    // Insert inventory_inspection_report
    $stmt = $pdo->prepare("
        INSERT INTO inventory_inspection_report (iir_no, user_ID)
        VALUES (:iir_no, :user_ID)
    ");
    $stmt->execute([
        ':iir_no'  => $iir_no,
        ':user_ID' => $user_ID
    ]);
    $iir_ID = $pdo->lastInsertId();

    // Prepare statements
    $stmtIIRAsset = $pdo->prepare("
        INSERT INTO iir_asset (
            iir_ID, asset_ID, quantity, total_cost,
            accumulated_depreciation, accumulated_impairment_losses, carrying_amount,
            sale, transfer, disposal, damage, others, current_condition
        ) VALUES (
            :iir_ID, :asset_ID, :quantity, :total_cost,
            :accumulated_depreciation, :accumulated_impairment_losses, :carrying_amount,
            :sale, :transfer, :disposal, :damage, :others, :current_condition
        )
    ");


    $stmtUpdateCondition = $pdo->prepare("
        UPDATE asset 
        SET asset_condition_ID = :condition_ID
        WHERE asset_ID = :asset_ID
    ");

    $stmtFindAsset = $pdo->prepare("
        SELECT asset_ID, responsible_user_ID
        FROM asset
        WHERE kld_property_tag = ?
    ");

    $stmtGetPropertyCard = $pdo->prepare("
        SELECT property_card_ID
        FROM property_card
        WHERE asset_ID = :asset_ID
    ");
    $stmtCreatePropertyCard = $pdo->prepare("
        INSERT INTO property_card (asset_ID)
        VALUES (:asset_ID)
    ");

    $stmtGetConditionName = $pdo->prepare("
        SELECT condition_name
        FROM asset_condition
        WHERE asset_condition_ID = :condition_ID
    ");

    $stmtInsertPCR = $pdo->prepare("
        INSERT INTO property_card_record
            (property_card_ID, reference_type, reference_ID, officer_user_ID, price_amount, remarks)
        VALUES
            (:property_card_ID, 'IIR', :reference_ID, :officer_user_ID, :price_amount, :remarks)
    ");

    foreach ($assets as $a) {
        if (empty($a['kld_property_tag'])) {
            throw new Exception("Missing kld_property_tag in one of the assets");
        }

        $stmtFindAsset->execute([$a['kld_property_tag']]);
        $assetRow = $stmtFindAsset->fetch(PDO::FETCH_ASSOC);
        if (!$assetRow) {
            throw new Exception("Asset not found: " . $a['kld_property_tag']);
        }

        $asset_ID = (int)$assetRow['asset_ID'];
        $officer_user_ID = !empty($assetRow['responsible_user_ID']) ? (int)$assetRow['responsible_user_ID'] : null;

        $quantity       = (int)($a['quantity'] ?? 0);
        $unitCost       = (float)($a['unitCost'] ?? 0);   // new
        $totalCost      = (float)($a['totalCost'] ?? 0);
        $accumDep       = (float)($a['accumulatedDepreciation'] ?? 0);
        $accumImp       = (float)($a['accumulatedImpairment'] ?? 0);
        $carryingAmount = (float)($a['carryingAmount'] ?? 0);
        $sale           = (int)($a['sale'] ?? 0);
        $transfer       = (int)($a['transfer'] ?? 0);
        $disposal       = (int)($a['disposal'] ?? 0);
        $damage         = (int)($a['damage'] ?? 0);
        $others         = isset($a['others']) && $a['others'] !== '' ? (string)$a['others'] : null;

        // Determine condition values
        $condition_ID = isset($a['condition']) && $a['condition'] !== '' ? (int)$a['condition'] : 0;
        $currentCondition = '';

        if (!empty($a['conditionName'])) {
            $currentCondition = (string)$a['conditionName'];
        } elseif ($condition_ID > 0) {
            $stmtGetConditionName->execute([':condition_ID' => $condition_ID]);
            $cn = $stmtGetConditionName->fetch(PDO::FETCH_ASSOC);
            $currentCondition = $cn ? (string)$cn['condition_name'] : '';
        }

        // Insert iir_asset (now includes current_condition)
        $stmtIIRAsset->execute([
            ':iir_ID'                        => $iir_ID,
            ':asset_ID'                      => $asset_ID,
            ':quantity'                      => $quantity,
            ':total_cost'                    => $totalCost,
            ':accumulated_depreciation'      => $accumDep,
            ':accumulated_impairment_losses' => $accumImp,
            ':carrying_amount'               => $carryingAmount,
            ':sale'                          => $sale,
            ':transfer'                      => $transfer,
            ':disposal'                      => $disposal,
            ':damage'                        => $damage,
            ':others'                        => $others,
            ':current_condition'             => $currentCondition
        ]);


        // Update only asset condition (no more price_amount updates)
        $condition_ID = isset($a['condition']) && $a['condition'] !== '' ? (int)$a['condition'] : 0;
        if ($condition_ID > 0) {
            $stmtUpdateCondition->execute([
                ':condition_ID' => $condition_ID,
                ':asset_ID'     => $asset_ID
            ]);
        }

        // Property card
        $stmtGetPropertyCard->execute([':asset_ID' => $asset_ID]);
        $pc = $stmtGetPropertyCard->fetch(PDO::FETCH_ASSOC);
        if ($pc) {
            $property_card_ID = (int)$pc['property_card_ID'];
        } else {
            $stmtCreatePropertyCard->execute([':asset_ID' => $asset_ID]);
            $property_card_ID = (int)$pdo->lastInsertId();
        }

        // Remarks
        $remarks = '';
        if (!empty($a['conditionName'])) {
            $remarks = (string)$a['conditionName'];
        } elseif ($condition_ID > 0) {
            $stmtGetConditionName->execute([':condition_ID' => $condition_ID]);
            $cn = $stmtGetConditionName->fetch(PDO::FETCH_ASSOC);
            $remarks = $cn ? (string)$cn['condition_name'] : '';
        }

        //  Insert property card record using $unitCost instead of $carryingAmount
        $stmtInsertPCR->execute([
            ':property_card_ID' => $property_card_ID,
            ':reference_ID'     => $iir_no,
            ':officer_user_ID'  => $officer_user_ID,
            ':price_amount'     => $unitCost,
            ':remarks'          => $remarks
        ]);
    }

    $pdo->commit();

    echo json_encode([
        "success" => true,
        "message" => "Inspection report saved successfully; assets & property card updated",
        "iir_ID"  => $iir_ID,
        "iir_no"  => $iir_no
    ]);

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
?>
