<?php
session_start();
header("Content-Type: application/json");
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(["success" => false, "message" => "Not logged in"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

$house_no = trim($data['house_no'] ?? '');
$landmark = trim($data['landmark'] ?? '');
$city = trim($data['city'] ?? '');
$pincode = trim($data['pincode'] ?? '');

if (!$house_no || !$city || !$pincode) {
    echo json_encode(["success" => false, "message" => "Missing required fields"]);
    exit;
}

try {
    $stmt = $pdo->prepare("INSERT INTO addresses (user_id, house_no, landmark, city, pincode, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
    $stmt->execute([
       $_SESSION['user_id'],
        $house_no,
        $landmark,
        $city,
        $pincode
    ]);

    echo json_encode(["success" => true, "message" => "Address added successfully"]);
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Failed to save address"]);
}
?>