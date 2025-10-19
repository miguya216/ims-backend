<?php
// File: /api/User-Handlers/fetch_user_assets_for_par.php
header('Content-Type: application/json');
require_once __DIR__ . '/../conn.php';

if (!isset($_GET['user_ID'])) {
    echo json_encode(['status' => 'error', 'message' => 'Missing user_ID']);
    exit;
}

$user_ID = intval($_GET['user_ID']);

try {
    // Fetch user info for header
    $stmtUser = $pdo->prepare("
        SELECT 
            CONCAT(u.f_name, ' ', COALESCE(u.m_name, ''), ' ', u.l_name) AS full_name,
            unit.unit_name AS department
        FROM user u
        LEFT JOIN unit ON u.unit_ID = unit.unit_ID
        WHERE u.user_ID = ?
    ");
    $stmtUser->execute([$user_ID]);
    $user = $stmtUser->fetch();

    if (!$user) {
        echo json_encode(['status' => 'error', 'message' => 'User not found']);
        exit;
    }

    // Fetch assets (amount > 50,000)
    $stmtAssets = $pdo->prepare("
       SELECT 
            CONCAT(at.asset_type, ' / ', b.brand_name) AS description,
            a.kld_property_tag,
            a.price_amount AS amount,
            (
                SELECT MAX(pt.created_at)
                FROM ptr_asset pta2
                JOIN property_transfer pt 
                    ON pta2.ptr_ID = pt.ptr_ID
                WHERE pta2.asset_ID = a.asset_ID
                AND pt.to_accounted_user_ID = a.responsible_user_ID
            ) AS date_transferred
        FROM asset a
        JOIN asset_type at ON a.asset_type_ID = at.asset_type_ID
        JOIN brand b ON a.brand_ID = b.brand_ID
        WHERE a.responsible_user_ID = ?
        AND a.price_amount > 50000
        AND a.asset_status = 'active'
        ORDER BY a.date_acquired ASC;
    ");
    $stmtAssets->execute([$user_ID]);
    $assets = $stmtAssets->fetchAll();

    // Count total qualified assets
    $stmtTotal = $pdo->prepare("
        SELECT COUNT(*) AS total_assets
        FROM asset
        WHERE responsible_user_ID = ?
          AND price_amount > 50000
          AND asset_status = 'active'
    ");
    $stmtTotal->execute([$user_ID]);
    $total = $stmtTotal->fetch();

    // Generate PAR number: PAR-YY-##### (user_ID padded)
    $yearPrefix = date('y');
    $paddedID   = str_pad($user_ID, 5, '0', STR_PAD_LEFT);
    $controlNo  = "PAR-{$yearPrefix}-{$paddedID}";

    echo json_encode([
        'status' => 'success',
        'header' => [
            'full_name'    => $user['full_name'],
            'department'   => $user['department'],
            'control_no'   => $controlNo,
            'total_assets' => $total['total_assets'] ?? 0
        ],
        'assets' => $assets
    ]);

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
