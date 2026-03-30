<?php
/**
 * Leads – View
 * Trash Panda Roll-Offs
 */

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once TMPL_PATH . '/layout.php';

require_login();

// ── Fetch lead ───────────────────────────────────────────────────────────────
$id   = (int)($_GET['id'] ?? 0);
$lead = $id
    ? db_fetch(
        "SELECT leads.*, users.name AS assigned_name
         FROM leads
         LEFT JOIN users ON leads.assigned_to = users.id
         WHERE leads.id = ? LIMIT 1",
        [$id]
      )
    : false;

if (!$lead) {
    http_response_code(404);
    die('<h1>404 – Lead not found.</h1>');
}

// ── Fetch related data ───────────────────────────────────────────────────────
$notes = db_fetchall(
    "SELECT lead_notes.*, users.name AS author_name
     FROM lead_notes
     LEFT JOIN users ON lead_notes.user_id = users.id
     WHERE lead_notes.lead_id = ?
     ORDER BY lead_notes.created_at DESC",
    [$id]
);

$quotes = db_fetchall(
    "SELECT * FROM quotes WHERE lead_id = ? ORDER BY created_at DESC",
    [$id]
);

$customer = null;
if (!empty($lead['converted_to'])) {
    $customer = db_fetch(
        "SELECT * FROM customers WHERE id = ? LIMIT 1",
        [(int)$lead['converted_to']]
    );
}

// ── Handle POST: add_note ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_note') {
    csrf_check();

    $note_body = trim($_POST['note_body'] ?? '');
    if ($note_body !== '') {
        db_insert('lead_notes', [
            'lead_id'    => $id,
            'user_id'    => (int)($_SESSION['user_id'] ?? 0),
            'note'       => $note_body,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        log_activity('note', 'Added note to lead: ' . $lead['name'], 'lead', $id);
        flash_success('Note added.');
    }
    redirect(APP_URL . '/modules/leads/view.php?id=' . $id);
}

// ── Handle POST: update_status ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_status') {
    csrf_check();

    $new_status = trim($_POST['status'] ?? '');
    $valid      = ['new', 'contacted', 'quoted', 'won', 'lost'];

    if (in_array($new_status, $valid, true)) {
        $old_status = $lead['status'];
        db_update('leads', [
            'status'     => $new_status,
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id', $id);

        // Auto-note the status change
        db_insert('lead_notes', [
            'lead_id'    => $id,
            'user_id'    => (int)($_SESSION['user_id'] ?? 0),
            'note'       => 'Status changed from "' . $old_status . '" to "' . $new_status . '".',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        log_activity('status', 'Lead status updated to ' . $new_status, 'lead', $id);
        flash_success('Status updated to "' . ucfirst($new_status) . '".');
    }
    redirect(APP_URL . '/modules/leads/view.php?id=' . $id);
}

// ── Status label map ─────────────────────────────────────────────────────────
$status_labels = [
    'new'       => 'New',
    'contacted' => 'Contacted',
    'quoted'    => 'Quoted',
    'won'       => 'Won',
    'lost'      => 'Lost',
];

layout_start('Lead: ' . $lead['name'], 'leads');
?>

<!-- Page header -->
<div class="tp-page-header d-flex align-items-center justify-content-between mb-3">
    <div>
        <a href="<?= APP_URL ?>/modules/leads/index.php" class="text-muted small text-decoration-none">
            <i class="fa-solid fa-arrow-left"></i> Back to Leads
        </a>
        <h2 class="tp-page-title mb-0 mt-1">
            <?= e($lead['name']) ?>
            <?= status_badge($lead['status'] ?? 'new') ?>
        </h2>
    </div>
</div>

<!-- 2-Column layout -->
<div class="row g-4">

    <!-- ====== LEFT COLUMN ====== -->
    <div class="col-lg-8">

        <!-- Lead Info Card -->
        <div class="tp-card mb-4">
            <div class="tp-card-header d-flex align-items-center justify-content-between">
                <h5 class="mb-0"><i class="fa-solid fa-circle-info me-2"></i>Lead Information</h5>
                <?php if (has_role('admin', 'office')): ?>
                <a href="<?= APP_URL ?>/modules/leads/edit.php?id=<?= (int)$lead['id'] ?>"
                   class="btn-tp-ghost btn-tp-sm">
                    <i class="fa-solid fa-pencil"></i> Edit
                </a>
                <?php endif; ?>
            </div>
            <div class="detail-grid mt-3">
                <div class="detail-row">
                    <span class="detail-label">Phone</span>
                    <span class="detail-value">
                        <?= e(fmt_phone($lead['phone'])) ?: '—' ?>
                    </span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Email</span>
                    <span class="detail-value">
                        <?php if (!empty($lead['email'])): ?>
                            <a href="mailto:<?= e($lead['email']) ?>"><?= e($lead['email']) ?></a>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Address</span>
                    <span class="detail-value">
                        <?php
                        $parts = array_filter([
                            $lead['address'] ?? '',
                            $lead['city']    ?? '',
                            $lead['state']   ?? '',
                            $lead['zip']     ?? '',
                        ]);
                        echo $parts ? e(implode(', ', $parts)) : '—';
                        ?>
                    </span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Dumpster Size</span>
                    <span class="detail-value"><?= e($lead['size_needed'] ?? '—') ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Project Type</span>
                    <span class="detail-value"><?= e($lead['project_type'] ?? '—') ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Source</span>
                    <span class="detail-value"><?= e($lead['source'] ?? '—') ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Assigned To</span>
                    <span class="detail-value"><?= e($lead['assigned_name'] ?? 'Unassigned') ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Created</span>
                    <span class="detail-value"><?= e(fmt_datetime($lead['created_at'])) ?></span>
                </div>
                <?php if (!empty($lead['updated_at'])): ?>
                <div class="detail-row">
                    <span class="detail-label">Last Updated</span>
                    <span class="detail-value"><?= e(fmt_datetime($lead['updated_at'])) ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($lead['message'])): ?>
                <div class="detail-row">
                    <span class="detail-label">Message</span>
                    <span class="detail-value"><?= nl2br(e($lead['message'])) ?></span>
                </div>
                <?php endif; ?>
                <?php if ($customer): ?>
                <div class="detail-row">
                    <span class="detail-label">Converted To</span>
                    <span class="detail-value">
                        <a href="<?= APP_URL ?>/modules/customers/view.php?id=<?= (int)$customer['id'] ?>">
                            <?= e($customer['name']) ?>
                        </a>
                    </span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Linked Quotes Card -->
        <div class="tp-card mb-4">
            <div class="tp-card-header d-flex align-items-center justify-content-between">
                <h5 class="mb-0"><i class="fa-solid fa-file-invoice-dollar me-2"></i>Quotes</h5>
                <?php if (has_role('admin', 'office')): ?>
                <a href="<?= APP_URL ?>/modules/quotes/create.php?lead_id=<?= (int)$lead['id'] ?>"
                   class="btn-tp-ghost btn-tp-sm">
                    <i class="fa-solid fa-plus"></i> New Quote
                </a>
                <?php endif; ?>
            </div>
            <?php if (empty($quotes)): ?>
                <p class="text-muted mt-3 mb-0">No quotes linked to this lead.</p>
            <?php else: ?>
            <div class="table-responsive mt-3">
                <table class="tp-table">
                    <thead>
                        <tr>
                            <th>Quote #</th>
                            <th>Status</th>
                            <th>Amount</th>
                            <th>Date</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($quotes as $quote): ?>
                        <tr>
                            <td>
                                <a href="<?= APP_URL ?>/modules/quotes/view.php?id=<?= (int)$quote['id'] ?>">
                                    <?= e($quote['quote_number'] ?? '#' . $quote['id']) ?>
                                </a>
                            </td>
                            <td><?= status_badge($quote['status'] ?? 'draft') ?></td>
                            <td><?= e(fmt_money($quote['total'] ?? 0)) ?></td>
                            <td><?= e(fmt_date($quote['created_at'])) ?></td>
                            <td class="text-end">
                                <a href="<?= APP_URL ?>/modules/quotes/view.php?id=<?= (int)$quote['id'] ?>"
                                   class="btn-tp-ghost btn-tp-sm">
                                    <i class="fa-solid fa-eye"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <!-- Notes Timeline Card -->
        <div class="tp-card">
            <div class="tp-card-header">
                <h5 class="mb-0"><i class="fa-solid fa-comments me-2"></i>Notes</h5>
            </div>

            <!-- Add Note Form -->
            <form method="post"
                  action="<?= APP_URL ?>/modules/leads/view.php?id=<?= (int)$lead['id'] ?>"
                  class="mt-3">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add_note">
                <div class="input-group">
                    <textarea name="note_body" class="form-control" rows="2"
                              placeholder="Add a note…" required></textarea>
                    <button type="submit" class="btn-tp-primary">
                        <i class="fa-solid fa-paper-plane"></i>
                    </button>
                </div>
            </form>

            <!-- Notes list -->
            <?php if (empty($notes)): ?>
                <p class="text-muted mt-3 mb-0">No notes yet. Add the first note above.</p>
            <?php else: ?>
            <div class="notes-timeline mt-3">
                <?php foreach ($notes as $note): ?>
                <div class="note-item d-flex gap-3 pb-3 mb-3 border-bottom">
                    <div class="note-avatar flex-shrink-0"
                         style="width:36px;height:36px;border-radius:50%;background:var(--tp-orange);
                                color:#fff;display:flex;align-items:center;justify-content:center;
                                font-weight:700;font-size:.85rem;">
                        <?= strtoupper(substr($note['author_name'] ?? '?', 0, 1)) ?>
                    </div>
                    <div class="flex-grow-1">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <strong><?= e($note['author_name'] ?? 'Unknown') ?></strong>
                            <small class="text-muted"><?= e(fmt_datetime($note['created_at'])) ?></small>
                        </div>
                        <p class="mb-0"><?= nl2br(e($note['note'])) ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

    </div><!-- /col-lg-8 -->

    <!-- ====== RIGHT COLUMN ====== -->
    <div class="col-lg-4">

        <!-- Status Updater Card -->
        <div class="tp-card mb-4">
            <div class="tp-card-header">
                <h5 class="mb-0"><i class="fa-solid fa-arrow-right-arrow-left me-2"></i>Status</h5>
            </div>
            <div class="mt-3 text-center mb-3">
                <?= status_badge($lead['status'] ?? 'new') ?>
            </div>
            <?php if (has_role('admin', 'office')): ?>
            <form method="post"
                  action="<?= APP_URL ?>/modules/leads/view.php?id=<?= (int)$lead['id'] ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_status">
                <div class="d-flex gap-2">
                    <select name="status" class="form-select form-select-sm flex-grow-1">
                        <?php foreach ($status_labels as $val => $lbl): ?>
                            <option value="<?= e($val) ?>"
                                    <?= ($lead['status'] ?? '') === $val ? 'selected' : '' ?>>
                                <?= e($lbl) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn-tp-primary btn-tp-sm">Update</button>
                </div>
            </form>
            <?php endif; ?>
        </div>

        <!-- Quick Actions Card -->
        <div class="tp-card">
            <div class="tp-card-header">
                <h5 class="mb-0"><i class="fa-solid fa-bolt me-2"></i>Quick Actions</h5>
            </div>
            <div class="qa-grid mt-3 d-flex flex-column gap-2">
                <?php if (has_role('admin', 'office')): ?>
                <a href="<?= APP_URL ?>/modules/quotes/create.php?lead_id=<?= (int)$lead['id'] ?>"
                   class="btn-tp-ghost w-100 justify-content-start">
                    <i class="fa-solid fa-file-invoice-dollar"></i> Create Quote
                </a>
                <a href="<?= APP_URL ?>/modules/customers/create.php?lead_id=<?= (int)$lead['id'] ?>"
                   class="btn-tp-ghost w-100 justify-content-start">
                    <i class="fa-solid fa-user-plus"></i> Convert to Customer
                </a>
                <a href="<?= APP_URL ?>/modules/leads/edit.php?id=<?= (int)$lead['id'] ?>"
                   class="btn-tp-ghost w-100 justify-content-start">
                    <i class="fa-solid fa-pencil"></i> Edit Lead
                </a>
                <a href="<?= APP_URL ?>/modules/leads/delete.php?id=<?= (int)$lead['id'] ?>"
                   class="btn-tp-ghost w-100 justify-content-start text-danger"
                   onclick="return confirm('Archive this lead?')">
                    <i class="fa-solid fa-box-archive"></i> Archive Lead
                </a>
                <?php else: ?>
                <p class="text-muted small mb-0">No actions available for your role.</p>
                <?php endif; ?>
            </div>
        </div>

    </div><!-- /col-lg-4 -->

</div><!-- /row -->

<?php layout_end(); ?>
