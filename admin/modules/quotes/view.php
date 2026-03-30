<?php
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once TMPL_PATH . '/layout.php';
require_login();
$pdo = get_db();

// ── Fetch quote ───────────────────────────────────────────────────────────────
$id    = (int)($_GET['id'] ?? 0);
$stmt  = $pdo->prepare(
    'SELECT quotes.*, users.name AS created_by_name
     FROM quotes
     LEFT JOIN users ON quotes.created_by = users.id
     WHERE quotes.id = ?
     LIMIT 1'
);
$stmt->execute([$id]);
$quote = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$quote) {
    flash_error('Quote not found.');
    redirect('index.php');
}

// ── Fetch linked lead and customer ────────────────────────────────────────────
$linked_lead = null;
if (!empty($quote['lead_id'])) {
    $ls = $pdo->prepare('SELECT id, name, email FROM leads WHERE id = ? LIMIT 1');
    $ls->execute([$quote['lead_id']]);
    $linked_lead = $ls->fetch(PDO::FETCH_ASSOC);
}

$linked_customer = null;
if (!empty($quote['customer_id'])) {
    $cs = $pdo->prepare('SELECT id, name, email FROM customers WHERE id = ? LIMIT 1');
    $cs->execute([$quote['customer_id']]);
    $linked_customer = $cs->fetch(PDO::FETCH_ASSOC);
}

// ── Handle status update (POST) ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $new_status     = trim($_POST['new_status'] ?? '');
    $valid_statuses = ['draft', 'sent', 'approved', 'rejected'];

    if (!in_array($new_status, $valid_statuses)) {
        flash_error('Invalid status value.');
        redirect('view.php?id=' . $id);
    }

    $upd = $pdo->prepare('UPDATE quotes SET status = ?, updated_at = NOW() WHERE id = ?');
    $upd->execute([$new_status, $id]);

    log_activity('update_quote_status',
        'Changed quote ' . $quote['quote_number'] . ' status to ' . $new_status,
        $id
    );
    flash_success('Quote status updated to ' . ucfirst($new_status) . '.');
    redirect('view.php?id=' . $id);
}

// ── Print mode ────────────────────────────────────────────────────────────────
$is_print = isset($_GET['print']);

if ($is_print):
    $company_name    = get_setting('company_name',    'Trash Panda Roll-Offs');
    $company_phone   = get_setting('company_phone',   '');
    $company_email   = get_setting('company_email',   '');
    $company_address = get_setting('company_address', '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quote <?= htmlspecialchars($quote['quote_number']) ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 13px; color: #222; background: #fff; }
        .page { max-width: 800px; margin: 0 auto; padding: 30px 40px; }

        /* Letterhead */
        .letterhead { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 3px solid #2563eb; padding-bottom: 16px; margin-bottom: 24px; }
        .company-name { font-size: 22px; font-weight: bold; color: #2563eb; }
        .company-info { font-size: 12px; color: #555; margin-top: 4px; line-height: 1.6; }
        .quote-label { text-align: right; }
        .quote-label h2 { font-size: 28px; color: #2563eb; letter-spacing: 2px; text-transform: uppercase; }
        .quote-label .quote-num { font-size: 16px; font-weight: bold; color: #111; }
        .quote-label .quote-meta { font-size: 12px; color: #555; margin-top: 4px; line-height: 1.7; }

        /* Two-col info grid */
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px; }
        .info-block h4 { font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: #888; margin-bottom: 6px; border-bottom: 1px solid #e5e7eb; padding-bottom: 4px; }
        .info-block p { font-size: 13px; line-height: 1.8; color: #222; }

        /* Items table */
        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        .items-table th { background: #2563eb; color: #fff; padding: 8px 12px; text-align: left; font-size: 12px; }
        .items-table th:last-child { text-align: right; }
        .items-table td { padding: 8px 12px; border-bottom: 1px solid #e5e7eb; font-size: 13px; }
        .items-table td:last-child { text-align: right; font-weight: 600; }
        .items-table tr.subtotal-row td { background: #f9fafb; font-weight: 600; border-top: 2px solid #d1d5db; }
        .items-table tr.total-row td { background: #2563eb; color: #fff; font-weight: bold; font-size: 15px; }
        .items-table tr.total-row td:last-child { text-align: right; }

        /* Terms */
        .terms-box { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 4px; padding: 14px; margin-bottom: 24px; }
        .terms-box h4 { font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: #888; margin-bottom: 8px; }
        .terms-box p { font-size: 12px; line-height: 1.7; color: #444; white-space: pre-wrap; }

        /* Signature */
        .signature-row { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-top: 32px; padding-top: 16px; border-top: 1px solid #e5e7eb; }
        .sig-field label { font-size: 11px; color: #888; display: block; margin-bottom: 30px; }
        .sig-field .line { border-bottom: 1px solid #555; margin-bottom: 4px; }
        .sig-field span { font-size: 11px; color: #555; }

        /* Print button */
        .print-btn { display: block; text-align: center; margin: 20px auto 0; }
        .print-btn button { padding: 10px 30px; background: #2563eb; color: #fff; border: none; border-radius: 4px; font-size: 14px; cursor: pointer; }

        @media print {
            .print-btn { display: none !important; }
            body { padding: 0; }
            .page { padding: 20px; }
        }
    </style>
</head>
<body>
<div class="page">

    <!-- Letterhead -->
    <div class="letterhead">
        <div>
            <div class="company-name"><?= htmlspecialchars($company_name) ?></div>
            <div class="company-info">
                <?php if ($company_address): ?><?= nl2br(htmlspecialchars($company_address)) ?><br><?php endif; ?>
                <?php if ($company_phone): ?>Phone: <?= htmlspecialchars($company_phone) ?><br><?php endif; ?>
                <?php if ($company_email): ?>Email: <?= htmlspecialchars($company_email) ?><?php endif; ?>
            </div>
        </div>
        <div class="quote-label">
            <h2>Quote</h2>
            <div class="quote-num"><?= htmlspecialchars($quote['quote_number']) ?></div>
            <div class="quote-meta">
                Date: <?= date('m/d/Y', strtotime($quote['created_at'])) ?><br>
                <?php if (!empty($quote['valid_until'])): ?>
                Valid Until: <?= date('m/d/Y', strtotime($quote['valid_until'])) ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Customer & Service Info -->
    <div class="info-grid">
        <div class="info-block">
            <h4>Bill To</h4>
            <p>
                <strong><?= htmlspecialchars($quote['cust_name']) ?></strong><br>
                <?php if (!empty($quote['cust_address'])): ?>
                    <?= nl2br(htmlspecialchars($quote['cust_address'])) ?><br>
                <?php endif; ?>
                <?php if (!empty($quote['cust_phone'])): ?>
                    <?= htmlspecialchars($quote['cust_phone']) ?><br>
                <?php endif; ?>
                <?php if (!empty($quote['cust_email'])): ?>
                    <?= htmlspecialchars($quote['cust_email']) ?>
                <?php endif; ?>
            </p>
        </div>
        <div class="info-block">
            <h4>Service Address</h4>
            <p>
                <?php if (!empty($quote['service_address'])): ?>
                    <?= htmlspecialchars($quote['service_address']) ?><br>
                <?php endif; ?>
                <?php if (!empty($quote['service_city'])): ?>
                    <?= htmlspecialchars($quote['service_city']) ?>
                <?php endif; ?>
                <?php if (empty($quote['service_address']) && empty($quote['service_city'])): ?>
                    <em style="color:#888;">Same as billing address</em>
                <?php endif; ?>
            </p>
            <?php if (!empty($quote['size']) || !empty($quote['project_type'])): ?>
            <br>
            <h4>Project Details</h4>
            <p>
                <?php if (!empty($quote['size'])): ?>Size: <?= htmlspecialchars($quote['size']) ?><br><?php endif; ?>
                <?php if (!empty($quote['project_type'])): ?>Type: <?= htmlspecialchars($quote['project_type']) ?><?php endif; ?>
                <?php if (!empty($quote['rental_days'])): ?><br>Rental Period: <?= (int)$quote['rental_days'] ?> day<?= $quote['rental_days'] != 1 ? 's' : '' ?><?php endif; ?>
            </p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Itemized Table -->
    <table class="items-table">
        <thead>
            <tr>
                <th>Description</th>
                <th style="text-align:right;">Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    Dumpster Rental
                    <?php if (!empty($quote['size'])): ?> — <?= htmlspecialchars($quote['size']) ?><?php endif; ?>
                    <?php if (!empty($quote['rental_days'])): ?>
                        (<?= (int)$quote['rental_days'] ?> day<?= $quote['rental_days'] != 1 ? 's' : '' ?>)
                    <?php endif; ?>
                </td>
                <td>$<?= number_format((float)$quote['rental_price'], 2) ?></td>
            </tr>
            <?php if ((float)($quote['delivery_fee'] ?? 0) > 0): ?>
            <tr>
                <td>Delivery Fee</td>
                <td>$<?= number_format((float)$quote['delivery_fee'], 2) ?></td>
            </tr>
            <?php endif; ?>
            <?php if ((float)($quote['pickup_fee'] ?? 0) > 0): ?>
            <tr>
                <td>Pickup Fee</td>
                <td>$<?= number_format((float)$quote['pickup_fee'], 2) ?></td>
            </tr>
            <?php endif; ?>
            <?php if ((float)($quote['extra_fees'] ?? 0) > 0): ?>
            <tr>
                <td>
                    Additional Fees
                    <?php if (!empty($quote['extra_fee_desc'])): ?>
                        — <?= htmlspecialchars($quote['extra_fee_desc']) ?>
                    <?php endif; ?>
                </td>
                <td>$<?= number_format((float)$quote['extra_fees'], 2) ?></td>
            </tr>
            <?php endif; ?>
            <tr class="subtotal-row">
                <td>Subtotal</td>
                <td>$<?= number_format((float)($quote['subtotal'] ?? 0), 2) ?></td>
            </tr>
            <tr>
                <td>Tax (<?= number_format((float)($quote['tax_rate'] ?? 0), 2) ?>%)</td>
                <td>$<?= number_format((float)($quote['tax_amount'] ?? 0), 2) ?></td>
            </tr>
            <tr class="total-row">
                <td>TOTAL</td>
                <td>$<?= number_format((float)($quote['total'] ?? 0), 2) ?></td>
            </tr>
        </tbody>
    </table>

    <!-- Notes -->
    <?php if (!empty($quote['notes'])): ?>
    <div class="terms-box" style="margin-bottom:16px;">
        <h4>Notes</h4>
        <p><?= htmlspecialchars($quote['notes']) ?></p>
    </div>
    <?php endif; ?>

    <!-- Terms -->
    <?php if (!empty($quote['terms'])): ?>
    <div class="terms-box">
        <h4>Terms &amp; Conditions</h4>
        <p><?= htmlspecialchars($quote['terms']) ?></p>
    </div>
    <?php endif; ?>

    <!-- Signature Lines -->
    <div class="signature-row">
        <div class="sig-field">
            <label>Customer Signature</label>
            <div class="line"></div>
            <span>Signature &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; Date</span>
        </div>
        <div class="sig-field">
            <label>Authorized By</label>
            <div class="line"></div>
            <span>Authorized Signature &nbsp;&nbsp;&nbsp;&nbsp; Date</span>
        </div>
    </div>

</div>

<div class="print-btn">
    <button onclick="window.print()"><i class="fas fa-print"></i> Print / Save PDF</button>
</div>

</body>
</html>
<?php
    exit;
endif;

// ── Normal admin view ─────────────────────────────────────────────────────────
function qv_status_badge(string $status): string
{
    $map = [
        'draft'    => ['Draft',    'bg-secondary'],
        'sent'     => ['Sent',     'bg-info text-dark'],
        'approved' => ['Approved', 'bg-success'],
        'rejected' => ['Rejected', 'bg-danger'],
    ];
    [$label, $cls] = $map[$status] ?? [ucfirst($status), 'bg-secondary'];
    return '<span class="badge fs-6 ' . $cls . '">' . htmlspecialchars($label) . '</span>';
}

layout_start('Quote: ' . $quote['quote_number'], 'quotes');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">
            <?= htmlspecialchars($quote['quote_number']) ?>
            <?= qv_status_badge($quote['status']) ?>
        </h1>
        <p class="text-muted mb-0">Created <?= date('m/d/Y \a\t g:i A', strtotime($quote['created_at'])) ?>
            by <?= htmlspecialchars($quote['created_by_name'] ?? 'Unknown') ?></p>
    </div>
    <a href="index.php" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left me-1"></i>Back to Quotes
    </a>
</div>

<div class="row g-4">
    <!-- Left: Quote Details -->
    <div class="col-lg-8">
        <div class="card shadow-sm mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0"><i class="fas fa-file-invoice me-2"></i>Quote Details</h5>
                <a href="edit.php?id=<?= $id ?>" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-edit me-1"></i>Edit
                </a>
            </div>
            <div class="card-body">
                <div class="row g-0">
                    <!-- Customer Column -->
                    <div class="col-md-6 border-end pe-4">
                        <h6 class="text-uppercase text-muted small mb-3">Customer Information</h6>
                        <dl class="detail-grid row mb-0">
                            <dt class="col-sm-5 text-muted">Name</dt>
                            <dd class="col-sm-7 fw-semibold"><?= htmlspecialchars($quote['cust_name']) ?></dd>

                            <?php if (!empty($quote['cust_phone'])): ?>
                            <dt class="col-sm-5 text-muted">Phone</dt>
                            <dd class="col-sm-7"><?= htmlspecialchars($quote['cust_phone']) ?></dd>
                            <?php endif; ?>

                            <?php if (!empty($quote['cust_email'])): ?>
                            <dt class="col-sm-5 text-muted">Email</dt>
                            <dd class="col-sm-7"><?= htmlspecialchars($quote['cust_email']) ?></dd>
                            <?php endif; ?>

                            <?php if (!empty($quote['cust_address'])): ?>
                            <dt class="col-sm-5 text-muted">Billing Addr</dt>
                            <dd class="col-sm-7"><?= nl2br(htmlspecialchars($quote['cust_address'])) ?></dd>
                            <?php endif; ?>

                            <?php if ($linked_lead): ?>
                            <dt class="col-sm-5 text-muted">From Lead</dt>
                            <dd class="col-sm-7">
                                <a href="<?= BASE_URL ?>/modules/leads/view.php?id=<?= (int)$linked_lead['id'] ?>">
                                    <?= htmlspecialchars($linked_lead['name']) ?>
                                </a>
                            </dd>
                            <?php endif; ?>

                            <?php if ($linked_customer): ?>
                            <dt class="col-sm-5 text-muted">Customer</dt>
                            <dd class="col-sm-7">
                                <a href="<?= BASE_URL ?>/modules/customers/view.php?id=<?= (int)$linked_customer['id'] ?>">
                                    <?= htmlspecialchars($linked_customer['name']) ?>
                                </a>
                            </dd>
                            <?php endif; ?>
                        </dl>
                    </div>

                    <!-- Service Column -->
                    <div class="col-md-6 ps-4">
                        <h6 class="text-uppercase text-muted small mb-3">Service Details</h6>
                        <dl class="detail-grid row mb-0">
                            <?php if (!empty($quote['service_address'])): ?>
                            <dt class="col-sm-5 text-muted">Service Addr</dt>
                            <dd class="col-sm-7"><?= htmlspecialchars($quote['service_address']) ?></dd>
                            <?php endif; ?>

                            <?php if (!empty($quote['service_city'])): ?>
                            <dt class="col-sm-5 text-muted">City</dt>
                            <dd class="col-sm-7"><?= htmlspecialchars($quote['service_city']) ?></dd>
                            <?php endif; ?>

                            <?php if (!empty($quote['size'])): ?>
                            <dt class="col-sm-5 text-muted">Size</dt>
                            <dd class="col-sm-7"><?= htmlspecialchars($quote['size']) ?></dd>
                            <?php endif; ?>

                            <?php if (!empty($quote['project_type'])): ?>
                            <dt class="col-sm-5 text-muted">Project</dt>
                            <dd class="col-sm-7"><?= htmlspecialchars($quote['project_type']) ?></dd>
                            <?php endif; ?>

                            <?php if (!empty($quote['rental_days'])): ?>
                            <dt class="col-sm-5 text-muted">Rental Days</dt>
                            <dd class="col-sm-7"><?= (int)$quote['rental_days'] ?> days</dd>
                            <?php endif; ?>

                            <?php if (!empty($quote['valid_until'])): ?>
                            <dt class="col-sm-5 text-muted">Valid Until</dt>
                            <dd class="col-sm-7"><?= date('m/d/Y', strtotime($quote['valid_until'])) ?></dd>
                            <?php endif; ?>
                        </dl>
                    </div>
                </div>

                <hr>

                <!-- Pricing -->
                <h6 class="text-uppercase text-muted small mb-3">Pricing</h6>
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-sm">
                            <tbody>
                                <tr>
                                    <td class="text-muted">Rental Price</td>
                                    <td class="text-end">$<?= number_format((float)$quote['rental_price'], 2) ?></td>
                                </tr>
                                <?php if ((float)($quote['delivery_fee'] ?? 0) > 0): ?>
                                <tr>
                                    <td class="text-muted">Delivery Fee</td>
                                    <td class="text-end">$<?= number_format((float)$quote['delivery_fee'], 2) ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php if ((float)($quote['pickup_fee'] ?? 0) > 0): ?>
                                <tr>
                                    <td class="text-muted">Pickup Fee</td>
                                    <td class="text-end">$<?= number_format((float)$quote['pickup_fee'], 2) ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php if ((float)($quote['extra_fees'] ?? 0) > 0): ?>
                                <tr>
                                    <td class="text-muted">
                                        Extra Fees
                                        <?php if (!empty($quote['extra_fee_desc'])): ?>
                                            <small class="d-block text-muted"><?= htmlspecialchars($quote['extra_fee_desc']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">$<?= number_format((float)$quote['extra_fees'], 2) ?></td>
                                </tr>
                                <?php endif; ?>
                                <tr class="table-light">
                                    <td class="fw-semibold">Subtotal</td>
                                    <td class="text-end fw-semibold">$<?= number_format((float)($quote['subtotal'] ?? 0), 2) ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Tax (<?= number_format((float)($quote['tax_rate'] ?? 0), 2) ?>%)</td>
                                    <td class="text-end">$<?= number_format((float)($quote['tax_amount'] ?? 0), 2) ?></td>
                                </tr>
                                <tr class="table-primary">
                                    <td class="fw-bold">Total</td>
                                    <td class="text-end fw-bold fs-5">$<?= number_format((float)($quote['total'] ?? 0), 2) ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <?php if (!empty($quote['notes'])): ?>
                <hr>
                <h6 class="text-uppercase text-muted small mb-2">Notes</h6>
                <p class="mb-0"><?= nl2br(htmlspecialchars($quote['notes'])) ?></p>
                <?php endif; ?>

                <?php if (!empty($quote['terms'])): ?>
                <hr>
                <h6 class="text-uppercase text-muted small mb-2">Terms &amp; Conditions</h6>
                <p class="mb-0 text-muted" style="white-space:pre-wrap;"><?= htmlspecialchars($quote['terms']) ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right: Actions -->
    <div class="col-lg-4">

        <!-- Status Actions -->
        <div class="card shadow-sm mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="fas fa-tasks me-2"></i>Status Actions</h5>
            </div>
            <div class="card-body">
                <p class="mb-3">
                    Current Status: <?php
                    $map = [
                        'draft'    => ['Draft',    'bg-secondary'],
                        'sent'     => ['Sent',     'bg-info text-dark'],
                        'approved' => ['Approved', 'bg-success'],
                        'rejected' => ['Rejected', 'bg-danger'],
                    ];
                    [$label, $cls] = $map[$quote['status']] ?? [ucfirst($quote['status']), 'bg-secondary'];
                    echo '<span class="badge ' . $cls . '">' . htmlspecialchars($label) . '</span>';
                    ?>
                </p>

                <?php if ($quote['status'] === 'draft'): ?>
                <form method="post" action="">
                    <?= csrf_field() ?>
                    <input type="hidden" name="new_status" value="sent">
                    <button type="submit" class="btn btn-info w-100 mb-2">
                        <i class="fas fa-paper-plane me-1"></i>Mark as Sent
                    </button>
                </form>
                <?php endif; ?>

                <?php if (in_array($quote['status'], ['draft', 'sent'])): ?>
                <form method="post" action="">
                    <?= csrf_field() ?>
                    <input type="hidden" name="new_status" value="approved">
                    <button type="submit" class="btn btn-success w-100 mb-2">
                        <i class="fas fa-check me-1"></i>Mark as Approved
                    </button>
                </form>
                <form method="post" action="">
                    <?= csrf_field() ?>
                    <input type="hidden" name="new_status" value="rejected">
                    <button type="submit" class="btn btn-danger w-100 mb-2"
                            onclick="return confirm('Mark this quote as rejected?')">
                        <i class="fas fa-times me-1"></i>Mark as Rejected
                    </button>
                </form>
                <?php endif; ?>

                <?php if ($quote['status'] === 'rejected'): ?>
                <form method="post" action="">
                    <?= csrf_field() ?>
                    <input type="hidden" name="new_status" value="draft">
                    <button type="submit" class="btn btn-secondary w-100 mb-2">
                        <i class="fas fa-undo me-1"></i>Revert to Draft
                    </button>
                </form>
                <?php endif; ?>

                <?php if ($quote['status'] === 'approved' && empty($quote['converted_to'])): ?>
                <hr>
                <a href="convert.php?quote_id=<?= $id ?>" class="btn btn-warning w-100 mb-2">
                    <i class="fas fa-exchange-alt me-1"></i>Convert to Work Order
                </a>
                <?php endif; ?>

                <?php if (!empty($quote['converted_to'])): ?>
                <hr>
                <div class="alert alert-success py-2 mb-0">
                    <small>
                        <i class="fas fa-check-circle me-1"></i>
                        Converted to
                        <a href="../work_orders/view.php?id=<?= (int)$quote['converted_to'] ?>">Work Order</a>
                    </small>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="card shadow-sm mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
            </div>
            <div class="card-body d-grid gap-2">
                <a href="?id=<?= $id ?>&print=1" target="_blank" class="btn btn-outline-secondary">
                    <i class="fas fa-print me-1"></i>Print / PDF
                </a>
                <a href="edit.php?id=<?= $id ?>" class="btn btn-outline-primary">
                    <i class="fas fa-edit me-1"></i>Edit Quote
                </a>
                <a href="delete.php?id=<?= $id ?>" class="btn btn-outline-danger"
                   onclick="return confirm('Delete this quote? This cannot be undone.')">
                    <i class="fas fa-trash me-1"></i>Delete Quote
                </a>
            </div>
        </div>

        <!-- Quote Meta -->
        <div class="card shadow-sm">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="fas fa-info-circle me-2"></i>Quote Info</h5>
            </div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-sm-5 text-muted">Quote #</dt>
                    <dd class="col-sm-7 fw-semibold"><?= htmlspecialchars($quote['quote_number']) ?></dd>

                    <dt class="col-sm-5 text-muted">Created</dt>
                    <dd class="col-sm-7"><?= date('m/d/Y', strtotime($quote['created_at'])) ?></dd>

                    <dt class="col-sm-5 text-muted">Updated</dt>
                    <dd class="col-sm-7"><?= date('m/d/Y', strtotime($quote['updated_at'])) ?></dd>

                    <dt class="col-sm-5 text-muted">Created By</dt>
                    <dd class="col-sm-7"><?= htmlspecialchars($quote['created_by_name'] ?? '—') ?></dd>
                </dl>
            </div>
        </div>

    </div>
</div>

<?php layout_end(); ?>
