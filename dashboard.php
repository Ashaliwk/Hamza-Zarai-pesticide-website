<?php
require_once 'includes/auth.php';
require_once 'config/database.php';
require_login();

$pageTitle    = 'Dashboard';
$activeNav    = 'dashboard';

$currentMonthKey   = date('Y-m');
$currentMonthLabel = date('F Y');

$totalProducts = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();

// Current Month Total Sales
$stmtMonthSales = $pdo->prepare("SELECT COALESCE(SUM(total),0) FROM sales WHERE DATE_FORMAT(sale_date, '%Y-%m') = ?");
$stmtMonthSales->execute([$currentMonthKey]);
$monthTotalSales = $stmtMonthSales->fetchColumn();

// Current Month Total Profit
$stmtMonthProfit = $pdo->prepare("
    SELECT COALESCE(SUM((s.price_per_unit - p.purchase_price) * s.quantity),0)
    FROM sales s JOIN products p ON p.id = s.product_id
    WHERE DATE_FORMAT(s.sale_date, '%Y-%m') = ?
");
$stmtMonthProfit->execute([$currentMonthKey]);
$monthTotalProfit = $stmtMonthProfit->fetchColumn();

// Current Month Sales Volume (Units Sold)
$stmtMonthVolume = $pdo->prepare("SELECT COALESCE(SUM(quantity),0) FROM sales WHERE DATE_FORMAT(sale_date, '%Y-%m') = ?");
$stmtMonthVolume->execute([$currentMonthKey]);
$monthSalesVolume = $stmtMonthVolume->fetchColumn();

$allTimeSales = $pdo->query("SELECT COALESCE(SUM(total),0) FROM sales")->fetchColumn();

// Disciplined Monthly Breakdown of Sales
$monthlySalesBreakdown = $pdo->query("
    SELECT 
        DATE_FORMAT(s.sale_date, '%Y-%m') AS month_key,
        DATE_FORMAT(s.sale_date, '%M %Y') AS month_name,
        COUNT(s.id) AS total_orders,
        COALESCE(SUM(s.quantity), 0) AS total_units,
        COALESCE(SUM(s.total), 0) AS total_sales,
        COALESCE(SUM(s.paid_amount), 0) AS total_paid,
        COALESCE(SUM(s.due_amount), 0) AS total_due,
        COALESCE(SUM((s.price_per_unit - p.purchase_price) * s.quantity), 0) AS total_profit
    FROM sales s
    LEFT JOIN products p ON p.id = s.product_id
    GROUP BY month_key, month_name
    ORDER BY month_key DESC
")->fetchAll();

$lowStockProducts = $pdo->query("
    SELECT p.*, c.name AS category_name
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    WHERE p.quantity <= p.low_stock_threshold
    ORDER BY p.quantity ASC
")->fetchAll();

$lowStock = count($lowStockProducts);

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
        <div class="text-muted small mt-1" style="font-size: 0.76rem;">In inventory</div>
      </div>
      <div class="stat-icon icon-blue"><i class="fa-solid fa-cube"></i></div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-card d-flex justify-content-between align-items-start">
      <div>
        <div class="stat-label">This Month Sales</div>
        <div class="stat-value text-dark"><?= money($monthTotalSales) ?></div>
        <div class="text-muted small mt-1" style="font-size: 0.76rem;" title="Resets every new month">
          <i class="fa-regular fa-calendar-check me-1 text-success"></i><?= e($currentMonthLabel) ?>
        </div>
      </div>
      <div class="stat-icon icon-green"><i class="fa-solid fa-cart-shopping"></i></div>
    </div>
  </div>
 <div class="col-6 col-lg-3">
    <div class="stat-card d-flex justify-content-between align-items-start">
        <div>
            <div class="stat-label">This Month Profit</div>

            <!-- Hidden Profit -->
            <div class="stat-value text-purple" id="profitAmount" data-value="<?= money($monthTotalProfit) ?>" onclick="toggleProfit()" style="cursor:pointer;">
                ******
            </div>
            <div class="text-muted small mt-1" style="font-size: 0.76rem;">
              <i class="fa-solid fa-arrow-trend-up me-1 text-purple"></i><?= e($currentMonthLabel) ?>
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
            profit.innerHTML = '****** ';
        } else {
            profit.innerHTML = profit.dataset.value ;
        }

        profitVisible = !profitVisible;
    }
</script>
  <div class="col-6 col-lg-3">
    <div class="stat-card d-flex justify-content-between align-items-start position-relative"
         style="cursor: pointer; transition: transform 0.15s ease, box-shadow 0.15s ease;"
         data-bs-toggle="modal"
         data-bs-target="#lowStockModal"
         title="Click to view low stock items">
      <div>
        <div class="stat-label">Low Stock Items</div>
        <div class="stat-value text-danger"><?= (int)$lowStock ?></div>
        <div class="text-muted small mt-1" style="font-size: 0.76rem;">
          <i class="fa-solid fa-list-ul me-1 text-warning"></i>Click to view items
        </div>
      </div>
      <div class="stat-icon icon-orange"><i class="fa-solid fa-triangle-exclamation"></i></div>
    </div>
  </div>
</div>

<div class="row g-3 mb-4">
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

<!-- Monthly Sales History & Breakdown Panel -->
<div class="row g-3 mb-4">
  <div class="col-12">
    <div class="panel">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
          <h5 class="panel-title mb-1 d-flex align-items-center gap-2">
            <i class="fa-solid fa-calendar-days text-success"></i> Monthly Sales History & Breakdown
          </h5>
          <p class="text-muted small mb-0">Disciplined archive of all past and current month sales (resets every month)</p>
        </div>
        <a href="sales.php" class="btn btn-sm btn-outline-success fw-semibold">
          <i class="fa-solid fa-list me-1"></i> View All Sales
        </a>
      </div>
      
      <div class="table-responsive">
        <table class="table table-clean align-middle mb-0">
          <thead>
            <tr>
              <th>Month & Year</th>
              <th>Total Orders</th>
              <th>Units Sold</th>
              <th>Total Sales Volume</th>
              <th>Amount Paid</th>
              <th>Outstanding Due</th>
              <th>Est. Profit</th>
              <th class="text-end">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$monthlySalesBreakdown): ?>
              <tr><td colspan="8" class="text-center text-muted py-4">No monthly sales recorded yet.</td></tr>
            <?php else: ?>
              <?php foreach ($monthlySalesBreakdown as $mb): 
                $isCurrentMonth = ($mb['month_key'] === $currentMonthKey);
              ?>
                <tr>
                  <td>
                    <div class="fw-bold text-dark d-flex align-items-center gap-2">
                      <?= e($mb['month_name']) ?>
                      <?php if ($isCurrentMonth): ?>
                        <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1" style="font-size:0.7rem;">Active Month</span>
                      <?php endif; ?>
                    </div>
                    <div class="text-muted small"><?= e($mb['month_key']) ?></div>
                  </td>
                  <td class="fw-semibold text-dark"><?= (int)$mb['total_orders'] ?> sale<?= (int)$mb['total_orders'] === 1 ? '' : 's' ?></td>
                  <td class="fw-semibold"><?= rtrim(rtrim(number_format($mb['total_units'], 2), '0'), '.') ?></td>
                  <td class="fw-bold text-dark"><?= money($mb['total_sales']) ?></td>
                  <td class="text-success fw-semibold"><?= money($mb['total_paid']) ?></td>
                  <td>
                    <?php if ((float)$mb['total_due'] > 0): ?>
                      <span class="text-danger fw-bold"><?= money($mb['total_due']) ?></span>
                    <?php else: ?>
                      <span class="text-muted">Rs 0.00</span>
                    <?php endif; ?>
                  </td>
                  <td class="fw-bold text-primary"><?= money($mb['total_profit']) ?></td>
                  <td class="text-end">
                    <a href="sales.php?month=<?= e($mb['month_key']) ?>" class="btn btn-sm btn-light border fw-semibold text-muted">
                      <i class="fa-solid fa-eye me-1"></i> View Month
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Low Stock Items Modal -->
<div class="modal fade" id="lowStockModal" tabindex="-1" aria-labelledby="lowStockModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header border-bottom-0 pb-0">
        <h5 class="modal-title fw-bold text-dark d-flex align-items-center gap-2" id="lowStockModalLabel">
          <span class="badge bg-warning-subtle text-warning-emphasis p-2 rounded-3">
            <i class="fa-solid fa-triangle-exclamation text-warning fs-5"></i>
          </span>
          Low Stock Products
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body pt-3">
        <?php if (!$lowStockProducts): ?>
          <div class="text-center text-muted py-5">
            <i class="fa-solid fa-circle-check text-success fa-3x mb-3"></i>
            <h6 class="fw-bold">No Low Stock Items</h6>
            <p class="small text-muted mb-0">All products have sufficient quantity in stock.</p>
          </div>
        <?php else: ?>
          <p class="text-muted small mb-3">The following products are at or below their low stock threshold:</p>
          <div class="table-responsive">
            <table class="table table-clean align-middle mb-0">
              <thead>
                <tr>
                  <th>Product</th>
                  <th>Category</th>
                  <th>Current Quantity</th>
                  <th>Threshold</th>
                  <th class="text-end">Action</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($lowStockProducts as $lp): ?>
                  <tr>
                    <td>
                      <div class="fw-bold text-dark"><?= e($lp['name']) ?></div>
                      <div class="text-muted small"><?= e($lp['sku']) ?></div>
                    </td>
                    <td>
                      <span class="badge-cat"><?= e($lp['category_name'] ?: 'Uncategorized') ?></span>
                    </td>
                    <td>
                      <span class="fw-bold text-danger">
                        <?= rtrim(rtrim(number_format($lp['quantity'], 2), '0'), '.') ?> <?= e($lp['unit']) ?>
                      </span>
                    </td>
                    <td class="text-muted">
                      <?= rtrim(rtrim(number_format($lp['low_stock_threshold'], 2), '0'), '.') ?> <?= e($lp['unit']) ?>
                    </td>
                    <td class="text-end">
                      <a href="purchases.php" class="btn btn-sm btn-brand fw-semibold">
                        <i class="fa-solid fa-cart-plus me-1"></i> Restock
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
      <div class="modal-footer border-top-0 pt-0">
        <a href="products.php" class="btn btn-light fw-semibold text-muted">View All Products</a>
        <button type="button" class="btn btn-brand" data-bs-dismiss="modal">Close</button>
      </div>
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

