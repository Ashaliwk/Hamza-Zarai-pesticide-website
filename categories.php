<?php
require_once 'includes/auth.php';
require_once 'config/database.php';
require_login();

$pageTitle = 'Categories';
$activeNav = 'categories';

$categories = $pdo->query("SELECT * FROM categories ORDER BY type DESC, name")->fetchAll();

require_once 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-start mb-1">
  <div>
    <h1 class="page-title">Categories</h1>
    <p class="page-subtitle">Manage product categories</p>
  </div>
  <button class="btn btn-brand fw-semibold" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
    <i class="fa-solid fa-plus me-1"></i> Add Category
  </button>
</div>

<div class="row g-3">
  <?php foreach ($categories as $c): ?>
    <div class="col-md-4">
      <div class="cat-card">
        <div class="cat-icon"><i class="fa-solid fa-layer-group"></i></div>
        <div>
          <div class="cat-name"><?= e($c['name']) ?></div>
          <div class="cat-type"><?= $c['type'] === 'default' ? 'Default Category' : 'Custom Category' ?></div>
        </div>
        <?php if ($c['type'] === 'custom'): ?>
          <form method="POST" action="categories_action.php" onsubmit="return confirm('Delete this category?');">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= $c['id'] ?>">
            <button type="submit" class="cat-del" title="Delete"><i class="fa-solid fa-trash"></i></button>
          </form>
        <?php endif; ?>
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
        <h5 class="modal-title fw-bold">Add Category</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <label class="form-label small fw-semibold">Category Name</label>
        <input type="text" name="name" class="form-control" required autofocus placeholder="e.g. Herbicides">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-brand">Save Category</button>
      </div>
    </form>
  </div>
</div>

<?php require_once 'includes/footer.php'; ?>
