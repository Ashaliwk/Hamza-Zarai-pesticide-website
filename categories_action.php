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
            
            $catId = $pdo->lastInsertId();
            $subcatName = trim($_POST['subcategory_name'] ?? '');
            if ($subcatName !== '') {
                $stmtSub = $pdo->prepare("INSERT INTO subcategories (category_id, name) VALUES (?, ?)");
                $stmtSub->execute([$catId, $subcatName]);
            }
            
            flash_set('success', 'Category added successfully.');
        } catch (PDOException $e) {
            flash_set('error', 'That category already exists.');
        }
    }
} elseif ($action === 'add_subcategory') {
    $categoryId = (int)($_POST['category_id'] ?? 0);
    $subName = trim($_POST['subcategory_name'] ?? '');
    
    if ($categoryId <= 0) {
        flash_set('error', 'Please select a valid parent category.');
    } elseif ($subName === '') {
        flash_set('error', 'Subcategory name is required.');
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO subcategories (category_id, name) VALUES (?, ?)");
            $stmt->execute([$categoryId, $subName]);
            flash_set('success', 'Subcategory added successfully.');
        } catch (PDOException $e) {
            flash_set('error', 'Failed to add subcategory.');
        }
    }
} elseif ($action === 'delete_subcategory') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $stmt = $pdo->prepare("DELETE FROM subcategories WHERE id = ?");
        $stmt->execute([$id]);
        flash_set('success', 'Subcategory deleted.');
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

