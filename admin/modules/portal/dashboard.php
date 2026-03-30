<?php
/**
 * Customer Self-Service Portal – Dashboard
 * Trash Panda Roll-Offs
 */

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once INC_PATH . '/db.php';
require_once INC_PATH . '/helpers.php';
require_once INC_PATH . '/auth.php';

// Security headers
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');

session_name('tp_portal_session');
session_start();

$company_name = get_setting('company_name', 'Trash Panda Roll-Offs');

// ── Token login ──────────────────────────────────────────────────────────────
if (isset($_GET['token'])) {
    $token = trim($_GET['token']);

    $row = db_fetch(
        'SELECT cpt.*, c.name AS cust_name
         FROM customer_portal_tokens cpt
         JOIN customers c ON cpt.customer_id = c.id
         WHERE cpt.token = ? AND cpt.expires_at > NOW()
         LIMIT 1',
        [$token]
    );

    if ($row) {
        $_SESSION['portal_customer_id']   = $row['customer_id'];
        $_SESSION['portal_customer_name'] = $row['cust_name'];
        $_SESSION['portal_last_active']   = time();

        // Invalidate token after use
        db_execute('DELETE FROM customer_portal_tokens WHERE token = ?', [$token]);

        // Redirect to clean URL
        header('Location: ' . APP_URL . '/modules/portal/dashboard.php');
        exit;
    } else {
        $error_msg = 'This login link is invalid or has expired. Please request a new one.';
    }
}

// ── Session check ─────────────────────────────────────────────────────────────
if (empty($_SESSION['portal_customer_id'])) {
    header('Location: ' . APP_URL . '/modules/portal/index.php');
    exit;
}

// Session idle timeout: 2 hours
if (!empty($_SESSION['portal_last_active']) && (time() - $_SESSION['portal_last_active']) > 7200) {
    session_destroy();
    header('Location: ' . APP_URL . '/modules/portal/index.php?expired=1');
    exit;
}
$_SESSION['portal_last_active'] = time();

$cust_id   = (int)$_SESSION['portal_customer_id'];
$cust_name = $_SESSION['portal_customer_name'] ?? 'Customer';

// ── Fetch customer data ───────────────────────────────────────────────────────
$customer = db_fetch('SELECT * FROM customers WHERE id = ? LIMIT 1', [$cust_id]);
if (!$customer) {
    session_destroy();
    header('Location: ' . APP_URL . '/modules/portal/index.php');
    exit;
}

// Work orders
$work_orders = db_fetchall(
    'SELECT * FROM work_orders WHERE customer_id = ? ORDER BY created_at DESC',
    [$cust_id]
);

// Open invoices
$open_invoices = db_fetchall(
    "SELECT i.*, wo.wo_number, wo.service_address
     FROM invoices i
     LEFT JOIN work_orders wo ON i.work_order_id = wo.id
     WHERE i.customer_id = ? AND i.status IN ('unpaid','partial')
     ORDER BY i.due_date ASC",
    [$cust_id]
);

// Past payments
$payments = db_fetchall(
    "SELECT p.*, wo.wo_number
     FROM payments p
     LEFT JOIN work_orders wo ON p.work_order_id = wo.id
     WHERE p.customer_id = ? AND p.status = 'paid'
     ORDER BY p.paid_at DESC LIMIT 10",
    [$cust_id]
);

// ── Logout ────────────────────────────────────────────────────────────────────
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ' . APP_URL . '/modules/portal/index.php');
    exit;
}

$status_labels = [
    'scheduled'        => ['Scheduled',   'primary'],
    'delivered'        => ['Delivered',   'info'],
    'active'           => ['Active',      'success'],
    'pickup_requested' => ['Pickup Req.', 'warning'],
    'picked_up'        => ['Picked Up',   'secondary'],
    'completed'        => ['Completed',   'dark'],
    'canceled'         => ['Canceled',    'danger'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Account | <?= htmlspecialchars($company_name, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600&family=Barlow+Condensed:wght@600;700&display=swap" rel="stylesheet">
    <style>
        body { background: #f3f4f6; font-family: 'Barlow', sans-serif; color: #1f2937; }
        .portal-nav { background: #1a1d27; color: #e5e7eb; padding: 14px 0; margin-bottom: 32px; }
        .portal-nav .brand { color: #f97316; font-family: 'Barlow Condensed', sans-serif;
                              font-size: 1.3rem; font-weight: 700; text-decoration: none; }
        .portal-nav .brand:hover { color: #f97316; }
        .section-card { background: #fff; border-radius: 10px; box-shadow: 0 2px 12px rgba(0,0,0,.07); padding: 24px; margin-bottom: 24px; }
        .section-title { font-family: 'Barlow Condensed', sans-serif; font-size: 1.1rem;
                         font-weight: 700; color: #374151; margin-bottom: 16px; }
        .btn-orange { background: #f97316; color: #fff; border: none; border-radius: 6px;
                      padding: .45rem 1.2rem; font-weight: 600; font-size: .9rem; text-decoration: none; display: inline-flex; align-items: center; gap: .4rem; }
        .btn-orange:hover { background: #ea6c0e; color: #fff; }
        .status-dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 5px; }
    </style>
</head>
<body>

<!-- Nav -->
<nav class="portal-nav">
    <div class="container d-flex justify-content-between align-items-center">
        <a class="brand" href="dashboard.php">
            <i class="fa-solid fa-dumpster me-1"></i>
            <?= htmlspecialchars($company_name, ENT_QUOTES, 'UTF-8') ?>
        </a>
        <div class="d-flex align-items-center gap-3">
            <span style="font-size:.9rem;color:#9ca3af;">
                <i class="fa-solid fa-user me-1"></i>
                <?= htmlspecialchars($cust_name, ENT_QUOTES, 'UTF-8') ?>
            </span>
            <a href="?logout=1" style="color:#9ca3af;font-size:.85rem;text-decoration:none;">
                <i class="fa-solid fa-arrow-right-from-bracket"></i> Sign Out
            </a>
        </div>
    </div>
</nav>

<div class="container pb-5">

    <!-- Welcome -->
    <div class="mb-4">
        <h2 style="font-family:'Barlow Condensed',sans-serif;font-weight:700;">
            Welcome, <?= htmlspecialchars($cust_name, ENT_QUOTES, 'UTF-8') ?>!
        </h2>
        <p class="text-muted mb-0">Here you can view your work orders, invoices, and make payments.</p>
    </div>

    <!-- Open Invoices -->
    <?php if (!empty($open_invoices)): ?>
    <div class="section-card border-start border-warning border-3">
        <div class="section-title">
            <i class="fa-solid fa-file-invoice-dollar text-warning me-2"></i>
            Outstanding Invoices
        </div>
        <?php foreach ($open_invoices as $inv): ?>
        <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-3 flex-wrap gap-2">
            <div>
                <div class="fw-semibold"><?= htmlspecialchars($inv['invoice_number'], ENT_QUOTES, 'UTF-8') ?></div>
                <div class="text-muted" style="font-size:.85rem;">
                    <?= htmlspecialchars($inv['wo_number'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                    &bull;
                    <?= htmlspecialchars($inv['service_address'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                </div>
                <div style="font-size:.85rem;">
                    Due: <?= htmlspecialchars($inv['due_date'] ? date('F j, Y', strtotime($inv['due_date'])) : 'Upon receipt', ENT_QUOTES, 'UTF-8') ?>
                </div>
            </div>
            <div class="text-end">
                <div style="font-size:1.3rem;font-weight:700;color:#f97316;">
                    $<?= number_format((float)$inv['amount'] - (float)$inv['amount_paid'], 2) ?>
                    <span style="font-size:.75rem;color:#9ca3af;">due</span>
                </div>
                <a href="pay.php?invoice_id=<?= (int)$inv['id'] ?>" class="btn-orange mt-1">
                    <i class="fa-solid fa-credit-card"></i> Pay Now
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Work Orders -->
    <div class="section-card">
        <div class="section-title">
            <i class="fa-solid fa-clipboard-list me-2" style="color:#f97316;"></i>
            My Work Orders
        </div>
        <?php if (empty($work_orders)): ?>
        <p class="text-muted">No work orders found.</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm table-hover">
                <thead>
                    <tr style="font-size:.85rem;color:#6b7280;">
                        <th>WO #</th>
                        <th>Address</th>
                        <th>Size</th>
                        <th>Delivery</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($work_orders as $wo): ?>
                <?php
                    $st = $wo['status'];
                    [$st_label, $st_color] = $status_labels[$st] ?? [ucfirst($st), 'secondary'];
                ?>
                <tr style="font-size:.9rem;">
                    <td><?= htmlspecialchars($wo['wo_number'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($wo['service_address'] . ($wo['service_city'] ? ', ' . $wo['service_city'] : ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($wo['size'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= $wo['delivery_date'] ? htmlspecialchars(date('M j, Y', strtotime($wo['delivery_date'])), ENT_QUOTES, 'UTF-8') : '—' ?></td>
                    <td><span class="badge bg-<?= $st_color ?>"><?= $st_label ?></span></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- Past Payments -->
    <?php if (!empty($payments)): ?>
    <div class="section-card">
        <div class="section-title">
            <i class="fa-solid fa-receipt me-2" style="color:#22c55e;"></i>
            Payment History
        </div>
        <div class="table-responsive">
            <table class="table table-sm">
                <thead>
                    <tr style="font-size:.85rem;color:#6b7280;">
                        <th>Date</th>
                        <th>WO #</th>
                        <th>Amount</th>
                        <th>Method</th>
                        <th>Receipt</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($payments as $pay): ?>
                <tr style="font-size:.9rem;">
                    <td><?= $pay['paid_at'] ? htmlspecialchars(date('M j, Y', strtotime($pay['paid_at'])), ENT_QUOTES, 'UTF-8') : '—' ?></td>
                    <td><?= htmlspecialchars($pay['wo_number'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                    <td style="color:#22c55e;font-weight:600;">$<?= number_format((float)$pay['amount'], 2) ?></td>
                    <td><?= htmlspecialchars(ucfirst($pay['method']), ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <a href="receipt.php?id=<?= (int)$pay['id'] ?>" style="font-size:.85rem;">
                            <i class="fa-solid fa-receipt"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

</div><!-- /.container -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
