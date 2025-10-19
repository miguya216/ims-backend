<?php
require_once __DIR__ . "/../conn.php";
header("Content-Type: application/json; charset=UTF-8");

try {
    if (!isset($_GET["token"]) || empty($_GET["token"])) {
        echo json_encode(["valid" => false]);
        exit;
    }

    $token = $_GET["token"];

    $stmt = $pdo->prepare("
        SELECT token_ID 
        FROM forgot_pass_token 
        WHERE token = :token 
          AND is_used = FALSE 
          AND token_expiry >= CURDATE()
        LIMIT 1
    ");
    $stmt->execute([":token" => $token]);
    $tokenValid = $stmt->fetch();

    echo json_encode(["valid" => $tokenValid ? true : false]);
} catch (Throwable $t) {
    echo json_encode(["valid" => false, "error" => $t->getMessage()]);
}
?>
