<?php
/**
 * Mailer – PHPMailer (preferred) or PHP mail() fallback
 * Trash Panda Roll-Offs
 *
 * When Composer dependencies are installed (composer install run inside admin/),
 * PHPMailer is used automatically for reliable SMTP delivery.
 * Otherwise the system falls back to PHP's built-in mail().
 *
 * SMTP settings are read from the admin settings table:
 *   smtp_host, smtp_port, smtp_username, smtp_password, smtp_encryption (tls|ssl|none)
 * Leave smtp_host blank to use PHP mail() even if PHPMailer is installed.
 */

// Load PHPMailer autoloader when available
$_tp_phpmailer_autoload = ROOT_PATH . '/vendor/autoload.php';
if (file_exists($_tp_phpmailer_autoload)) {
    require_once $_tp_phpmailer_autoload;
}
unset($_tp_phpmailer_autoload);

/**
 * Send an HTML email, using PHPMailer+SMTP when configured, otherwise mail().
 *
 * @param string $to
 * @param string $subject
 * @param string $html_body
 * @param string $from  Override From; defaults to company email from settings
 * @return bool
 */
function send_email(string $to, string $subject, string $html_body, string $from = ''): bool
{
    $from_name  = get_setting('email_from_name',  get_setting('company_name', 'Trash Panda Roll-Offs'));
    $from_email = get_setting('email_from_email', get_setting('company_email', 'noreply@example.com'));

    if (!empty($from)) {
        // Parse "Name <email>" if provided, otherwise use as-is for the email address
        if (preg_match('/^(.+)<(.+)>$/', $from, $m)) {
            $from_name  = trim($m[1]);
            $from_email = trim($m[2]);
        } else {
            $from_email = $from;
        }
    }

    $smtp_host = get_setting('smtp_host', '');

    // ── PHPMailer + SMTP ─────────────────────────────────────────────────────
    if (!empty($smtp_host) && class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
        try {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = $smtp_host;
            $mail->Port       = (int) get_setting('smtp_port', 587);
            $mail->SMTPAuth   = true;
            $mail->Username   = get_setting('smtp_username', '');
            $mail->Password   = get_setting('smtp_password', '');

            $enc = strtolower(get_setting('smtp_encryption', 'tls'));
            if ($enc === 'ssl') {
                $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($enc === 'tls') {
                $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            } else {
                $mail->SMTPAutoTLS = false;
            }

            $mail->setFrom($from_email, $from_name);
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->CharSet = 'UTF-8';
            $mail->Subject = $subject;
            $mail->Body    = $html_body;
            $mail->AltBody = strip_tags($html_body);

            $mail->send();
            _log_notification('email', $to, $subject, $html_body, 'sent');
            return true;
        } catch (\Throwable $e) {
            _log_notification('email', $to, $subject, $html_body, 'failed');
            return false;
        }
    }

    // ── PHP mail() fallback ──────────────────────────────────────────────────
    $headers  = 'MIME-Version: 1.0' . "\r\n";
    $headers .= 'Content-Type: text/html; charset=UTF-8' . "\r\n";
    $headers .= 'From: ' . $from_name . ' <' . $from_email . '>' . "\r\n";
    $headers .= 'Reply-To: ' . $from_email . "\r\n";
    $headers .= 'X-Mailer: PHP/' . PHP_VERSION . "\r\n";

    $result = @mail($to, $subject, $html_body, $headers);

    _log_notification('email', $to, $subject, $html_body, $result ? 'sent' : 'failed');

    return (bool)$result;
}

/**
 * Send an email to every address listed in the admin notification_emails setting.
 * Comma-separated list. Silently skips invalid addresses.
 *
 * @param string $subject
 * @param string $html_body
 * @return int  Number of successfully sent messages
 */
function notify_admins(string $subject, string $html_body): int
{
    $raw     = get_setting('notification_emails', get_setting('company_email', ''));
    $emails  = array_filter(array_map('trim', explode(',', $raw)));
    $sent    = 0;
    foreach ($emails as $email) {
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            if (send_email($email, $subject, $html_body)) {
                $sent++;
            }
        }
    }
    return $sent;
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
