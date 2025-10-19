<?php
session_start();

$total = $_SESSION['import_total'] ?? 0;
$current = $_SESSION['import_progress'] ?? 0;
$percentage = $total > 0 ? round(($current / $total) * 100) : 0;

echo json_encode([
    "current" => $current,
    "total" => $total,
    "percentage" => $percentage
]);
?>