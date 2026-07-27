<?php
require_once 'includes/auth.php';
require_once 'config/database.php';
require_login();

$pageTitle = 'Sales';
$activeNav = 'sales';

$currentMonthKey = date('Y-m');
$selectedMonth   = trim($_GET['month'] ?? $currentMonthKey);
$search          = trim($_GET['search'] ?? '');

$products = $pdo->query("
    SELECT p.*, c.name AS category_name, sc.name AS subcategory_name
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    LEFT JOIN subcategories sc ON sc.id = p.subcategory_id
    ORDER BY c.name, sc.name, p.name
")->fetchAll();

// Available months from sales table
$availableMonths = $pdo->query("
    SELECT DISTINCT DATE_FORMAT(sale_date, '%Y-%m') AS month_key, DATE_FORMAT(sale_date, '%M %Y') AS month_name
    FROM sales
    ORDER BY month_key DESC
")->fetchAll();

$whereConditions = [];
$params = [];

if ($selectedMonth !== 'all') {
    $whereConditions[] = "DATE_FORMAT(s.sale_date, '%Y-%m') = :month";
    $params[':month'] = $selectedMonth;
}

if ($search !== '') {
    $whereConditions[] = "(p.name LIKE :search OR sc.name LIKE :search OR c.name LIKE :search OR s.customer_name LIKE :search OR s.sale_date LIKE :search OR s.payment_status LIKE :search)";
    $params[':search'] = "%$search%";
}

$whereSql = count($whereConditions) > 0 ? "WHERE " . implode(' AND ', $whereConditions) : "";

$stmt = $pdo->prepare("
    SELECT s.*, p.name AS product_name, p.unit AS product_unit, c.name AS category_name, sc.name AS subcategory_name
    FROM sales s 
    JOIN products p ON p.id = s.product_id
    LEFT JOIN categories c ON c.id = p.category_id
    LEFT JOIN subcategories sc ON sc.id = p.subcategory_id
    {$whereSql}
    ORDER BY s.sale_date DESC, s.id DESC
");
$stmt->execute($params);
$sales = $stmt->fetchAll();

$company = $_SESSION['company_name'] ?? 'Hamza Zarai Corporation';

$totalSalesVolume = 0;
$totalAmountPaid  = 0;
$totalAmountDue   = 0;

foreach ($sales as $s) {
    $totalSalesVolume += (float)$s['total'];
    $totalAmountPaid  += (float)($s['paid_amount'] ?? $s['total']);
    $totalAmountDue   += (float)($s['due_amount'] ?? 0.00);
}

// Disciplined Monthly Sales Archive (sales of all months formatted)
$monthlySalesArchive = $pdo->query("
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

require_once 'includes/header.php';
?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
  <div>
    <h1 class="page-title mb-1">Sales</h1>
    <p class="page-subtitle mb-0">Track monthly sales performance & payment dues</p>
  </div>
  <div class="d-flex align-items-center gap-2">
    <form method="GET" action="sales.php" id="monthFilterForm" class="d-flex align-items-center gap-2">
      <?php if ($search !== ''): ?>
        <input type="hidden" name="search" value="<?= e($search) ?>">
      <?php endif; ?>
      <div class="bg-white border rounded-3 px-2 py-1 shadow-sm d-flex align-items-center gap-2">
        <i class="fa-solid fa-calendar-days text-success ms-1"></i>
        <span class="small fw-bold text-muted">Period:</span>
        <select name="month" id="monthSelect" class="form-select form-select-sm border-0 fw-bold text-dark shadow-none" onchange="this.form.submit()" style="cursor:pointer; min-width: 170px; background-color: transparent;">
          <option value="<?= $currentMonthKey ?>" <?= $selectedMonth === $currentMonthKey ? 'selected' : '' ?>>Current Month (<?= date('F Y') ?>)</option>
          <option value="all" <?= $selectedMonth === 'all' ? 'selected' : '' ?>>All Time (All Months)</option>
          <?php foreach ($availableMonths as $m): ?>
            <?php if ($m['month_key'] !== $currentMonthKey): ?>
              <option value="<?= $m['month_key'] ?>" <?= $selectedMonth === $m['month_key'] ? 'selected' : '' ?>>
                <?= e($m['month_name']) ?>
              </option>
            <?php endif; ?>
          <?php endforeach; ?>
        </select>
      </div>
    </form>
    <button class="btn btn-brand fw-semibold ms-1" data-bs-toggle="modal" data-bs-target="#addSaleModal">
      <i class="fa-solid fa-plus me-1"></i> Record Sale
    </button>
  </div>
</div>

<?php
  $periodLabel = ($selectedMonth === 'all')
    ? 'All Time'
    : date('F Y', strtotime($selectedMonth . '-01'));
?>

<!-- Financial Summary Cards -->
<div class="row g-3 mb-4">
  <div class="col-md-4">
    <div class="stat-card d-flex align-items-center gap-3">
      <div class="stat-icon icon-green"><i class="fa-solid fa-chart-line"></i></div>
      <div>
        <div class="stat-label">Total Sales Volume (<?= e($periodLabel) ?>)</div>
        <div class="stat-value text-dark"><?= money($totalSalesVolume) ?></div>
        <div class="text-muted small mt-1" style="font-size:0.75rem;">
          <i class="fa-solid fa-rotate-left text-success me-1"></i>Resets every new month
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="stat-card d-flex align-items-center gap-3">
      <div class="stat-icon icon-blue"><i class="fa-solid fa-wallet"></i></div>
      <div>
        <div class="stat-label">Total Amount Paid (<?= e($periodLabel) ?>)</div>
        <div class="stat-value text-success"><?= money($totalAmountPaid) ?></div>
        <div class="text-muted small mt-1" style="font-size:0.75rem;">
          Collected revenue
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="stat-card d-flex align-items-center gap-3">
      <div class="stat-icon icon-orange"><i class="fa-solid fa-clock-rotate-left"></i></div>
      <div>
        <div class="stat-label">Total Outstanding Due (<?= e($periodLabel) ?>)</div>
        <div class="stat-value <?= $totalAmountDue > 0 ? 'text-danger' : 'text-muted' ?>"><?= money($totalAmountDue) ?></div>
        <div class="text-muted small mt-1" style="font-size:0.75rem;">
          Pending customer balances
        </div>
      </div>
    </div>
  </div>
</div>

<div class="panel">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div class="search-box position-relative" style="max-width: 320px; width: 100%;">
      <i class="fa-solid fa-magnifying-glass search-icon"></i>
      <input type="text" id="salesSearch" class="form-control search-input" placeholder="Search product, customer, status..." value="<?= e($search) ?>" autocomplete="off">
      <button class="search-clear-btn" id="clearSalesSearch" type="button" style="display: <?= $search !== '' ? 'flex' : 'none' ?>;"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="text-muted small" id="salesCount">
      <?= count($sales) ?> sale<?= count($sales) === 1 ? '' : 's' ?> found
    </div>
  </div>
  <div class="table-responsive">
  <table class="table table-clean mb-0">
    <thead>
      <tr>
        <th>Date</th>
        <th>Subcategory</th>
        <th>Category</th>
        <th>Customer</th>
        <th>Quantity</th>
        <th>Price/Unit</th>
        <th>Total</th>
        <th>Status</th>
        <th>Paid / Due</th>
        <th class="text-end">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$sales): ?>
        <tr><td colspan="10" class="text-center text-muted py-4">No sales found.</td></tr>
      <?php endif; ?>
      <tr id="noMatchSalesRow" style="display:none;"><td colspan="10" class="text-center text-muted py-4">No matching sales recorded.</td></tr>
      <?php foreach ($sales as $s):
        $st = $s['payment_status'] ?? 'paid';
        $paid = (float)($s['paid_amount'] ?? $s['total']);
        $due = (float)($s['due_amount'] ?? 0.00);

        $displayName = !empty($s['subcategory_name']) 
          ? $s['subcategory_name'] . (!empty($s['category_name']) ? ' (' . $s['category_name'] . ')' : '')
          : $s['product_name'];

        $salePayload = [
          'id' => $s['id'],
          'product' => $displayName,
          'customer' => $s['customer_name'],
          'qty' => rtrim(rtrim(number_format($s['quantity'],2), '0'), '.'),
          'unit' => $s['product_unit'] ?? '',
          'price' => number_format($s['price_per_unit'], 2),
          'total' => number_format($s['total'], 2),
          'payment_status' => $st,
          'paid_amount' => number_format($paid, 2),
          'due_amount' => number_format($due, 2),
          'date' => date('d M, Y', strtotime($s['sale_date']))
        ];
        $jsonPayload = htmlspecialchars(json_encode($salePayload), ENT_QUOTES, 'UTF-8');
      ?>
      <tr class="sale-row">
        <td><?= date('n/j/Y', strtotime($s['sale_date'])) ?></td>
        <td class="fw-bold text-dark">
          <?php if (!empty($s['subcategory_name'])): ?>
            <span class="badge bg-success-subtle text-success-emphasis border border-success-subtle fw-semibold px-2 py-1" style="font-size:0.85rem;">
              <i class="fa-solid fa-tag me-1 text-success" style="font-size:0.75rem;"></i><?= e($s['subcategory_name']) ?>
            </span>
          <?php else: ?>
            <span class="fw-semibold text-dark"><?= e($s['product_name']) ?></span>
          <?php endif; ?>
        </td>
        <td>
          <span class="badge-cat"><?= e($s['category_name'] ?: 'General') ?></span>
        </td>
        <td><?= e($s['customer_name']) ?></td>
        <td><?= rtrim(rtrim(number_format($s['quantity'],2), '0'), '.') ?> <?= e($s['product_unit'] ?? '') ?></td>
        <td><?= money($s['price_per_unit']) ?></td>
        <td class="fw-bold text-dark"><?= money($s['total']) ?></td>
        <td>
          <?php if ($st === 'paid'): ?>
            <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1"><i class="fa-solid fa-circle-check me-1"></i> Paid</span>
          <?php elseif ($st === 'unpaid'): ?>
            <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1"><i class="fa-solid fa-circle-xmark me-1"></i> Unpaid</span>
          <?php else: ?>
            <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-2 py-1"><i class="fa-solid fa-clock me-1"></i> Partial</span>
          <?php endif; ?>
        </td>
        <td>
          <div class="small">
            <span class="text-success font-monospace">Paid: <?= money($paid) ?></span>
            <?php if ($due > 0): ?>
              <br><span class="text-danger fw-bold font-monospace">Due: <?= money($due) ?></span>
            <?php else: ?>
              <br><span class="text-muted font-monospace">Due: Rs 0.00</span>
            <?php endif; ?>
          </div>
        </td>
        <td class="text-end">
          <button class="icon-btn pdf me-1" title="Download PDF" onclick='downloadRowPDF(<?= $jsonPayload ?>)'>
            <i class="fa-solid fa-file-pdf"></i>
          </button>
          <button class="icon-btn print me-1" title="Print Sale" onclick='printRowReceipt(<?= $jsonPayload ?>)'>
            <i class="fa-solid fa-print"></i>
          </button>
          <button class="icon-btn edit me-1" title="Edit"
            data-bs-toggle="modal" data-bs-target="#editSaleModal"
            data-id="<?= $s['id'] ?>"
            data-product="<?= $s['product_id'] ?>"
            data-customer="<?= e($s['customer_name']) ?>"
            data-qty="<?= $s['quantity'] ?>"
            data-price="<?= $s['price_per_unit'] ?>"
            data-status="<?= $st ?>"
            data-paid="<?= $paid ?>"
            data-date="<?= $s['sale_date'] ?>">
            <i class="fa-solid fa-pen"></i>
          </button>
          <form method="POST" action="sales_action.php" class="d-inline" onsubmit="return confirm('Delete this sale? Stock will be restored.');">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= $s['id'] ?>">
            <button type="submit" class="icon-btn del" title="Delete"><i class="fa-solid fa-trash"></i></button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>

<div class="panel mt-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h3 class="panel-title mb-1 d-flex align-items-center gap-2">
        <i class="fa-solid fa-boxes-stacked text-success"></i> Monthly Sales
      </h3>
      <p class="text-muted small mb-0">Disciplined historical log of all monthly sales volumes, collections, dues, and profits</p>
    </div>
    <?php if ($selectedMonth !== 'all'): ?>
      <a href="sales.php?month=all" class="btn btn-sm btn-light border fw-semibold">
        <i class="fa-solid fa-globe me-1"></i> View All Months
      </a>
    <?php endif; ?>
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
          <th class="text-end">Filter Scope</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$monthlySalesArchive): ?>
          <tr><td colspan="8" class="text-center text-muted py-4">No monthly sales records logged.</td></tr>
        <?php else: ?>
          <?php foreach ($monthlySalesArchive as $ma):
            $isCurrent = ($ma['month_key'] === $currentMonthKey);
            $isSelected = ($ma['month_key'] === $selectedMonth);
          ?>
            <tr class="<?= $isSelected ? 'table-success-subtle' : '' ?>">
              <td>
                <div class="fw-bold text-dark d-flex align-items-center gap-2">
                  <?= e($ma['month_name']) ?>
                  <?php if ($isCurrent): ?>
                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-0.5" style="font-size:0.7rem;">Active Month</span>
                  <?php endif; ?>
                  <?php if ($isSelected): ?>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-0.5" style="font-size:0.7rem;">Selected View</span>
                  <?php endif; ?>
                </div>
                <div class="text-muted small"><?= e($ma['month_key']) ?></div>
              </td>
              <td class="fw-semibold text-dark"><?= (int)$ma['total_orders'] ?> order<?= (int)$ma['total_orders'] === 1 ? '' : 's' ?></td>
              <td class="fw-semibold"><?= rtrim(rtrim(number_format($ma['total_units'], 2), '0'), '.') ?></td>
              <td class="fw-bold text-dark"><?= money($ma['total_sales']) ?></td>
              <td class="text-success fw-semibold"><?= money($ma['total_paid']) ?></td>
              <td>
                <?php if ((float)$ma['total_due'] > 0): ?>
                  <span class="text-danger fw-bold"><?= money($ma['total_due']) ?></span>
                <?php else: ?>
                  <span class="text-muted">Rs 0.00</span>
                <?php endif; ?>
              </td>
              <td class="fw-bold text-primary"><?= money($ma['total_profit']) ?></td>
              <td class="text-end">
                <a href="sales.php?month=<?= e($ma['month_key']) ?>" class="btn btn-sm <?= $isSelected ? 'btn-brand' : 'btn-light border text-muted' ?> fw-semibold">
                  <i class="fa-solid fa-filter me-1"></i> Filter Records
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="modal fade" id="addSaleModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" action="sales_action.php" class="modal-content">
      <input type="hidden" name="action" value="add">
      <div class="modal-header">
        <h5 class="modal-title fw-bold">Record Sale</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label small fw-semibold">Subcategory & Category</label>
          <select name="product_id" id="sale_product" class="form-select" required onchange="fillSalePrice(); calculateSaleDue('add');">
            <option value="">Select subcategory / item...</option>
            <?php foreach ($products as $p): 
              $pLabel = !empty($p['subcategory_name']) ? $p['subcategory_name'] . ' (' . ($p['category_name'] ?: 'General') . ')' : $p['name'];
            ?>
              <option value="<?= $p['id'] ?>" data-price="<?= $p['selling_price'] ?>" data-stock="<?= $p['quantity'] ?>" data-unit="<?= e($p['unit']) ?>">
                <?= e($pLabel) ?> (<?= rtrim(rtrim(number_format($p['quantity'],2),'0'),'.') ?> <?= e($p['unit']) ?> in stock)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label small fw-semibold">Customer Name</label>
          <input type="text" name="customer_name" class="form-control" required>
        </div>
        <div class="row">
          <div class="col-6 mb-3">
            <label class="form-label small fw-semibold">Quantity</label>
            <input type="number" step="0.01" name="quantity" id="sale_qty" class="form-control" required oninput="calculateSaleDue('add')">
          </div>
          <div class="col-6 mb-3">
            <label class="form-label small fw-semibold">Price / Unit (Rs)</label>
            <input type="number" step="0.01" name="price_per_unit" id="sale_price" class="form-control" required oninput="calculateSaleDue('add')">
          </div>
        </div>

        <div class="row">
          <div class="col-6 mb-3">
            <label class="form-label small fw-semibold">Payment Status</label>
            <select name="payment_status" id="sale_payment_status" class="form-select" onchange="togglePaidInput('add')">
              <option value="paid">Paid (Full)</option>
              <option value="unpaid">Unpaid</option>
              <option value="partial">Partial Payment</option>
            </select>
          </div>
          <div class="col-6 mb-3" id="paid_amount_box" style="display:none;">
            <label class="form-label small fw-semibold">Amount Paid (Rs)</label>
            <input type="number" step="0.01" name="paid_amount" id="sale_paid_amount" class="form-control" placeholder="0.00" oninput="calculateSaleDue('add')">
          </div>
        </div>

        <div class="p-2.5 rounded mb-3 bg-light border small text-muted d-flex justify-content-between align-items-center">
          <span>Calculated Balance Due:</span>
          <strong class="text-danger fs-6" id="due_preview_amount">Rs 0.00</strong>
        </div>

        <div class="mb-1">
          <label class="form-label small fw-semibold">Sale Date</label>
          <input type="date" name="sale_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-brand">Save Sale</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Sale Modal -->
<div class="modal fade" id="editSaleModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" action="sales_action.php" class="modal-content">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="id" id="edit_sale_id">
      <div class="modal-header">
        <h5 class="modal-title fw-bold">Edit Sale</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label small fw-semibold">Subcategory & Category</label>
          <select name="product_id" id="edit_sale_product" class="form-select" required onchange="fillEditSalePrice(); calculateSaleDue('edit');">
            <option value="">Select subcategory / item...</option>
            <?php foreach ($products as $p): 
              $pLabel = !empty($p['subcategory_name']) ? $p['subcategory_name'] . ' (' . ($p['category_name'] ?: 'General') . ')' : $p['name'];
            ?>
              <option value="<?= $p['id'] ?>" data-price="<?= $p['selling_price'] ?>" data-stock="<?= $p['quantity'] ?>" data-unit="<?= e($p['unit']) ?>">
                <?= e($pLabel) ?> (<?= rtrim(rtrim(number_format($p['quantity'],2),'0'),'.') ?> <?= e($p['unit']) ?> in stock)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label small fw-semibold">Customer Name</label>
          <input type="text" name="customer_name" id="edit_sale_customer" class="form-control" required>
        </div>
        <div class="row">
          <div class="col-6 mb-3">
            <label class="form-label small fw-semibold">Quantity</label>
            <input type="number" step="0.01" name="quantity" id="edit_sale_qty" class="form-control" required oninput="calculateSaleDue('edit')">
          </div>
          <div class="col-6 mb-3">
            <label class="form-label small fw-semibold">Price / Unit (Rs)</label>
            <input type="number" step="0.01" name="price_per_unit" id="edit_sale_price" class="form-control" required oninput="calculateSaleDue('edit')">
          </div>
        </div>

        <div class="row">
          <div class="col-6 mb-3">
            <label class="form-label small fw-semibold">Payment Status</label>
            <select name="payment_status" id="edit_sale_payment_status" class="form-select" onchange="togglePaidInput('edit')">
              <option value="paid">Paid (Full)</option>
              <option value="unpaid">Unpaid</option>
              <option value="partial">Partial Payment</option>
            </select>
          </div>
          <div class="col-6 mb-3" id="edit_paid_amount_box" style="display:none;">
            <label class="form-label small fw-semibold">Amount Paid (Rs)</label>
            <input type="number" step="0.01" name="paid_amount" id="edit_sale_paid_amount" class="form-control" placeholder="0.00" oninput="calculateSaleDue('edit')">
          </div>
        </div>

        <div class="p-2.5 rounded mb-3 bg-light border small text-muted d-flex justify-content-between align-items-center">
          <span>Calculated Balance Due:</span>
          <strong class="text-danger fs-6" id="edit_due_preview_amount">Rs 0.00</strong>
        </div>

        <div class="mb-1">
          <label class="form-label small fw-semibold">Sale Date</label>
          <input type="date" name="sale_date" id="edit_sale_date" class="form-control" required>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-brand">Update Sale</button>
      </div>
    </form>
  </div>
</div>

<!-- Sale Receipt / Invoice Modal -->
<div class="modal fade" id="saleReceiptModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
      <div class="modal-header bg-light border-0 py-3 no-print">
        <h5 class="modal-title fw-bold text-dark d-flex align-items-center gap-2">
          <i class="fa-solid fa-receipt text-success"></i> Sale Receipt & Invoice
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4" id="printableInvoiceArea">
        <div class="invoice-box p-4" style="background:#fff; border:1px solid #e2e8f0; border-radius:12px;">
          <!-- Header -->
          <div class="d-flex justify-content-between align-items-start border-bottom pb-3 mb-4">
            <div>
              <div class="d-flex align-items-center gap-2 mb-1">
                <span style="font-size: 1.8rem;">🌱</span>
                <h3 class="fw-extrabold mb-0" style="color: #14532d; font-weight:800; letter-spacing:-0.5px;"><?= e($company) ?></h3>
              </div>
              <p class="text-muted small mb-0">Quality Agricultural Pesticides & Fertilizers</p>
              <p class="text-muted small mb-0"><i class="fa-solid fa-location-dot me-1"></i> Main Agricultural Market, Pakistan</p>
            </div>
            <div class="text-end">
              <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 fw-semibold rounded-pill mb-2">
                <i class="fa-solid fa-circle-check me-1"></i> SALE INVOICE
              </span>
              <h5 class="fw-bold mb-1" id="rec_invoice_no" style="color:#1b6b3a;">#INV-S-0000</h5>
              <div class="small text-muted" id="rec_date">Date: --</div>
            </div>
          </div>

          <!-- Customer info bar -->
          <div class="row mb-4 p-3 rounded" style="background-color: #f8faf9; border: 1px solid #edf2f0;">
            <div class="col-6">
              <div class="text-uppercase small fw-bold text-muted mb-1">Billed To Customer</div>
              <h6 class="fw-bold mb-1 text-dark" id="rec_customer_name">--</h6>
              <span class="badge bg-light text-secondary border">Customer Account</span>
            </div>
            <div class="col-6 text-end">
              <div class="text-uppercase small fw-bold text-muted mb-1">Payment Status</div>
              <div id="rec_status_badge">
                <span class="badge bg-success px-3 py-1">PAID</span>
              </div>
              <div class="small text-muted mt-1">Transaction Ref: <span id="rec_trans_id">TXN-000</span></div>
            </div>
          </div>

          <!-- Items Table -->
          <div class="table-responsive mb-4">
            <table class="table table-bordered align-middle mb-0">
              <thead style="background-color: #f8faf9;">
                <tr>
                  <th style="width: 8%">#</th>
                  <th>Product Description</th>
                  <th class="text-center" style="width: 20%">Quantity</th>
                  <th class="text-end" style="width: 22%">Price / Unit</th>
                  <th class="text-end" style="width: 24%">Total Amount</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td class="fw-semibold">1</td>
                  <td>
                    <strong id="rec_product_name" class="text-dark">--</strong>
                    <div class="text-muted small">Unit: <span id="rec_unit_label">--</span></div>
                  </td>
                  <td class="text-center fw-semibold" id="rec_quantity">0</td>
                  <td class="text-end fw-semibold" id="rec_price">Rs 0.00</td>
                  <td class="text-end fw-bold text-dark" id="rec_total">Rs 0.00</td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Total & Due Summary -->
          <div class="row align-items-center justify-content-end mb-4">
            <div class="col-md-6">
              <div class="p-3 rounded" style="background:#f8faf9; border:1px solid #edf2f0;">
                <div class="d-flex justify-content-between mb-2">
                  <span class="text-muted small">Total Bill Amount</span>
                  <span class="fw-bold text-dark" id="rec_subtotal">Rs 0.00</span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                  <span class="text-muted small">Amount Received (Paid)</span>
                  <span class="fw-bold text-success" id="rec_paid">Rs 0.00</span>
                </div>
                <hr class="my-2">
                <div class="d-flex justify-content-between align-items-center">
                  <span class="fw-bold">Outstanding Due</span>
                  <h4 class="fw-extrabold mb-0" id="rec_due">Rs 0.00</h4>
                </div>
              </div>
            </div>
          </div>

          <!-- Footer signature -->
          <div class="row pt-3 mt-3 border-top align-items-end">
            <div class="col-7">
              <p class="small text-muted mb-1"><strong>Terms:</strong> All sales are final. Retain invoice for records.</p>
              <p class="small text-muted mb-0">Thank you for doing business with <?= e($company) ?>!</p>
            </div>
            <div class="col-5 text-end">
              <div style="border-bottom: 1px dashed #94a3b8; width: 150px; display: inline-block; margin-bottom: 5px;"></div>
              <div class="small fw-semibold text-muted">Authorized Signature</div>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer bg-light no-print d-flex justify-content-between">
        <a href="#" id="rec_direct_link" target="_blank" class="btn btn-outline-secondary btn-sm">
          <i class="fa-solid fa-up-right-from-square me-1"></i> Standalone Receipt
        </a>
        <div class="d-flex gap-2">
          <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Close</button>
          <button type="button" class="btn btn-danger" onclick="downloadModalPDF()">
            <i class="fa-solid fa-file-pdf me-1"></i> Download PDF
          </button>
          <button type="button" class="btn btn-brand" onclick="printModalInvoice()">
            <i class="fa-solid fa-print me-1"></i> Print Receipt
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<?php
$extraScripts = '<script>
let currentSaleData = null;

function fillSalePrice() {
  const sel = document.getElementById("sale_product");
  const opt = sel.options[sel.selectedIndex];
  document.getElementById("sale_price").value = opt ? (opt.dataset.price || "") : "";
}
function fillEditSalePrice() {
  const sel = document.getElementById("edit_sale_product");
  const opt = sel.options[sel.selectedIndex];
  if (opt && opt.dataset.price) {
    document.getElementById("edit_sale_price").value = opt.dataset.price;
  }
}

function togglePaidInput(mode) {
  const prefix = mode === "edit" ? "edit_" : "";
  const st = document.getElementById(prefix + "sale_payment_status").value;
  const paidBox = document.getElementById(mode === "edit" ? "edit_paid_amount_box" : "paid_amount_box");
  if (st === "partial") {
    paidBox.style.display = "";
  } else {
    paidBox.style.display = "none";
  }
  calculateSaleDue(mode);
}

function calculateSaleDue(mode) {
  const prefix = mode === "edit" ? "edit_" : "";
  const qty = parseFloat(document.getElementById(prefix + "sale_qty")?.value || 0);
  const price = parseFloat(document.getElementById(prefix + "sale_price")?.value || 0);
  const total = qty * price;
  const st = document.getElementById(prefix + "sale_payment_status").value;
  const paidInput = document.getElementById(prefix + "sale_paid_amount");
  let paid = 0;

  if (st === "paid") {
    paid = total;
  } else if (st === "unpaid") {
    paid = 0;
  } else {
    paid = parseFloat(paidInput?.value || 0);
  }

  const due = Math.max(0, total - paid);
  const dueEl = document.getElementById(mode === "edit" ? "edit_due_preview_amount" : "due_preview_amount");
  if (dueEl) {
    dueEl.textContent = "Rs " + due.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
    if (due > 0) {
      dueEl.className = "text-danger fs-6 fw-bold";
    } else {
      dueEl.className = "text-success fs-6 fw-bold";
    }
  }
}

document.getElementById("editSaleModal")?.addEventListener("show.bs.modal", function (event) {
  const btn = event.relatedTarget;
  if (!btn) return;
  document.getElementById("edit_sale_id").value = btn.dataset.id;
  document.getElementById("edit_sale_product").value = btn.dataset.product;
  document.getElementById("edit_sale_customer").value = btn.dataset.customer;
  document.getElementById("edit_sale_qty").value = btn.dataset.qty;
  document.getElementById("edit_sale_price").value = btn.dataset.price;
  document.getElementById("edit_sale_date").value = btn.dataset.date;

  const st = btn.dataset.status || "paid";
  document.getElementById("edit_sale_payment_status").value = st;
  document.getElementById("edit_sale_paid_amount").value = btn.dataset.paid || "";
  togglePaidInput("edit");
});

function populateReceiptModal(data) {
  currentSaleData = data;
  const padId = String(data.id).padStart(4, "0");
  document.getElementById("rec_invoice_no").textContent = "#INV-S-" + padId;
  document.getElementById("rec_date").textContent = "Date: " + data.date;
  document.getElementById("rec_customer_name").textContent = data.customer;
  document.getElementById("rec_trans_id").textContent = "TXN-S" + String(data.id).padStart(5, "0");
  document.getElementById("rec_product_name").textContent = data.product;
  document.getElementById("rec_unit_label").textContent = data.unit || "Unit";
  document.getElementById("rec_quantity").textContent = data.qty + " " + (data.unit || "");
  document.getElementById("rec_price").textContent = "Rs " + data.price;
  document.getElementById("rec_total").textContent = "Rs " + data.total;
  document.getElementById("rec_subtotal").textContent = "Rs " + data.total;
  document.getElementById("rec_paid").textContent = "Rs " + data.paid_amount;

  const dueEl = document.getElementById("rec_due");
  const dueVal = parseFloat(data.due_amount.replace(/,/g, ""));
  dueEl.textContent = "Rs " + data.due_amount;
  if (dueVal > 0) {
    dueEl.className = "fw-extrabold mb-0 text-danger";
  } else {
    dueEl.className = "fw-extrabold mb-0 text-success";
  }

  const badgeBox = document.getElementById("rec_status_badge");
  if (data.payment_status === "paid") {
    badgeBox.innerHTML = \'<span class="badge bg-success px-3 py-1">PAID</span>\';
  } else if (data.payment_status === "unpaid") {
    badgeBox.innerHTML = \'<span class="badge bg-danger px-3 py-1">UNPAID</span>\';
  } else {
    badgeBox.innerHTML = \'<span class="badge bg-warning text-dark px-3 py-1">PARTIAL PAYMENT</span>\';
  }

  document.getElementById("rec_direct_link").href = "sale_receipt.php?id=" + data.id;
}

function printRowReceipt(data) {
  populateReceiptModal(data);
  const modalEl = document.getElementById("saleReceiptModal");
  const bsModal = new bootstrap.Modal(modalEl);
  bsModal.show();
  setTimeout(() => {
    window.print();
  }, 400);
}

function downloadRowPDF(data) {
  populateReceiptModal(data);
  const element = document.getElementById("printableInvoiceArea");
  const padId = String(data.id).padStart(4, "0");
  const custName = data.customer.replace(/[^A-Za-z0-9]/g, "_");
  const opt = {
    margin:       10,
    filename:     "Sale-Invoice-#INV-S-" + padId + "-" + custName + ".pdf",
    image:        { type: "jpeg", quality: 0.98 },
    html2canvas:  { scale: 2, useCORS: true },
    jsPDF:        { unit: "mm", format: "a4", orientation: "portrait" }
  };
  html2pdf().set(opt).from(element).save();
}

function printModalInvoice() {
  window.print();
}

function downloadModalPDF() {
  if (currentSaleData) {
    downloadRowPDF(currentSaleData);
  }
}

const salesInput = document.getElementById("salesSearch");
const clearSalesBtn = document.getElementById("clearSalesSearch");
const salesRows = document.querySelectorAll("tbody tr.sale-row");
const salesCount = document.getElementById("salesCount");
const noMatchSalesRow = document.getElementById("noMatchSalesRow");

function filterSales() {
  const query = salesInput.value.toLowerCase().trim();
  clearSalesBtn.style.display = query.length > 0 ? "flex" : "none";

  let visibleCount = 0;
  salesRows.forEach(row => {
    const text = row.innerText.toLowerCase();
    if (text.includes(query)) {
      row.style.display = "";
      visibleCount++;
    } else {
      row.style.display = "none";
    }
  });

  if (salesCount) {
    salesCount.textContent = visibleCount + (visibleCount === 1 ? " sale found" : " sales found");
  }

  if (noMatchSalesRow) {
    noMatchSalesRow.style.display = (visibleCount === 0 && salesRows.length > 0) ? "" : "none";
  }
}

if (salesInput) {
  salesInput.addEventListener("input", filterSales);
  clearSalesBtn.addEventListener("click", function() {
    salesInput.value = "";
    filterSales();
    salesInput.focus();
  });
}
</script>

<style media="print">
@media print {
  body * {
    visibility: hidden !important;
  }
  #saleReceiptModal, #saleReceiptModal * {
    visibility: visible !important;
  }
  #saleReceiptModal {
    position: absolute !important;
    left: 0 !important;
    top: 0 !important;
    width: 100% !important;
    margin: 0 !important;
    padding: 0 !important;
    background: #ffffff !important;
  }
  .modal-dialog {
    max-width: 100% !important;
    margin: 0 !important;
    width: 100% !important;
  }
  .modal-content {
    border: none !important;
    box-shadow: none !important;
  }
  .modal-header, .modal-footer, .no-print, .btn, .btn-close, nav, sidebar, header, .sidebar-wrapper, .topbar {
    display: none !important;
  }
  #printableInvoiceArea {
    width: 100% !important;
    margin: 0 !important;
    padding: 10px !important;
    box-shadow: none !important;
    border: none !important;
  }
}
</style>';
require_once 'includes/footer.php';
?>
