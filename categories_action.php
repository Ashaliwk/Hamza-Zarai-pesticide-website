<?php
require_once 'includes/auth.php';
require_once 'config/database.php';
require_login();

$action = $_POST['action'] ?? '';

if ($action === 'add') {
    $name = trim($_POST['name'] ?? '');
    if ($name === '') {
        flash_set('error', 'Category name is required.');
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO categories (name, type) VALUES (?, 'custom')");
            $stmt->execute([$name]);
            flash_set('success', 'Category added.');
        } catch (PDOException $e) {
            flash_set('error', 'That category already exists.');
        }
    }
} elseif ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    // Prevent deleting a category that still has products
    $check = $pdo->prepare("SELECT COUNT(*) FROM products WHERE category_id = ?");
    $check->execute([$id]);
    if ($check->fetchColumn() > 0) {
        flash_set('error', 'Cannot delete a category that still has products assigned to it.');
    } else {
        $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->execute([$id]);
        flash_set('success', 'Category deleted.');
    }
}

header('Location: categories.php');
exit;
