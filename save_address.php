<?php
require_once 'common_start.php';
require 'db.php';
header('Content-Type: application/json'); // Use your db connection file

$data = json_decode(file_get_contents("php://input"), true);

$house = $data["houseNo"];
$landmark = $data["landmark"];
$city = $data["city"];
$pincode = preg_replace("/[^0-9]/", "", $data["pincode"]);
$userId = (int) ($_SESSION['user']['id'] ?? $_SESSION['user_id'] ?? 0);

// If not logged in, keep original behavior (return empty array)
if ($userId <= 0) {
    echo json_encode([]);
    exit;
}

$stmt = $pdo->prepare("INSERT INTO addresses (user_id, house_no, landmark, city, pincode) VALUES (?, ?, ?, ?, ?)");
$success = $stmt->execute([$userId, $house, $landmark, $city, $pincode]);

echo json_encode(["success" => $success]);
?>
