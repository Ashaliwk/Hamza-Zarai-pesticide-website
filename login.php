<?php
require_once 'includes/auth.php';
require_once 'config/database.php';

if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Please enter email and password.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM admins WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password'])) {
            $_SESSION['admin_id']      = $admin['id'];
            $_SESSION['admin_name']    = $admin['name'];
            $_SESSION['admin_email']   = $admin['email'];
            $_SESSION['company_name']  = $admin['company_name'];
            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'Invalid email or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login · Hamza Zarai Admin</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
<link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="login-wrap">
  <div class="login-card">
    <div class="text-center mb-4">
      <div style="font-size:2.2rem;">🌱</div>
      <h4 class="fw-bold mt-1 mb-0">Hamza Zarai Admin</h4>
      <div class="text-muted small">Pesticide & Fertilizer Inventory</div>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-danger py-2 small"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="POST">
      <div class="mb-3">
        <label class="form-label small fw-semibold">Email</label>
        <input type="email" name="email" class="form-control" placeholder="admin@pesticide.com" required autofocus>
      </div>
      <div class="mb-3">
        <label class="form-label small fw-semibold">Password</label>
        <input type="password" name="password" class="form-control" placeholder="•••" required>
      </div>
      <button type="submit" class="btn btn-brand w-100 py-2 fw-semibold">Log In</button>
    </form>
    <p class="text-center text-muted small mt-3 mb-0">
      Developed by: Ali Ashraf
    </p>
  </div>
</div>
</body>
</html>
