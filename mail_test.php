<?php
require_once __DIR__ . '/mail_config.php';
try {
  $m = mailer();
  $m->addAddress('vishwasbarnwal20@gmail.com'); // change to your email
  $m->Subject = 'SMTP test';
  $m->Body    = 'If you got this, SMTP works 👍';
  $m->send();
  echo 'OK';
} catch (Throwable $e) {
  echo 'ERROR: ' . $e->getMessage();
}
