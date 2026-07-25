<?php
require_once 'includes/auth.php';
require_once 'config/database.php';
require_login();

$pageTitle = 'Categories';
$activeNav = 'categories';

// Ensure subcategories table exists
$pdo->exec("
    CREATE TABLE IF NOT EXISTS subcategories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        category_id INT NOT NULL,
        name VARCHAR(100) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// Seed default subcategories if table is empty
$subCount = $pdo->query("SELECT COUNT(*) FROM subcategories")->fetchColumn();
if ($subCount == 0) {
    $fertId = $pdo->query("SELECT id FROM categories WHERE name = 'Fertilizers'")->fetchColumn();
    $sprayId = $pdo->query("SELECT id FROM categories WHERE name = 'Sprays'")->fetchColumn();
    $seedId = $pdo->query("SELECT id FROM categories WHERE name = 'Seeds'")->fetchColumn();
    $insectId = $pdo->query("SELECT id FROM categories WHERE name = 'Insecticides'")->fetchColumn();
    $pestId = $pdo->query("SELECT id FROM categories WHERE name = 'Pesticides'")->fetchColumn();

    $stmtInsertSub = $pdo->prepare("INSERT INTO subcategories (category_id, name) VALUES (?, ?)");
    if ($fertId) {
        $stmtInsertSub->execute([$fertId, 'Nitrogenous Fertilizers']);
        $stmtInsertSub->execute([$fertId, 'Phosphate Fertilizers']);
        $stmtInsertSub->execute([$fertId, 'Organic Manure']);
    }
    if ($sprayId) {
        $stmtInsertSub->execute([$sprayId, 'Fungicide Spray']);
        $stmtInsertSub->execute([$sprayId, 'Weedicide Spray']);
    }
    if ($seedId) {
        $stmtInsertSub->execute([$seedId, 'Hybrid Seeds']);
        $stmtInsertSub->execute([$seedId, 'Crop Seeds']);
    }
    if ($insectId) {
        $stmtInsertSub->execute([$insectId, 'Liquid Insecticide']);
        $stmtInsertSub->execute([$insectId, 'Powder Insecticide']);
    }
    if ($pestId) {
        $stmtInsertSub->execute([$pestId, 'Systemic Pesticide']);
    }
}

$categories = $pdo->query("SELECT * FROM categories ORDER BY type DESC, name")->fetchAll();

$rawSubcategories = $pdo->query("
    SELECT s.*, c.name AS category_name 
    FROM subcategories s
    JOIN categories c ON c.id = s.category_id
    ORDER BY s.name
")->fetchAll();

$subcatsByCat = [];
foreach ($rawSubcategories as $sub) {
    $subcatsByCat[$sub['category_id']][] = $sub;
}

$search = trim($_GET['search'] ?? '');

require_once 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
  <div>
    <h1 class="page-title">Categories & Subcategories</h1>
    <p class="page-subtitle">Manage product categories and subcategories</p>
  </div>
  <div class="d-flex gap-2">
    <button class="btn btn-outline-success fw-semibold" data-bs-toggle="modal" data-bs-target="#addSubCategoryModal">
      <i class="fa-solid fa-folder-plus me-1"></i> Add Subcategory
    </button>
    <button class="btn btn-brand fw-semibold" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
      <i class="fa-solid fa-plus me-1"></i> Add Category
    </button>
  </div>
</div>

<div class="panel mb-4">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
    <div class="search-box position-relative" style="max-width: 360px; width: 100%;">
      <i class="fa-solid fa-magnifying-glass search-icon"></i>
      <input type="text" id="categorySearch" class="form-control search-input" placeholder="Search category or subcategory name..." value="<?= e($search) ?>" autocomplete="off">
      <button class="search-clear-btn" id="clearCategorySearch" type="button" style="display: <?= $search !== '' ? 'flex' : 'none' ?>;"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="text-muted small" id="categoryCount">
      <?= count($categories) ?> category<?= count($categories) === 1 ? '' : 'ies' ?> found
    </div>
  </div>
</div>

<div class="row g-3" id="categoriesGrid">
  <?php if (!$categories): ?>
    <div class="col-12 text-center text-muted py-4">No categories found.</div>
  <?php endif; ?>
  <div id="noMatchCategory" class="col-12 text-center text-muted py-4" style="display:none;">No matching categories or subcategories found.</div>
  
  <?php foreach ($categories as $c): 
      $catSubs = $subcatsByCat[$c['id']] ?? [];
  ?>
    <div class="col-md-6 col-lg-4 category-item">
      <div class="panel h-100 d-flex flex-column justify-content-between border shadow-sm rounded-3 p-3">
        <div>
          <div class="d-flex justify-content-between align-items-center mb-2">
            <div class="d-flex align-items-center gap-2">
              <div class="cat-icon me-1"><i class="fa-solid fa-layer-group"></i></div>
              <div>
                <h5 class="cat-name mb-0 fw-bold"><?= e($c['name']) ?></h5>
                <span class="badge bg-light text-secondary border fw-normal" style="font-size:0.7rem;">
                  <?= $c['type'] === 'default' ? 'Default Category' : 'Custom Category' ?>
                </span>
              </div>
            </div>
            <?php if ($c['type'] === 'custom'): ?>
              <form method="POST" action="categories_action.php" onsubmit="return confirm('Delete category <?= e($c['name']) ?>?');" class="d-inline">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= $c['id'] ?>">
                <button type="submit" class="btn btn-sm btn-outline-danger border-0" title="Delete Category"><i class="fa-solid fa-trash"></i></button>
              </form>
            <?php endif; ?>
          </div>

          <hr class="my-2 text-muted opacity-25">

          <!-- Subcategories List -->
          <div class="mt-2">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <span class="text-muted small fw-bold text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.04em;">Subcategories (<?= count($catSubs) ?>)</span>
            </div>
            
            <div class="subcategories-list d-flex flex-wrap gap-1 mb-2">
              <?php if (empty($catSubs)): ?>
                <span class="text-muted small fst-italic">No subcategories yet</span>
              <?php else: ?>
                <?php foreach ($catSubs as $sub): ?>
                  <span class="badge bg-success-subtle text-success-emphasis border border-success-subtle fw-semibold d-inline-flex align-items-center gap-1 py-1 px-2 mb-1" style="font-size: 0.82rem;">
                    <i class="fa-solid fa-tag text-success" style="font-size:0.7rem;"></i>
                    <span class="sub-name"><?= e($sub['name']) ?></span>
                    <form method="POST" action="categories_action.php" class="d-inline" onsubmit="return confirm('Delete subcategory <?= e($sub['name']) ?>?');">
                      <input type="hidden" name="action" value="delete_subcategory">
                      <input type="hidden" name="id" value="<?= $sub['id'] ?>">
                      <button type="submit" class="border-0 bg-transparent p-0 ms-1 text-danger-emphasis opacity-75" title="Remove subcategory" style="line-height: 1;">
                        <i class="fa-solid fa-xmark" style="font-size:0.75rem;"></i>
                      </button>
                    </form>
                  </span>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <div class="pt-2 mt-auto">
          <button type="button" 
                  class="btn btn-sm btn-light w-100 border text-success fw-semibold text-start d-flex justify-content-between align-items-center"
                  onclick="openAddSubModal(<?= $c['id'] ?>, '<?= e(addslashes($c['name'])) ?>')">
            <span><i class="fa-solid fa-plus me-1"></i> Add Subcategory</span>
            <i class="fa-solid fa-chevron-right small text-muted"></i>
          </button>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<!-- Add Category Modal -->
<div class="modal fade" id="addCategoryModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" action="categories_action.php" class="modal-content">
      <input type="hidden" name="action" value="add">
      <div class="modal-header">
        <h5 class="modal-title fw-bold"><i class="fa-solid fa-layer-group me-2 text-success"></i>Add Category</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label small fw-semibold">Category Name</label>
          <input type="text" name="name" class="form-control" required autofocus placeholder="e.g. Herbicides">
        </div>
        <div class="mb-2">
          <label class="form-label small fw-semibold">Optional Initial Subcategory Name</label>
          <input type="text" name="subcategory_name" class="form-control" placeholder="e.g. Selective Herbicides">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-brand">Save Category</button>
      </div>
    </form>
  </div>
</div>

<!-- Add Subcategory Modal -->
<div class="modal fade" id="addSubCategoryModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" action="categories_action.php" class="modal-content">
      <input type="hidden" name="action" value="add_subcategory">
      <div class="modal-header">
        <h5 class="modal-title fw-bold"><i class="fa-solid fa-folder-plus me-2 text-success"></i>Add Subcategory</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label small fw-semibold">Parent Category</label>
          <select name="category_id" id="subcat_parent_select" class="form-select" required>
            <option value="">Select category...</option>
            <?php foreach ($categories as $c): ?>
              <option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mb-2">
          <label class="form-label small fw-semibold">Subcategory Name</label>
          <input type="text" name="subcategory_name" id="subcat_name_input" class="form-control" required placeholder="e.g. Bio Fertilizers">
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
function openAddSubModal(catId, catName) {
  const select = document.getElementById("subcat_parent_select");
  if (select) {
    select.value = catId;
  }
  const modal = new bootstrap.Modal(document.getElementById("addSubCategoryModal"));
  modal.show();
}

const catInput = document.getElementById("categorySearch");
const clearCatBtn = document.getElementById("clearCategorySearch");
const catItems = document.querySelectorAll(".category-item");
const catCount = document.getElementById("categoryCount");
const noMatchCat = document.getElementById("noMatchCategory");

function filterCategories() {
  const query = catInput.value.toLowerCase().trim();
  clearCatBtn.style.display = query.length > 0 ? "flex" : "none";

  let visibleCount = 0;
  catItems.forEach(item => {
    const text = item.innerText.toLowerCase();
    if (text.includes(query)) {
      item.style.display = "";
      visibleCount++;
    } else {
      item.style.display = "none";
    }
  });

  if (catCount) {
    catCount.textContent = visibleCount + (visibleCount === 1 ? " category found" : " categories found");
  }

  if (noMatchCat) {
    noMatchCat.style.display = (visibleCount === 0 && catItems.length > 0) ? "block" : "none";
  }
}

if (catInput) {
  catInput.addEventListener("input", filterCategories);
  clearCatBtn.addEventListener("click", function() {
    catInput.value = "";
    filterCategories();
    catInput.focus();
  });
}
</script>';
require_once 'includes/footer.php';
?>


