<?php
session_start();

// Destroy all session variables
$_SESSION = [];

// Destroy the session itself
session_destroy();

// Optionally clear any authentication cookies
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Redirect to login page or send a response
header("Location: index.html"); // Or replace with your desired page
exit;
?>