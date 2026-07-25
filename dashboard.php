<?php
require_once 'includes/auth.php';
require_once 'config/database.php';
require_login();

$pageTitle    = 'Dashboard';
$activeNav    = 'dashboard';

$totalProducts = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();

$totalSales = $pdo->query("SELECT COALESCE(SUM(total),0) FROM sales")->fetchColumn();

// Profit = SUM( (price_per_unit - purchase_price) * quantity ) across all sales
$totalProfit = $pdo->query("
    SELECT COALESCE(SUM((s.price_per_unit - p.purchase_price) * s.quantity),0)
    FROM sales s JOIN products p ON p.id = s.product_id
")->fetchColumn();

$lowStock = $pdo->query("SELECT COUNT(*) FROM products WHERE quantity <= low_stock_threshold")->fetchColumn();

// ---- Top products by sales quantity ----
$topProducts = $pdo->query("
    SELECT p.name, SUM(s.quantity) as qty
    FROM sales s JOIN products p ON p.id = s.product_id
    GROUP BY p.id, p.name
    ORDER BY qty DESC
    LIMIT 6
")->fetchAll();

// ---- Recent sales ----
$recentSales = $pdo->query("
    SELECT s.*, p.name as product_name
    FROM sales s JOIN products p ON p.id = s.product_id
    ORDER BY s.sale_date DESC, s.id DESC
    LIMIT 5
")->fetchAll();

$owner_image_url = "./assets/images/shafqat.jpeg";

$owner_name  = "M. Shafqat";
$owner_title = "Owner";

$default_avatar = "https://ui-avatars.com/api/?name=" . urlencode($owner_name) . "&background=1b6b3a&color=fff&size=200&bold=true";
$final_owner_avatar = !empty($owner_image_url) ? $owner_image_url : $default_avatar;

require_once 'includes/header.php';
?>

<div class="row align-items-center mb-4 g-3">
  <div class="col-md-7 col-lg-8">
    <h1 class="page-title mb-1">Dashboard</h1>
    <p class="page-subtitle mb-0">Welcome back, Here's what's happening today.</p>
  </div>
  <div class="col-md-5 col-lg-4">
    <!-- Website Owner Card -->
    <div class="owner-profile-card d-flex align-items-center gap-3">
      <div class="owner-card-accent"></div>
      <div class="owner-avatar-wrapper">
        <img src="<?= e($final_owner_avatar) ?>" alt="<?= e($owner_name) ?>" class="owner-avatar-img" onerror="this.src='<?= e($default_avatar) ?>';">
        <span class="owner-status-dot" title="Active Owner Session"></span>
      </div>
      <div class="owner-info flex-grow-1 overflow-hidden">
        <div class="owner-role-tag">
          <i class="fa-solid fa-crown"></i> <?= e($owner_title) ?>
        </div>
        <div class="owner-title-name text-truncate" title="<?= e($owner_name) ?>"><?= e($owner_name) ?></div>
        <div class="owner-subtitle text-truncate"><i class="fa-solid fa-shield-halved me-1 text-success"></i>Verified Administrator</div>
      </div>
    </div>
  </div>
</div>


<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3">
    <div class="stat-card d-flex justify-content-between align-items-start">
      <div>
        <div class="stat-label">Total Products</div>
        <div class="stat-value"><?= (int)$totalProducts ?></div>
      </div>
      <div class="stat-icon icon-blue"><i class="fa-solid fa-cube"></i></div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-card d-flex justify-content-between align-items-start">
      <div>
        <div class="stat-label">Total Sales</div>
        <div class="stat-value"><?= money($totalSales) ?></div>
      </div>
      <div class="stat-icon icon-green"><i class="fa-solid fa-cart-shopping"></i></div>
    </div>
  </div>
 <div class="col-6 col-lg-3">
    <div class="stat-card d-flex justify-content-between align-items-start">
        <div>
            <div class="stat-label">Total Profit</div>

            <!-- Hidden Profit -->
            <div class="stat-value" id="profitAmount" data-value="<?= money($totalProfit) ?>" onclick="toggleProfit()" style="cursor:pointer;">
                ****** <i class="fa-solid fa-eye"></i>
            </div>
        </div>

        <div class="stat-icon icon-purple">
            <i class="fa-solid fa-arrow-trend-up"></i>
        </div>
    </div>
</div>

<script>
    let profitVisible = false;
    function toggleProfit() {
        const profit = document.getElementById('profitAmount');

        if (profitVisible) {
            profit.innerHTML = '****** <i class="fa-solid fa-eye"></i>';
        } else {
            profit.innerHTML = profit.dataset.value + ' <i class="fa-solid fa-eye-slash"></i>';
        }

        profitVisible = !profitVisible;
    }
</script>
  <div class="col-6 col-lg-3">
    <div class="stat-card d-flex justify-content-between align-items-start">
      <div>
        <div class="stat-label">Low Stock Items</div>
        <div class="stat-value"><?= (int)$lowStock ?></div>
      </div>
      <div class="stat-icon icon-orange"><i class="fa-solid fa-triangle-exclamation"></i></div>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="panel h-100">
      <div class="panel-title">Top Products by Sales</div>
      <canvas id="topProductsChart" height="230"></canvas>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="panel h-100">
      <div class="panel-title">Recent Sales</div>
      <?php if (!$recentSales): ?>
        <p class="text-muted small">No sales recorded yet.</p>
      <?php endif; ?>
      <?php foreach ($recentSales as $s): ?>
        <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
          <div>
            <div class="fw-semibold"><?= e($s['customer_name']) ?></div>
            <div class="text-muted small"><?= date('n/j/Y', strtotime($s['sale_date'])) ?></div>
          </div>
          <div class="text-end">
            <div class="fw-bold text-success"><?= money($s['total']) ?></div>
            <div class="text-muted small"><?= (int)$s['quantity'] ?> units</div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<?php
$extraScripts = '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
const ctx = document.getElementById("topProductsChart");
new Chart(ctx, {
  type: "bar",
  data: {
    labels: ' . json_encode(array_column($topProducts, 'name')) . ',
    datasets: [{
      label: "Units Sold",
      data: ' . json_encode(array_map('floatval', array_column($topProducts, 'qty'))) . ',
      backgroundColor: "#1b6b3a",
      borderRadius: 6,
      maxBarThickness: 60
    }]
  },
  options: {
    plugins: { legend: { display: false } },
    scales: { y: { beginAtZero: true, grid: { color: "#f0f2f1" } }, x: { grid: { display: false } } }
  }
});
</script>';
require_once 'includes/footer.php';
?>
