<?php
require_once __DIR__ . '/../../common_start.php';
require_once __DIR__ . '/../lib/admin_ship_api.php';
header('Content-Type: application/json');

$pin = trim($_GET['pin'] ?? $_POST['pin'] ?? '');
if (!$pin) { echo json_encode(['success'=>false,'error'=>'missing pin']); exit; }

$resp = admin_api_post('/fastag_admin/api/pincode_serviceability.php', ['pincode'=>$pin]);
echo json_encode($resp['json'] ?? ['success'=>false,'error'=>'no_response']);
exit;
