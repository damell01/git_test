<?php
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_login();
require_role('admin', 'office', 'dispatcher');

// ── Fetch work order ──────────────────────────────────────────────────────────
$id   = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM work_orders WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$wo = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$wo) {
    flash_error('Work order not found.');
    redirect('index.php');
}

$errors = [];
$old    = $wo;

// ── Handle POST ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $old = [
        'cust_name'       => trim($_POST['cust_name']       ?? ''),
        'cust_phone'      => trim($_POST['cust_phone']      ?? ''),
        'cust_email'      => trim($_POST['cust_email']      ?? ''),
        'service_address' => trim($_POST['service_address'] ?? ''),
        'service_city'    => trim($_POST['service_city']    ?? ''),
        'service_state'   => trim($_POST['service_state']   ?? ''),
        'service_zip'     => trim($_POST['service_zip']     ?? ''),
        'size'            => trim($_POST['size']            ?? ''),
        'project_type'    => trim($_POST['project_type']    ?? ''),
        'dumpster_id'     => trim($_POST['dumpster_id']     ?? ''),
        'delivery_date'   => trim($_POST['delivery_date']   ?? ''),
        'pickup_date'     => trim($_POST['pickup_date']     ?? ''),
        'assigned_driver' => trim($_POST['assigned_driver'] ?? ''),
        'amount'          => trim($_POST['amount']          ?? ''),
        'priority'        => trim($_POST['priority']        ?? 'normal'),
        'internal_notes'  => trim($_POST['internal_notes']  ?? ''),
        'footer_notes'    => trim($_POST['footer_notes']    ?? ''),
    ];

    // Validation
    if ($old['cust_name'] === '') {
        $errors[] = 'Customer name is required.';
    }
    if ($old['service_address'] === '') {
        $errors[] = 'Service address is required.';
    }
    if ($old['delivery_date'] === '') {
        $errors[] = 'Delivery date is required.';
    }

    $valid_priorities = ['normal', 'high', 'urgent'];
    if (!in_array($old['priority'], $valid_priorities)) {
        $old['priority'] = 'normal';
    }

    if (empty($errors)) {
        $new_dumpster_id = $old['dumpster_id'] !== '' ? (int)$old['dumpster_id'] : null;
        $old_dumpster_id = !empty($wo['dumpster_id']) ? (int)$wo['dumpster_id'] : null;

        // Handle dumpster change: release old, reserve new
        if ($old_dumpster_id !== $new_dumpster_id) {
            if ($old_dumpster_id) {
                $pdo->prepare('UPDATE dumpsters SET status = ? WHERE id = ?')
                    ->execute(['available', $old_dumpster_id]);
            }
            if ($new_dumpster_id) {
                $pdo->prepare('UPDATE dumpsters SET status = ? WHERE id = ?')
                    ->execute(['reserved', $new_dumpster_id]);
            }
        }

        $driver_id_v   = $old['assigned_driver'] !== '' ? (int)$old['assigned_driver'] : null;
        $amount_v      = $old['amount'] !== '' ? (float)$old['amount'] : null;
        $pickup_date_v = $old['pickup_date'] !== '' ? $old['pickup_date'] : null;

        $upd = $pdo->prepare(
            'UPDATE work_orders SET
                cust_name       = ?,
                cust_phone      = ?,
                cust_email      = ?,
                service_address = ?,
                service_city    = ?,
                service_state   = ?,
                service_zip     = ?,
                size            = ?,
                project_type    = ?,
                dumpster_id     = ?,
                delivery_date   = ?,
                pickup_date     = ?,
                assigned_driver = ?,
                amount          = ?,
                priority        = ?,
                internal_notes  = ?,
                footer_notes    = ?,
                updated_at      = NOW()
             WHERE id = ?'
        );
        $upd->execute([
            $old['cust_name'],
            $old['cust_phone'],
            $old['cust_email'],
            $old['service_address'],
            $old['service_city'],
            $old['service_state'],
            $old['service_zip'],
            $old['size'],
            $old['project_type'],
            $new_dumpster_id,
            $old['delivery_date'],
            $pickup_date_v,
            $driver_id_v,
            $amount_v,
            $old['priority'],
            $old['internal_notes'],
            $old['footer_notes'],
            $id,
        ]);

        log_activity('update_work_order', 'Updated work order ' . $wo['wo_number'], $id);
        flash_success('Work Order ' . $wo['wo_number'] . ' updated successfully.');
        redirect('view.php?id=' . $id);
    }
}

// ── Fetch supporting data ─────────────────────────────────────────────────────
$sizes         = dumpster_sizes();
$project_types = project_types();

$dumpsters_stmt = $pdo->query(
    "SELECT id, unit_code, size, status
     FROM dumpsters
     WHERE status IN ('available', 'reserved')
     ORDER BY unit_code"
);
$dumpsters = $dumpsters_stmt->fetchAll(PDO::FETCH_ASSOC);

// Include the currently assigned dumpster even if its status changed
if (!empty($wo['dumpster_id'])) {
    $assigned_in_list = false;
    foreach ($dumpsters as $d) {
        if ((int)$d['id'] === (int)$wo['dumpster_id']) {
            $assigned_in_list = true;
            break;
        }
    }
    if (!$assigned_in_list) {
        $ds = $pdo->prepare('SELECT id, unit_code, size, status FROM dumpsters WHERE id = ? LIMIT 1');
        $ds->execute([$wo['dumpster_id']]);
        $cur = $ds->fetch(PDO::FETCH_ASSOC);
        if ($cur) {
            array_unshift($dumpsters, $cur);
        }
    }
}

$drivers_stmt = $pdo->query(
    "SELECT id, name FROM users
     WHERE role IN ('admin', 'office', 'dispatcher', 'driver') AND active = 1
     ORDER BY name"
);
$drivers = $drivers_stmt->fetchAll(PDO::FETCH_ASSOC);

layout_start('Edit Work Order: ' . $wo['wo_number'], 'work_orders');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">
        Edit Work Order: <span class="text-primary"><?= htmlspecialchars($wo['wo_number']) ?></span>
    </h1>
    <div>
        <a href="view.php?id=<?= $id ?>" class="btn btn-outline-secondary me-2">
            <i class="fas fa-eye me-1"></i>View
        </a>
        <a href="index.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i>Back
        </a>
    </div>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <strong>Please fix the following errors:</strong>
    <ul class="mb-0 mt-1">
        <?php foreach ($errors as $e): ?>
            <li><?= htmlspecialchars($e) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<form method="post" action="">
    <?= csrf_field() ?>

    <div class="row g-4">
        <!-- Left Column -->
        <div class="col-lg-7">

            <!-- Customer Information -->
            <div class="card shadow-sm mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="fas fa-user me-2"></i>Customer Information</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="cust_name" class="form-label">Customer Name <span class="text-danger">*</span></label>
                        <input type="text" id="cust_name" name="cust_name" class="form-control"
                               value="<?= htmlspecialchars($old['cust_name']) ?>" required>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="cust_phone" class="form-label">Phone</label>
                            <input type="text" id="cust_phone" name="cust_phone" class="form-control"
                                   value="<?= htmlspecialchars($old['cust_phone'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="cust_email" class="form-label">Email</label>
                            <input type="email" id="cust_email" name="cust_email" class="form-control"
                                   value="<?= htmlspecialchars($old['cust_email'] ?? '') ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Service Location -->
            <div class="card shadow-sm mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="fas fa-map-marker-alt me-2"></i>Service Location</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="service_address" class="form-label">Service Address <span class="text-danger">*</span></label>
                        <input type="text" id="service_address" name="service_address" class="form-control"
                               value="<?= htmlspecialchars($old['service_address'] ?? '') ?>" required>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-5">
                            <label for="service_city" class="form-label">City</label>
                            <input type="text" id="service_city" name="service_city" class="form-control"
                                   value="<?= htmlspecialchars($old['service_city'] ?? '') ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="service_state" class="form-label">State</label>
                            <input type="text" id="service_state" name="service_state" class="form-control"
                                   value="<?= htmlspecialchars($old['service_state'] ?? '') ?>"
                                   maxlength="2" placeholder="TX">
                        </div>
                        <div class="col-md-4">
                            <label for="service_zip" class="form-label">ZIP</label>
                            <input type="text" id="service_zip" name="service_zip" class="form-control"
                                   value="<?= htmlspecialchars($old['service_zip'] ?? '') ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Notes -->
            <div class="card shadow-sm mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="fas fa-sticky-note me-2"></i>Notes</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="internal_notes" class="form-label">Internal Notes</label>
                        <textarea id="internal_notes" name="internal_notes" class="form-control" rows="3"><?= htmlspecialchars($old['internal_notes'] ?? '') ?></textarea>
                    </div>
                    <div>
                        <label for="footer_notes" class="form-label">Footer Notes</label>
                        <textarea id="footer_notes" name="footer_notes" class="form-control" rows="3"><?= htmlspecialchars($old['footer_notes'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

        </div>

        <!-- Right Column -->
        <div class="col-lg-5">

            <!-- Job Details -->
            <div class="card shadow-sm mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="fas fa-dumpster me-2"></i>Job Details</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="size" class="form-label">Dumpster Size</label>
                            <select id="size" name="size" class="form-select">
                                <option value="">— Select Size —</option>
                                <?php foreach ($sizes as $sz): ?>
                                    <option value="<?= htmlspecialchars($sz) ?>"
                                        <?= ($old['size'] ?? '') === $sz ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($sz) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="project_type" class="form-label">Project Type</label>
                            <select id="project_type" name="project_type" class="form-select">
                                <option value="">— Select Type —</option>
                                <?php foreach ($project_types as $pt): ?>
                                    <option value="<?= htmlspecialchars($pt) ?>"
                                        <?= ($old['project_type'] ?? '') === $pt ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($pt) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mt-3">
                        <label for="dumpster_id" class="form-label">Assign Dumpster</label>
                        <select id="dumpster_id" name="dumpster_id" class="form-select">
                            <option value="">— Unassigned —</option>
                            <?php foreach ($dumpsters as $d): ?>
                                <option value="<?= (int)$d['id'] ?>"
                                    <?= (string)($old['dumpster_id'] ?? '') === (string)$d['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($d['unit_code']) ?>
                                    (<?= htmlspecialchars($d['size'] ?? 'unknown') ?>)
                                    — <?= ucfirst($d['status']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (!empty($wo['dumpster_id'])): ?>
                        <div class="form-text text-warning">
                            <i class="fas fa-exclamation-triangle me-1"></i>
                            Changing the dumpster will update inventory status automatically.
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Scheduling -->
            <div class="card shadow-sm mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="fas fa-calendar me-2"></i>Scheduling</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="delivery_date" class="form-label">Delivery Date <span class="text-danger">*</span></label>
                        <input type="date" id="delivery_date" name="delivery_date" class="form-control"
                               value="<?= htmlspecialchars($old['delivery_date'] ?? '') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="pickup_date" class="form-label">Scheduled Pickup Date</label>
                        <input type="date" id="pickup_date" name="pickup_date" class="form-control"
                               value="<?= htmlspecialchars($old['pickup_date'] ?? '') ?>">
                    </div>
                    <div>
                        <label for="assigned_driver" class="form-label">Assigned Driver</label>
                        <select id="assigned_driver" name="assigned_driver" class="form-select">
                            <option value="">— Unassigned —</option>
                            <?php foreach ($drivers as $drv): ?>
                                <option value="<?= (int)$drv['id'] ?>"
                                    <?= (string)($old['assigned_driver'] ?? '') === (string)$drv['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($drv['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Amount & Priority -->
            <div class="card shadow-sm mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="fas fa-dollar-sign me-2"></i>Amount &amp; Priority</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="amount" class="form-label">Amount</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" id="amount" name="amount" class="form-control"
                                   step="0.01" min="0"
                                   value="<?= htmlspecialchars($old['amount'] ?? '') ?>">
                        </div>
                    </div>
                    <div>
                        <label class="form-label">Priority</label>
                        <div class="d-flex gap-3">
                            <?php foreach (['normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent'] as $pv => $pl): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="priority"
                                       id="priority_<?= $pv ?>" value="<?= $pv ?>"
                                    <?= ($old['priority'] ?? 'normal') === $pv ? 'checked' : '' ?>>
                                <label class="form-check-label" for="priority_<?= $pv ?>"><?= $pl ?></label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-save me-1"></i>Save Changes
                </button>
                <a href="view.php?id=<?= $id ?>" class="btn btn-outline-secondary">Cancel</a>
            </div>

        </div>
    </div>
</form>

<?php layout_end(); ?>
