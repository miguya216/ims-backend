<?php
session_start();
require_once __DIR__ . '/conn.php';
require_once __DIR__ . '/logActivity.php'; // helper you already created
header("Content-Type: application/json");

// Handle preflight (optional)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    // if using CORS with credentials, the front-end must set a specific origin and server must echo it
    // header("Access-Control-Allow-Origin: https://your.frontend.domain");
    // header("Access-Control-Allow-Credentials: true");
    // header("Access-Control-Allow-Methods: POST, OPTIONS");
    // header("Access-Control-Allow-Headers: Content-Type, Authorization");
    http_response_code(200);
    exit();
}

try {
    $account_ID = null;
    $token = null;

    // 1) Try Authorization header (Bearer token)
    $authHeader = null;
    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
    } elseif (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    } elseif (function_exists('getallheaders')) {
        $hdrs = getallheaders();
        if (!empty($hdrs['Authorization'])) $authHeader = $hdrs['Authorization'];
        if (!empty($hdrs['authorization'])) $authHeader = $hdrs['authorization'];
    }
    if ($authHeader && preg_match('/Bearer\s+(\S+)/i', $authHeader, $m)) {
        $token = $m[1];
    }

    // 2) Try JSON body token (if front-end sends JSON with token)
    if (!$token) {
        $raw = file_get_contents('php://input');
        $body = json_decode($raw, true);
        if (!empty($body['token'])) $token = $body['token'];
    }

    // 3) Try cookie
    if (!$token && isset($_COOKIE['remember_token'])) {
        $token = $_COOKIE['remember_token'];
    }

    // 4) Try session account_ID if available
    if (!empty($_SESSION['user']['account_ID'])) {
        $account_ID = $_SESSION['user']['account_ID'];
    }

    // 5) If we have a token but not account_ID, look up account by token
    if ($token && !$account_ID) {
        $stmt = $pdo->prepare("SELECT account_ID FROM account WHERE remember_token = :token LIMIT 1");
        $stmt->execute(['token' => $token]);
        $row = $stmt->fetch();
        if ($row) $account_ID = $row['account_ID'];
    }

    // 6) Clear token in DB — prefer clearing by account_ID
    if ($account_ID) {
        $stmt = $pdo->prepare("UPDATE account SET remember_token = NULL, token_expiry = NULL WHERE account_ID = :id");
        $stmt->execute(['id' => $account_ID]);
    } elseif ($token) {
        // fallback: clear by token
        $stmt = $pdo->prepare("UPDATE account SET remember_token = NULL, token_expiry = NULL WHERE remember_token = :token");
        $stmt->execute(['token' => $token]);
    }

    // 7) Destroy session and expire cookie (match the same params used when setting it)
    session_unset();
    session_destroy();

    // expire cookie — keep same path/domain/secure/httponly settings as when you set it
    setcookie("remember_token", "", time() - 3600, "/", "", false, true);

    // 8) Log activity if we know the account
    if ($account_ID) {
        logActivity(
            $pdo,
            $account_ID,
            "LOGOUT",
            "account",
            $account_ID,
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown device'
        );
    }

    echo json_encode(["success" => true, "message" => "Logged out"]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>


<!-- 
session_start();
require_once __DIR__ . '/conn.php';
require_once __DIR__ . '/logActivity.php'; // include helper

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Use cookie instead of Authorization header
$token = $_COOKIE['remember_token'] ?? '';

try {
    $account_ID = null;

    if ($token) {
        // Find account by token
        $stmt = $pdo->prepare("SELECT account_ID FROM account WHERE remember_token = :token");
        $stmt->execute(["token" => $token]);
        $account = $stmt->fetch();

        if ($account) {
            $account_ID = $account["account_ID"];
        }

        // Clear token in DB
        $stmt = $pdo->prepare("UPDATE account SET remember_token = NULL, token_expiry = NULL WHERE remember_token = :token");
        $stmt->execute(["token" => $token]);
    }

    // Clear session
    session_unset();
    session_destroy();

    // Expire the cookie
    setcookie("remember_token", "", time() - 3600, "/", "", false, true);

    // log activity
    if ($account_ID) {
        logActivity(
            $pdo,
            $account_ID,
            "LOGOUT",
            "account",
            $account_ID,
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown device'
        );
    }

    echo json_encode(["success" => true, "message" => "Logged out"]);
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
 -->
