<?php
require_once 'includes/auth.php';
require_once 'config/database.php';
require_login();

$action = $_POST['action'] ?? '';

if ($action === 'add') {
    $product_id = (int)($_POST['product_id'] ?? 0);
    $customer   = trim($_POST['customer_name'] ?? '');
    $qty        = (float)($_POST['quantity'] ?? 0);
    $price      = (float)($_POST['price_per_unit'] ?? 0);
    $date       = $_POST['sale_date'] ?? date('Y-m-d');
    $total      = $qty * $price;

    if ($product_id <= 0 || $customer === '' || $qty <= 0) {
        flash_set('error', 'Please fill all required fields.');
        header('Location: sales.php');
        exit;
    }

    $prod = $pdo->prepare("SELECT quantity FROM products WHERE id = ?");
    $prod->execute([$product_id]);
    $stock = $prod->fetchColumn();

    if ($stock === false) {
        flash_set('error', 'Product not found.');
        header('Location: sales.php');
        exit;
    }
    if ($qty > $stock) {
        flash_set('error', 'Not enough stock available for this sale.');
        header('Location: sales.php');
        exit;
    }

    $pdo->beginTransaction();
    $stmt = $pdo->prepare("INSERT INTO sales (product_id, customer_name, quantity, price_per_unit, total, sale_date) VALUES (?,?,?,?,?,?)");
    $stmt->execute([$product_id, $customer, $qty, $price, $total, $date]);

    $upd = $pdo->prepare("UPDATE products SET quantity = quantity - ? WHERE id = ?");
    $upd->execute([$qty, $product_id]);
    $pdo->commit();

    flash_set('success', 'Sale recorded.');
} elseif ($action === 'edit') {
    $id         = (int)($_POST['id'] ?? 0);
    $product_id = (int)($_POST['product_id'] ?? 0);
    $customer   = trim($_POST['customer_name'] ?? '');
    $qty        = (float)($_POST['quantity'] ?? 0);
    $price      = (float)($_POST['price_per_unit'] ?? 0);
    $date       = $_POST['sale_date'] ?? date('Y-m-d');
    $total      = $qty * $price;

    if ($id <= 0 || $product_id <= 0 || $customer === '' || $qty <= 0) {
        flash_set('error', 'Please fill all required fields.');
        header('Location: sales.php');
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM sales WHERE id = ?");
    $stmt->execute([$id]);
    $oldSale = $stmt->fetch();

    if (!$oldSale) {
        flash_set('error', 'Sale transaction not found.');
        header('Location: sales.php');
        exit;
    }

    $old_product_id = (int)$oldSale['product_id'];
    $old_qty        = (float)$oldSale['quantity'];

    if ($product_id === $old_product_id) {
        $prod = $pdo->prepare("SELECT quantity FROM products WHERE id = ?");
        $prod->execute([$product_id]);
        $current_stock = (float)$prod->fetchColumn();

        $available_stock = $current_stock + $old_qty;
        if ($qty > $available_stock) {
            flash_set('error', 'Not enough stock available for this sale change.');
            header('Location: sales.php');
            exit;
        }

        $pdo->beginTransaction();
        $upd = $pdo->prepare("UPDATE products SET quantity = quantity + ? - ? WHERE id = ?");
        $upd->execute([$old_qty, $qty, $product_id]);

        $updSale = $pdo->prepare("UPDATE sales SET product_id = ?, customer_name = ?, quantity = ?, price_per_unit = ?, total = ?, sale_date = ? WHERE id = ?");
        $updSale->execute([$product_id, $customer, $qty, $price, $total, $date, $id]);
        $pdo->commit();
    } else {
        $prod = $pdo->prepare("SELECT quantity FROM products WHERE id = ?");
        $prod->execute([$product_id]);
        $new_stock = (float)$prod->fetchColumn();

        if ($qty > $new_stock) {
            flash_set('error', 'Not enough stock available for the new selected product.');
            header('Location: sales.php');
            exit;
        }

        $pdo->beginTransaction();
        $updOld = $pdo->prepare("UPDATE products SET quantity = quantity + ? WHERE id = ?");
        $updOld->execute([$old_qty, $old_product_id]);

        $updNew = $pdo->prepare("UPDATE products SET quantity = quantity - ? WHERE id = ?");
        $updNew->execute([$qty, $product_id]);

        $updSale = $pdo->prepare("UPDATE sales SET product_id = ?, customer_name = ?, quantity = ?, price_per_unit = ?, total = ?, sale_date = ? WHERE id = ?");
        $updSale->execute([$product_id, $customer, $qty, $price, $total, $date, $id]);
        $pdo->commit();
    }

    flash_set('success', 'Sale transaction updated.');
} elseif ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT * FROM sales WHERE id = ?");
    $stmt->execute([$id]);
    $sale = $stmt->fetch();

    if ($sale) {
        $pdo->beginTransaction();
        // restore stock
        $upd = $pdo->prepare("UPDATE products SET quantity = quantity + ? WHERE id = ?");
        $upd->execute([$sale['quantity'], $sale['product_id']]);
        $del = $pdo->prepare("DELETE FROM sales WHERE id = ?");
        $del->execute([$id]);
        $pdo->commit();
    }
    flash_set('success', 'Sale deleted and stock restored.');
}

header('Location: sales.php');
exit;
