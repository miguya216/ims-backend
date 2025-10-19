<?php
session_start();
header("Content-Type: application/json");

// Check if user is logged in
if (!isset($_SESSION['user']['user_ID'])) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

require_once __DIR__ . '/../../conn.php';
require_once __DIR__ . '/../../logActivity.php';

$user_ID = $_SESSION['user']['user_ID'];

// Get JSON input
$input = json_decode(file_get_contents("php://input"), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid input"]);
    exit;
}

// Sanitize inputs
$firstName  = trim($input['firstName'] ?? "");
$middleName = trim($input['middleName'] ?? "");
$lastName   = trim($input['lastName'] ?? "");
$email      = trim($input['email'] ?? "");

// Basic validation
if (empty($firstName) || empty($lastName) || empty($email)) {
    http_response_code(400);
    echo json_encode(["error" => "First name, last name, and email are required."]);
    exit;
}

try {
    // Update query with PDO
    $sql = "UPDATE user u
        JOIN kld k ON u.kld_ID = k.kld_ID
        SET u.f_name = :firstName,
            u.m_name = :middleName,
            u.l_name = :lastName,
            k.kld_email = :email
        WHERE u.user_ID = :user_ID";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ":firstName"  => $firstName,
        ":middleName" => $middleName,
        ":lastName"   => $lastName,
        ":email"      => $email,
        ":user_ID"    => $user_ID
    ]);

    // Log the activity
    logActivity(
        $pdo,
        $user_ID,                         // who did it
        "UPDATE",                         // action type
        "user",                           // module
        $user_ID,                         // affected record
        "Updated profile info: $firstName $lastName <$email>" // description
    );

    // Return updated data
    echo json_encode([
        "success"    => true,
        "firstName"  => $firstName,
        "middleName" => $middleName,
        "lastName"   => $lastName,
        "email"      => $email
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Database error: " . $e->getMessage()]);
}
?>