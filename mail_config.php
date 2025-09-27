<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ✅ autoloader must be relative to THIS file
require_once __DIR__ . '/vendor/autoload.php';

function mailer(): PHPMailer {
    $c = require __DIR__ . '/.env.php';

    $mail = new PHPMailer(true);
    $mail->isSMTP();

    // IMPORTANT: use hostname (not IP) so TLS cert matches
    $mail->Host = $c['SMTP_HOST']; // 'smtp.gmail.com'
    $mail->SMTPAuth = true;
    $mail->Username = $c['SMTP_USER'];
    $mail->Password = $c['SMTP_PASS'];

    if (strcasecmp($c['SMTP_SECURE'], 'SSL') === 0) {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = 465;
    } else {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
    }

    $mail->Timeout       = 10;
    $mail->SMTPKeepAlive = true;
    $mail->CharSet       = 'UTF-8';
    $mail->setFrom($c['SMTP_FROM'], $c['SMTP_FROM_NAME']);

    // Optional while debugging:
    // $mail->SMTPDebug   = 2;
    // $mail->Debugoutput = 'error_log';

    return $mail;
}
