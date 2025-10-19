<?php
require_once __DIR__ . "/../conn.php";
header("Content-Type: application/json; charset=UTF-8");

try {
    $input = json_decode(file_get_contents("php://input"), true);
    $token = $input["token"] ?? "";
    $newPassword = $input["newPassword"] ?? "";

    if (empty($token) || empty($newPassword)) {
        echo json_encode(["success" => false, "error" => "Missing data."]);
        exit;
    }

    // Check token validity
    $stmt = $pdo->prepare("
        SELECT kld_email 
        FROM forgot_pass_token 
        WHERE token = :token 
          AND is_used = FALSE 
          AND token_expiry >= CURDATE()
        LIMIT 1
    ");
    $stmt->execute([":token" => $token]);
    $record = $stmt->fetch();

    if (!$record) {
        echo json_encode(["success" => false, "error" => "Invalid or expired token."]);
        exit;
    }

    $email = $record["kld_email"];

    // Get the account ID via KLD email
    $stmt = $pdo->prepare("
        SELECT a.account_ID 
        FROM account a
        JOIN kld k ON a.kld_ID = k.kld_ID
        WHERE k.kld_email = :email
    ");
    $stmt->execute([":email" => $email]);
    $account = $stmt->fetch();

    if (!$account) {
        echo json_encode(["success" => false, "error" => "No account found for this email."]);
        exit;
    }

    // Update password
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    $pdo->prepare("
        UPDATE account 
        SET password_hash = :hash 
        WHERE account_ID = :id
    ")->execute([":hash" => $hashedPassword, ":id" => $account["account_ID"]]);

    // Mark token as used
    $pdo->prepare("
        UPDATE forgot_pass_token 
        SET is_used = TRUE 
        WHERE token = :token
    ")->execute([":token" => $token]);

    echo json_encode(["success" => true, "message" => "Password updated successfully."]);
} catch (Throwable $t) {
    echo json_encode(["success" => false, "error" => "Server error: " . $t->getMessage()]);
}
?>
