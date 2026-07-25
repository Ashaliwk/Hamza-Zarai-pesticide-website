<?php

define('DB_HOST', 'localhost');
define('DB_NAME', 'pesticide_db');
define('DB_USER', 'root');
define('DB_PASS', '');

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage() .
        "<br>Make sure you imported database/schema.sql and updated config/database.php");
}

$count = $pdo->query("SELECT COUNT(*) FROM admins")->fetchColumn();
if ($count == 0) {
    $hash = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO admins (name, email, password, company_name) VALUES (?,?,?,?)");
    $stmt->execute(['Admin', 'admin@pesticide.com', $hash, 'Hamza zarai Corporation']);
}
