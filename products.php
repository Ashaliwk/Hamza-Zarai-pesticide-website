<?php
require_once 'includes/auth.php';
require_once 'config/database.php';
require_login();

$pageTitle = 'Products';
$activeNav = 'products';

$search = trim($_GET['search'] ?? '');

$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();

if ($search !== '') {
    $stmt = $pdo->prepare("
        SELECT p.*, c.name AS category_name
        FROM products p
        JOIN categories c ON c.id = p.category_id
        WHERE p.name LIKE :s OR p.sku LIKE :s OR c.name LIKE :s
        ORDER BY p.created_at DESC
    ");
    $stmt->execute([':s' => "%$search%"]);
    $products = $stmt->fetchAll();
} else {
    $products = $pdo->query("
        SELECT p.*, c.name AS category_name
        FROM products p
        JOIN categories c ON c.id = p.category_id
        ORDER BY p.created_at DESC
    ")->fetchAll();
}

require_once 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-start mb-1">
  <div>
    <h1 class="page-title">Products</h1>
    <p class="page-subtitle">Manage your product inventory</p>
  </div>
  <button class="btn btn-brand fw-semibold" data-bs-toggle="modal" data-bs-target="#addProductModal">
    <i class="fa-solid fa-plus me-1"></i> Add Product
  </button>
</div>

<div class="panel">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div class="search-box position-relative" style="max-width: 320px; width: 100%;">
      <i class="fa-solid fa-magnifying-glass search-icon"></i>
      <input type="text" id="productSearch" class="form-control search-input" placeholder="Search product or category..." value="<?= e($search) ?>" autocomplete="off">
      <button class="search-clear-btn" id="clearSearch" type="button" style="display: <?= $search !== '' ? 'flex' : 'none' ?>;"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="text-muted small" id="searchCount">
      <?= count($products) ?> product<?= count($products) === 1 ? '' : 's' ?> found
    </div>
  </div>
  <div class="table-responsive">
  <table class="table table-clean mb-0">
    <thead>
      <tr>
        <th>Product</th>
        <th>Category</th>
        <th>Purchase Price</th>
        <th>Selling Price</th>
        <th>Quantity</th>
        <th>Profit Margin</th>
        <th class="text-end">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$products): ?>
        <tr><td colspan="7" class="text-center text-muted py-4">No products found.</td></tr>
      <?php endif; ?>
      <tr id="noMatchRow" style="display:none;"><td colspan="7" class="text-center text-muted py-4">No matching products found.</td></tr>
      <?php foreach ($products as $p):
          $margin = $p['purchase_price'] > 0
              ? (($p['selling_price'] - $p['purchase_price']) / $p['purchase_price']) * 100
              : 0;
          $isLow = $p['quantity'] <= $p['low_stock_threshold'];
      ?>
      <tr class="product-row">
        <td>
          <div class="fw-semibold"><?= e($p['name']) ?></div>
          <div class="text-muted small"><?= e($p['sku']) ?></div>
        </td>
        <td><span class="badge-cat"><?= e($p['category_name']) ?></span></td>
        <td><?= money($p['purchase_price']) ?></td>
        <td><?= money($p['selling_price']) ?></td>
        <td class="<?= $isLow ? 'qty-low' : '' ?>"><?= rtrim(rtrim(number_format($p['quantity'],2), '0'), '.') ?> <?= e($p['unit']) ?></td>
        <td class="<?= $margin >= 0 ? 'profit-pos' : 'profit-neg' ?>"><?= number_format($margin, 1) ?>%</td>
        <td class="text-end">
          <button class="icon-btn edit" title="Edit"
            data-bs-toggle="modal" data-bs-target="#editProductModal"
            data-id="<?= $p['id'] ?>"
            data-name="<?= e($p['name']) ?>"
            data-sku="<?= e($p['sku']) ?>"
            data-category="<?= $p['category_id'] ?>"
            data-unit="<?= e($p['unit']) ?>"
            data-purchase="<?= $p['purchase_price'] ?>"
            data-selling="<?= $p['selling_price'] ?>"
            data-qty="<?= $p['quantity'] ?>"
            data-threshold="<?= $p['low_stock_threshold'] ?>">
            <i class="fa-solid fa-pen"></i>
          </button>
          <form method="POST" action="products_action.php" class="d-inline" onsubmit="return confirm('Delete this product?');">
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

<!-- Add Product Modal -->
<div class="modal fade" id="addProductModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" action="products_action.php" class="modal-content">
      <input type="hidden" name="action" value="add">
      <div class="modal-header">
        <h5 class="modal-title fw-bold">Add Product</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <?php $prefix = ''; include 'includes/product_form_fields.php'; ?>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-brand">Save Product</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Product Modal -->
<div class="modal fade" id="editProductModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" action="products_action.php" class="modal-content" id="editProductForm">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="id" id="edit_id">
      <div class="modal-header">
        <h5 class="modal-title fw-bold">Edit Product</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <?php $prefix = 'edit_'; include 'includes/product_form_fields.php'; ?>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-brand">Update Product</button>
      </div>
    </form>
  </div>
</div>

<?php
$extraScripts = '<script>
document.getElementById("editProductModal").addEventListener("show.bs.modal", function (event) {
  const btn = event.relatedTarget;
  document.getElementById("edit_id").value = btn.dataset.id;
  document.getElementById("edit_name").value = btn.dataset.name;
  document.getElementById("edit_sku").value = btn.dataset.sku;
  document.getElementById("edit_category_id").value = btn.dataset.category;
  document.getElementById("edit_unit").value = btn.dataset.unit;
  document.getElementById("edit_purchase_price").value = btn.dataset.purchase;
  document.getElementById("edit_selling_price").value = btn.dataset.selling;
  document.getElementById("edit_quantity").value = btn.dataset.qty;
  document.getElementById("edit_low_stock_threshold").value = btn.dataset.threshold;
});

const searchInput = document.getElementById("productSearch");
const clearBtn = document.getElementById("clearSearch");
const tableRows = document.querySelectorAll("tbody tr.product-row");
const searchCount = document.getElementById("searchCount");
const noMatchRow = document.getElementById("noMatchRow");

function filterProducts() {
  const query = searchInput.value.toLowerCase().trim();
  clearBtn.style.display = query.length > 0 ? "flex" : "none";

  let visibleCount = 0;
  tableRows.forEach(row => {
    const text = row.innerText.toLowerCase();
    if (text.includes(query)) {
      row.style.display = "";
      visibleCount++;
    } else {
      row.style.display = "none";
    }
  });

  if (searchCount) {
    searchCount.textContent = visibleCount + (visibleCount === 1 ? " product found" : " products found");
  }

  if (noMatchRow) {
    noMatchRow.style.display = (visibleCount === 0 && tableRows.length > 0) ? "" : "none";
  }
}

if (searchInput) {
  searchInput.addEventListener("input", filterProducts);
  clearBtn.addEventListener("click", function() {
    searchInput.value = "";
    filterProducts();
    searchInput.focus();
  });
}
</script>';
require_once 'includes/footer.php';
?>

