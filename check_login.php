<?php
// check_login.php
require_once 'common_start.php'; // ensure this starts the session
header('Content-Type: application/json');

if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
  echo json_encode(['logged_in' => true, 'user_id' => $_SESSION['user_id'], 'name' => $_SESSION['user_name'] ?? null]);
} else {
  echo json_encode(['logged_in' => false]);
}
exit;
