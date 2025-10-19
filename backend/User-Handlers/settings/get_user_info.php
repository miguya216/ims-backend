<?php
// fetch_account.php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../conn.php';

// Ensure user is logged in
if (!isset($_SESSION['user']['user_ID'])) {
    echo json_encode(["error" => "Not logged in"]);
    exit;
}

$user_ID = $_SESSION['user']['user_ID'];

try {
    $stmt = $pdo->prepare("
        SELECT 
            u.f_name AS firstName,
            u.m_name AS middleName,
            u.l_name AS lastName,
            k.kld_email AS email
        FROM user u
        INNER JOIN account a ON u.user_ID = a.user_ID
        INNER JOIN kld k ON a.kld_ID = k.kld_ID
        WHERE u.user_ID = :user_ID
          AND u.user_status = 'active'
    ");
    $stmt->execute(['user_ID' => $user_ID]);
    $result = $stmt->fetch();

    if ($result) {
        echo json_encode($result);
    } else {
        echo json_encode(["error" => "No account found"]);
    }
} catch (Exception $e) {
    echo json_encode(["error" => $e->getMessage()]);
}
?>