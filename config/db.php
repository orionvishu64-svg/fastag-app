<?php
// db.php (Correct configuration for Bitnami MariaDB)

$host = "localhost";
$dbname = "fastag_app";
$user = "admin";
$password = "Apna1234";

// IMPORTANT: Bitnami MariaDB uses a custom socket path
$dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4;unix_socket=/opt/bitnami/mariadb/tmp/mysql.sock";

try {
    $pdo = new PDO($dsn, $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>