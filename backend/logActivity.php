<?php
// logActivity.php

if (!function_exists('logActivity')) {
    /**
     * Logs user activity into the activity_log table
     *
     * @param PDO $pdo            Database connection
     * @param int|null $account_ID  Who performed the action (null if system-generated)
     * @param string $action_type   Action type (INSERT, UPDATE, DELETE, LOGIN, LOGOUT, etc.)
     * @param string $module        Module/feature affected (asset, account, request_form, etc.)
     * @param int|null $record_ID   Affected record ID (nullable)
     * @param string|null $description Additional description (device/browser info, remarks, etc.)
     */
    function logActivity($pdo, $account_ID, $action_type, $module, $record_ID = null, $description = null) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO activity_log (account_ID, action_type, module, record_ID, description)
                VALUES (:account_ID, :action_type, :module, :record_ID, :description)
            ");
            $stmt->execute([
                "account_ID" => $account_ID,
                "action_type" => $action_type,
                "module" => $module,
                "record_ID" => $record_ID,
                "description" => $description
            ]);
        } catch (Exception $e) {
            error_log("Activity log failed: " . $e->getMessage());
        }
    }
}
