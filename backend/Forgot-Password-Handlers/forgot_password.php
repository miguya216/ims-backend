<?php
// backend/api/Auth/forgot_password.php
require_once __DIR__ . "/../conn.php";
require_once __DIR__ . "/../email_config.php";

use PHPMailer\PHPMailer\Exception;

header("Content-Type: application/json; charset=UTF-8");

try {
    // Parse JSON input
    $input = json_decode(file_get_contents("php://input"), true);
    if (!isset($input["email"]) || empty(trim($input["email"]))) {
        echo json_encode(["success" => false, "error" => "Email is required."]);
        exit;
    }

    $email = trim($input["email"]);

    // 1. Check if email exists in `kld`
    $stmt = $pdo->prepare("
        SELECT kld_email 
        FROM kld 
        WHERE kld_email = :email 
          AND kld_email_status = 'active'
    ");
    $stmt->execute([":email" => $email]);
    $user = $stmt->fetch();

    if (!$user) {
        echo json_encode(["success" => false, "error" => "Email not found or inactive."]);
        exit;
    }

    // 2. Create a unique token and compute expiry (24 hours from now)
    $token = bin2hex(random_bytes(32)); // 64-char hex token
    $expiryDate = date("Y-m-d", strtotime("+1 day")); // expires in 1 day

    // 3. Mark all previous tokens for this email as used
    $pdo->prepare("
        UPDATE forgot_pass_token 
        SET is_used = TRUE 
        WHERE kld_email = :email
    ")->execute([":email" => $email]);

    // 4. Insert new token with expiry
    $stmt = $pdo->prepare("
        INSERT INTO forgot_pass_token (kld_email, token, token_expiry, is_used)
        VALUES (:email, :token, :expiry, FALSE)
    ");
    $stmt->execute([
        ":email" => $email,
        ":token" => $token,
        ":expiry" => $expiryDate
    ]);

    // 5. Prepare reset link
    global $domain;
    $resetLink = rtrim($domain, "/") . "/RecoverAccount/" . $token;

    // 6. Send email using PHPMailer
    $mail = getMailer();
    $mail->addAddress($email);
    $mail->isHTML(true);
    $mail->Subject = "IMS Password Reset Request";
    $mail->Body = "
                    <div style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
                        <h2 style='color: #006705;'>Password Reset Request</h2>
                        <p>Dear user,</p>
                        <p>We received a request to reset your password for your <strong>IMS | CLARITY</strong> account.</p>
                        <p>Please click the link below to reset your password. <br>
                        <strong>Note:</strong> This link is valid for <strong>24 hours</strong> from the time of this email.</p>
                        <p style='margin: 16px 0;'>
                            <a href='$resetLink' 
                            style='display: inline-block; background-color: #006705; color: #fff; text-decoration: none; 
                                    padding: 10px 18px; border-radius: 6px; font-weight: bold;'>
                                Reset Password
                            </a>
                        </p>
                        <p>If you did not request this password reset, please ignore this email. Your account will remain secure.</p>
                        <br>
                        <p>Best regards,<br><strong>IMS Admin Team</strong></p>
                    </div>
                ";


    $mail->send();

    echo json_encode([
        "success" => true,
        "message" => "Password reset email sent successfully.",
        "link" => $resetLink // Optional: only for testing
    ]);
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "error" => "Email could not be sent. Mailer Error: " . $e->getMessage()
    ]);
} catch (Throwable $t) {
    echo json_encode([
        "success" => false,
        "error" => "Server error: " . $t->getMessage()
    ]);
}
?>
