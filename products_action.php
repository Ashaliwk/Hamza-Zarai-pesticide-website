<?php
require_once 'includes/auth.php';
require_once 'config/database.php';
require_login();

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'add' || $action === 'edit') {
    $name       = trim($_POST['name'] ?? '');
    $sku        = trim($_POST['sku'] ?? '');
    $category   = (int)($_POST['category_id'] ?? 0);
    $unit       = $_POST['unit'] ?? 'kg';
    $purchase   = (float)($_POST['purchase_price'] ?? 0);
    $selling    = (float)($_POST['selling_price'] ?? 0);
    $qty        = (float)($_POST['quantity'] ?? 0);
    $threshold  = (float)($_POST['low_stock_threshold'] ?? 10);

    if ($name === '' || $category <= 0) {
        flash_set('error', 'Product name and category are required.');
        header('Location: products.php');
        exit;
    }

    if ($action === 'add') {
        $stmt = $pdo->prepare("INSERT INTO products (name, sku, category_id, unit, purchase_price, selling_price, quantity, low_stock_threshold)
                                VALUES (?,?,?,?,?,?,?,?)");
        $stmt->execute([$name, $sku, $category, $unit, $purchase, $selling, $qty, $threshold]);
        flash_set('success', 'Product added successfully.');
    } else {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("UPDATE products SET name=?, sku=?, category_id=?, unit=?, purchase_price=?, selling_price=?, quantity=?, low_stock_threshold=? WHERE id=?");
        $stmt->execute([$name, $sku, $category, $unit, $purchase, $selling, $qty, $threshold, $id]);
        flash_set('success', 'Product updated successfully.');
    }
} elseif ($action === 'delete') {
    $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
    $stmt = $pdo->prepare("DELETE FROM products WHERE id=?");
    $stmt->execute([$id]);
    flash_set('success', 'Product deleted.');
}

header('Location: products.php');
exit;
