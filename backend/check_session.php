<?php
session_start();

require_once __DIR__ . '/conn.php';

if (isset($_SESSION['user'])) {
    echo json_encode([
        'loggedIn' => true,
        'account_ID' => $_SESSION['user']['account_ID'],
        'user_ID' => $_SESSION['user']['user_ID'],
        'email' => $_SESSION['user']['email'],
        'role' => $_SESSION['user']['role'],
        'name' => $_SESSION['user']['name'],
        'unit_ID' => $_SESSION['user']['unit_ID']
    ]);
    exit;
}

// ✅ Check for remember_token cookie
if (isset($_COOKIE['remember_token'])) {
    $token = $_COOKIE['remember_token'];

    $stmt = $pdo->prepare("SELECT * FROM account WHERE remember_token = :token AND token_expiry > NOW()");
    $stmt->execute(['token' => $token]);
    $account = $stmt->fetch();

    if ($account) {
        $_SESSION['user'] = [
            "account_ID" => $account["account_ID"],
            "role" => $account["role_ID"],
            "name" => $account["f_name"] . " " . $account["l_name"],
            "email" => $account['kld_email']
        ];
        echo json_encode(['loggedIn' => true, 'role' => $account["role_ID"]]);
        exit;
    }
}

echo json_encode(['loggedIn' => false]);
?>