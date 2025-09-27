<?php
require_once __DIR__ . '/common_start.php';
header('Content-Type: application/json; charset=utf-8');

$user = $_SESSION['user'] ?? null;
if ($user && !empty($user['id'])) {
  echo json_encode([
    'logged_in' => true,
    'user' => [
      'id'    => (int)$user['id'],
      'name'  => $user['name'] ?? null,
      'email' => $user['email'] ?? null,
      'phone' => $user['phone'] ?? null,
    ]
  ]);
} else {
  echo json_encode(['logged_in' => false]);
}
exit;
