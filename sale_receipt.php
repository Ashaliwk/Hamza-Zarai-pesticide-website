<?php
require_once 'includes/auth.php';
require_once 'config/database.php';
require_login();

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT s.*, p.name AS product_name, p.unit AS product_unit
    FROM sales s
    JOIN products p ON p.id = s.product_id
    WHERE s.id = ?
");
$stmt->execute([$id]);
$sale = $stmt->fetch();

if (!$sale) {
    echo '<div style="font-family:sans-serif; text-align:center; padding:50px;">
        <h2>Sale Record Not Found</h2>
        <a href="sales.php" style="color:#1b6b3a;">Return to Sales Page</a>
    </div>';
    exit;
}

$company       = $_SESSION['company_name'] ?? 'Hamza Zarai Pesticide Corporation';
$autoAction    = $_GET['auto'] ?? '';
$paymentStatus = $sale['payment_status'] ?? 'paid';
$paidAmount    = (float)($sale['paid_amount'] ?? $sale['total']);
$dueAmount     = (float)($sale['due_amount'] ?? 0.00);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sale Receipt #INV-S-<?= sprintf('%04d', $sale['id']) ?> · <?= e($company) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<style>
  body {
    background-color: #f1f5f9;
    font-family: 'Inter', system-ui, -apple-system, sans-serif;
    color: #1e293b;
  }
  .receipt-card {
    max-width: 800px;
    margin: 30px auto;
    background: #ffffff;
    border-radius: 16px;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
    border: 1px solid #e2e8f0;
    overflow: hidden;
  }
  .top-action-bar {
    background: #ffffff;
    border-bottom: 1px solid #e2e8f0;
    padding: 12px 24px;
  }
  @media print {
    body { background: #fff !important; }
    .no-print, .top-action-bar { display: none !important; }
    .receipt-card {
      box-shadow: none !important;
      border: none !important;
      margin: 0 !important;
      max-width: 100% !important;
    }
  }
</style>
</head>
<body>

<div class="top-action-bar no-print d-flex justify-content-between align-items-center">
  <a href="sales.php" class="btn btn-outline-secondary btn-sm">
    <i class="fa-solid fa-arrow-left me-1"></i> Back to Sales
  </a>
  <div class="d-flex gap-2">
    <button onclick="downloadPDF()" class="btn btn-danger btn-sm fw-semibold">
      <i class="fa-solid fa-file-pdf me-1"></i> Download PDF
    </button>
    <button onclick="window.print()" class="btn btn-success btn-sm fw-semibold" style="background:#1b6b3a; border-color:#1b6b3a;">
      <i class="fa-solid fa-print me-1"></i> Print Receipt
    </button>
  </div>
</div>

<div class="receipt-card p-4 p-md-5" id="receiptContent">
  <div class="d-flex justify-content-between align-items-start border-bottom pb-4 mb-4">
    <div>
      <div class="d-flex align-items-center gap-2 mb-1">
        <span style="font-size: 2.2rem;">🌱</span>
        <h2 class="fw-bold mb-0" style="color: #14532d; letter-spacing: -0.5px;">Hamza Zarai Corporation</h2>
      </div>
      <p class="text-muted small mb-1"><i class="fa-solid fa-location-dot me-1"></i> Norpur, Pakistan</p>
      <p class="text-muted small mb-0"><i class="fa-solid fa-phone me-1"></i> Contact: +92 300 6901657 | Info@zarai.com</p>
    </div>
    <div class="text-end">
      <div class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 fw-bold rounded-pill mb-2">
        <i class="fa-solid fa-receipt me-1"></i> SALE INVOICE
      </div>
      <h4 class="fw-bold mb-1" style="color: #1b6b3a;">#INV-S-<?= sprintf('%04d', $sale['id']) ?></h4>
      <div class="text-muted small"><strong>Date:</strong> <?= date('F j, Y', strtotime($sale['sale_date'])) ?></div>
    </div>
  </div>

  <!-- Customer & Payment Status -->
  <div class="row mb-4 p-3 rounded" style="background-color: #f8faf9; border: 1px solid #eef3f0;">
    <div class="col-sm-6 mb-2 mb-sm-0">
      <div class="text-uppercase small fw-bold text-muted mb-1">Billed To Customer</div>
      <h5 class="fw-bold mb-1 text-dark"><?= e($sale['customer_name']) ?></h5>
      <span class="badge bg-light text-secondary border">Customer Account</span>
    </div>
    <div class="col-sm-6 text-sm-end">
      <div class="text-uppercase small fw-bold text-muted mb-1">Payment Status</div>
      <?php if ($paymentStatus === 'paid'): ?>
        <span class="badge bg-success px-3 py-1 fs-6"><i class="fa-solid fa-circle-check me-1"></i> FULLY PAID</span>
      <?php elseif ($paymentStatus === 'unpaid'): ?>
        <span class="badge bg-danger px-3 py-1 fs-6"><i class="fa-solid fa-circle-xmark me-1"></i> UNPAID</span>
      <?php else: ?>
        <span class="badge bg-warning text-dark px-3 py-1 fs-6"><i class="fa-solid fa-clock me-1"></i> PARTIAL PAYMENT</span>
      <?php endif; ?>
      <div class="small text-muted mt-2"><strong>Transaction Ref:</strong> TXN-S<?= sprintf('%05d', $sale['id']) ?></div>
    </div>
  </div>

  <!-- Product Item Table -->
  <div class="table-responsive mb-4">
    <table class="table table-bordered align-middle">
      <thead style="background-color: #f1f5f9;">
        <tr>
          <th style="width: 8%">#</th>
          <th>Product Description</th>
          <th class="text-center" style="width: 18%">Quantity</th>
          <th class="text-end" style="width: 22%">Price / Unit</th>
          <th class="text-end" style="width: 24%">Total Amount</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td class="fw-semibold">1</td>
          <td>
            <strong class="text-dark fs-6"><?= e($sale['product_name']) ?></strong>
            <div class="text-muted small">Unit Type: <?= e($sale['product_unit'] ?? 'Unit') ?></div>
          </td>
          <td class="text-center fw-semibold fs-6">
            <?= rtrim(rtrim(number_format($sale['quantity'], 2), '0'), '.') ?> <?= e($sale['product_unit'] ?? '') ?>
          </td>
          <td class="text-end fw-semibold fs-6"><?= money($sale['price_per_unit']) ?></td>
          <td class="text-end fw-bold text-dark fs-6"><?= money($sale['total']) ?></td>
        </tr>
      </tbody>
    </table>
  </div>

  <!-- Financial Summary & Balance Due -->
  <div class="row align-items-center justify-content-end mb-4">
    <div class="col-md-6">
      <div class="p-3 rounded" style="background-color: #f8faf9; border: 1px solid #eef3f0;">
        <div class="d-flex justify-content-between mb-2">
          <span class="text-muted small">Total Bill Amount</span>
          <span class="fw-bold text-dark"><?= money($sale['total']) ?></span>
        </div>
        <div class="d-flex justify-content-between mb-2">
          <span class="text-muted small">Amount Received (Paid)</span>
          <span class="fw-bold text-success"><?= money($paidAmount) ?></span>
        </div>
        <hr class="my-2">
        <div class="d-flex justify-content-between align-items-center">
          <span class="fw-bold fs-6">Outstanding Balance Due</span>
          <h4 class="fw-extrabold mb-0 <?= $dueAmount > 0 ? 'text-danger' : 'text-success' ?>">
            <?= money($dueAmount) ?>
          </h4>
        </div>
      </div>
    </div>
  </div>

  <!-- Terms & Signature -->
  <div class="row pt-4 mt-3 border-top align-items-end">
    <div class="col-7">
      <p class="small text-muted mb-1"><strong>Terms:</strong> All sales terms apply. Retain receipt for account records.</p>
      <p class="small text-muted mb-0">Thank you for choosing <?= e($company) ?>!</p>
    </div>
    <div class="col-5 text-end">
      <div style="border-bottom: 1px dashed #94a3b8; width: 170px; display: inline-block; margin-bottom: 6px;"></div>
      <div class="small fw-bold text-muted">Authorized Signature</div>
    </div>
  </div>
</div>

<script>
function downloadPDF() {
  const element = document.getElementById('receiptContent');
  const opt = {
    margin:       10,
    filename:     'Sale-Invoice-#INV-S-<?= sprintf('%04d', $sale['id']) ?>-<?= preg_replace('/[^A-Za-z0-9]/', '_', $sale['customer_name']) ?>.pdf',
    image:        { type: 'jpeg', quality: 0.98 },
    html2canvas:  { scale: 2, useCORS: true },
    jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
  };
  html2pdf().set(opt).from(element).save();
}

<?php if ($autoAction === 'print'): ?>
  window.addEventListener('DOMContentLoaded', () => {
    setTimeout(() => { window.print(); }, 500);
  });
<?php elseif ($autoAction === 'pdf'): ?>
  window.addEventListener('DOMContentLoaded', () => {
    setTimeout(() => { downloadPDF(); }, 500);
  });
<?php endif; ?>
</script>
</body>
</html>
