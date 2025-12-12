<?php
// /db.php

$credFile = "/opt/bitnami/fastag_secrets/db_credentials.php";

if (!file_exists($credFile)) {
    die("DB config file missing.");
}

require_once $credFile; 

$host     = FASTAG_DB_HOST;
$dbname   = FASTAG_DB_NAME;
$user     = FASTAG_DB_USER;
$password = FASTAG_DB_PASS;

$dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4;unix_socket=/opt/bitnami/mariadb/tmp/mysql.sock";

try {
    $pdo = new PDO($dsn, $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>