<?php
session_start();
require 'db.php'; // Use your db connection file

$data = json_decode(file_get_contents("php://input"), true);

$house = $data["houseNo"];
$landmark = $data["landmark"];
$city = $data["city"];
$pincode = $data["pincode"];
$userId = $_SESSION["user_id"]; // assume user is logged in

$stmt = $pdo->prepare("INSERT INTO addresses (user_id, house_no, landmark, city, pincode) VALUES (?, ?, ?, ?, ?)");
$success = $stmt->execute([$userId, $house, $landmark, $city, $pincode]);

echo json_encode(["success" => $success]);
?>
