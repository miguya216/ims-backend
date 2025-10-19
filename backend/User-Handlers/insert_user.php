<?php
session_start();
require_once __DIR__ . '/../conn.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    $user = new User();

    // ✅ Get the account_ID from session
    $account_ID = isset($_SESSION['user']['user_ID']) ? intval($_SESSION['user']['user_ID']) : null;

    $response = $user->insertNewUser(
        $account_ID,                       // who performed the action
        $data['role'] ?? '',
        $data['kld_email'] ?? '',
        $data['password'] ?? '',
        $data['kld_id'] ?? '',
        $data['first_name'] ?? '',
        $data['middle_name'] ?? '',
        $data['last_name'] ?? '',
        $data['new_unit'] ?: $data['unit'] // Use new unit name if provided
    );

    echo json_encode([
        'status' => $response === true ? 'success' : 'error',
        'message' => $response
    ]);
    exit;
}


class User {

    private $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }

    public function insertNewUser($account_ID, $role, $kld_email, $password, $kld_id, $firstName, $middleName, $lastName, $unit) {
    try {
        $this->pdo->beginTransaction();

        // Always insert into kld if kld_ID is given
        if (!empty($kld_id)) {
            $stmt = $this->pdo->prepare("SELECT kld_ID FROM kld WHERE kld_ID = ?");
            $stmt->execute([$kld_id]);

            if ($stmt->rowCount() === 0) {
                $stmt = $this->pdo->prepare("INSERT INTO kld (kld_ID, kld_email) VALUES (?, ?)");
                $stmt->execute([$kld_id, $kld_email]);
            }
        }

        // Handle unit (insert if name, get ID if numeric)
        if (is_numeric($unit)) {
            $stmt = $this->pdo->prepare("SELECT unit_ID FROM unit WHERE unit_ID = ?");
            $stmt->execute([$unit]);
            $unit_id = $stmt->fetchColumn();
        } else {
            $stmt = $this->pdo->prepare("SELECT unit_ID FROM unit WHERE unit_name = ?");
            $stmt->execute([$unit]);
            if ($stmt->rowCount() === 0) {
                $stmt = $this->pdo->prepare("INSERT INTO unit (unit_name) VALUES (?)");
                $stmt->execute([$unit]);
                $unit_id = $this->pdo->lastInsertId();
            } else {
                $unit_id = $stmt->fetchColumn();
            }
        }

        // Handle role if creating account
        $role_id = null;
        if (!empty($kld_email) && !empty($password) && !empty($role)) {
            if (is_numeric($role)) {
                $stmt = $this->pdo->prepare("SELECT role_ID FROM role WHERE role_ID = ?");
                $stmt->execute([$role]);
                $role_id = $stmt->fetchColumn();
            } else {
                $stmt = $this->pdo->prepare("SELECT role_ID FROM role WHERE role_name = ?");
                $stmt->execute([$role]);
                if ($stmt->rowCount() === 0) {
                    $stmt = $this->pdo->prepare("INSERT INTO role (role_name) VALUES (?)");
                    $stmt->execute([$role]);
                    $role_id = $this->pdo->lastInsertId();
                } else {
                    $role_id = $stmt->fetchColumn();
                }
            }
        }

        // Check duplicate
        $stmt = $this->pdo->prepare("
            SELECT user_ID FROM user 
            WHERE f_name = ? AND m_name = ? AND l_name = ? AND unit_ID = ?
        ");
        $stmt->execute([$firstName, $middleName, $lastName, $unit_id]);
        if ($stmt->rowCount() > 0) {
            $this->pdo->rollBack();
            return "duplicate";
        }

        // Insert into user
        $stmt = $this->pdo->prepare("
            INSERT INTO user (f_name, m_name, l_name, kld_ID, unit_ID) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$firstName, $middleName, $lastName, $kld_id ?: null, $unit_id]);
        $userId = $this->pdo->lastInsertId();

        // Insert into account if info provided
        if (!empty($kld_email) && !empty($password) && !empty($role_id)) {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $this->pdo->prepare("
                INSERT INTO account (user_ID, kld_ID, password_hash, role_ID, qr_ID) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$userId, $kld_id, $hashedPassword, $role_id, NULL]);

            // Generate QR code
            $qr = new QrCode($kld_id);
            $writer = new PngWriter();
            $qrFilename = uniqid("qr_account_") . ".png";
            $qrPath = "qrcodes/$qrFilename";
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . "/IMS-REACT/frontend/public/" . $qrPath, $writer->write($qr)->getString());

            // Save qr_code
            $stmt = $this->pdo->prepare("INSERT INTO qr_code (qr_image_path) VALUES (?)");
            $stmt->execute([$qrPath]);
            $new_qr_id = $this->pdo->lastInsertId();

            $stmt = $this->pdo->prepare("UPDATE account SET qr_ID = ? WHERE user_ID = ?");
            $stmt->execute([$new_qr_id, $userId]);
        }

        // === Insert Activity Log ===
        require_once __DIR__ . '/../logActivity.php';
        logActivity(
            $this->pdo,
            $account_ID,        // who performed the action (from session ideally)
            "INSERT",           // action
            "user",             // module
            $userId,            // record_ID
            "Registered new user: {$firstName} {$middleName} {$lastName} (KLD ID: {$kld_id}, Email: {$kld_email})"
        );

        $this->pdo->commit();
        return true;

    } catch (PDOException $e) {
        $this->pdo->rollBack();
        return $e->getMessage();
    }
}
    
}
?>
