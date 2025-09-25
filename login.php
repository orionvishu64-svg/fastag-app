<?php
// login.php
session_start();
require_once "db.php"; // must set $pdo (PDO instance)

// expected POST: email, password (adjust to your auth method)
header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

$email = trim($input['email'] ?? '');
$password = trim($input['password'] ?? '');

if ($email === '' || $password === '') {
    echo json_encode(['success' => false, 'message' => 'Email and password required.']);
    exit;
}

try {
    // Adjust query to your auth logic (password hashing etc.)
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Invalid credentials.']);
        exit;
    }

    // If you store hashed passwords
    // if (!password_verify($password, $user['password'])) { ... }
    // For the example below, we'll accept any password equal to 'test' OR match hashed:
    $ok = false;
    if (isset($user['password']) && password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
        // not typical - please adapt to your password storage
    }
    // Use your real password verification
    if (isset($user['password']) && password_verify($password, $user['password'])) {
        $ok = true;
    } elseif ($password === 'test') {
        // fallback for local testing only — remove in production
        $ok = true;
    }

    if (!$ok) {
        echo json_encode(['success' => false, 'message' => 'Invalid credentials.']);
        exit;
    }

    // Auth OK -> set session
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['user'] = ['id' => $user['id'], 'name' => $user['name'] ?? '', 'email' => $user['email']];

    // Return JSON with has_filled_partner_form flag
    echo json_encode([
        'success' => true,
        'message' => 'Login successful.',
        'user' => [
            'id' => (int)$user['id'],
            'name' => $user['name'] ?? '',
            'email' => $user['email'] ?? '',
            'login_type' => $user['login_type'] ?? '',
            'has_filled_partner_form' => (int)($user['has_filled_partner_form'] ?? 0)
        ]
    ]);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error.']);
    exit;
}
