<?php
// /config/google_login.php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../db.php';           // adjust path to your PDO $pdo
require_once __DIR__ . '/../vendor/autoload.php';

use Google\Client as GoogleClient;

try {
  $in = json_decode(file_get_contents('php://input'), true);
  if (empty($in['id_token'])) {
    http_response_code(400);
    echo json_encode(['success'=>false,'message'=>'Missing id_token']); exit;
  }

  $client = new GoogleClient();
  $client->setClientId('451470803008-4elocicg2u7j5ug7m0rutps2k72ln3nh.apps.googleusercontent.com'); // your web client id
  $payload = $client->verifyIdToken($in['id_token']);
  if (!$payload) {
    http_response_code(401);
    echo json_encode(['success'=>false,'message'=>'Invalid Google token']); exit;
  }

  $googleId = $payload['sub'];
  $email    = $payload['email'] ?? null;
  $name     = $payload['name'] ?? '';
  $picture  = $payload['picture'] ?? '';
  if (!$email || !($payload['email_verified'] ?? false)) {
    echo json_encode(['success'=>false,'message'=>'Google email not verified']); exit;
  }

  // Upsert user (adjust to your schema)
  $pdo->beginTransaction();
  $st = $pdo->prepare("SELECT id FROM users WHERE email=:e OR google_id=:g LIMIT 1");
  $st->execute([':e'=>$email, ':g'=>$googleId]);
  $u = $st->fetch(PDO::FETCH_ASSOC);

  if ($u) {
    $uid = $u['id'];
    $pdo->prepare("UPDATE users SET google_id=:g, provider='google', avatar_url=:p WHERE id=:id")
        ->execute([':g'=>$googleId, ':p'=>$picture, ':id'=>$uid]);
  } else {
    $pdo->prepare("INSERT INTO users (name,email,google_id,avatar_url,provider,created_at) VALUES (:n,:e,:g,:p,'google',NOW())")
        ->execute([':n'=>$name, ':e'=>$email, ':g'=>$googleId, ':p'=>$picture]);
    $uid = $pdo->lastInsertId();
  }
  $pdo->commit();

  $_SESSION['user_id'] = $uid;
  $_SESSION['user_email'] = $email;
  $_SESSION['user_name'] = $name;
  $_SESSION['auth_provider'] = 'google';

  echo json_encode([
    'success'=>true,
    'redirect'=>'/profile.php',
    'user'=>['id'=>$uid,'name'=>$name,'email'=>$email,'avatar'=>$picture]
  ]);
} catch (Throwable $e) {
  if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
  http_response_code(500);
  echo json_encode(['success'=>false,'message'=>'Server error']);
}
