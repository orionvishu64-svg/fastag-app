<?php
// expected_tat.php
// POST JSON: { "origin_pin": "560001", "dest_pin":"110001", "weight":1.0, "mode":"E" }
// Response JSON: { success:true, tat:3, expected_date:"2025-09-19T00:00:00", raw:... }

require_once __DIR__ . '/config/database.php'; // optional, only if you need DB
require_once __DIR__ . '/delhivery.php';

header('Content-Type: application/json; charset=utf-8');

$raw = file_get_contents('php://input');
$in = json_decode($raw, true);
if (!is_array($in)) $in = $_POST;

$origin = trim($in['origin_pin'] ?? ($in['o_pin'] ?? ''));
$dest = trim($in['dest_pin'] ?? ($in['d_pin'] ?? ($in['pincode'] ?? '')));
$weight = isset($in['weight']) ? floatval($in['weight']) : 1.0;
$mode = $in['mode'] ?? 'E';

if (!$origin || !$dest) {
    echo json_encode(['success'=>false,'message'=>'origin_pin and dest_pin are required']);
    exit;
}

// simple caching: store last checked in temporary file (optional)
$cache_key = "tat_{$origin}_{$dest}_{$weight}_{$mode}";
$cache_dir = __DIR__ . '/cache';
@mkdir($cache_dir, 0755, true);
$cache_file = $cache_dir . '/' . md5($cache_key) . '.json';
$cache_ttl = 60 * 60; // 1 hour
if (file_exists($cache_file) && (time() - filemtime($cache_file) < $cache_ttl)) {
    $cached = json_decode(file_get_contents($cache_file), true);
    if ($cached) {
        echo json_encode(['success'=>true,'tat'=>$cached['tat'] ?? null,'expected_date'=>$cached['expected_date'] ?? null,'cached'=>true,'raw'=>$cached['raw'] ?? null]);
        exit;
    }
}

// call helper
$res = delhivery_get_tat($origin, $dest, $weight, $mode);
if ($res['success']) {
    // cache a copy
    @file_put_contents($cache_file, json_encode(['tat'=>$res['tat'],'expected_date'=>$res['expected_date'],'raw'=>$res['raw']]));
    echo json_encode(['success'=>true,'tat'=>$res['tat'],'expected_date'=>$res['expected_date'],'raw'=>$res['raw']]);
} else {
    echo json_encode(['success'=>false,'message'=>$res['message'] ?? 'Failed','raw'=>$res['raw'] ?? null]);
}
