<?php
require __DIR__.'/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

$c = require __DIR__.'/.env.php';

function trySend($host, $port, $secureConst, $c) {
  $m = new PHPMailer(true);
  $m->SMTPDebug = SMTP::DEBUG_SERVER;        // verbose logs
  $m->Debugoutput = 'error_log';             // send to php-fpm log
  $m->isSMTP();
  $m->Host       = $host;
  $m->SMTPAuth   = true;
  $m->Username   = $c['SMTP_USERNAME'];
  $m->Password   = $c['SMTP_PASSWORD'];
  $m->SMTPSecure = $secureConst;
  $m->Port       = $port;

  $m->setFrom($c['FROM_EMAIL'], $c['FROM_NAME']);
  $m->addAddress($c['FROM_EMAIL'], $c['FROM_NAME']); // send to self
  $m->Subject = "PHPMailer test {$host}:{$port}";
  $m->Body    = 'Hello from your server';

  $m->send();
}

try {
  echo "Trying STARTTLS :587...\n";
  trySend('smtp.gmail.com', 587, PHPMailer::ENCRYPTION_STARTTLS, $c);
  echo "OK via STARTTLS:587\n";
} catch (\Throwable $e1) {
  echo "Failed via STARTTLS:587 => ".$e1->getMessage()."\n";
  try {
    echo "Trying SMTPS :465...\n";
    trySend('smtp.gmail.com', 465, PHPMailer::ENCRYPTION_SMTPS, $c);
    echo "OK via SMTPS:465\n";
  } catch (\Throwable $e2) {
    echo "Failed via SMTPS:465 => ".$e2->getMessage()."\n";
  }
}
