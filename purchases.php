<?php
require_once 'includes/auth.php';
require_once 'config/database.php';
require_login();

$pageTitle = 'Purchases';
$activeNav = 'purchases';

$products = $pdo->query("SELECT id, name, purchase_price, unit FROM products ORDER BY name")->fetchAll();

$purchases = $pdo->query("
    SELECT pu.*, p.name AS product_name
    FROM purchases pu JOIN products p ON p.id = pu.product_id
    ORDER BY pu.purchase_date DESC, pu.id DESC
")->fetchAll();

require_once 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-start mb-1">
  <div>
    <h1 class="page-title">Purchases</h1>
    <p class="page-subtitle">Track your purchase transactions</p>
  </div>
  <button class="btn btn-brand fw-semibold" data-bs-toggle="modal" data-bs-target="#addPurchaseModal">
    <i class="fa-solid fa-plus me-1"></i> Record Purchase
  </button>
</div>

<div class="panel">
  <div class="table-responsive">
  <table class="table table-clean mb-0">
    <thead>
      <tr>
        <th>Date</th>
        <th>Product</th>
        <th>Supplier</th>
        <th>Quantity</th>
        <th>Price/Unit</th>
        <th>Total</th>
        <th class="text-end">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$purchases): ?>
        <tr><td colspan="7" class="text-center text-muted py-4">No purchases recorded yet.</td></tr>
      <?php endif; ?>
      <?php foreach ($purchases as $p): ?>
      <tr>
        <td><?= date('n/j/Y', strtotime($p['purchase_date'])) ?></td>
        <td><?= e($p['product_name']) ?></td>
        <td><?= e($p['supplier_name']) ?></td>
        <td><?= rtrim(rtrim(number_format($p['quantity'],2), '0'), '.') ?></td>
        <td><?= money($p['price_per_unit']) ?></td>
        <td class="fw-bold"><?= money($p['total']) ?></td>
        <td class="text-end">
          <button class="icon-btn edit me-1" title="Edit"
            data-bs-toggle="modal" data-bs-target="#editPurchaseModal"
            data-id="<?= $p['id'] ?>"
            data-product="<?= $p['product_id'] ?>"
            data-supplier="<?= e($p['supplier_name']) ?>"
            data-qty="<?= $p['quantity'] ?>"
            data-price="<?= $p['price_per_unit'] ?>"
            data-date="<?= $p['purchase_date'] ?>">
            <i class="fa-solid fa-pen"></i>
          </button>
          <form method="POST" action="purchases_action.php" class="d-inline" onsubmit="return confirm('Delete this purchase? Stock will be adjusted.');">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= $p['id'] ?>">
            <button type="submit" class="icon-btn del" title="Delete"><i class="fa-solid fa-trash"></i></button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>

<!-- Record Purchase Modal -->
<div class="modal fade" id="addPurchaseModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" action="purchases_action.php" class="modal-content">
      <input type="hidden" name="action" value="add">
      <div class="modal-header">
        <h5 class="modal-title fw-bold">Record Purchase</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label small fw-semibold">Product</label>
          <select name="product_id" class="form-select" required>
            <option value="">Select product...</option>
            <?php foreach ($products as $p): ?>
              <option value="<?= $p['id'] ?>"><?= e($p['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label small fw-semibold">Supplier Name</label>
          <input type="text" name="supplier_name" class="form-control" required>
        </div>
        <div class="row">
          <div class="col-6 mb-3">
            <label class="form-label small fw-semibold">Quantity</label>
            <input type="number" step="0.01" name="quantity" class="form-control" required>
          </div>
          <div class="col-6 mb-3">
            <label class="form-label small fw-semibold">Price / Unit (Rs)</label>
            <input type="number" step="0.01" name="price_per_unit" class="form-control" required>
          </div>
        </div>
        <div class="mb-1">
          <label class="form-label small fw-semibold">Purchase Date</label>
          <input type="date" name="purchase_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-brand">Save Purchase</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Purchase Modal -->
<div class="modal fade" id="editPurchaseModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" action="purchases_action.php" class="modal-content">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="id" id="edit_purchase_id">
      <div class="modal-header">
        <h5 class="modal-title fw-bold">Edit Purchase</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label small fw-semibold">Product</label>
          <select name="product_id" id="edit_purchase_product" class="form-select" required>
            <option value="">Select product...</option>
            <?php foreach ($products as $p): ?>
              <option value="<?= $p['id'] ?>"><?= e($p['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label small fw-semibold">Supplier Name</label>
          <input type="text" name="supplier_name" id="edit_purchase_supplier" class="form-control" required>
        </div>
        <div class="row">
          <div class="col-6 mb-3">
            <label class="form-label small fw-semibold">Quantity</label>
            <input type="number" step="0.01" name="quantity" id="edit_purchase_qty" class="form-control" required>
          </div>
          <div class="col-6 mb-3">
            <label class="form-label small fw-semibold">Price / Unit (Rs)</label>
            <input type="number" step="0.01" name="price_per_unit" id="edit_purchase_price" class="form-control" required>
          </div>
        </div>
        <div class="mb-1">
          <label class="form-label small fw-semibold">Purchase Date</label>
          <input type="date" name="purchase_date" id="edit_purchase_date" class="form-control" required>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-brand">Update Purchase</button>
      </div>
    </form>
  </div>
</div>

<?php
$extraScripts = '<script>
document.getElementById("editPurchaseModal")?.addEventListener("show.bs.modal", function (event) {
  const btn = event.relatedTarget;
  if (!btn) return;
  document.getElementById("edit_purchase_id").value = btn.dataset.id;
  document.getElementById("edit_purchase_product").value = btn.dataset.product;
  document.getElementById("edit_purchase_supplier").value = btn.dataset.supplier;
  document.getElementById("edit_purchase_qty").value = btn.dataset.qty;
  document.getElementById("edit_purchase_price").value = btn.dataset.price;
  document.getElementById("edit_purchase_date").value = btn.dataset.date;
});
</script>';
require_once 'includes/footer.php';
?>
