<?php
require_once 'includes/auth.php';
require_once 'config/database.php';
require_login();

$action = $_POST['action'] ?? '';

if ($action === 'add') {
    $product_id     = (int)($_POST['product_id'] ?? 0);
    $subcategory_id = !empty($_POST['subcategory_id']) ? (int)$_POST['subcategory_id'] : null;
    $supplier       = trim($_POST['supplier_name'] ?? '');
    $qty            = (float)($_POST['quantity'] ?? 0);
    $price          = (float)($_POST['price_per_unit'] ?? 0);
    $date           = $_POST['purchase_date'] ?? date('Y-m-d');
    $total          = $qty * $price;

    if ($product_id <= 0 || $supplier === '' || $qty <= 0) {
        flash_set('error', 'Please fill all required fields.');
        header('Location: purchases.php');
        exit;
    }

    $pdo->beginTransaction();
    $stmt = $pdo->prepare("INSERT INTO purchases (product_id, subcategory_id, supplier_name, quantity, price_per_unit, total, purchase_date) VALUES (?,?,?,?,?,?,?)");
    $stmt->execute([$product_id, $subcategory_id, $supplier, $qty, $price, $total, $date]);

    $upd = $pdo->prepare("UPDATE products SET quantity = quantity + ?, purchase_price = ? WHERE id = ?");
    $upd->execute([$qty, $price, $product_id]);
    $pdo->commit();

    flash_set('success', 'Purchase recorded.');
} elseif ($action === 'edit') {
    $id             = (int)($_POST['id'] ?? 0);
    $product_id     = (int)($_POST['product_id'] ?? 0);
    $subcategory_id = !empty($_POST['subcategory_id']) ? (int)$_POST['subcategory_id'] : null;
    $supplier       = trim($_POST['supplier_name'] ?? '');
    $qty            = (float)($_POST['quantity'] ?? 0);
    $price          = (float)($_POST['price_per_unit'] ?? 0);
    $date           = $_POST['purchase_date'] ?? date('Y-m-d');
    $total          = $qty * $price;

    if ($id <= 0 || $product_id <= 0 || $supplier === '' || $qty <= 0) {
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

    if ($product_id === $old_product_id) {
        $pdo->beginTransaction();
        $upd = $pdo->prepare("UPDATE products SET quantity = GREATEST(quantity - ? + ?, 0), purchase_price = ? WHERE id = ?");
        $upd->execute([$old_qty, $qty, $price, $product_id]);

        $updPur = $pdo->prepare("UPDATE purchases SET product_id = ?, subcategory_id = ?, supplier_name = ?, quantity = ?, price_per_unit = ?, total = ?, purchase_date = ? WHERE id = ?");
        $updPur->execute([$product_id, $subcategory_id, $supplier, $qty, $price, $total, $date, $id]);
        $pdo->commit();
    } else {
        $pdo->beginTransaction();
        $updOld = $pdo->prepare("UPDATE products SET quantity = GREATEST(quantity - ?, 0) WHERE id = ?");
        $updOld->execute([$old_qty, $old_product_id]);

        $updNew = $pdo->prepare("UPDATE products SET quantity = quantity + ?, purchase_price = ? WHERE id = ?");
        $updNew->execute([$qty, $price, $product_id]);

        $updPur = $pdo->prepare("UPDATE purchases SET product_id = ?, subcategory_id = ?, supplier_name = ?, quantity = ?, price_per_unit = ?, total = ?, purchase_date = ? WHERE id = ?");
        $updPur->execute([$product_id, $subcategory_id, $supplier, $qty, $price, $total, $date, $id]);
        $pdo->commit();
    }

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

