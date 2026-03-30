<?php
/**
 * Notifications – Manual Send
 * Trash Panda Roll-Offs
 */

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once INC_PATH . '/mailer.php';
require_once TMPL_PATH . '/layout.php';
require_login();
require_role('admin', 'office');

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $type      = trim($_POST['type']      ?? 'email');
    $recipient = trim($_POST['recipient'] ?? '');
    $subject   = trim($_POST['subject']   ?? '');
    $body      = trim($_POST['body']      ?? '');

    if ($type === 'email') {
        if (empty($recipient) || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'A valid recipient email is required.';
        }
        if (empty($subject)) {
            $errors[] = 'Subject is required.';
        }
        if (empty($body)) {
            $errors[] = 'Message body is required.';
        }
    } else {
        if (empty($recipient)) {
            $errors[] = 'Recipient phone/identifier is required.';
        }
        if (empty($body)) {
            $errors[] = 'Message body is required.';
        }
    }

    if (empty($errors)) {
        if ($type === 'email') {
            $html   = email_template('Message from ' . get_setting('company_name', 'Trash Panda Roll-Offs'), '<p>' . nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8')) . '</p>');
            $result = send_email($recipient, $subject, $html);
        } else {
            // SMS placeholder — log only
            _log_notification('sms', $recipient, $subject ?: '(SMS)', $body, 'sent');
            $result = true;
        }

        if ($result) {
            flash_success('Notification sent successfully.');
        } else {
            flash_error('Notification failed to send. Check the log for details.');
        }
        redirect('index.php');
    }
}

layout_start('Send Notification', 'notifications');
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">Send Manual Notification</h5>
    <a href="index.php" class="btn-tp-ghost btn-tp-sm"><i class="fa-solid fa-arrow-left"></i> Back</a>
</div>

<div class="tp-card" style="max-width:620px;">

    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger mb-3">
        <ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></li><?php endforeach; ?></ul>
    </div>
    <?php endif; ?>

    <form method="POST" action="send.php">
        <?= csrf_field() ?>

        <div class="mb-3">
            <label class="form-label">Notification Type</label>
            <select name="type" id="notif-type" class="form-select">
                <option value="email" <?= ($_POST['type'] ?? 'email') === 'email' ? 'selected' : '' ?>>Email</option>
                <option value="sms"   <?= ($_POST['type'] ?? '') === 'sms'   ? 'selected' : '' ?>>SMS (placeholder)</option>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label" for="recipient">Recipient</label>
            <input type="text" id="recipient" name="recipient" class="form-control"
                   placeholder="Email address or phone number"
                   value="<?= htmlspecialchars($_POST['recipient'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <div class="mb-3" id="subject-row">
            <label class="form-label" for="subject">Subject</label>
            <input type="text" id="subject" name="subject" class="form-control"
                   value="<?= htmlspecialchars($_POST['subject'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <div class="mb-3">
            <label class="form-label" for="body">Message</label>
            <textarea id="body" name="body" class="form-control" rows="5"><?= htmlspecialchars($_POST['body'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn-tp-primary">
                <i class="fa-solid fa-paper-plane"></i> Send
            </button>
            <a href="index.php" class="btn-tp-ghost">Cancel</a>
        </div>
    </form>
</div>

<script>
document.getElementById('notif-type').addEventListener('change', function() {
    document.getElementById('subject-row').style.display = this.value === 'email' ? '' : 'none';
});
</script>

<?php layout_end(); ?>
