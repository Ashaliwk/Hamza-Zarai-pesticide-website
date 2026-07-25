<?php
require_once 'includes/auth.php';
require_once 'config/database.php';
require_login();

$action = $_POST['action'] ?? '';

if ($action === 'add') {
    $name             = trim($_POST['name'] ?? '');
    $email            = trim($_POST['email'] ?? '');
    $password         = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $company_name     = trim($_POST['company_name'] ?? '');

    if ($company_name === '') {
        $company_name = $_SESSION['company_name'] ?? 'My Corporation';
    }

    if ($name === '' || $email === '' || $password === '') {
        flash_set('error', 'All required fields must be filled out.');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash_set('error', 'Please enter a valid email address.');
    } elseif (strlen($password) < 6) {
        flash_set('error', 'Password must be at least 6 characters long.');
    } elseif ($password !== $confirm_password) {
        flash_set('error', 'Passwords do not match.');
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM admins WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetchColumn() > 0) {
            flash_set('error', 'An admin with this email already exists.');
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO admins (name, email, password, company_name) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $email, $hashedPassword, $company_name]);
            flash_set('success', 'New admin account added successfully!');
        }
    }
} elseif ($action === 'edit') {
    $id           = (int)($_POST['id'] ?? 0);
    $name         = trim($_POST['name'] ?? '');
    $email        = trim($_POST['email'] ?? '');
    $company_name = trim($_POST['company_name'] ?? '');
    $new_password = $_POST['password'] ?? '';

    if ($id <= 0 || $name === '' || $email === '') {
        flash_set('error', 'Name and email are required.');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash_set('error', 'Please enter a valid email address.');
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM admins WHERE email = ? AND id != ?");
        $stmt->execute([$email, $id]);
        if ($stmt->fetchColumn() > 0) {
            flash_set('error', 'Another admin with this email already exists.');
        } else {
            if ($company_name === '') {
                $company_name = $_SESSION['company_name'] ?? 'My Corporation';
            }
            if ($new_password !== '') {
                if (strlen($new_password) < 6) {
                    flash_set('error', 'Password must be at least 6 characters long.');
                    header('Location: admin.php');
                    exit;
                }
                $hashedPassword = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE admins SET name = ?, email = ?, company_name = ?, password = ? WHERE id = ?");
                $stmt->execute([$name, $email, $company_name, $hashedPassword, $id]);
            } else {
                $stmt = $pdo->prepare("UPDATE admins SET name = ?, email = ?, company_name = ? WHERE id = ?");
                $stmt->execute([$name, $email, $company_name, $id]);
            }

            if ($_SESSION['admin_id'] == $id) {
                $_SESSION['admin_name']   = $name;
                $_SESSION['admin_email']  = $email;
                $_SESSION['company_name'] = $company_name;
            }

            flash_set('success', 'Admin details updated successfully!');
        }
    }
} elseif ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);

    if ($id == $_SESSION['admin_id']) {
        flash_set('error', 'You cannot delete your own logged-in admin account.');
    } else {
        $count = $pdo->query("SELECT COUNT(*) FROM admins")->fetchColumn();
        if ($count <= 1) {
            flash_set('error', 'Cannot delete the only remaining admin account.');
        } else {
            $stmt = $pdo->prepare("DELETE FROM admins WHERE id = ?");
            $stmt->execute([$id]);
            flash_set('success', 'Admin user deleted successfully.');
        }
    }
}

header('Location: admin.php');
exit;
