<?php
/**
 * Invoices – View / Print
 * Trash Panda Roll-Offs
 */

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once TMPL_PATH . '/layout.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { flash_error('Invalid invoice ID.'); redirect('index.php'); }

$inv = db_fetch('SELECT * FROM invoices WHERE id = ? LIMIT 1', [$id]);
if (!$inv) { flash_error('Invoice not found.'); redirect('index.php'); }

$items = db_fetchall('SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY id ASC', [$id]);

// Settings
$company_name    = get_setting('company_name',    'Trash Panda Roll-Offs');
$company_phone   = get_setting('company_phone',   '');
$company_email   = get_setting('company_email',   '');
$company_address = get_setting('company_address', '');

$print_mode = !empty($_GET['print']);

if ($print_mode):
// ── PRINT MODE ────────────────────────────────────────────────────────────────
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Invoice <?= e($inv['invoice_number']) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<style>
  body { background:#fff; color:#111; font-family:Arial,Helvetica,sans-serif; font-size:14px; }
  .inv-header { border-bottom:2px solid #f97316; padding-bottom:1rem; margin-bottom:1.5rem; }
  .inv-logo { font-size:2rem; font-weight:900; letter-spacing:.04em; color:#111; }
  .inv-logo span { color:#f97316; }
  .badge-status { display:inline-block; padding:.25rem .75rem; border-radius:4px; font-size:.8rem; font-weight:700; text-transform:uppercase; }
  .badge-draft { background:#e5e7eb; color:#374151; }
  .badge-sent  { background:#dbeafe; color:#1d4ed8; }
  .badge-paid  { background:#d1fae5; color:#065f46; }
  .badge-void  { background:#fee2e2; color:#991b1b; }
  .items-table th { background:#f9fafb; font-size:.8rem; text-transform:uppercase; letter-spacing:.06em; color:#6b7280; }
  .totals-table td { padding:.35rem .5rem; }
  .subtotal-row td { border-top:2px solid #e5e7eb; font-weight:600; background:#f9fafb; }
  .total-row td { border-top:2px solid #f97316; font-weight:700; font-size:1.1rem; }
  .payment-box { background:#fffbeb; border:1px solid #f59e0b; border-radius:6px; padding:1rem 1.25rem; margin-top:1.5rem; }
  @media print {
    .no-print { display:none !important; }
    body { -webkit-print-color-adjust:exact; print-color-adjust:exact; }
  }
</style>
</head>
<body>
<div class="container py-4">
  <div class="inv-header d-flex justify-content-between align-items-start">
    <div>
      <div class="inv-logo">🗑 <span><?= e($company_name) ?></span></div>
      <?php if ($company_phone):   ?><div><?= e($company_phone) ?></div><?php endif; ?>
      <?php if ($company_email):   ?><div><?= e($company_email) ?></div><?php endif; ?>
      <?php if ($company_address): ?><div><?= e($company_address) ?></div><?php endif; ?>
    </div>
    <div class="text-end">
      <h2 class="mb-1">INVOICE</h2>
      <div class="fw-bold fs-5"><?= e($inv['invoice_number']) ?></div>
      <div class="text-muted" style="font-size:.85rem;">
        Date: <?= e(fmt_date($inv['created_at'])) ?>
        <?php if ($inv['due_date']): ?>
        <br>Due: <?= e(fmt_date($inv['due_date'])) ?>
        <?php endif; ?>
      </div>
      <div class="mt-1">
        <span class="badge-status badge-<?= e($inv['status']) ?>"><?= e(ucfirst($inv['status'])) ?></span>
      </div>
    </div>
  </div>

  <!-- Bill To -->
  <div class="row mb-4">
    <div class="col-md-6">
      <strong>Bill To:</strong>
      <div><?= e($inv['cust_name']) ?></div>
      <?php if ($inv['cust_phone']):   ?><div><?= e($inv['cust_phone']) ?></div><?php endif; ?>
      <?php if ($inv['cust_email']):   ?><div><?= e($inv['cust_email']) ?></div><?php endif; ?>
      <?php if ($inv['cust_address']): ?><div><?= e($inv['cust_address']) ?></div><?php endif; ?>
    </div>
  </div>

  <!-- Line Items -->
  <table class="table items-table mb-0">
    <thead>
      <tr>
        <th style="width:50%">Description</th>
        <th>Rate Type</th>
        <th class="text-end">Qty</th>
        <th class="text-end">Unit Price</th>
        <th class="text-end">Amount</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($items as $it): ?>
      <tr>
        <td><?= e($it['description']) ?></td>
        <td><?= e(ucfirst($it['rate_type'])) ?></td>
        <td class="text-end"><?= e(rtrim(rtrim(number_format((float)$it['quantity'], 2, '.', ''), '0'), '.')) ?></td>
        <td class="text-end"><?= e(fmt_money($it['unit_price'])) ?></td>
        <td class="text-end"><?= e(fmt_money($it['amount'])) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr class="subtotal-row">
        <td colspan="4" class="text-end">Subtotal</td>
        <td class="text-end"><?= e(fmt_money($inv['subtotal'])) ?></td>
      </tr>
      <?php if ((float)$inv['tax_rate'] > 0): ?>
      <tr>
        <td colspan="4" class="text-end">Tax (<?= number_format((float)$inv['tax_rate'], 2) ?>%)</td>
        <td class="text-end"><?= e(fmt_money($inv['tax_amount'])) ?></td>
      </tr>
      <?php endif; ?>
      <tr class="total-row">
        <td colspan="4" class="text-end">Total Due</td>
        <td class="text-end"><?= e(fmt_money($inv['total'])) ?></td>
      </tr>
    </tfoot>
  </table>

  <?php if (!empty($inv['stripe_payment_link'])): ?>
  <div class="payment-box">
    <strong>Pay Online:</strong>
    <a href="<?= e($inv['stripe_payment_link']) ?>" style="color:#d97706;">
      <?= e($inv['stripe_payment_link']) ?>
    </a>
  </div>
  <?php endif; ?>

  <?php if (!empty($inv['notes'])): ?>
  <div class="mt-3"><strong>Notes:</strong><br><?= nl2br(e($inv['notes'])) ?></div>
  <?php endif; ?>
  <?php if (!empty($inv['terms'])): ?>
  <div class="mt-2" style="font-size:.85rem;color:#6b7280;"><strong>Terms:</strong><br><?= nl2br(e($inv['terms'])) ?></div>
  <?php endif; ?>

  <div class="no-print mt-4 text-center">
    <button class="btn btn-primary" onclick="window.print()">Print / Save PDF</button>
    <a href="view.php?id=<?= $id ?>" class="btn btn-outline-secondary ms-2">Back</a>
  </div>
</div>
</body>
</html>
<?php
else:
// ── SCREEN MODE ───────────────────────────────────────────────────────────────
layout_start('Invoice ' . $inv['invoice_number'], 'invoices');
?>

<!-- Page header -->
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-0">Invoice <?= e($inv['invoice_number']) ?></h1>
        <small class="text-muted">Created <?= e(fmt_datetime($inv['created_at'])) ?></small>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="view.php?id=<?= $id ?>&print=1" class="btn-tp-ghost btn-tp-sm" target="_blank">
            <i class="fa-solid fa-print"></i> Print / PDF
        </a>
        <?php if (has_role('admin', 'office')): ?>
        <a href="edit.php?id=<?= $id ?>" class="btn-tp-ghost btn-tp-sm">
            <i class="fa-solid fa-pencil"></i> Edit
        </a>
        <?php endif; ?>
        <a href="index.php" class="btn-tp-ghost btn-tp-sm">
            <i class="fa-solid fa-arrow-left"></i> Back
        </a>
    </div>
</div>

<?php flash_display(); ?>

<div class="row g-4">
    <div class="col-lg-8">

        <!-- Invoice card -->
        <div class="tp-card mb-4">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-3">
                <div>
                    <div style="font-family:var(--font-display);font-size:1.3rem;">
                        🗑 <?= e($company_name) ?>
                    </div>
                    <?php if ($company_phone):   ?><div style="color:var(--gl);font-size:.88rem;"><?= e($company_phone) ?></div><?php endif; ?>
                    <?php if ($company_email):   ?><div style="color:var(--gl);font-size:.88rem;"><?= e($company_email) ?></div><?php endif; ?>
                    <?php if ($company_address): ?><div style="color:var(--gl);font-size:.88rem;"><?= e($company_address) ?></div><?php endif; ?>
                </div>
                <div class="text-end">
                    <div style="font-size:1.5rem;font-weight:700;color:var(--or);"><?= e($inv['invoice_number']) ?></div>
                    <?= status_badge($inv['status']) ?>
                    <div style="color:var(--gl);font-size:.85rem;margin-top:.25rem;">
                        Date: <?= e(fmt_date($inv['created_at'])) ?>
                        <?php if ($inv['due_date']): ?>
                        <br>Due: <?= e(fmt_date($inv['due_date'])) ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Bill To -->
            <div class="mb-4">
                <div style="color:var(--gl);font-size:.8rem;text-transform:uppercase;letter-spacing:.06em;margin-bottom:.35rem;">Bill To</div>
                <div style="font-weight:600;"><?= e($inv['cust_name']) ?></div>
                <?php if ($inv['cust_phone']):   ?><div style="color:var(--gl);"><?= e($inv['cust_phone']) ?></div><?php endif; ?>
                <?php if ($inv['cust_email']):   ?><div style="color:var(--gl);"><?= e($inv['cust_email']) ?></div><?php endif; ?>
                <?php if ($inv['cust_address']): ?><div style="color:var(--gl);"><?= e($inv['cust_address']) ?></div><?php endif; ?>
            </div>

            <!-- Line items table -->
            <div class="table-responsive">
                <table class="table tp-table mb-0">
                    <thead>
                        <tr>
                            <th style="width:45%">Description</th>
                            <th>Rate Type</th>
                            <th class="text-end">Qty</th>
                            <th class="text-end">Unit Price</th>
                            <th class="text-end">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $it): ?>
                        <tr>
                            <td><?= e($it['description']) ?></td>
                            <td><span class="tp-badge" style="text-transform:capitalize;"><?= e($it['rate_type']) ?></span></td>
                            <td class="text-end"><?= e(rtrim(rtrim(number_format((float)$it['quantity'], 2, '.', ''), '0'), '.')) ?></td>
                            <td class="text-end"><?= e(fmt_money($it['unit_price'])) ?></td>
                            <td class="text-end" style="font-weight:600;"><?= e(fmt_money($it['amount'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="4" class="text-end" style="color:var(--gl);">Subtotal</td>
                            <td class="text-end fw-semibold"><?= e(fmt_money($inv['subtotal'])) ?></td>
                        </tr>
                        <?php if ((float)$inv['tax_rate'] > 0): ?>
                        <tr>
                            <td colspan="4" class="text-end" style="color:var(--gl);">
                                Tax (<?= number_format((float)$inv['tax_rate'], 2) ?>%)
                            </td>
                            <td class="text-end fw-semibold"><?= e(fmt_money($inv['tax_amount'])) ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <td colspan="4" class="text-end fw-bold"
                                style="border-top:2px solid var(--or);font-size:1.05rem;">
                                Total Due
                            </td>
                            <td class="text-end fw-bold"
                                style="border-top:2px solid var(--or);font-size:1.15rem;color:var(--or);">
                                <?= e(fmt_money($inv['total'])) ?>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <?php if (!empty($inv['notes'])): ?>
            <div class="mt-3 pt-3" style="border-top:1px solid var(--st2);">
                <div style="color:var(--gl);font-size:.8rem;text-transform:uppercase;letter-spacing:.06em;margin-bottom:.35rem;">Notes</div>
                <div style="color:var(--gl);"><?= nl2br(e($inv['notes'])) ?></div>
            </div>
            <?php endif; ?>

            <?php if (!empty($inv['terms'])): ?>
            <div class="mt-3 pt-3" style="border-top:1px solid var(--st2);">
                <div style="color:var(--gl);font-size:.8rem;text-transform:uppercase;letter-spacing:.06em;margin-bottom:.35rem;">Terms</div>
                <div style="color:var(--gl);font-size:.85rem;"><?= nl2br(e($inv['terms'])) ?></div>
            </div>
            <?php endif; ?>
        </div>

    </div>

    <div class="col-lg-4">

        <!-- Payment Link card -->
        <div class="tp-card mb-4">
            <div class="tp-card-header">
                <h5 class="mb-0"><i class="fab fa-stripe me-2" style="color:#635bff;"></i>Stripe Payment Link</h5>
            </div>
            <?php if (!empty($inv['stripe_payment_link'])): ?>
            <div class="mt-3">
                <a href="<?= e($inv['stripe_payment_link']) ?>" target="_blank"
                   class="btn-tp-primary d-block text-center mb-2">
                    <i class="fa-solid fa-credit-card me-1"></i> Open Payment Link
                </a>
                <div class="mt-2" style="background:var(--dk3);border:1px solid var(--st2);border-radius:6px;padding:.6rem .9rem;word-break:break-all;font-size:.8rem;color:var(--gl);">
                    <?= e($inv['stripe_payment_link']) ?>
                </div>
                <button class="btn-tp-ghost btn-tp-xs w-100 mt-2"
                        data-copy-url="<?= e($inv['stripe_payment_link']) ?>"
                        onclick="copyPayLink(this)" type="button">
                    <i class="fa-solid fa-copy me-1"></i> Copy Link
                </button>
<script>
function copyPayLink(btn) {
    var url = btn.getAttribute('data-copy-url');
    navigator.clipboard.writeText(url).then(function() {
        btn.textContent = '✓ Copied!';
        setTimeout(function() { btn.innerHTML = '<i class="fa-solid fa-copy" style="margin-right:.25rem;"></i> Copy Link'; }, 2000);
    });
}
</script>
            </div>
            <?php else: ?>
            <p class="text-muted mt-3 mb-2" style="font-size:.9rem;">No payment link generated.</p>
            <?php if (has_role('admin', 'office')): ?>
            <a href="edit.php?id=<?= $id ?>" class="btn-tp-ghost btn-tp-sm">
                <i class="fa-solid fa-link me-1"></i> Edit invoice to generate one
            </a>
            <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Quick status update -->
        <?php if (has_role('admin', 'office') && $inv['status'] !== 'paid' && $inv['status'] !== 'void'): ?>
        <div class="tp-card mb-4">
            <div class="tp-card-header">
                <h5 class="mb-0"><i class="fa-solid fa-circle-check me-2"></i>Mark as Paid</h5>
            </div>
            <form method="post" action="update_status.php" class="mt-3">
                <?= csrf_field() ?>
                <input type="hidden" name="id"     value="<?= $id ?>">
                <input type="hidden" name="status" value="paid">
                <button type="submit" class="btn-tp-primary btn-tp-sm w-100"
                        onclick="return confirm('Mark this invoice as Paid?')">
                    <i class="fa-solid fa-check me-1"></i> Mark Paid
                </button>
            </form>
        </div>
        <?php endif; ?>

        <!-- Summary -->
        <div class="tp-card">
            <div class="tp-card-header">
                <h5 class="mb-0"><i class="fa-solid fa-info-circle me-2"></i>Invoice Summary</h5>
            </div>
            <table class="table tp-table table-sm mt-3 mb-0">
                <tr><td style="color:var(--gl);">Invoice #</td><td class="fw-semibold"><?= e($inv['invoice_number']) ?></td></tr>
                <tr><td style="color:var(--gl);">Status</td><td><?= status_badge($inv['status']) ?></td></tr>
                <tr><td style="color:var(--gl);">Items</td><td><?= count($items) ?></td></tr>
                <tr><td style="color:var(--gl);">Subtotal</td><td><?= e(fmt_money($inv['subtotal'])) ?></td></tr>
                <tr><td style="color:var(--gl);">Tax</td><td><?= e(fmt_money($inv['tax_amount'])) ?></td></tr>
                <tr><td style="color:var(--gl);font-weight:700;">Total</td><td style="color:var(--or);font-weight:700;font-size:1.1rem;"><?= e(fmt_money($inv['total'])) ?></td></tr>
                <?php if ($inv['due_date']): ?>
                <tr><td style="color:var(--gl);">Due Date</td><td><?= e(fmt_date($inv['due_date'])) ?></td></tr>
                <?php endif; ?>
            </table>
        </div>

    </div>
</div>

<?php
layout_end();
endif; // print mode
?>
