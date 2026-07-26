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

// Auto-migrate sales table to support payment status and due tracking
try {
    $colCheck = $pdo->query("SHOW COLUMNS FROM sales LIKE 'payment_status'")->fetch();
    if (!$colCheck) {
        $pdo->exec("ALTER TABLE sales ADD COLUMN payment_status ENUM('paid', 'unpaid', 'partial') NOT NULL DEFAULT 'paid' AFTER total");
        $pdo->exec("ALTER TABLE sales ADD COLUMN paid_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER payment_status");
        $pdo->exec("ALTER TABLE sales ADD COLUMN due_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER paid_amount");
        $pdo->exec("UPDATE sales SET paid_amount = total, due_amount = 0.00, payment_status = 'paid'");
    }
} catch (Exception $e) {
    // database migration fallback
}

// Auto-migrate products table to support subcategory_id
try {
    $subColCheck = $pdo->query("SHOW COLUMNS FROM products LIKE 'subcategory_id'")->fetch();
    if (!$subColCheck) {
        $pdo->exec("ALTER TABLE products ADD COLUMN subcategory_id INT DEFAULT NULL AFTER category_id");
        $subs = $pdo->query("SELECT id, category_id, name FROM subcategories")->fetchAll();
        $subMap = [];
        foreach ($subs as $s) {
            $subMap[$s['category_id']][] = $s;
        }
        $prods = $pdo->query("SELECT id, category_id, name FROM products")->fetchAll();
        $updSub = $pdo->prepare("UPDATE products SET subcategory_id = ? WHERE id = ?");
        foreach ($prods as $p) {
            if (!empty($subMap[$p['category_id']])) {
                $firstSubId = $subMap[$p['category_id']][0]['id'];
                $updSub->execute([$firstSubId, $p['id']]);
            }
        }
    }
} catch (Exception $e) {
    // database migration fallback
}


