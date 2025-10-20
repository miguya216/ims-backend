<?php
header("Access-Control-Allow-Origin: https://ims-kld-app.infinityfreeapp.com");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}


$host = 'sql210.infinityfree.com';
$db   = 'if0_39940134_IMS';
$user = 'if0_39940134';
$pass = 'Apnengcnnn216';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Change only this when moving from local → hosting
// define("BASE_STORAGE_PATH", $_SERVER['DOCUMENT_ROOT'] . '/IMS-REACT/frontend/public/');

define("BASE_STORAGE_PATH", $_SERVER['DOCUMENT_ROOT'] . '/'); // for hosting (barcode and qrcode directory)

?>
