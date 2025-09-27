<?php
use PHPMailer\PHPMailer\PHPMailer;
require_once __DIR__ . '/vendor/autoload.php';

function mailer(): PHPMailer {
    $c = require __DIR__ . '/.env.php';
    $mail = new PHPMailer(true);
    $mail->SMTPDebug  = 2;              // verbose
    $mail->Debugoutput = 'error_log';   // send output to php error_log
    $mail->isSMTP();
    $mail->Host       = $c['SMTP_HOST'];
    $mail->SMTPAuth   = true;
    $mail->Username   = $c['SMTP_USER'];
    $mail->Password   = $c['SMTP_PASS'];
    $mail->SMTPSecure = $c['SMTP_SECURE'] === 'ssl'
        ? PHPMailer::ENCRYPTION_SMTPS
        : PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = (int)$c['SMTP_PORT'];
    $mail->setFrom($c['SMTP_FROM'], $c['SMTP_FROM_NAME']);
    return $mail;
}
