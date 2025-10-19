<?php
session_start();
require_once __DIR__ . '/../conn.php';

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['tag']) || !isset($data['room_ID'])) {
    http_response_code(400);
    echo json_encode(["error" => "Missing parameters"]);
    exit;
}

$user_ID = $_SESSION['user']['user_ID']; // Only employee's assets
$tag = trim($data['tag']);
$room_ID = intval($data['room_ID']);

try {
    // update asset only if it belongs to logged-in employee
    $sql = "
        UPDATE asset
        SET room_ID = :room_ID
        WHERE (kld_property_tag = :tag OR property_tag = :tag)
          AND asset_status = 'active'
          AND responsible_user_ID = :user_ID
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ":room_ID" => $room_ID,
        ":tag" => $tag,
        ":user_ID" => $user_ID
    ]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(["success" => true, "message" => "Asset moved successfully"]);
    } else {
        echo json_encode(["success" => false, "message" => "No asset found with that tag or not assigned to you"]);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}
?>
