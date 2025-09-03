<?php
session_start();
require 'db.php';

$userId = $_SESSION["user_id"];

$stmt = $pdo->prepare("SELECT * FROM addresses WHERE user_id = ?");
$stmt->execute([$userId]);
$addresses = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($addresses);
?>