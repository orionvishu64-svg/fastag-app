<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in.']);
    exit;
}

$user_id = $_SESSION['user_id'];
$name = $_POST['name'];
$phone = $_POST['phone'];
$house_no = $_POST['house_no'];
$landmark = $_POST['landmark'];
$city = $_POST['city'];
$pincode = $_POST['pincode'];

// Update user info
$stmt = $pdo->prepare("UPDATE users SET name = ?, phone = ? WHERE id = ?");
$stmt->execute([$name, $phone, $user_id]);

// Check if address exists
$stmt = $pdo->prepare("SELECT id FROM addresses WHERE user_id = ?");
$stmt->execute([$user_id]);

if ($stmt->fetch()) {
    // Update address
    $stmt = $pdo->prepare("UPDATE addresses SET house_no = ?, landmark = ?, city = ?, pincode = ? WHERE user_id = ?");
    $stmt->execute([$house_no, $landmark, $city, $pincode, $user_id]);
} else {
    // Insert new address
    $stmt = $pdo->prepare("INSERT INTO addresses (user_id, house_no, landmark, city, pincode) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $house_no, $landmark, $city, $pincode]);
}

echo json_encode(['success' => true, 'message' => 'Profile updated successfully.']);