<?php
require_once 'includes/auth.php';
require_once 'config/database.php';
require_login();

$pageTitle = 'Purchases';
$activeNav = 'purchases';

// Ensure subcategory_id & unit columns exist in purchases table
try {
    $pdo->exec("ALTER TABLE purchases ADD COLUMN subcategory_id INT NULL AFTER product_id");
} catch (PDOException $e) {}
try {
    $pdo->exec("ALTER TABLE purchases ADD COLUMN unit VARCHAR(20) NULL AFTER quantity");
} catch (PDOException $e) {}

$search = trim($_GET['search'] ?? '');

$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();

$subcategories = $pdo->query("
    SELECT s.*, c.name AS category_name
    FROM subcategories s
    JOIN categories c ON c.id = s.category_id
    ORDER BY c.name, s.name
")->fetchAll();

$products = $pdo->query("SELECT id, name, purchase_price, unit, category_id, subcategory_id FROM products ORDER BY name")->fetchAll();

if ($search !== '') {
    $stmt = $pdo->prepare("
        SELECT pu.*, p.name AS product_name, COALESCE(pu.unit, p.unit) AS product_unit, sc.name AS subcategory_name, c.name AS category_name
        FROM purchases pu
        JOIN products p ON p.id = pu.product_id
        LEFT JOIN subcategories sc ON sc.id = pu.subcategory_id
        LEFT JOIN categories c ON c.id = p.category_id
        WHERE p.name LIKE :s OR pu.supplier_name LIKE :s OR pu.purchase_date LIKE :s OR sc.name LIKE :s OR c.name LIKE :s
        ORDER BY pu.purchase_date DESC, pu.id DESC
    ");
    $stmt->execute([':s' => "%$search%"]);
    $purchases = $stmt->fetchAll();
} else {
    $purchases = $pdo->query("
        SELECT pu.*, p.name AS product_name, COALESCE(pu.unit, p.unit) AS product_unit, sc.name AS subcategory_name, c.name AS category_name
        FROM purchases pu
        JOIN products p ON p.id = pu.product_id
        LEFT JOIN subcategories sc ON sc.id = pu.subcategory_id
        LEFT JOIN categories c ON c.id = p.category_id
        ORDER BY pu.purchase_date DESC, pu.id DESC
    ")->fetchAll();
}

require_once 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
  <div>
    <h1 class="page-title">Purchases</h1>
    <p class="page-subtitle">Track your purchase transactions & subcategories</p>
  </div>
  <div class="d-flex gap-2">
    <!-- <button class="btn btn-outline-success fw-semibold" data-bs-toggle="modal" data-bs-target="#addPurchaseSubCategoryModal">
      <i class="fa-solid fa-folder-plus me-1"></i> Add Subcategory
    </button> -->
    <button class="btn btn-brand fw-semibold" data-bs-toggle="modal" data-bs-target="#addPurchaseModal">
      <i class="fa-solid fa-plus me-1"></i> Record Purchase
    </button>
  </div>
</div>

<div class="panel">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div class="search-box position-relative" style="max-width: 320px; width: 100%;">
      <i class="fa-solid fa-magnifying-glass search-icon"></i>
      <input type="text" id="purchasesSearch" class="form-control search-input" placeholder="Search product, subcategory, supplier, date..." value="<?= e($search) ?>" autocomplete="off">
      <button class="search-clear-btn" id="clearPurchasesSearch" type="button" style="display: <?= $search !== '' ? 'flex' : 'none' ?>;"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="text-muted small" id="purchasesCount">
      <?= count($purchases) ?> purchase<?= count($purchases) === 1 ? '' : 's' ?> found
    </div>
  </div>
  <div class="table-responsive">
  <table class="table table-clean mb-0">
    <thead>
      <tr>
        <th>Date</th>
        <th>Subcategory</th>
        <th>Category</th>
        <th>Supplier</th>
        <th>Quantity</th>
        <th>Price/Unit</th>
        <th>Total</th>
        <th class="text-end">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$purchases): ?>
        <tr><td colspan="8" class="text-center text-muted py-4">No purchases found.</td></tr>
      <?php endif; ?>
      <tr id="noMatchPurchasesRow" style="display:none;"><td colspan="8" class="text-center text-muted py-4">No matching purchases recorded.</td></tr>
      <?php foreach ($purchases as $p): ?>
      <tr class="purchase-row">
        <td><?= date('n/j/Y', strtotime($p['purchase_date'])) ?></td>
        <td class="fw-bold text-dark">
          <?php if (!empty($p['subcategory_name'])): ?>
            <span class="badge bg-success-subtle text-success-emphasis border border-success-subtle fw-semibold px-2 py-1" style="font-size:0.85rem;">
              <i class="fa-solid fa-tag me-1 text-success" style="font-size:0.75rem;"></i><?= e($p['subcategory_name']) ?>
            </span>
          <?php else: ?>
            <span class="fw-semibold text-dark"><?= e($p['product_name']) ?></span>
          <?php endif; ?>
        </td>
        <td>
          <span class="badge-cat"><?= e($p['category_name'] ?: 'General') ?></span>
        </td>
        <td><?= e($p['supplier_name']) ?></td>
        <td><?= rtrim(rtrim(number_format($p['quantity'],2), '0'), '.') ?> <?= e($p['product_unit'] ?? '') ?></td>
        <td><?= money($p['price_per_unit']) ?></td>
        <td class="fw-bold"><?= money($p['total']) ?></td>
        <td class="text-end">
          <button class="icon-btn edit me-1" title="Edit"
            data-bs-toggle="modal" data-bs-target="#editPurchaseModal"
            data-id="<?= $p['id'] ?>"
            data-product-name="<?= e($p['product_name']) ?>"
            data-category="<?= $p['category_id'] ?? '' ?>"
            data-subcategory="<?= $p['subcategory_id'] ?? '' ?>"
            data-supplier="<?= e($p['supplier_name']) ?>"
            data-qty="<?= $p['quantity'] ?>"
            data-unit="<?= e($p['product_unit'] ?? '') ?>"
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

<!-- Datalist for Existing Products -->
<datalist id="existing_products_list">
  <?php foreach ($products as $p): ?>
    <option value="<?= e($p['name']) ?>" data-category="<?= $p['category_id'] ?>" data-subcategory="<?= $p['subcategory_id'] ?? '' ?>" data-unit="<?= e($p['unit']) ?>" data-price="<?= $p['purchase_price'] ?>"></option>
  <?php endforeach; ?>
</datalist>

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
          <label class="form-label small fw-semibold">Category</label>
          <select name="category_id" id="add_purchase_category" class="form-select">
            <option value="">-- Select Category --</option>
            <?php foreach ($categories as $c): ?>
              <option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mb-3">
          <div class="d-flex justify-content-between align-items-center mb-1">
            <label class="form-label small fw-semibold mb-0">Subcategory</label>
            <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none text-success small fw-semibold" data-bs-toggle="modal" data-bs-target="#addPurchaseSubCategoryModal">
              <i class="fa-solid fa-plus me-1"></i>New Subcategory
            </button>
          </div>
          <select name="subcategory_id" id="add_purchase_subcategory" class="form-select" required>
            <option value="">-- Select Subcategory --</option>
            <?php foreach ($subcategories as $sub): ?>
              <option value="<?= $sub['id'] ?>" data-category="<?= $sub['category_id'] ?>">
                <?= e($sub['name']) ?> (<?= e($sub['category_name']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label small fw-semibold">Supplier Name</label>
          <input type="text" name="supplier_name" class="form-control" required>
        </div>
        <div class="row">
          <div class="col-4 mb-3">
            <label class="form-label small fw-semibold">Quantity</label>
            <input type="number" step="0.01" name="quantity" id="add_purchase_qty" class="form-control" required placeholder="0.00">
          </div>
          <div class="col-4 mb-3">
            <label class="form-label small fw-semibold">Unit</label>
            <select name="unit" id="add_purchase_unit" class="form-select" required>
              <option value="kg">kg</option>
              <option value="g">g</option>
              <option value="liter">liter</option>
              <option value="ml">ml</option>
              <option value="bag">bag</option>
              <option value="pcs">pcs</option>
            </select>
          </div>
          <div class="col-4 mb-3">
            <label class="form-label small fw-semibold">Price / Unit (Rs)</label>
            <input type="number" step="0.01" name="price_per_unit" id="add_purchase_price" class="form-control" required placeholder="0.00">
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
          <label class="form-label small fw-semibold">Category</label>
          <select name="category_id" id="edit_purchase_category" class="form-select">
            <option value="">-- Select Category --</option>
            <?php foreach ($categories as $c): ?>
              <option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mb-3">
          <div class="d-flex justify-content-between align-items-center mb-1">
            <label class="form-label small fw-semibold mb-0">Subcategory</label>
            <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none text-success small fw-semibold" data-bs-toggle="modal" data-bs-target="#addPurchaseSubCategoryModal">
              <i class="fa-solid fa-plus me-1"></i>New Subcategory
            </button>
          </div>
          <select name="subcategory_id" id="edit_purchase_subcategory" class="form-select" required>
            <option value="">-- Select Subcategory --</option>
            <?php foreach ($subcategories as $sub): ?>
              <option value="<?= $sub['id'] ?>" data-category="<?= $sub['category_id'] ?>">
                <?= e($sub['name']) ?> (<?= e($sub['category_name']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label small fw-semibold">Supplier Name</label>
          <input type="text" name="supplier_name" id="edit_purchase_supplier" class="form-control" required>
        </div>
        <div class="row">
          <div class="col-4 mb-3">
            <label class="form-label small fw-semibold">Quantity</label>
            <input type="number" step="0.01" name="quantity" id="edit_purchase_qty" class="form-control" required>
          </div>
          <div class="col-4 mb-3">
            <label class="form-label small fw-semibold">Unit</label>
            <select name="unit" id="edit_purchase_unit" class="form-select" required>
              <option value="kg">kg</option>
              <option value="g">g</option>
              <option value="liter">liter</option>
              <option value="ml">ml</option>
              <option value="bag">bag</option>
              <option value="pcs">pcs</option>
            </select>
          </div>
          <div class="col-4 mb-3">
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

<!-- Add Subcategory Modal -->
<div class="modal fade" id="addPurchaseSubCategoryModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" action="purchases_action.php" class="modal-content">
      <input type="hidden" name="action" value="add_subcategory">
      <div class="modal-header">
        <h5 class="modal-title fw-bold"><i class="fa-solid fa-folder-plus me-2 text-success"></i>Add Subcategory</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label small fw-semibold">Parent Category</label>
          <select name="category_id" class="form-select" required>
            <option value="">Select parent category...</option>
            <?php foreach ($categories as $c): ?>
              <option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mb-2">
          <label class="form-label small fw-semibold">Subcategory Name</label>
          <input type="text" name="subcategory_name" class="form-control" required placeholder="e.g. Organic Manure, Hybrid Seeds">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-brand">Save Subcategory</button>
      </div>
    </form>
  </div>
</div>

<?php
$extraScripts = '<script>
function setupPurchaseCategorySubFilter(catId, subId) {
  const catSelect = document.getElementById(catId);
  const subSelect = document.getElementById(subId);
  if (!catSelect || !subSelect) return;

  function filterSub() {
    const selectedCat = catSelect.value;
    Array.from(subSelect.options).forEach(opt => {
      if (!opt.value) return;
      const optCat = opt.getAttribute("data-category");
      if (!selectedCat || optCat === selectedCat) {
        opt.hidden = false;
        opt.disabled = false;
        opt.style.display = "";
      } else {
        opt.hidden = true;
        opt.disabled = true;
        opt.style.display = "none";
      }
    });

    const curSubOpt = subSelect.options[subSelect.selectedIndex];
    if (curSubOpt && curSubOpt.value && selectedCat && curSubOpt.getAttribute("data-category") !== selectedCat) {
      subSelect.value = "";
    }
  }

  catSelect.addEventListener("change", filterSub);
  filterSub();
}

setupPurchaseCategorySubFilter("add_purchase_category", "add_purchase_subcategory");
setupPurchaseCategorySubFilter("edit_purchase_category", "edit_purchase_subcategory");

function setupProductNameAutofill(prodNameId, catId, subId, unitId, priceId) {
  const nameInput = document.getElementById(prodNameId);
  const catSelect = document.getElementById(catId);
  const subSelect = document.getElementById(subId);
  const unitSelect = document.getElementById(unitId);
  const priceInput = document.getElementById(priceId);
  const datalist = document.getElementById("existing_products_list");

  if (!nameInput || !datalist) return;

  nameInput.addEventListener("input", function() {
    const val = nameInput.value.trim().toLowerCase();
    if (!val) return;

    const options = Array.from(datalist.options);
    const matchedOpt = options.find(opt => opt.value.toLowerCase() === val);
    if (matchedOpt) {
      const cat = matchedOpt.getAttribute("data-category");
      const sub = matchedOpt.getAttribute("data-subcategory");
      const unit = matchedOpt.getAttribute("data-unit");
      const price = matchedOpt.getAttribute("data-price");

      if (catSelect && cat) {
        catSelect.value = cat;
        catSelect.dispatchEvent(new Event("change"));
      }
      if (subSelect && sub) {
        subSelect.value = sub;
      }
      if (unitSelect && unit) {
        unitSelect.value = unit;
      }
      if (priceInput && price && !priceInput.value) {
        priceInput.value = price;
      }
    }
  });
}

setupProductNameAutofill("add_purchase_product_name", "add_purchase_category", "add_purchase_subcategory", "add_purchase_unit", "add_purchase_price");
setupProductNameAutofill("edit_purchase_product_name", "edit_purchase_category", "edit_purchase_subcategory", "edit_purchase_unit", "edit_purchase_price");

document.getElementById("editPurchaseModal")?.addEventListener("show.bs.modal", function (event) {
  const btn = event.relatedTarget;
  if (!btn) return;
  document.getElementById("edit_purchase_id").value = btn.dataset.id;
  document.getElementById("edit_purchase_product_name").value = btn.dataset.productName || "";
  const catSelect = document.getElementById("edit_purchase_category");
  if (catSelect && btn.dataset.category) {
    catSelect.value = btn.dataset.category;
    catSelect.dispatchEvent(new Event("change"));
  }
  if (btn.dataset.subcategory) {
    document.getElementById("edit_purchase_subcategory").value = btn.dataset.subcategory;
  }
  document.getElementById("edit_purchase_supplier").value = btn.dataset.supplier || "";
  document.getElementById("edit_purchase_qty").value = btn.dataset.qty || "";
  if (btn.dataset.unit) {
    document.getElementById("edit_purchase_unit").value = btn.dataset.unit;
  }
  document.getElementById("edit_purchase_price").value = btn.dataset.price || "";
  document.getElementById("edit_purchase_date").value = btn.dataset.date || "";
});

const purchasesInput = document.getElementById("purchasesSearch");
const clearPurchasesBtn = document.getElementById("clearPurchasesSearch");
const purchaseRows = document.querySelectorAll("tbody tr.purchase-row");
const purchasesCount = document.getElementById("purchasesCount");
const noMatchPurchasesRow = document.getElementById("noMatchPurchasesRow");

function filterPurchases() {
  const query = purchasesInput.value.toLowerCase().trim();
  clearPurchasesBtn.style.display = query.length > 0 ? "flex" : "none";

  let visibleCount = 0;
  purchaseRows.forEach(row => {
    const text = row.innerText.toLowerCase();
    if (text.includes(query)) {
      row.style.display = "";
      visibleCount++;
    } else {
      row.style.display = "none";
    }
  });

  if (purchasesCount) {
    purchasesCount.textContent = visibleCount + (visibleCount === 1 ? " purchase found" : " purchases found");
  }

  if (noMatchPurchasesRow) {
    noMatchPurchasesRow.style.display = (visibleCount === 0 && purchaseRows.length > 0) ? "" : "none";
  }
}

if (purchasesInput) {
  purchasesInput.addEventListener("input", filterPurchases);
  clearPurchasesBtn.addEventListener("click", function() {
    purchasesInput.value = "";
    filterPurchases();
    purchasesInput.focus();
  });
}
</script>';
require_once 'includes/footer.php';
?>
