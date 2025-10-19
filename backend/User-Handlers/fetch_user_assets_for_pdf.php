<?php
// File: /api/User-Handlers/fetch_user_assets_for_pdf.php
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
            CONCAT(u.f_name, ' ', u.m_name, ' ', u.l_name) AS full_name,
            unit.unit_name,
            role.role_name
        FROM user u
        LEFT JOIN unit ON u.unit_ID = unit.unit_ID
        LEFT JOIN account a ON a.user_ID = u.user_ID
        LEFT JOIN role ON a.role_ID = role.role_ID
        WHERE u.user_ID = ?
    ");
    $stmtUser->execute([$user_ID]);
    $user = $stmtUser->fetch();

    if (!$user) {
        echo json_encode(['status' => 'error', 'message' => 'User not found']);
        exit;
    }

    // Fetch assets assigned to this user
    $stmtAssets = $pdo->prepare("
        SELECT 
            a.date_acquired,
            CONCAT(at.asset_type, ' - ', b.brand_name) AS item,
            a.kld_property_tag AS property_no,
            a.property_tag,
            1 AS quantity,
            a.price_amount,
            a.serviceable_year
        FROM asset a
        LEFT JOIN asset_type at ON a.asset_type_ID = at.asset_type_ID
        LEFT JOIN brand b ON a.brand_ID = b.brand_ID
        WHERE a.responsible_user_ID = ?
          AND a.asset_status = 'active'
        ORDER BY a.date_acquired ASC
    ");
    $stmtAssets->execute([$user_ID]);
    $assets = $stmtAssets->fetchAll();

    // Fetch total assets count
    $stmtTotal = $pdo->prepare("
        SELECT COUNT(*) AS total_assets
        FROM asset
        WHERE responsible_user_ID = ?
          AND asset_status = 'active'
    ");
    $stmtTotal->execute([$user_ID]);
    $total = $stmtTotal->fetch();

   // Generate control number: EAF-YY-##### (padded user_ID)
    $yearPrefix = date('y'); // last two digits of year
    $paddedID   = str_pad($user_ID, 5, '0', STR_PAD_LEFT);
    $controlNo  = "EAF-{$yearPrefix}-{$paddedID}";

    echo json_encode([
        'status' => 'success',
        'header' => [
            'full_name'    => $user['full_name'],
            'department'   => $user['unit_name'],
            'position'     => $user['role_name'],
            'control_no'   => $controlNo,
            'total_assets' => $total['total_assets'] ?? 0
        ],
        'assets' => $assets
    ]);


} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
