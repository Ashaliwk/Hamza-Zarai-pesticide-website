<?php
require_once 'includes/auth.php';
require_once 'config/database.php';
require_login();

$pageTitle = 'Admin Management';
$activeNav = 'admin';

$admins = $pdo->query("SELECT * FROM admins ORDER BY id DESC")->fetchAll();

require_once 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-start mb-1">
  <div>
    <h1 class="page-title">Admin Management</h1>
    <p class="page-subtitle">Manage system administrators and add new admins</p>
  </div>
  <button class="btn btn-brand fw-semibold" data-bs-toggle="modal" data-bs-target="#addAdminModal">
    <i class="fa-solid fa-user-plus me-1"></i> Add Admin
  </button>
</div>

<div class="panel">
  <div class="table-responsive">
    <table class="table table-clean mb-0">
      <thead>
        <tr>
          <th>Admin Name</th>
          <th>Email Address</th>
          <th>Company Name</th>
          <th>Date Joined</th>
          <th class="text-end">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$admins): ?>
          <tr><td colspan="5" class="text-center text-muted py-4">No admin accounts found.</td></tr>
        <?php endif; ?>
        <?php foreach ($admins as $a):
            $isCurrent = ($a['id'] == $_SESSION['admin_id']);
        ?>
        <tr>
          <td>
            <div class="d-flex align-items-center gap-2">
              <div class="stat-icon icon-green" style="width: 32px; height: 32px; font-size: 0.9rem;">
                <i class="fa-solid fa-user"></i>
              </div>
              <div>
                <div class="fw-semibold">
                  <?= e($a['name']) ?>
                  <?php if ($isCurrent): ?>
                    <span class="badge bg-success ms-1" style="font-size: 0.7rem;">You</span>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </td>
          <td><span class="text-secondary"><?= e($a['email']) ?></span></td>
          <td><span class="badge-cat"><?= e($a['company_name']) ?></span></td>
          <td><?= date('M d, Y', strtotime($a['created_at'])) ?></td>
          <td class="text-end">
            <button class="icon-btn edit" title="Edit Admin"
              data-bs-toggle="modal" data-bs-target="#editAdminModal"
              data-id="<?= $a['id'] ?>"
              data-name="<?= e($a['name']) ?>"
              data-email="<?= e($a['email']) ?>"
              data-company="<?= e($a['company_name']) ?>">
              <i class="fa-solid fa-pen"></i>
            </button>
            <?php if (!$isCurrent && count($admins) > 1): ?>
              <form method="POST" action="admin_action.php" class="d-inline" onsubmit="return confirm('Are you sure you want to delete admin user \'<?= e($a['name']) ?>\'?');">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= $a['id'] ?>">
                <button type="submit" class="icon-btn del" title="Delete"><i class="fa-solid fa-trash"></i></button>
              </form>
            <?php else: ?>
              <button class="icon-btn" disabled style="opacity: 0.4; cursor: not-allowed;" title="<?= $isCurrent ? 'Cannot delete current account' : 'Cannot delete sole admin' ?>">
                <i class="fa-solid fa-trash"></i>
              </button>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add Admin Modal -->
<div class="modal fade" id="addAdminModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form method="POST" action="admin_action.php" class="modal-content">
      <input type="hidden" name="action" value="add">
      <div class="modal-header">
        <h5 class="modal-title fw-bold"><i class="fa-solid fa-user-plus text-success me-2"></i>Add New Admin</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label small fw-semibold">Full Name <span class="text-danger">*</span></label>
          <input type="text" name="name" class="form-control" placeholder="e.g. John Doe" required autofocus>
        </div>
        <div class="mb-3">
          <label class="form-label small fw-semibold">Email Address <span class="text-danger">*</span></label>
          <input type="email" name="email" class="form-control" placeholder="e.g. john@pesticide.com" required>
        </div>
        <div class="mb-3">
          <label class="form-label small fw-semibold">Company Name</label>
          <input type="text" name="company_name" class="form-control" value="<?= e($_SESSION['company_name'] ?? 'My Corporation') ?>" placeholder="e.g. Hamza Zarai Corporation">
        </div>
        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label small fw-semibold">Password <span class="text-danger">*</span></label>
            <input type="password" name="password" class="form-control" placeholder="••••••••" minlength="6" required>
            <div class="form-text small">At least 6 characters</div>
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label small fw-semibold">Confirm Password <span class="text-danger">*</span></label>
            <input type="password" name="confirm_password" class="form-control" placeholder="••••••••" minlength="6" required>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-brand fw-semibold"><i class="fa-solid fa-check me-1"></i> Add Admin</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Admin Modal -->
<div class="modal fade" id="editAdminModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form method="POST" action="admin_action.php" class="modal-content">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="id" id="edit_admin_id">
      <div class="modal-header">
        <h5 class="modal-title fw-bold"><i class="fa-solid fa-user-pen text-success me-2"></i>Edit Admin</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label small fw-semibold">Full Name <span class="text-danger">*</span></label>
          <input type="text" name="name" id="edit_admin_name" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label small fw-semibold">Email Address <span class="text-danger">*</span></label>
          <input type="email" name="email" id="edit_admin_email" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label small fw-semibold">Company Name</label>
          <input type="text" name="company_name" id="edit_admin_company" class="form-control">
        </div>
        <div class="mb-3">
          <label class="form-label small fw-semibold">New Password <span class="text-muted">(leave blank to keep current)</span></label>
          <input type="password" name="password" class="form-control" placeholder="••••••••" minlength="6">
          <div class="form-text small">Only fill if you wish to change password</div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-brand fw-semibold"><i class="fa-solid fa-save me-1"></i> Update Admin</button>
      </div>
    </form>
  </div>
</div>

<?php
$extraScripts = '<script>
document.getElementById("editAdminModal").addEventListener("show.bs.modal", function (event) {
  const btn = event.relatedTarget;
  document.getElementById("edit_admin_id").value = btn.dataset.id;
  document.getElementById("edit_admin_name").value = btn.dataset.name;
  document.getElementById("edit_admin_email").value = btn.dataset.email;
  document.getElementById("edit_admin_company").value = btn.dataset.company;
});
</script>';
require_once 'includes/footer.php';
?>
