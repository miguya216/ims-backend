<?php
session_start();
header("Content-Type: application/json");

require_once __DIR__ . '/../../conn.php';
require_once __DIR__ . '/../../logActivity.php'; // include activity logger

if (!isset($_SESSION['user']['account_ID'])) {
  echo json_encode(["success" => false, "message" => "Unauthorized"]);
  exit;
}

$data = json_decode(file_get_contents("php://input"), true);

$currentPassword = $data['currentPassword'] ?? '';
$newPassword = $data['newPassword'] ?? '';

if (empty($currentPassword) || empty($newPassword)) {
  echo json_encode(["success" => false, "message" => "Missing fields"]);
  exit;
}

$account_ID = $_SESSION['user']['account_ID'];

// Fetch current hashed password
$stmt = $pdo->prepare("SELECT password_hash FROM account WHERE account_ID = ?");
$stmt->execute([$account_ID]);
$row = $stmt->fetch();

if (!$row) {
  echo json_encode(["success" => false, "message" => "Account not found"]);
  exit;
}

$currentHashed = $row['password_hash'];

if (!password_verify($currentPassword, $currentHashed)) {
  echo json_encode(["success" => false, "message" => "Current password is incorrect"]);
  exit;
}

// Hash new password
$newHashed = password_hash($newPassword, PASSWORD_DEFAULT);

// Update password
$update = $pdo->prepare("UPDATE account SET password_hash = ? WHERE account_ID = ?");
$success = $update->execute([$newHashed, $account_ID]);

if ($success) {
  // ✅ Log the password change
  logActivity(
    $pdo,
    $account_ID,                     // who performed the action
    "UPDATE",                        // action type
    "account",                       // module
    $account_ID,                     // affected record
    "Password changed successfully"  // description
  );

  echo json_encode(["success" => true, "message" => "Password updated"]);
} else {
  echo json_encode(["success" => false, "message" => "Failed to update password"]);
}
?>