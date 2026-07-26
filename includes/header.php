<?php
$admin_name  = $_SESSION['admin_name']  ?? 'Admin';
$admin_email = $_SESSION['admin_email'] ?? '';
$company     = $_SESSION['company_name'] ?? 'My Corporation';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle ?? 'Dashboard') ?> · CropCare Admin</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
<link href="assets/css/style.css" rel="stylesheet">
</head>
<body>

<button class="btn btn-brand d-md-none m-2 position-fixed" style="z-index:1040" onclick="document.querySelector('.sidebar').classList.toggle('show')">
  <i class="fa-solid fa-bars"></i>
</button>

<div class="sidebar">
  <div class="brand">
    <div class="logo-icon">🌱</div>
    <div>
      <div class="brand-title">Hamza Zarai</div>
      <div class="brand-sub">Services</div>
    </div>
  </div>

  <nav class="nav flex-column mt-2">
    <a class="nav-link <?= ($activeNav ?? '') === 'dashboard' ? 'active' : '' ?>" href="dashboard.php">
      <i class="fa-solid fa-grip"></i> Dashboard
    </a>
    <a class="nav-link <?= ($activeNav ?? '') === 'products' ? 'active' : '' ?>" href="products.php">
      <i class="fa-solid fa-cube"></i> Stock
    </a>
    <a class="nav-link <?= ($activeNav ?? '') === 'categories' ? 'active' : '' ?>" href="categories.php">
      <i class="fa-solid fa-layer-group"></i> Categories
    </a>
    <a class="nav-link <?= ($activeNav ?? '') === 'sales' ? 'active' : '' ?>" href="sales.php">
      <i class="fa-solid fa-cart-shopping"></i> Sales
    </a>
    <a class="nav-link <?= ($activeNav ?? '') === 'purchases' ? 'active' : '' ?>" href="purchases.php">
      <i class="fa-solid fa-box-archive"></i> Purchases
    </a>
    <a class="nav-link <?= ($activeNav ?? '') === 'admin' ? 'active' : '' ?>" href="admin.php">
      <i class="fa-solid fa-user-shield"></i> Admin
    </a>
  </nav>

  <div class="sidebar-footer">
    <a href="logout.php" class="signout"><i class="fa-solid fa-right-from-bracket"></i> Log Out</a><br>
    <h6>Developed by: Ali Ashraf</h6>
  </div>
</div>

<div class="main-content">
  <?php $flash = flash_get(); ?>
  <?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : 'success' ?> alert-dismissible fade show" role="alert">
      <?= e($flash['msg']) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>
