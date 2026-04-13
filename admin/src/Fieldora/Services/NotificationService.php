<?php

namespace TrashPanda\Fieldora\Services;

use PHPMailer\PHPMailer\Exception as MailException;
use PHPMailer\PHPMailer\PHPMailer;
use RuntimeException;

class NotificationService
{
    public static function queue(
        int $tenantId,
        string $channel,
        string $recipient,
        ?string $subject,
        string $body,
        array $meta = []
    ): int {
        return (int) \db_insert('notifications', [
            'tenant_id' => $tenantId,
            'customer_id' => $meta['customer_id'] ?? null,
            'booking_id' => $meta['booking_id'] ?? null,
            'invoice_id' => $meta['invoice_id'] ?? null,
            'channel' => $channel,
            'template_key' => $meta['template_key'] ?? null,
            'recipient' => trim($recipient),
            'subject' => $subject,
            'body' => $body,
            'status' => 'queued',
            'provider' => $meta['provider'] ?? ($channel === 'email' ? 'smtp' : 'twilio'),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public static function queueTemplate(
        int $tenantId,
        string $templateKey,
        string $channel,
        string $recipient,
        array $context,
        array $meta = []
    ): int {
        $template = \db_fetch(
            'SELECT * FROM notification_templates WHERE tenant_id = ? AND template_key = ? AND channel = ? LIMIT 1',
            [$tenantId, $templateKey, $channel]
        );

        if (!$template) {
            $template = \db_fetch(
                'SELECT * FROM notification_templates WHERE tenant_id IS NULL AND template_key = ? AND channel = ? LIMIT 1',
                [$templateKey, $channel]
            );
        }

        $subject = self::renderTemplate((string) ($template['subject_template'] ?? ''), $context);
        $body = self::renderTemplate((string) ($template['body_template'] ?? ''), $context);

        return self::queue($tenantId, $channel, $recipient, $subject !== '' ? $subject : null, $body, $meta + [
            'template_key' => $templateKey,
        ]);
    }

    public static function processQueued(int $limit = 50): void
    {
        $notifications = \db_fetchall(
            "SELECT * FROM notifications WHERE status = 'queued' ORDER BY created_at ASC LIMIT " . max(1, $limit)
        );

        foreach ($notifications as $notification) {
            try {
                if (($notification['channel'] ?? '') === 'email') {
                    self::sendEmail(
                        (int) $notification['tenant_id'],
                        (string) $notification['recipient'],
                        (string) ($notification['subject'] ?? 'Fieldora notification'),
                        (string) ($notification['body'] ?? '')
                    );
                    \db_execute(
                        'UPDATE notifications SET status = ?, provider = ?, error_message = NULL, sent_at = NOW() WHERE id = ?',
                        ['sent', 'smtp', $notification['id']]
                    );
                } elseif (($notification['channel'] ?? '') === 'sms') {
                    if (!tenant_has_feature_runtime((int) $notification['tenant_id'], 'sms_notifications')) {
                        throw new RuntimeException('SMS notifications are not enabled for this plan.');
                    }

                    $sid = TenantService::setting((int) $notification['tenant_id'], 'twilio_sid', '');
                    $token = TenantService::setting((int) $notification['tenant_id'], 'twilio_token', '');
                    $from = TenantService::setting((int) $notification['tenant_id'], 'twilio_from', '');
                    if ($sid === '' || $token === '' || $from === '') {
                        throw new RuntimeException('Twilio is not configured for this tenant.');
                    }

                    \db_execute(
                        'UPDATE notifications SET status = ?, provider = ?, error_message = NULL, sent_at = NOW() WHERE id = ?',
                        ['sent', 'twilio-ready', $notification['id']]
                    );
                    \db_insert('sms_usage_logs', [
                        'tenant_id' => $notification['tenant_id'],
                        'notification_id' => $notification['id'],
                        'recipient' => $notification['recipient'],
                        'segments' => 1,
                        'status' => 'sent',
                        'created_at' => date('Y-m-d H:i:s'),
                    ]);
                }
            } catch (\Throwable $e) {
                \db_execute(
                    'UPDATE notifications SET status = ?, error_message = ? WHERE id = ?',
                    ['failed', $e->getMessage(), $notification['id']]
                );
                self::logError((int) $notification['tenant_id'], 'Notification delivery failed', [
                    'notification_id' => (int) $notification['id'],
                    'channel' => $notification['channel'],
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    public static function sendEmail(int $tenantId, string $to, string $subject, string $body): void
    {
        $host = TenantService::setting($tenantId, 'smtp_host', '');
        if ($host === '') {
            throw new RuntimeException('SMTP host is not configured.');
        }
        if (!class_exists(PHPMailer::class)) {
            throw new RuntimeException('PHPMailer is not installed. Run Composer install in admin/.');
        }

        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = $host;
            $mail->Port = (int) TenantService::setting($tenantId, 'smtp_port', '587');
            $mail->SMTPAuth = true;
            $mail->Username = TenantService::setting($tenantId, 'smtp_username', '');
            $mail->Password = TenantService::setting($tenantId, 'smtp_password', '');

            $encryption = strtolower(TenantService::setting($tenantId, 'smtp_encryption', 'tls'));
            if ($encryption === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($encryption === 'tls') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } else {
                $mail->SMTPSecure = false;
                $mail->SMTPAutoTLS = false;
            }

            $fromAddress = TenantService::setting($tenantId, 'smtp_from_email', '') ?: (TenantService::find($tenantId)['business_email'] ?? '');
            $fromName = TenantService::setting($tenantId, 'smtp_from_name', '') ?: (TenantService::find($tenantId)['name'] ?? APP_NAME);
            if ($fromAddress === '') {
                throw new RuntimeException('SMTP from email is not configured.');
            }

            $mail->setFrom($fromAddress, $fromName);
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = nl2br($body);
            $mail->AltBody = strip_tags(str_replace('<br>', "\n", nl2br($body)));
            $mail->send();
        } catch (MailException $e) {
            throw new RuntimeException('SMTP send failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public static function renderTemplate(string $template, array $context): string
    {
        $replacements = [];
        foreach ($context as $key => $value) {
            $replacements['{{' . $key . '}}'] = (string) $value;
        }

        return strtr($template, $replacements);
    }

    private static function logError(int $tenantId, string $message, array $context = []): void
    {
        \db_insert('error_logs', [
            'tenant_id' => $tenantId ?: null,
            'level' => 'error',
            'message' => $message,
            'context_json' => json_encode($context, JSON_UNESCAPED_SLASHES),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}

function tenant_has_feature_runtime(int $tenantId, string $featureKey): bool
{
    return FeatureService::tenantHasFeature($tenantId, $featureKey);
}
