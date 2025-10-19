<?php

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../conn.php';

class Details {
    private $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }

    public function getUserById($user_ID) {
        $stmt = $this->pdo->prepare("
            SELECT 
                u.user_ID, 
                u.f_name AS first_name, 
                u.m_name AS middle_name, 
                u.l_name AS last_name,
                u.kld_ID, 
                k.kld_email,
                un.unit_name,
                r.role_name
            FROM user u
            LEFT JOIN kld k ON u.kld_ID = k.kld_ID
            LEFT JOIN account a ON u.user_ID = a.user_ID
            LEFT JOIN role r ON a.role_ID = r.role_ID
            LEFT JOIN unit un ON u.unit_ID = un.unit_ID
            WHERE u.user_ID = ?
        ");
        $stmt->execute([$user_ID]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

// MAIN EXECUTION BLOCK
if (isset($_GET['user_ID'])) {
    $userID = $_GET['user_ID'];
    $details = new Details();
    $result = $details->getUserById($userID);

    if ($result) {
        echo json_encode($result);
    } else {
        http_response_code(404);
        echo json_encode(["error" => "User not found"]);
    }
} else {
    http_response_code(400);
    echo json_encode(["error" => "user_ID not provided"]);
}
?>
