<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$vendor = __DIR__.'/vendor/autoload.php';
if (!is_readable($vendor)) { error_log('PHPMailer autoload missing'); throw new RuntimeException('Mail setup error'); }
require_once $vendor;

$env = __DIR__.'/.env.php';
if (!is_readable($env)) { error_log('Mail env missing: '.$env); throw new RuntimeException('Mail config missing'); }

function mailer(): PHPMailer {
  $c = require __DIR__.'/.env.php';

  foreach (['SMTP_HOST','SMTP_PORT','SMTP_SECURE','SMTP_USERNAME','SMTP_PASSWORD','FROM_EMAIL','FROM_NAME'] as $k) {
    if (!isset($c[$k]) || $c[$k]==='') throw new RuntimeException("Missing mail config key: $k");
  }

  $m = new PHPMailer(true);
  $m->isSMTP();
  $m->Host       = $c['SMTP_HOST'];
  $m->SMTPAuth   = true;
  $m->Username   = $c['SMTP_USERNAME'];
  $m->Password   = $c['SMTP_PASSWORD'];
  $m->SMTPSecure = $c['SMTP_SECURE']; // PHPMailer constant
  $m->Port       = $c['SMTP_PORT'];
  $m->setFrom($c['FROM_EMAIL'], $c['FROM_NAME']);
  if (!empty($c['REPLY_TO'])) $m->addReplyTo($c['REPLY_TO'], $c['FROM_NAME']);
  $m->isHTML(true);
  return $m;
}
