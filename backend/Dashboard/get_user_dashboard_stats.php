<?php
session_start();
require_once __DIR__ . "/../conn.php";

header("Content-Type: application/json; charset=UTF-8");

if (!isset($_SESSION['user']['user_ID'])) {
    echo json_encode(["success" => false, "error" => "Unauthorized"]);
    exit;
}

$user_ID = $_SESSION['user']['user_ID'];

try {
    // Total asset assigned to the logged-in user (custodian)
    $stmt = $pdo->prepare("SELECT COUNT(*) AS total_assets FROM asset WHERE responsible_user_ID = ?");
    $stmt->execute([$user_ID]);
    $total_assets = $stmt->fetch()['total_assets'] ?? 0;

    // Total consumables received (RIS completed for this user)
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(rc.quantity_issuance), 0) AS total_consumables
        FROM requisition_and_issue ri
        JOIN ris_consumables rc ON ri.ris_ID = rc.ris_ID
        WHERE ri.account_ID = (
            SELECT account_ID FROM account WHERE user_ID = ?
        ) AND ri.ris_status = 'completed'
    ");
    $stmt->execute([$user_ID]);
    $total_consumables = $stmt->fetch()['total_consumables'] ?? 0;

    // Total rooms used by user (assets assigned in those rooms)
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT room_ID) AS total_rooms
        FROM asset
        WHERE responsible_user_ID = ? AND room_ID IS NOT NULL
    ");
    $stmt->execute([$user_ID]);
    $total_rooms = $stmt->fetch()['total_rooms'] ?? 0;

    // Requisition & Issue counts
    $stmt = $pdo->prepare("
        SELECT 
            SUM(ris_status = 'pending') AS pending,
            SUM(ris_status = 'cancelled') AS cancelled,
            SUM(ris_status = 'issuing') AS issuing
        FROM requisition_and_issue
        WHERE account_ID = (SELECT account_ID FROM account WHERE user_ID = ?)
    ");
    $stmt->execute([$user_ID]);
    $ris = $stmt->fetch();

    // Reservation & Borrowing counts
    $stmt = $pdo->prepare("
        SELECT 
            SUM(brs_status = 'pending') AS pending,
            SUM(brs_status = 'cancelled') AS cancelled,
            SUM(brs_status = 'issuing') AS issuing
        FROM reservation_borrowing
        WHERE user_ID = ?
    ");
    $stmt->execute([$user_ID]);
    $brs = $stmt->fetch();

    // Custodian Ownership Percentage (assets handled by this user / total active assets)
    $stmt = $pdo->query("SELECT COUNT(*) AS total_active FROM asset WHERE asset_status = 'active'");
    $total_active = $stmt->fetch()['total_active'] ?? 0;
    $ownership_percentage = $total_active > 0 ? round(($total_assets / $total_active) * 100, 2) : 0;

    // Asset Room Used (for bar chart)
    $stmt = $pdo->query("
        SELECT r.room_number, COUNT(a.asset_ID) AS asset_count
        FROM room r
        LEFT JOIN asset a ON a.room_ID = r.room_ID
        GROUP BY r.room_ID
    ");
    $rooms = $stmt->fetchAll();

    echo json_encode([
        "success" => true,
        "summary" => [
            "total_assets" => $total_assets,
            "total_consumables" => $total_consumables,
            "total_rooms" => $total_rooms,
        ],
        "requisition" => $ris,
        "borrowing" => $brs,
        "ownership" => [
            "percentage" => $ownership_percentage,
            "description" => "Percentage of total assets assigned to you"
        ],
        "room_usage" => $rooms
    ]);
} catch (Exception $e) {
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}
?>
