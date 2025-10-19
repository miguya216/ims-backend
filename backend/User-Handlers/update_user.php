<?php
session_start();
require_once __DIR__ . '/../conn.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../logActivity.php'; // ✅ make sure this is included

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);

    $update = new UpdateUser();

    // ✅ Get account_ID from session
    $account_ID = $_SESSION['user']['user_ID'] ?? null;

    $response = $update->UpdateUser(
        $account_ID,
        $data['user_ID'] ?? '',
        $data['kld_id'] ?? '',
        $data['role'] ?? '',
        $data['kld_email'] ?? '',
        $data['password'] ?? '',
        $data['first_name'] ?? '',
        $data['middle_name'] ?? '',
        $data['last_name'] ?? '',
        $data['new_unit'] ?: $data['unit']
    );

    echo json_encode([
        'status' => $response === true ? 'success' : 'error',
        'message' => $response
    ]);
    exit;
}


class UpdateUser {
    private $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }

    public function UpdateUser($account_ID, $user_ID, $kld_ID, $role, $kld_email, $password, $f_name, $m_name, $l_name, $unit) {
        try {
            $this->pdo->beginTransaction();

            // === fetch current data ===
            $stmt = $this->pdo->prepare("
                SELECT u.f_name, u.m_name, u.l_name, u.unit_ID, u.kld_ID, 
                       k.kld_email, a.role_ID, a.account_ID
                FROM user u
                LEFT JOIN account a ON u.user_ID = a.user_ID
                LEFT JOIN kld k ON u.kld_ID = k.kld_ID
                WHERE u.user_ID = ?
            ");
            $stmt->execute([$user_ID]);
            $current = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$current) {
                $this->pdo->rollBack();
                return "User not found";
            }

            $changes = false;

            // === KLD handling ===
            if (!empty($kld_ID)) {
                if (!empty($kld_email)) {
                    $stmt = $this->pdo->prepare("SELECT kld_ID FROM kld WHERE kld_email = ? AND kld_ID != ?");
                    $stmt->execute([$kld_email, $kld_ID]);
                    if ($stmt->rowCount() > 0) {
                        $this->pdo->rollBack();
                        return "duplicate_email";
                    }
                }

                $stmt = $this->pdo->prepare("SELECT * FROM kld WHERE kld_ID = ?");
                $stmt->execute([$kld_ID]);

                if ($stmt->rowCount() > 0) {
                    if (!empty($kld_email) && $kld_email !== $current['kld_email']) {
                        $update = $this->pdo->prepare("UPDATE kld SET kld_email = ? WHERE kld_ID = ?");
                        $update->execute([$kld_email, $kld_ID]);
                        $changes = true;
                    }
                } else {
                    $insert = $this->pdo->prepare("INSERT INTO kld (kld_ID, kld_email) VALUES (?, ?)");
                    $insert->execute([$kld_ID, $kld_email]);
                    $changes = true;
                }
            }

            // === Role handling ===
            if (!empty($role)) {
                $stmt = $this->pdo->prepare("SELECT role_ID FROM role WHERE role_name = ?");
                $stmt->execute([$role]);
                $roleRow = $stmt->fetch();

                if ($roleRow) {
                    $role_ID = $roleRow['role_ID'];
                } else {
                    $insertRole = $this->pdo->prepare("INSERT INTO role (role_name) VALUES (?)");
                    $insertRole->execute([$role]);
                    $role_ID = $this->pdo->lastInsertId();
                }

                if ((int)$role_ID !== (int)$current['role_ID']) {
                    $changes = true;
                }
            } else {
                $role_ID = $current['role_ID'];
            }

            if (!empty($password)) {
                $changes = true;
            }

            // === Check user details ===
            if (
                $f_name !== $current['f_name'] || 
                $m_name !== $current['m_name'] || 
                $l_name !== $current['l_name'] || 
                (int)$unit !== (int)$current['unit_ID'] || 
                $kld_ID !== $current['kld_ID']
            ) {
                $changes = true;
            }

            if (!$changes) {
                $this->pdo->rollBack();
                return "no_changes";
            }

            // === Update user ===
            $stmt = $this->pdo->prepare("
                UPDATE user SET f_name = ?, m_name = ?, l_name = ?, unit_ID = ?, kld_ID = ? WHERE user_ID = ?
            ");
            $stmt->execute([$f_name, $m_name, $l_name, $unit, $kld_ID ?: null, $user_ID]);

            // === Account handling ===
            if (!empty($kld_email)) {
                $stmt = $this->pdo->prepare("SELECT account_ID FROM account WHERE user_ID = ?");
                $stmt->execute([$user_ID]);
                $accountExists = $stmt->fetch();

                if ($accountExists) {
                    $query = "UPDATE account SET kld_ID = :kld_ID, role_ID = :role_ID";
                    $params = [
                        ':kld_ID' => $kld_ID,
                        ':role_ID' => $role_ID,
                        ':user_ID' => $user_ID
                    ];

                    if (!empty($password)) {
                        $query .= ", password_hash = :password_hash";
                        $params[':password_hash'] = password_hash($password, PASSWORD_DEFAULT);
                    }

                    $query .= " WHERE user_ID = :user_ID";
                    $stmt = $this->pdo->prepare($query);
                    $stmt->execute($params);
                } else {
                    $stmt = $this->pdo->prepare("
                        INSERT INTO account (user_ID, kld_ID, role_ID, password_hash) VALUES (?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $user_ID,
                        $kld_ID,
                        $role_ID,
                        password_hash($password, PASSWORD_DEFAULT)
                    ]);

                    // Generate QR
                    $qr = new QrCode($kld_ID);
                    $writer = new PngWriter();
                    $qrFilename = uniqid("qr_account_") . ".png";
                    $qrPath = "qrcodes/$qrFilename";
                    file_put_contents($_SERVER['DOCUMENT_ROOT'] . "/IMS-REACT/frontend/public/" . $qrPath, $writer->write($qr)->getString());

                    $stmt = $this->pdo->prepare("INSERT INTO qr_code (qr_image_path) VALUES (?)");
                    $stmt->execute([$qrPath]);
                    $new_qr_id = $this->pdo->lastInsertId();

                    $stmt = $this->pdo->prepare("UPDATE account SET qr_ID = ? WHERE user_ID = ?");
                    $stmt->execute([$new_qr_id, $user_ID]);
                }
            }

            // === Log activity ===
            logActivity(
                $this->pdo,
                $account_ID,
                "UPDATE",
                "user",
                $user_ID,
                "Updated user details (KLD ID: $kld_ID, Email: $kld_email)"
            );

            $this->pdo->commit();
            return true;

        } catch (Exception $e) {
            $this->pdo->rollBack();
            return $e->getMessage();
        }
    }
}
?>