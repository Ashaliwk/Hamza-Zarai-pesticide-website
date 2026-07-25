<?php
require_once 'includes/auth.php';
require_once 'config/database.php';
require_login();

$pageTitle = 'Sales';
$activeNav = 'sales';

$products = $pdo->query("SELECT id, name, selling_price, quantity, unit FROM products ORDER BY name")->fetchAll();

$sales = $pdo->query("
    SELECT s.*, p.name AS product_name
    FROM sales s JOIN products p ON p.id = s.product_id
    ORDER BY s.sale_date DESC, s.id DESC
")->fetchAll();

require_once 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-start mb-1">
  <div>
    <h1 class="page-title">Sales</h1>
    <p class="page-subtitle">Track your sales transactions</p>
  </div>
  <button class="btn btn-brand fw-semibold" data-bs-toggle="modal" data-bs-target="#addSaleModal">
    <i class="fa-solid fa-plus me-1"></i> Record Sale
  </button>
</div>

<div class="panel">
  <div class="table-responsive">
  <table class="table table-clean mb-0">
    <thead>
      <tr>
        <th>Date</th>
        <th>Product</th>
        <th>Customer</th>
        <th>Quantity</th>
        <th>Price/Unit</th>
        <th>Total</th>
        <th class="text-end">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$sales): ?>
        <tr><td colspan="7" class="text-center text-muted py-4">No sales recorded yet.</td></tr>
      <?php endif; ?>
      <?php foreach ($sales as $s): ?>
      <tr>
        <td><?= date('n/j/Y', strtotime($s['sale_date'])) ?></td>
        <td><?= e($s['product_name']) ?></td>
        <td><?= e($s['customer_name']) ?></td>
        <td><?= rtrim(rtrim(number_format($s['quantity'],2), '0'), '.') ?></td>
        <td><?= money($s['price_per_unit']) ?></td>
        <td class="fw-bold text-success"><?= money($s['total']) ?></td>
        <td class="text-end">
          <button class="icon-btn edit me-1" title="Edit"
            data-bs-toggle="modal" data-bs-target="#editSaleModal"
            data-id="<?= $s['id'] ?>"
            data-product="<?= $s['product_id'] ?>"
            data-customer="<?= e($s['customer_name']) ?>"
            data-qty="<?= $s['quantity'] ?>"
            data-price="<?= $s['price_per_unit'] ?>"
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

<!-- Record Sale Modal -->
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
          <label class="form-label small fw-semibold">Product</label>
          <select name="product_id" id="sale_product" class="form-select" required onchange="fillSalePrice()">
            <option value="">Select product...</option>
            <?php foreach ($products as $p): ?>
              <option value="<?= $p['id'] ?>" data-price="<?= $p['selling_price'] ?>" data-stock="<?= $p['quantity'] ?>" data-unit="<?= e($p['unit']) ?>">
                <?= e($p['name']) ?> (<?= rtrim(rtrim(number_format($p['quantity'],2),'0'),'.') ?> <?= e($p['unit']) ?> in stock)
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
            <input type="number" step="0.01" name="quantity" class="form-control" required>
          </div>
          <div class="col-6 mb-3">
            <label class="form-label small fw-semibold">Price / Unit (Rs)</label>
            <input type="number" step="0.01" name="price_per_unit" id="sale_price" class="form-control" required>
          </div>
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
          <label class="form-label small fw-semibold">Product</label>
          <select name="product_id" id="edit_sale_product" class="form-select" required onchange="fillEditSalePrice()">
            <option value="">Select product...</option>
            <?php foreach ($products as $p): ?>
              <option value="<?= $p['id'] ?>" data-price="<?= $p['selling_price'] ?>" data-stock="<?= $p['quantity'] ?>" data-unit="<?= e($p['unit']) ?>">
                <?= e($p['name']) ?> (<?= rtrim(rtrim(number_format($p['quantity'],2),'0'),'.') ?> <?= e($p['unit']) ?> in stock)
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
            <input type="number" step="0.01" name="quantity" id="edit_sale_qty" class="form-control" required>
          </div>
          <div class="col-6 mb-3">
            <label class="form-label small fw-semibold">Price / Unit (Rs)</label>
            <input type="number" step="0.01" name="price_per_unit" id="edit_sale_price" class="form-control" required>
          </div>
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

<?php
$extraScripts = '<script>
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
document.getElementById("editSaleModal")?.addEventListener("show.bs.modal", function (event) {
  const btn = event.relatedTarget;
  if (!btn) return;
  document.getElementById("edit_sale_id").value = btn.dataset.id;
  document.getElementById("edit_sale_product").value = btn.dataset.product;
  document.getElementById("edit_sale_customer").value = btn.dataset.customer;
  document.getElementById("edit_sale_qty").value = btn.dataset.qty;
  document.getElementById("edit_sale_price").value = btn.dataset.price;
  document.getElementById("edit_sale_date").value = btn.dataset.date;
});
</script>';
require_once 'includes/footer.php';
?>
