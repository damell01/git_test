<?php
/**
 * Mailer – PHP mail() wrapper with HTML template support
 * Trash Panda Roll-Offs
 */

/**
 * Send an HTML email using PHP mail().
 * Falls back gracefully on failure by logging to the notifications table.
 *
 * @param string $to
 * @param string $subject
 * @param string $html_body
 * @param string $from       Override From address; defaults to company email from settings
 * @return bool
 */
function send_email(string $to, string $subject, string $html_body, string $from = ''): bool
{
    if (empty($from)) {
        $from_name  = get_setting('email_from_name',  get_setting('company_name', 'Trash Panda Roll-Offs'));
        $from_email = get_setting('email_from_email', get_setting('company_email', 'noreply@example.com'));
        $from       = $from_name . ' <' . $from_email . '>';
    }

    $boundary = md5(uniqid('', true));

    $headers  = 'MIME-Version: 1.0' . "\r\n";
    $headers .= 'Content-Type: text/html; charset=UTF-8' . "\r\n";
    $headers .= 'From: ' . $from . "\r\n";
    $headers .= 'Reply-To: ' . $from . "\r\n";
    $headers .= 'X-Mailer: PHP/' . PHP_VERSION . "\r\n";

    $result = @mail($to, $subject, $html_body, $headers);

    // Log to notifications table regardless of result
    _log_notification('email', $to, $subject, $html_body, $result ? 'sent' : 'failed');

    return (bool)$result;
}

/**
 * Log a notification record to the notifications table.
 *
 * @param string $type    'email' or 'sms'
 * @param string $recipient
 * @param string $subject
 * @param string $body
 * @param string $status  'queued', 'sent', or 'failed'
 * @param string $related_type
 * @param int    $related_id
 */
function _log_notification(
    string $type,
    string $recipient,
    string $subject,
    string $body,
    string $status = 'sent',
    string $related_type = '',
    int $related_id = 0
): void {
    try {
        db_insert('notifications', [
            'type'         => $type,
            'recipient'    => $recipient,
            'subject'      => $subject,
            'body'         => $body,
            'status'       => $status,
            'related_type' => $related_type,
            'related_id'   => $related_id,
            'sent_at'      => $status === 'sent' ? date('Y-m-d H:i:s') : null,
            'created_at'   => date('Y-m-d H:i:s'),
        ]);
    } catch (\Throwable $e) {
        // Swallow DB errors so mailer does not break page rendering
    }
}

/**
 * Generate a full HTML email template.
 *
 * @param string $title
 * @param string $body_html
 * @param string $cta_text   Optional call-to-action button text
 * @param string $cta_url    Optional call-to-action button URL
 * @return string
 */
function email_template(string $title, string $body_html, string $cta_text = '', string $cta_url = ''): string
{
    $company_name    = get_setting('company_name',    'Trash Panda Roll-Offs');
    $company_phone   = get_setting('company_phone',   '');
    $company_email   = get_setting('company_email',   '');
    $company_address = get_setting('company_address', '');

    $cta_block = '';
    if ($cta_text && $cta_url) {
        $cta_block = '
        <tr>
          <td align="center" style="padding:20px 0 10px;">
            <a href="' . htmlspecialchars($cta_url, ENT_QUOTES, 'UTF-8') . '"
               style="background:#f97316;color:#ffffff;text-decoration:none;padding:12px 28px;
                      border-radius:6px;font-weight:700;font-size:1rem;display:inline-block;">
              ' . htmlspecialchars($cta_text, ENT_QUOTES, 'UTF-8') . '
            </a>
          </td>
        </tr>';
    }

    $footer_parts = array_filter([$company_name, $company_phone, $company_email, $company_address]);
    $footer_text  = implode(' &bull; ', array_map('htmlspecialchars', $footer_parts));

    return '<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f3f4f6;font-family:Arial,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;padding:30px 0;">
    <tr>
      <td align="center">
        <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;">

          <!-- Header -->
          <tr>
            <td style="background:#1a1d27;padding:24px 32px;border-radius:8px 8px 0 0;">
              <h1 style="margin:0;color:#f97316;font-size:1.5rem;font-weight:700;letter-spacing:.04em;">
                <span style="font-size:1.3rem;">&#128465;</span>
                ' . htmlspecialchars($company_name, ENT_QUOTES, 'UTF-8') . '
              </h1>
              <p style="margin:4px 0 0;color:#9ca3af;font-size:.85rem;">'
                . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</p>
            </td>
          </tr>

          <!-- Body -->
          <tr>
            <td style="background:#ffffff;padding:32px;border-left:1px solid #e5e7eb;border-right:1px solid #e5e7eb;">
              ' . $body_html . '
            </td>
          </tr>

          <!-- CTA -->
          ' . ($cta_block ? '<tr><td style="background:#ffffff;padding:0 32px;border-left:1px solid #e5e7eb;border-right:1px solid #e5e7eb;"><table width="100%">' . $cta_block . '</table></td></tr>' : '') . '

          <!-- Footer -->
          <tr>
            <td style="background:#f9fafb;padding:20px 32px;border:1px solid #e5e7eb;border-radius:0 0 8px 8px;
                       color:#9ca3af;font-size:.75rem;text-align:center;">
              ' . $footer_text . '<br>
              <span style="color:#d1d5db;">Powered by Trash Panda Roll-Offs Manager</span>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>';
}

/**
 * Send delivery reminder emails for work orders scheduled for tomorrow.
 */
function notify_delivery_tomorrow(): void
{
    $tomorrow = date('Y-m-d', strtotime('+1 day'));

    $wos = db_fetchall(
        "SELECT wo.*, c.email AS cust_email_db, c.name AS cust_name_db
         FROM work_orders wo
         LEFT JOIN customers c ON wo.customer_id = c.id
         WHERE wo.delivery_date = ? AND wo.status = 'scheduled'",
        [$tomorrow]
    );

    foreach ($wos as $wo) {
        $email = $wo['cust_email'] ?: ($wo['cust_email_db'] ?? '');
        if (empty($email)) {
            continue;
        }

        $name    = $wo['cust_name'] ?: ($wo['cust_name_db'] ?? 'Valued Customer');
        $address = $wo['service_address'] . ($wo['service_city'] ? ', ' . $wo['service_city'] : '');

        $body = '<p>Hello ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . ',</p>
<p>This is a reminder that your dumpster delivery is scheduled for <strong>tomorrow, '
. date('l, F j, Y', strtotime($tomorrow)) . '</strong>.</p>
<p><strong>Delivery Address:</strong> ' . htmlspecialchars($address, ENT_QUOTES, 'UTF-8') . '</p>
<p><strong>Dumpster Size:</strong> ' . htmlspecialchars($wo['size'] ?? 'N/A', ENT_QUOTES, 'UTF-8') . '</p>
<p>Please ensure the area is accessible for our driver. If you need to reschedule, contact us as soon as possible.</p>
<p>Thank you for choosing us!</p>';

        $html = email_template('Delivery Reminder', $body);
        send_email($email, 'Your Dumpster Delivery Is Tomorrow!', $html);
    }
}

/**
 * Send overdue pickup alerts to the internal company email.
 */
function notify_pickup_overdue(): void
{
    $today = date('Y-m-d');

    $wos = db_fetchall(
        "SELECT wo.*, c.email AS cust_email_db
         FROM work_orders wo
         LEFT JOIN customers c ON wo.customer_id = c.id
         WHERE wo.pickup_date < ? AND wo.status NOT IN ('picked_up','completed','canceled')
         ORDER BY wo.pickup_date ASC",
        [$today]
    );

    if (empty($wos)) {
        return;
    }

    $company_email = get_setting('company_email', '');
    if (empty($company_email)) {
        return;
    }

    $rows = '';
    foreach ($wos as $wo) {
        $rows .= '<tr>
          <td style="padding:8px;border:1px solid #e5e7eb;">' . htmlspecialchars($wo['wo_number'], ENT_QUOTES, 'UTF-8') . '</td>
          <td style="padding:8px;border:1px solid #e5e7eb;">' . htmlspecialchars($wo['cust_name'], ENT_QUOTES, 'UTF-8') . '</td>
          <td style="padding:8px;border:1px solid #e5e7eb;">' . htmlspecialchars($wo['service_address'], ENT_QUOTES, 'UTF-8') . '</td>
          <td style="padding:8px;border:1px solid #e5e7eb;">' . htmlspecialchars($wo['pickup_date'], ENT_QUOTES, 'UTF-8') . '</td>
          <td style="padding:8px;border:1px solid #e5e7eb;">' . htmlspecialchars($wo['status'], ENT_QUOTES, 'UTF-8') . '</td>
        </tr>';
    }

    $body = '<p>The following work orders have passed their scheduled pickup date and are still active:</p>
<table width="100%" style="border-collapse:collapse;font-size:.9rem;">
  <thead>
    <tr style="background:#f3f4f6;">
      <th style="padding:8px;border:1px solid #e5e7eb;text-align:left;">WO#</th>
      <th style="padding:8px;border:1px solid #e5e7eb;text-align:left;">Customer</th>
      <th style="padding:8px;border:1px solid #e5e7eb;text-align:left;">Address</th>
      <th style="padding:8px;border:1px solid #e5e7eb;text-align:left;">Pickup Date</th>
      <th style="padding:8px;border:1px solid #e5e7eb;text-align:left;">Status</th>
    </tr>
  </thead>
  <tbody>' . $rows . '</tbody>
</table>';

    $html = email_template('Overdue Pickup Alert', $body);
    send_email($company_email, 'Overdue Pickup Alert — ' . count($wos) . ' Work Orders', $html);
}

/**
 * Send an invoice email to the customer.
 *
 * @param int $invoice_id
 * @return bool
 */
function send_invoice_email(int $invoice_id): bool
{
    $inv = db_fetch(
        'SELECT i.*, wo.wo_number, wo.service_address, wo.service_city, wo.size,
                c.name AS cust_name, c.email AS cust_email
         FROM invoices i
         LEFT JOIN work_orders wo ON i.work_order_id = wo.id
         LEFT JOIN customers   c  ON i.customer_id   = c.id
         WHERE i.id = ? LIMIT 1',
        [$invoice_id]
    );

    if (!$inv) {
        return false;
    }

    $email = $inv['cust_email'] ?? '';
    if (empty($email)) {
        return false;
    }

    $company_name = get_setting('company_name', 'Trash Panda Roll-Offs');
    $due          = $inv['due_date'] ? date('F j, Y', strtotime($inv['due_date'])) : 'Upon receipt';

    $body = '<p>Hello ' . htmlspecialchars($inv['cust_name'] ?? 'Valued Customer', ENT_QUOTES, 'UTF-8') . ',</p>
<p>Please find your invoice details below.</p>
<table width="100%" style="border-collapse:collapse;font-size:.95rem;margin-bottom:16px;">
  <tr><td style="padding:6px 0;color:#6b7280;">Invoice #:</td><td><strong>' . htmlspecialchars($inv['invoice_number'], ENT_QUOTES, 'UTF-8') . '</strong></td></tr>
  <tr><td style="padding:6px 0;color:#6b7280;">Work Order #:</td><td>' . htmlspecialchars($inv['wo_number'] ?? 'N/A', ENT_QUOTES, 'UTF-8') . '</td></tr>
  <tr><td style="padding:6px 0;color:#6b7280;">Service Address:</td><td>' . htmlspecialchars(($inv['service_address'] ?? '') . ($inv['service_city'] ? ', ' . $inv['service_city'] : ''), ENT_QUOTES, 'UTF-8') . '</td></tr>
  <tr><td style="padding:6px 0;color:#6b7280;">Amount Due:</td><td><strong style="color:#f97316;font-size:1.1rem;">$' . number_format((float)$inv['amount'] - (float)$inv['amount_paid'], 2) . '</strong></td></tr>
  <tr><td style="padding:6px 0;color:#6b7280;">Due Date:</td><td>' . $due . '</td></tr>
</table>
<p>Please contact us if you have any questions.</p>';

    $portal_url = APP_URL . '/modules/portal/index.php';
    $html = email_template('Invoice ' . $inv['invoice_number'], $body, 'Pay Online', $portal_url);

    $result = send_email($email, 'Invoice ' . $inv['invoice_number'] . ' from ' . $company_name, $html);

    if ($result) {
        db_execute('UPDATE invoices SET sent_at = NOW() WHERE id = ?', [$invoice_id]);
    }

    return $result;
}

/**
 * Send a payment receipt email.
 *
 * @param int $payment_id
 * @return bool
 */
function send_receipt_email(int $payment_id): bool
{
    $pay = db_fetch(
        'SELECT p.*, wo.wo_number, c.name AS cust_name, c.email AS cust_email
         FROM payments p
         LEFT JOIN work_orders wo ON p.work_order_id = wo.id
         LEFT JOIN customers   c  ON p.customer_id   = c.id
         WHERE p.id = ? LIMIT 1',
        [$payment_id]
    );

    if (!$pay) {
        return false;
    }

    $email = $pay['cust_email'] ?? '';
    if (empty($email)) {
        return false;
    }

    $company_name = get_setting('company_name', 'Trash Panda Roll-Offs');
    $paid_at = $pay['paid_at'] ? date('F j, Y g:i A', strtotime($pay['paid_at'])) : date('F j, Y');

    $body = '<p>Hello ' . htmlspecialchars($pay['cust_name'] ?? 'Valued Customer', ENT_QUOTES, 'UTF-8') . ',</p>
<p>Thank you for your payment. Here are your receipt details:</p>
<table width="100%" style="border-collapse:collapse;font-size:.95rem;margin-bottom:16px;">
  <tr><td style="padding:6px 0;color:#6b7280;">Receipt #:</td><td><strong>REC-' . str_pad((string)$pay['id'], 5, '0', STR_PAD_LEFT) . '</strong></td></tr>
  <tr><td style="padding:6px 0;color:#6b7280;">Work Order #:</td><td>' . htmlspecialchars($pay['wo_number'] ?? 'N/A', ENT_QUOTES, 'UTF-8') . '</td></tr>
  <tr><td style="padding:6px 0;color:#6b7280;">Amount Paid:</td><td><strong style="color:#22c55e;font-size:1.1rem;">$' . number_format((float)$pay['amount'], 2) . '</strong></td></tr>
  <tr><td style="padding:6px 0;color:#6b7280;">Method:</td><td>' . htmlspecialchars(ucfirst($pay['method']), ENT_QUOTES, 'UTF-8') . '</td></tr>
  <tr><td style="padding:6px 0;color:#6b7280;">Date:</td><td>' . $paid_at . '</td></tr>
</table>
<p>Thank you for choosing ' . htmlspecialchars($company_name, ENT_QUOTES, 'UTF-8') . '!</p>';

    $receipt_url = APP_URL . '/modules/payments/receipt.php?id=' . $pay['id'];
    $html = email_template('Payment Receipt', $body, 'View Receipt', $receipt_url);

    $result = send_email($email, 'Payment Receipt from ' . $company_name, $html);

    if ($result) {
        db_execute('UPDATE payments SET receipt_sent = 1 WHERE id = ?', [$payment_id]);
    }

    return $result;
}

/**
 * Send a portal magic link to a customer.
 *
 * @param int    $customer_id
 * @param string $token
 * @return bool
 */
function send_portal_magic_link(int $customer_id, string $token): bool
{
    $customer = db_fetch('SELECT * FROM customers WHERE id = ? LIMIT 1', [$customer_id]);
    if (!$customer || empty($customer['email'])) {
        return false;
    }

    $company_name = get_setting('company_name', 'Trash Panda Roll-Offs');
    $magic_url    = APP_URL . '/modules/portal/dashboard.php?token=' . urlencode($token);

    $body = '<p>Hello ' . htmlspecialchars($customer['name'], ENT_QUOTES, 'UTF-8') . ',</p>
<p>Click the button below to access your customer portal. This link expires in 24 hours.</p>
<p style="color:#9ca3af;font-size:.85rem;">If you did not request this link, you can safely ignore this email.</p>';

    $html = email_template('Your Portal Access Link', $body, 'Access My Portal', $magic_url);

    return send_email($customer['email'], 'Your Portal Access Link — ' . $company_name, $html);
}
