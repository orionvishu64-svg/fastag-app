<?php
// update_profile.php — tolerant JSON API (accepts name OR firstName+lastName)
require_once 'common_start.php';
require_once 'db.php';

header('Content-Type: application/json; charset=utf-8');

// Ensure user is logged in
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in.']);
    exit;
}

$user_id = (int) $_SESSION['user_id'];

// Accept multiple possible input names
$raw_name     = trim($_POST['name'] ?? '');
$firstName    = trim($_POST['firstName'] ?? '');
$lastName     = trim($_POST['lastName'] ?? '');
$email        = trim($_POST['email'] ?? '');
$phone        = trim($_POST['phone'] ?? '');
$house_no     = trim($_POST['house_no'] ?? $_POST['houseNo'] ?? '');
$landmark     = trim($_POST['landmark'] ?? '');
$city         = trim($_POST['city'] ?? '');
$pincode      = trim($_POST['pincode'] ?? $_POST['pin'] ?? '');

// Compose a final name: prefer raw name, otherwise combine first+last
$final_name = $raw_name;
if ($final_name === '') {
    $final_name = trim(($firstName . ' ' . $lastName));
}

// Validate required fields
if ($final_name === '') {
    echo json_encode(['success' => false, 'message' => 'Name is required.']);
    exit;
}

// Basic validation for phone (optional)
if ($phone !== '' && !preg_match('/^[0-9+\-\s]{6,20}$/', $phone)) {
    echo json_encode(['success' => false, 'message' => 'Invalid phone number.']);
    exit;
}

try {
    // Update users table (update name/email/phone if present)
    $updateParts = [];
    $params = [];

    if ($final_name !== '') {
        $updateParts[] = "name = ?";
        $params[] = $final_name;
    }
    if ($email !== '') {
        $updateParts[] = "email = ?";
        $params[] = $email;
    }
    if ($phone !== '') {
        $updateParts[] = "phone = ?";
        $params[] = $phone;
    }

    if (!empty($updateParts)) {
        $params[] = $user_id;
        $sql = "UPDATE users SET " . implode(", ", $updateParts) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }

    // Update or insert addresses (if any address fields provided)
    $hasAddress = ($house_no !== '' || $landmark !== '' || $city !== '' || $pincode !== '');
    if ($hasAddress) {
        $stmt = $pdo->prepare("SELECT id FROM addresses WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $addr = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($addr) {
            $stmt = $pdo->prepare("UPDATE addresses SET house_no = ?, landmark = ?, city = ?, pincode = ? WHERE user_id = ?");
            $stmt->execute([$house_no, $landmark, $city, $pincode, $user_id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO addresses (user_id, house_no, landmark, city, pincode) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $house_no, $landmark, $city, $pincode]);
        }
    }

    echo json_encode(['success' => true, 'reload' => true]);
    exit;
} catch (Exception $e) {
    error_log("update_profile.php error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error updating profile.']);
    exit;
}
