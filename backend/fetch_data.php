<?php
require_once __DIR__ . '/conn.php';
session_start(); // make sure session is started
if (!isset($_SESSION['user']['account_ID']) || !isset($_SESSION['user']['unit_ID'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Not logged in']);
    return;
}

$account_ID = $_SESSION['user']['account_ID'];
$unit_ID = $_SESSION['user']['unit_ID'];

class Tables {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function fetchActiveAssets() {
        try {
            $stmt = $this->pdo->prepare(" 
               SELECT 
                    asset.asset_ID,
                    asset.kld_property_tag,
                    asset.price_amount,
                    asset_condition.condition_name AS asset_condition,
                    asset.asset_status,
                    brand.brand_name,
                    asset_type.asset_type,
                    CONCAT(user.f_name, ' ', COALESCE(user.m_name, ''), ' ', user.l_name) AS responsible
                FROM asset
                JOIN brand ON asset.brand_ID = brand.brand_ID
                JOIN asset_type ON asset.asset_type_ID = asset_type.asset_type_ID
                JOIN user ON asset.responsible_user_ID = user.user_ID
                JOIN asset_condition ON asset.asset_condition_ID = asset_condition.asset_condition_ID
                WHERE asset.asset_status = 'active' AND asset.is_borrowable = 'no';");
            $stmt->execute();
            echo json_encode($stmt->fetchAll());
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Query failed: ' . $e->getMessage()]);
        }
    }

    public function fetchActiveAssetsBorrowable() {
        try {
            $stmt = $this->pdo->prepare(" 
               SELECT 
                    asset.asset_ID,
                    asset.kld_property_tag,
                    asset.price_amount,
                    asset_condition.condition_name AS asset_condition,
                    asset.asset_status,
                    brand.brand_name,
                    asset_type.asset_type,
                    CONCAT(user.f_name, ' ', COALESCE(user.m_name, ''), ' ', user.l_name) AS responsible
                FROM asset
                JOIN brand ON asset.brand_ID = brand.brand_ID
                JOIN asset_type ON asset.asset_type_ID = asset_type.asset_type_ID
                JOIN user ON asset.responsible_user_ID = user.user_ID
                JOIN asset_condition ON asset.asset_condition_ID = asset_condition.asset_condition_ID
                WHERE asset.asset_status != 'inactive'AND asset.is_borrowable = 'yes';");
            $stmt->execute();
            echo json_encode($stmt->fetchAll());
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Query failed: ' . $e->getMessage()]);
        }
    }

     public function fetchInactiveAssets() {
        try {
            $stmt = $this->pdo->prepare(" 
               SELECT 
                    asset.asset_ID,
                    asset.kld_property_tag,
                    asset.price_amount,
                    asset_condition.condition_name AS asset_condition,
                    asset.asset_status,
                    brand.brand_name,
                    asset_type.asset_type,
                    CONCAT(user.f_name, ' ', COALESCE(user.m_name, ''), ' ', user.l_name) AS responsible
                FROM asset
                JOIN brand ON asset.brand_ID = brand.brand_ID
                JOIN asset_type ON asset.asset_type_ID = asset_type.asset_type_ID
                JOIN user ON asset.responsible_user_ID = user.user_ID
                JOIN asset_condition ON asset.asset_condition_ID = asset_condition.asset_condition_ID
                WHERE asset.asset_status = 'inactive';");
            $stmt->execute();
            echo json_encode($stmt->fetchAll());
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Query failed: ' . $e->getMessage()]);
        }
    }

    public function fetchActiveCustodianAssets($unit_ID) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    asset.asset_ID,
                    asset.kld_property_tag,
                    asset.property_tag,
                    asset_condition.condition_name AS asset_condition,
                    asset.asset_status,
                    brand.brand_name,
                    asset_type.asset_type,
                    CONCAT(user.f_name, ' ', COALESCE(user.m_name, ''), ' ', user.l_name) AS responsible
                FROM asset
                JOIN brand ON asset.brand_ID = brand.brand_ID
                JOIN asset_type ON asset.asset_type_ID = asset_type.asset_type_ID
                JOIN user ON asset.responsible_user_ID = user.user_ID
                JOIN asset_condition ON asset.asset_condition_ID = asset_condition.asset_condition_ID
                WHERE asset.asset_status = 'active' AND user.unit_ID = :unit_ID
            ");
            $stmt->execute(['unit_ID' => $unit_ID]);
            echo json_encode($stmt->fetchAll());
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Query failed: ' . $e->getMessage()]);
        }
    }

    public function fetchInactiveCustodianAssets($unit_ID) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    asset.asset_ID,
                    asset.kld_property_tag,
                    asset.property_tag,
                    asset_condition.condition_name AS asset_condition,
                    asset.asset_status,
                    brand.brand_name,
                    asset_type.asset_type,
                    CONCAT(user.f_name, ' ', COALESCE(user.m_name, ''), ' ', user.l_name) AS responsible
                FROM asset
                JOIN brand ON asset.brand_ID = brand.brand_ID
                JOIN asset_type ON asset.asset_type_ID = asset_type.asset_type_ID
                JOIN user ON asset.responsible_user_ID = user.user_ID
                JOIN asset_condition ON asset.asset_condition_ID = asset_condition.asset_condition_ID
                WHERE asset.asset_status = 'inactive' AND user.unit_ID = :unit_ID
            ");
            $stmt->execute(['unit_ID' => $unit_ID]);
            echo json_encode($stmt->fetchAll());
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Query failed: ' . $e->getMessage()]);
        }
    }

    public function fetchActiveUsers() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    user.user_ID,
                    kld.kld_ID AS kld_id,
                    CONCAT(user.f_name, ' ', COALESCE(user.m_name, ''), ' ', user.l_name) AS full_name,
                    unit.unit_name AS unit,
                    role.role_name AS role
                FROM user
                LEFT JOIN kld ON user.kld_ID = kld.kld_ID
                LEFT JOIN unit ON user.unit_ID = unit.unit_ID
                LEFT JOIN account ON user.user_ID = account.user_ID
                LEFT JOIN role ON account.role_ID = role.role_ID
                WHERE user.user_status = 'active' and user.user_ID != 1;");
            $stmt->execute();
            echo json_encode($stmt->fetchAll());
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Query failed: ' . $e->getMessage()]);
        }
    }

    public function fetchInactiveUsers() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    user.user_ID,
                    kld.kld_ID AS kld_id,
                    CONCAT(user.f_name, ' ', COALESCE(user.m_name, ''), ' ', user.l_name) AS full_name,
                    unit.unit_name AS unit,
                    role.role_name AS role
                FROM user
                LEFT JOIN kld ON user.kld_ID = kld.kld_ID
                LEFT JOIN unit ON user.unit_ID = unit.unit_ID
                LEFT JOIN account ON user.user_ID = account.user_ID
                LEFT JOIN role ON account.role_ID = role.role_ID
                WHERE user.user_status = 'inactive' and user.user_ID != 1;");
            $stmt->execute();
            echo json_encode($stmt->fetchAll());
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Query failed: ' . $e->getMessage()]);
        }
    }

    public function fetchActiveStandardRequests() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    rf.request_ID,
                    a.kld_ID,
                    a.kld_email,
                    rf.request_date,
                    rf.request_time,
                    rf.needed_date,
                    rf.needed_time,
                    rf.expected_due_date,
                    rf.expected_due_time,
                    rf.purpose,
                    rf.response_status,
                    GROUP_CONCAT(CONCAT(rt.asset_type, ' (x', ri.quantity, ')') SEPARATOR ', ') AS requested_items
                FROM request_form rf
                JOIN account acc ON rf.account_ID = acc.account_ID
                LEFT JOIN user u ON acc.user_ID = u.user_ID
                LEFT JOIN kld a ON u.kld_ID = a.kld_ID
                LEFT JOIN request_items ri ON rf.request_ID = ri.request_ID
                LEFT JOIN asset_type rt ON ri.asset_type_ID = rt.asset_type_ID
                WHERE rf.request_status = 'active'
                GROUP BY rf.request_ID
                ORDER BY rf.request_date DESC, rf.request_time DESC
            ");
            $stmt->execute();
            echo json_encode($stmt->fetchAll());
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Query failed: ' . $e->getMessage()]);
        }
    }

    public function fetchActiveCustodianStandardRequests($unit_ID) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    rf.request_ID,
                    a.kld_ID,
                    a.kld_email,
                    rf.request_date,
                    rf.request_time,
                    rf.needed_date,
                    rf.needed_time,
                    rf.expected_due_date,
                    rf.expected_due_time,
                    rf.purpose,
                    rf.response_status,
                    GROUP_CONCAT(CONCAT(rt.asset_type, ' (x', ri.quantity, ')') SEPARATOR ', ') AS requested_items
                FROM request_form rf
                JOIN account acc ON rf.account_ID = acc.account_ID
                JOIN user u ON acc.user_ID = u.user_ID
                LEFT JOIN kld a ON u.kld_ID = a.kld_ID
                LEFT JOIN request_items ri ON rf.request_ID = ri.request_ID
                LEFT JOIN asset_type rt ON ri.asset_type_ID = rt.asset_type_ID
                WHERE rf.request_status = 'active'
                AND u.unit_ID = :unit_ID
                GROUP BY rf.request_ID
                ORDER BY rf.request_date DESC, rf.request_time DESC
            ");
            $stmt->execute(['unit_ID' => $unit_ID]);
            echo json_encode($stmt->fetchAll());
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Query failed: ' . $e->getMessage()]);
        }
    }


    public function fetchActiveRequestsHistory($account_ID) {

        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    rf.request_ID,
                    a.kld_ID,
                    a.kld_email,
                    rf.request_date,
                    rf.request_time,
                    rf.needed_date,
                    rf.needed_time,
                    rf.expected_due_date,
                    rf.expected_due_time,
                    rf.purpose,
                    rf.response_status,
                    GROUP_CONCAT(CONCAT(rt.asset_type, ' (x', ri.quantity, ')') SEPARATOR ', ') AS requested_items
                FROM request_form rf
                JOIN account acc ON rf.account_ID = acc.account_ID
                LEFT JOIN user u ON acc.user_ID = u.user_ID
                LEFT JOIN kld a ON u.kld_ID = a.kld_ID
                LEFT JOIN request_items ri ON rf.request_ID = ri.request_ID
                LEFT JOIN asset_type rt ON ri.asset_type_ID = rt.asset_type_ID
                WHERE rf.request_status = 'active'
                AND rf.account_ID = :account_ID
                GROUP BY rf.request_ID
                ORDER BY rf.request_date DESC, rf.request_time DESC
            ");
            $stmt->execute(['account_ID' => $account_ID]);
            echo json_encode($stmt->fetchAll());
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Query failed: ' . $e->getMessage()]);
        }
    }

    public function fetchActiveCheckoutItems() {
        try {
            $stmt = $this->pdo->prepare("
               SELECT 
                    rf.account_ID,
                    k.kld_email AS borrower_email,
                    CONCAT(u.f_name, ' ', COALESCE(u.m_name, ''), ' ', u.l_name) AS borrower_name,
                    at.asset_type AS asset_type,
                    COUNT(bi.asset_ID) AS quantity,
                    rf.expected_due_date AS expected_return,
                    bi.returned_date,
                    bi.borrow_status

                FROM borrowed_items bi
                JOIN request_form rf ON bi.request_ID = rf.request_ID
                JOIN account acc ON rf.account_ID = acc.account_ID
                JOIN user u ON acc.user_ID = u.user_ID
                JOIN kld k ON acc.kld_ID = k.kld_ID
                JOIN asset a ON bi.asset_ID = a.asset_ID
                JOIN asset_type at ON a.asset_type_ID = at.asset_type_ID

                WHERE bi.borrow_status IN ('in use', 'returned') -- optional filter
                GROUP BY rf.account_ID, at.asset_type, rf.expected_due_date, bi.returned_date, bi.borrow_status
                ORDER BY rf.expected_due_date DESC;
            ");
            $stmt->execute();
            echo json_encode($stmt->fetchAll());
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Query failed: ' . $e->getMessage()]);
        }
    }

     public function fetchActiveCustodianCheckoutItems($unit_ID) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    rf.account_ID,
                    k.kld_email AS borrower_email,
                    CONCAT(u.f_name, ' ', COALESCE(u.m_name, ''), ' ', u.l_name) AS borrower_name,
                    at.asset_type AS asset_type,
                    COUNT(bi.asset_ID) AS quantity,
                    rf.expected_due_date AS expected_return,
                    bi.returned_date,
                    bi.borrow_status
                FROM borrowed_items bi
                JOIN request_form rf ON bi.request_ID = rf.request_ID
                JOIN account acc ON rf.account_ID = acc.account_ID
                JOIN user u ON acc.user_ID = u.user_ID
                JOIN kld k ON acc.kld_ID = k.kld_ID
                JOIN asset a ON bi.asset_ID = a.asset_ID
                JOIN asset_type at ON a.asset_type_ID = at.asset_type_ID
                WHERE bi.borrow_status IN ('in use', 'returned')
                AND u.unit_ID = :unit_ID
                GROUP BY rf.account_ID, at.asset_type, rf.expected_due_date, bi.returned_date, bi.borrow_status
                ORDER BY rf.expected_due_date DESC
            ");
            $stmt->execute(['unit_ID' => $unit_ID]); 
            echo json_encode($stmt->fetchAll());
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Query failed: ' . $e->getMessage()]);
        }
    }


    public function fetchActiveBorrowedItems($account_ID) {
        try {
            $stmt = $this->pdo->prepare("
               SELECT 
                    rf.account_ID,
                    k.kld_email AS borrower_email,
                    CONCAT(u.f_name, ' ', COALESCE(u.m_name, ''), ' ', u.l_name) AS borrower_name,
                    at.asset_type AS asset_type,
                    COUNT(bi.asset_ID) AS quantity,
                    rf.expected_due_date AS expected_return,
                    bi.returned_date,
                    bi.borrow_status

                FROM borrowed_items bi
                JOIN request_form rf ON bi.request_ID = rf.request_ID
                JOIN account acc ON rf.account_ID = acc.account_ID
                JOIN user u ON acc.user_ID = u.user_ID
                JOIN kld k ON acc.kld_ID = k.kld_ID
                JOIN asset a ON bi.asset_ID = a.asset_ID
                JOIN asset_type at ON a.asset_type_ID = at.asset_type_ID

                WHERE bi.borrow_status IN ('in use', 'returned')
                AND rf.account_ID = :account_ID
                GROUP BY rf.account_ID, at.asset_type, rf.expected_due_date, bi.returned_date, bi.borrow_status
                ORDER BY rf.expected_due_date DESC;
            ");
            $stmt->execute(['account_ID' => $account_ID]);
            echo json_encode($stmt->fetchAll());
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Query failed: ' . $e->getMessage()]);
        }
    }

}

$tables = new Tables($pdo);
$action = $_GET['action'] ?? 'assets';

switch ($action) {
    // for admin
    case 'usersarchive':
        $tables->fetchInactiveUsers();
        break;
    // for admin
    case 'users':
        $tables->fetchActiveUsers();
        break;
    // for admin
    case 'assets':
        $tables->fetchActiveAssets();
        break;
    case 'assetsborrowable':
        $tables->fetchActiveAssetsBorrowable();
        break;
    // for admin
    case 'assetsarchive':
        $tables->fetchInactiveAssets();
        break;
    // for admin
    case 'standardrequests':
        $tables->fetchActiveStandardRequests();
        break;
    // for admin
    case 'checkoutitems':
        $tables->fetchActiveCheckoutItems();
        break;
    // for custodians
    case 'custodianassets':
        $tables->fetchActiveCustodianAssets($unit_ID);
        break;
    // for custodians
    case 'custodianassetsarchive':
        $tables->fetchInactiveCustodianAssets($unit_ID);
        break;
    // for custodians
    case 'custodianstandardrequests':
        $tables->fetchActiveCustodianStandardRequests($unit_ID);
        break;
    // for custodian
    case 'custodiancheckoutitems':
        $tables->fetchActiveCustodianCheckoutItems($unit_ID);
        break;
    // for users
    case 'requesthistory':
        $tables->fetchActiveRequestsHistory($account_ID);
        break;
    // for user
    case 'borroweditems':
        $tables->fetchActiveBorrowedItems($account_ID);
        break;
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
        break;
}

?>
