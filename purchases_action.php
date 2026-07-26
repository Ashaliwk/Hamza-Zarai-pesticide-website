<?php
require_once 'includes/auth.php';
require_once 'config/database.php';
require_login();

// Ensure unit column exists in purchases table
try {
    $pdo->exec("ALTER TABLE purchases ADD COLUMN unit VARCHAR(20) NULL AFTER quantity");
} catch (PDOException $e) {}

$action = $_POST['action'] ?? '';

if ($action === 'add') {
    $product_name   = trim($_POST['product_name'] ?? '');
    $category_id    = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $subcategory_id = !empty($_POST['subcategory_id']) ? (int)$_POST['subcategory_id'] : null;
    $supplier       = trim($_POST['supplier_name'] ?? '');
    $qty            = (float)($_POST['quantity'] ?? 0);
    $unit           = trim($_POST['unit'] ?? 'kg');
    $price          = (float)($_POST['price_per_unit'] ?? 0);
    $date           = $_POST['purchase_date'] ?? date('Y-m-d');
    $total          = $qty * $price;

    if ($product_name === '' || $supplier === '' || $qty <= 0) {
        flash_set('error', 'Please fill all required fields.');
        header('Location: purchases.php');
        exit;
    }

    $pdo->beginTransaction();

    // Check if product exists by name
    $find = $pdo->prepare("SELECT * FROM products WHERE LOWER(name) = LOWER(?) LIMIT 1");
    $find->execute([$product_name]);
    $existingProduct = $find->fetch();

    if ($existingProduct) {
        $product_id = (int)$existingProduct['id'];
        $catUpdate = $category_id ?: $existingProduct['category_id'];
        $subUpdate = $subcategory_id ?: $existingProduct['subcategory_id'];
        $unitUpdate = $unit !== '' ? $unit : $existingProduct['unit'];

        $upd = $pdo->prepare("UPDATE products SET quantity = quantity + ?, purchase_price = ?, unit = ?, category_id = ?, subcategory_id = ? WHERE id = ?");
        $upd->execute([$qty, $price, $unitUpdate, $catUpdate, $subUpdate, $product_id]);
    } else {
        if (!$category_id) {
            $catStmt = $pdo->query("SELECT id FROM categories ORDER BY id ASC LIMIT 1");
            $category_id = (int)($catStmt->fetchColumn() ?: 1);
        }
        $sku = 'SKU-' . strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $product_name), 0, 4)) . '-' . rand(100, 999);
        $selling_price = $price > 0 ? round($price * 1.15, 2) : 0;

        $insProd = $pdo->prepare("INSERT INTO products (name, sku, category_id, subcategory_id, unit, purchase_price, selling_price, quantity) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $insProd->execute([$product_name, $sku, $category_id, $subcategory_id, $unit !== '' ? $unit : 'kg', $price, $selling_price, $qty]);
        $product_id = (int)$pdo->lastInsertId();
    }

    $stmt = $pdo->prepare("INSERT INTO purchases (product_id, subcategory_id, supplier_name, quantity, unit, price_per_unit, total, purchase_date) VALUES (?,?,?,?,?,?,?,?)");
    $stmt->execute([$product_id, $subcategory_id, $supplier, $qty, $unit, $price, $total, $date]);

    $pdo->commit();

    flash_set('success', 'Purchase recorded and stock updated.');
} elseif ($action === 'edit') {
    $id             = (int)($_POST['id'] ?? 0);
    $product_name   = trim($_POST['product_name'] ?? '');
    $category_id    = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $subcategory_id = !empty($_POST['subcategory_id']) ? (int)$_POST['subcategory_id'] : null;
    $supplier       = trim($_POST['supplier_name'] ?? '');
    $qty            = (float)($_POST['quantity'] ?? 0);
    $unit           = trim($_POST['unit'] ?? '');
    $price          = (float)($_POST['price_per_unit'] ?? 0);
    $date           = $_POST['purchase_date'] ?? date('Y-m-d');
    $total          = $qty * $price;

    if ($id <= 0 || $product_name === '' || $supplier === '' || $qty <= 0) {
        flash_set('error', 'Please fill all required fields.');
        header('Location: purchases.php');
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM purchases WHERE id = ?");
    $stmt->execute([$id]);
    $oldPurchase = $stmt->fetch();

    if (!$oldPurchase) {
        flash_set('error', 'Purchase transaction not found.');
        header('Location: purchases.php');
        exit;
    }

    $old_product_id = (int)$oldPurchase['product_id'];
    $old_qty        = (float)$oldPurchase['quantity'];

    $pdo->beginTransaction();

    // Check if product exists by name
    $find = $pdo->prepare("SELECT * FROM products WHERE LOWER(name) = LOWER(?) LIMIT 1");
    $find->execute([$product_name]);
    $existingProduct = $find->fetch();

    if ($existingProduct) {
        $product_id = (int)$existingProduct['id'];
    } else {
        if (!$category_id) {
            $catStmt = $pdo->query("SELECT id FROM categories ORDER BY id ASC LIMIT 1");
            $category_id = (int)($catStmt->fetchColumn() ?: 1);
        }
        $sku = 'SKU-' . strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $product_name), 0, 4)) . '-' . rand(100, 999);
        $selling_price = $price > 0 ? round($price * 1.15, 2) : 0;

        $insProd = $pdo->prepare("INSERT INTO products (name, sku, category_id, subcategory_id, unit, purchase_price, selling_price, quantity) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $insProd->execute([$product_name, $sku, $category_id, $subcategory_id, $unit !== '' ? $unit : 'kg', $price, $selling_price, 0]);
        $product_id = (int)$pdo->lastInsertId();
    }

    if ($product_id === $old_product_id) {
        $upd = $pdo->prepare("UPDATE products SET quantity = GREATEST(quantity - ? + ?, 0), purchase_price = ?, unit = ?, category_id = COALESCE(?, category_id), subcategory_id = ? WHERE id = ?");
        $upd->execute([$old_qty, $qty, $price, $unit, $category_id, $subcategory_id, $product_id]);
    } else {
        $updOld = $pdo->prepare("UPDATE products SET quantity = GREATEST(quantity - ?, 0) WHERE id = ?");
        $updOld->execute([$old_qty, $old_product_id]);

        $updNew = $pdo->prepare("UPDATE products SET quantity = quantity + ?, purchase_price = ?, unit = ?, category_id = COALESCE(?, category_id), subcategory_id = ? WHERE id = ?");
        $updNew->execute([$qty, $price, $unit, $category_id, $subcategory_id, $product_id]);
    }

    $updPur = $pdo->prepare("UPDATE purchases SET product_id = ?, subcategory_id = ?, supplier_name = ?, quantity = ?, unit = ?, price_per_unit = ?, total = ?, purchase_date = ? WHERE id = ?");
    $updPur->execute([$product_id, $subcategory_id, $supplier, $qty, $unit, $price, $total, $date, $id]);

    $pdo->commit();

    flash_set('success', 'Purchase transaction updated.');
} elseif ($action === 'add_subcategory') {
    $categoryId = (int)($_POST['category_id'] ?? 0);
    $subName    = trim($_POST['subcategory_name'] ?? '');

    if ($categoryId <= 0) {
        flash_set('error', 'Please select a parent category.');
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
} elseif ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT * FROM purchases WHERE id = ?");
    $stmt->execute([$id]);
    $purchase = $stmt->fetch();

    if ($purchase) {
        $pdo->beginTransaction();
        // reverse stock addition
        $upd = $pdo->prepare("UPDATE products SET quantity = GREATEST(quantity - ?, 0) WHERE id = ?");
        $upd->execute([$purchase['quantity'], $purchase['product_id']]);
        $del = $pdo->prepare("DELETE FROM purchases WHERE id = ?");
        $del->execute([$id]);
        $pdo->commit();
    }
    flash_set('success', 'Purchase deleted and stock adjusted.');
}

header('Location: purchases.php');
exit;

