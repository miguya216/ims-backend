<?php
require_once __DIR__ . '/../conn.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$type = $data['selectedType'] ?? '';
$id = $data['referenceID'] ?? '';

if (!$type || !$id) {
  echo json_encode(['success' => false, 'message' => 'Missing type or ID.']);
  exit;
}

// Mapping for dynamic table, ID field, and status column
$referenceMap = [
  'role' => [
    'table' => 'role',
    'idField' => 'role_ID',
    'statusField' => 'role_status',
  ],
  'unit' => [
    'table' => 'unit',
    'idField' => 'unit_ID',
    'statusField' => 'unit_status',
  ],
  'brand' => [
    'table' => 'brand',
    'idField' => 'brand_ID',
    'statusField' => 'brand_status',
  ],
  'asset_type' => [
    'table' => 'asset_type',
    'idField' => 'asset_type_ID',
    'statusField' => 'asset_type_status',
  ],
  'room' => [
    'table' => 'room',
    'idField' => 'room_ID',
    'statusField' => 'room_status',
  ]
];

if (!isset($referenceMap[$type])) {
  echo json_encode(['success' => false, 'message' => 'Invalid reference type.']);
  exit;
}

$table = $referenceMap[$type]['table'];
$idField = $referenceMap[$type]['idField'];
$statusField = $referenceMap[$type]['statusField'];

try {
  $stmt = $pdo->prepare("UPDATE $table SET $statusField = 'active' WHERE $idField = :id");
  $stmt->execute([':id' => $id]);

  echo json_encode(['success' => true, 'message' => ucfirst($type) . ' restored successfully.']);
} catch (PDOException $e) {
  echo json_encode(['success' => false, 'message' => 'Deletion failed: ' . $e->getMessage()]);
}
?>