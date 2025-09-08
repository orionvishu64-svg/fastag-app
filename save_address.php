<?php
require_once 'common_start.php';
require 'db.php';
header('Content-Type: application/json'); // Use your db connection file

$data = json_decode(file_get_contents("php://input"), true);

$house = $data["houseNo"];
$landmark = $data["landmark"];
$city = $data["city"];
$pincode = preg_replace("/[^0-9]/", "", $data["pincode"]);
if (!isset($_SESSION["user_id"])) { echo json_encode(["success"=>false,"message"=>"Not logged in"]); exit; }
$userId = $_SESSION["user_id"];

$stmt = $pdo->prepare("INSERT INTO addresses (user_id, house_no, landmark, city, pincode) VALUES (?, ?, ?, ?, ?)");
$success = $stmt->execute([$userId, $house, $landmark, $city, $pincode]);

echo json_encode(["success" => $success]);
?>
