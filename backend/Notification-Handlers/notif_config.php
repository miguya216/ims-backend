<?php
require_once __DIR__ . '/../conn.php';

/**
 * Send a notification to a specific user or broadcast to all.
 *
 * @param PDO $pdo                Database connection
 * @param string $title           Short title (e.g. "New Borrow Request")
 * @param string $message         Full notification message
 * @param int|null $recipientID   Account ID of recipient, or NULL for broadcast
 * @param int|null $senderID      Account ID of sender (optional)
 * @param string|null $module     Module name (e.g. "BRS", "RIS", "Asset")
 * @param string|null $referenceID Related record ID (optional)
 * @return bool                   True on success, false on failure
 */
function sendNotification(PDO $pdo, $title, $message, $recipientID = null, $senderID = null, $module = null, $referenceID = null)
{
    try {
        $sql = "INSERT INTO notification 
                (recipient_account_ID, sender_account_ID, title, message, module, reference_ID)
                VALUES (:recipient_account_ID, :sender_account_ID, :title, :message, :module, :reference_ID)";
                
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':recipient_account_ID' => $recipientID,
            ':sender_account_ID'    => $senderID,
            ':title'                => $title,
            ':message'              => $message,
            ':module'               => $module,
            ':reference_ID'         => $referenceID
        ]);
        
        return true;
    } catch (PDOException $e) {
        error_log("Notification insert failed: " . $e->getMessage());
        return false;
    }
}
?>
