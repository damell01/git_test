<?php
/**
 * Web Push Notification Helper — Trash Panda Roll-Offs
 *
 * Uses minishlink/web-push (VAPID) when the Composer autoloader is present.
 * Falls back silently when the library is not installed.
 *
 * VAPID keys are generated automatically on first use and stored in the
 * settings table (vapid_public_key, vapid_private_key, vapid_subject).
 *
 * Usage:
 *   push_notify_admins('New Booking', 'B-0012 from John Doe', '/admin/modules/bookings/view.php?id=5');
 *   push_notify_customer('john@example.com', 'Booking Confirmed', 'Your rental starts tomorrow');
 */

/**
 * Ensure VAPID keys are initialised in settings and return them.
 * Returns null if the WebPush library is unavailable.
 *
 * @return array{public: string, private: string, subject: string}|null
 */
function _push_vapid_keys(): ?array
{
    if (!class_exists('Minishlink\\WebPush\\WebPush')) {
        return null;
    }

    $pub  = get_setting('vapid_public_key',  '');
    $priv = get_setting('vapid_private_key', '');

    if (empty($pub) || empty($priv)) {
        // Generate a new VAPID key pair
        try {
            $keys = \Minishlink\WebPush\VAPID::createVapidKeys();
        } catch (\Throwable $e) {
            error_log('[Push] VAPID key generation failed: ' . $e->getMessage());
            return null;
        }

        $pub  = $keys['publicKey'];
        $priv = $keys['privateKey'];

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = preg_replace('/[^a-zA-Z0-9\-\.:]/', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
        $subject = $scheme . '://' . $host;

        try {
            db_execute("REPLACE INTO settings (`key`, `value`) VALUES ('vapid_public_key', ?)",  [$pub]);
            db_execute("REPLACE INTO settings (`key`, `value`) VALUES ('vapid_private_key', ?)", [$priv]);
            db_execute("REPLACE INTO settings (`key`, `value`) VALUES ('vapid_subject', ?)",     [$subject]);
        } catch (\Throwable $e) {
            error_log('[Push] Failed to save VAPID keys: ' . $e->getMessage());
            return null;
        }
    }

    $subject = get_setting('vapid_subject', '');
    if (empty($subject)) {
        $scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host    = preg_replace('/[^a-zA-Z0-9\-\.:]/', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
        $subject = $scheme . '://' . $host;
    }

    return ['public' => $pub, 'private' => $priv, 'subject' => $subject];
}

/**
 * Return the VAPID public key (base64url string) for embedding in the front-end.
 * Returns an empty string if unavailable.
 */
function push_vapid_public_key(): string
{
    $keys = _push_vapid_keys();
    return $keys ? $keys['public'] : '';
}

/**
 * Save a push subscription (upsert by endpoint).
 *
 * @param string $subscriber_type 'admin' or 'customer'
 * @param string $subscriber_id   Admin user_id (as string) or customer email/phone
 * @param array  $subscription    Keys: endpoint, keys.p256dh, keys.auth
 * @param string $user_agent
 */
function push_save_subscription(
    string $subscriber_type,
    string $subscriber_id,
    array  $subscription,
    string $user_agent = ''
): void {
    $endpoint = trim($subscription['endpoint'] ?? '');
    $p256dh   = trim($subscription['keys']['p256dh'] ?? '');
    $auth     = trim($subscription['keys']['auth']   ?? '');

    if (empty($endpoint) || empty($p256dh) || empty($auth)) {
        return;
    }

    try {
        db_execute(
            "INSERT INTO push_subscriptions
               (subscriber_type, subscriber_id, endpoint, p256dh, auth, user_agent, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
             ON DUPLICATE KEY UPDATE
               subscriber_type = VALUES(subscriber_type),
               subscriber_id   = VALUES(subscriber_id),
               p256dh          = VALUES(p256dh),
               auth            = VALUES(auth),
               user_agent      = VALUES(user_agent),
               updated_at      = NOW()",
            [$subscriber_type, $subscriber_id, $endpoint, $p256dh, $auth, $user_agent ?: null]
        );
    } catch (\Throwable $e) {
        error_log('[Push] Failed to save subscription: ' . $e->getMessage());
    }
}

/**
 * Delete a push subscription by endpoint.
 */
function push_delete_subscription(string $endpoint): void
{
    try {
        db_execute("DELETE FROM push_subscriptions WHERE endpoint = ?", [trim($endpoint)]);
    } catch (\Throwable $e) {
        error_log('[Push] Failed to delete subscription: ' . $e->getMessage());
    }
}

/**
 * Send a push notification to one subscription row.
 *
 * @param \Minishlink\WebPush\WebPush $webPush
 * @param array  $sub_row   Row from push_subscriptions
 * @param string $title
 * @param string $body
 * @param string $url        Click destination (relative or absolute)
 * @param string $icon
 */
function _push_send_one(
    \Minishlink\WebPush\WebPush $webPush,
    array  $sub_row,
    string $title,
    string $body,
    string $url  = '/',
    string $icon = '/assets/icon-192.png'
): void {
    $payload = json_encode([
        'title' => $title,
        'body'  => $body,
        'url'   => $url,
        'icon'  => $icon,
        'badge' => '/assets/icon-192.png',
    ]);

    $subscription = \Minishlink\WebPush\Subscription::create([
        'endpoint' => $sub_row['endpoint'],
        'keys'     => [
            'p256dh' => $sub_row['p256dh'],
            'auth'   => $sub_row['auth'],
        ],
    ]);

    $webPush->queueNotification($subscription, $payload);
}

/**
 * Build a WebPush instance using stored VAPID keys.
 * Returns null if library or keys are unavailable.
 */
function _push_build_webpush(): ?\Minishlink\WebPush\WebPush
{
    $keys = _push_vapid_keys();
    if ($keys === null) {
        return null;
    }

    try {
        return new \Minishlink\WebPush\WebPush([
            'VAPID' => [
                'subject'    => $keys['subject'],
                'publicKey'  => $keys['public'],
                'privateKey' => $keys['private'],
            ],
        ]);
    } catch (\Throwable $e) {
        error_log('[Push] Failed to build WebPush instance: ' . $e->getMessage());
        return null;
    }
}

/**
 * Send push notifications to all admin subscribers.
 *
 * @param string $title
 * @param string $body
 * @param string $url  Click destination (admin panel URL)
 */
function push_notify_admins(string $title, string $body, string $url = ''): void
{
    $webPush = _push_build_webpush();
    if ($webPush === null) {
        return;
    }

    try {
        $subs = db_fetchall(
            "SELECT * FROM push_subscriptions WHERE subscriber_type = 'admin'",
            []
        );
    } catch (\Throwable $e) {
        error_log('[Push] Failed to query admin subscriptions: ' . $e->getMessage());
        return;
    }

    if (empty($subs)) {
        return;
    }

    $click_url = $url ?: (defined('APP_URL') ? APP_URL . '/dashboard.php' : '/admin/dashboard.php');
    $icon = defined('ASSET_PATH') ? ASSET_PATH . '/img/icon-192.png' : '/admin/assets/img/icon-192.png';

    foreach ($subs as $sub) {
        _push_send_one($webPush, $sub, $title, $body, $click_url, $icon);
    }

    _push_flush($webPush);
}

/**
 * Send push notifications to all subscriptions tied to a customer identifier
 * (email address or phone number).
 *
 * @param string $identifier  Customer email or normalised phone
 * @param string $title
 * @param string $body
 * @param string $url         Click destination on the public site
 */
function push_notify_customer(string $identifier, string $title, string $body, string $url = '/my-bookings.php'): void
{
    if (empty(trim($identifier))) {
        return;
    }

    $webPush = _push_build_webpush();
    if ($webPush === null) {
        return;
    }

    try {
        $subs = db_fetchall(
            "SELECT * FROM push_subscriptions
              WHERE subscriber_type = 'customer' AND subscriber_id = ?",
            [trim($identifier)]
        );
    } catch (\Throwable $e) {
        error_log('[Push] Failed to query customer subscriptions: ' . $e->getMessage());
        return;
    }

    if (empty($subs)) {
        return;
    }

    foreach ($subs as $sub) {
        _push_send_one($webPush, $sub, $title, $body, $url, '/assets/icon-192.png');
    }

    _push_flush($webPush);
}

/**
 * Flush queued push notifications and clean up expired subscriptions.
 */
function _push_flush(\Minishlink\WebPush\WebPush $webPush): void
{
    try {
        foreach ($webPush->flush() as $report) {
            if (!$report->isSuccess()) {
                $endpoint = $report->getEndpoint();
                // 404 / 410 means the subscription is no longer valid; remove it.
                if (in_array($report->getResponse()?->getStatusCode(), [404, 410], true)) {
                    push_delete_subscription($endpoint);
                } else {
                    error_log('[Push] Delivery failed for endpoint ' . substr($endpoint, 0, 60) . '…: ' . $report->getReason());
                }
            }
        }
    } catch (\Throwable $e) {
        error_log('[Push] Flush error: ' . $e->getMessage());
    }
}
