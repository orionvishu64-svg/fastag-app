<?php
session_start();

header('Content-Type: application/json');

require __DIR__ . '/vendor/autoload.php'; // Load Composer autoloader

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'db.php'; // Your database connection

// Get JSON data
$data = json_decode(file_get_contents("php://input"), true);
$email = $data['email'] ?? '';

if (empty($email)) {
    echo json_encode(["success" => false, "message" => "Email address is required."]);
    exit;
}

// Check if user exists
$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
$stmt->execute([$email]);
if (!$stmt->fetch()) {
    echo json_encode(["success" => false, "message" => "No user found with that email address."]);
    exit;
}

// Generate OTP
$otp = rand(100000, 999999);
$otp_expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));

// Update OTP in DB
$update = $pdo->prepare("UPDATE users SET otp_code = ?, otp_expires_at = ? WHERE email = ?");
$update->execute([$otp, $otp_expires_at, $email]);

// SMTP Configuration (replace with your Gmail details)
$smtpHost = "smtp.gmail.com";
$smtpUsername = "vishwasbarnwal20@gmail.com"; // Your Gmail
$smtpPassword = "dvoo qgif tyqc zwue";   // Your Gmail App Password
$smtpPort = 465; // 465 for SSL, 587 for TLS
$smtpSecure = PHPMailer::ENCRYPTION_SMTPS; // For SSL

try {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = $smtpHost;
    $mail->SMTPAuth   = true;
    $mail->Username   = $smtpUsername;
    $mail->Password   = $smtpPassword;
    $mail->SMTPSecure = $smtpSecure;
    $mail->Port       = $smtpPort;
    $mail->CharSet    = 'UTF-8';

    // Recipients
    $mail->setFrom($smtpUsername, 'Apna Payments');
    $mail->addAddress($email);

    // Content
    $mail->isHTML(true);
    $mail->Subject = 'Password Reset OTP';
    $mail->Body    = "Your OTP for password reset is: <strong>$otp</strong>. It is valid for 10 minutes.";
    $mail->AltBody = "Your OTP for password reset is: $otp. It is valid for 10 minutes.";

    $mail->send();
    echo json_encode(["success" => true, "message" => "OTP sent to your email."]);

} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Mailer Error: " . $mail->ErrorInfo]);
}
