<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

function getMailer(): PHPMailer {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'repuya.juanmiguel.kld@gmail.com'; // Can be modify
    $mail->Password   = 'qglriuafygubefju'; // Can be modify
    $mail->SMTPSecure = 'tls';
    $mail->Port       = 587;

    $mail->setFrom('repuya.juanmiguel.kld@gmail.com', 'IMS Admin'); // Can be modify
    return $mail;
}

$domain = "https://ims-kld-app.infinityfreeapp.com/"
?>