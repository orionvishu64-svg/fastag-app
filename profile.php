<?php
require_once 'common_start.php';
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in.']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch user and address data
$stmt = $pdo->prepare("SELECT u.name, u.email, u.phone, a.house_no, a.landmark, a.city, a.pincode
                       FROM users u
                       LEFT JOIN addresses a ON u.id = a.user_id
                       WHERE u.id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user) {
    echo json_encode(['success' => true, 'user' => $user]);
} else {
    echo json_encode(['success' => false, 'message' => 'User not found.']);
}
