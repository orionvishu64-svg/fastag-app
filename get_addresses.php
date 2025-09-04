<?php
session_start();
require 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION["user_id"])) { echo json_encode([]); exit; }

$userId = $_SESSION["user_id"];

$stmt = $pdo->prepare("SELECT * FROM addresses WHERE user_id = ?");
$stmt->execute([$userId]);
$addresses = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($addresses);
?>