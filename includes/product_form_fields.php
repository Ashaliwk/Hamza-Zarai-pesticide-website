<?php
$prefix = $prefix ?? '';
?>
<div class="mb-3">
  <label class="form-label small fw-semibold">Product Name</label>
  <input type="text" name="name" id="<?= $prefix ?>name" class="form-control" required>
</div>
<div class="row">
  <div class="col-6 mb-3">
    <label class="form-label small fw-semibold">SKU / Code</label>
    <input type="text" name="sku" id="<?= $prefix ?>sku" class="form-control">
  </div>
  <div class="col-6 mb-3">
    <label class="form-label small fw-semibold">Category</label>
    <select name="category_id" id="<?= $prefix ?>category_id" class="form-select" required>
      <option value="">Select category...</option>
      <?php foreach ($categories as $c): ?>
        <option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
</div>
<div class="mb-3">
  <div class="d-flex justify-content-between align-items-center mb-1">
    <label class="form-label small fw-semibold mb-0">Subcategory</label>
    <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none text-success small fw-semibold" data-bs-toggle="modal" data-bs-target="#addProductSubCategoryModal">
      <i class="fa-solid fa-plus me-1"></i>Add Subcategory
    </button>
  </div>
  <select name="subcategory_id" id="<?= $prefix ?>subcategory_id" class="form-select">
    <option value="">Select subcategory (Optional)...</option>
    <?php foreach ($subcategories as $sub): ?>
      <option value="<?= $sub['id'] ?>" data-category="<?= $sub['category_id'] ?>">
        <?= e($sub['name']) ?> (<?= e($sub['category_name']) ?>)
      </option>
    <?php endforeach; ?>
  </select>
</div>
<div class="row">
  <div class="col-6 mb-3">
    <label class="form-label small fw-semibold">Purchase Price (Rs)</label>
    <input type="number" step="0.01" name="purchase_price" id="<?= $prefix ?>purchase_price" class="form-control" required>
  </div>
  <div class="col-6 mb-3">
    <label class="form-label small fw-semibold">Selling Price (Rs)</label>
    <input type="number" step="0.01" name="selling_price" id="<?= $prefix ?>selling_price" class="form-control" required>
  </div>
</div>
<div class="row">
  <div class="col-4 mb-3">
    <label class="form-label small fw-semibold">Quantity</label>
    <input type="number" step="0.01" name="quantity" id="<?= $prefix ?>quantity" class="form-control" required>
  </div>
  <div class="col-4 mb-3">
    <label class="form-label small fw-semibold">Unit</label>
    <select name="unit" id="<?= $prefix ?>unit" class="form-select">
      <option value="kg">kg</option>
      <option value="g">g</option>
      <option value="liter">liter</option>
      <option value="ml">ml</option>
      <option value="bag">bag</option>
      <option value="pcs">pcs</option>
    </select>
  </div>
  <div class="col-4 mb-3">
    <label class="form-label small fw-semibold">Low Stock At</label>
    <input type="number" step="0.01" name="low_stock_threshold" id="<?= $prefix ?>low_stock_threshold" class="form-control" value="10">
  </div>
</div>
