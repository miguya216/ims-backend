<?php
session_start();
require_once __DIR__ . '/conn.php';
require_once __DIR__ . '/logActivity.php';  // ✅ include helper
header("Content-Type: application/json");

// Decode JSON input
$data = json_decode(file_get_contents("php://input"), true);
$email = $data["email"] ?? '';
$password = $data["password"] ?? '';
$rememberMe = $data["rememberMe"] ?? false;

// Basic validation
if (!$email || !$password) {
    echo json_encode(["success" => false, "message" => "Missing fields"]);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT 
            acc.*, 
            u.f_name, 
            u.l_name, 
            u.unit_ID,
            k.kld_email
        FROM account acc
        JOIN kld k ON k.kld_ID = acc.kld_ID
        JOIN user u ON u.user_ID = acc.user_ID
        WHERE k.kld_email = :email and user_status = 'active'
    ");
    $stmt->execute(["email" => $email]);
    $account = $stmt->fetch(); 

    if ($account && password_verify($password, $account['password_hash'])) {
        $token = bin2hex(random_bytes(16));
        $expiry = date('Y-m-d H:i:s', strtotime('+7 days'));

        if ($rememberMe) {
            setcookie("remember_token", $token, time() + (7 * 24 * 60 * 60), "/", "", false, true);
        }

        $_SESSION['user'] = [
            "account_ID" => $account["account_ID"],
            "user_ID" => $account["user_ID"],
            "role" => $account["role_ID"],
            "name" => $account["f_name"] . " " . $account["l_name"],
            "email" => $account['kld_email'],
            "unit_ID" => $account['unit_ID']
        ];

        $update = $pdo->prepare("
            UPDATE account 
            SET remember_token = :token, token_expiry = :expiry 
            WHERE account_ID = :id
        ");
        $update->execute([
            "token" => $token,
            "expiry" => $expiry,
            "id" => $account["account_ID"]
        ]);

        // log activity
        logActivity(
            $pdo,
            $account["account_ID"],
            "LOGIN",
            "account",
            $account["account_ID"],
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown device'
        );

        echo json_encode([
            "success" => true,
            "token" => $token,
            "user_ID" => $account["user_ID"],
            "name" => $account["f_name"] . " " . $account["l_name"],
            "role" => $account["role_ID"],
            "email" => $account["kld_email"],
            "unit_ID" => $account["unit_ID"]
        ]);
    } else {
        echo json_encode(["success" => false, "message" => "Invalid credentials"]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Server error: " . $e->getMessage()]);
}
?>
