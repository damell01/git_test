<?php
require_once __DIR__ . '/_bootstrap.php';

require_permission('webhooks.manage');
require_feature('webhooks');

$example = [
    'event' => 'booking.created',
    'tenant_id' => 123,
    'timestamp' => gmdate('c'),
    'data' => [
        'booking' => [
            'id' => 42,
            'booking_number' => 'BK-00042',
            'status' => 'requested',
            'scheduled_date' => '2026-04-13',
            'customer' => [
                'id' => 88,
                'name' => 'Jordan Lee',
                'email' => 'jordan@example.com',
                'phone' => '+1 555-0100',
            ],
        ],
    ],
];

fieldora_layout_start('Webhook Docs', 'webhooks');
?>
<section class="card stack">
    <h3>Payload format</h3>
    <p class="muted">Every Fieldora webhook sends the same top-level structure so Zapier, n8n, Make, and custom handlers can map data consistently.</p>
    <pre style="white-space:pre-wrap;overflow:auto;"><?= e(json_encode($example, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre>
</section>

<section class="card stack" style="margin-top:20px;">
    <h3>Headers</h3>
    <p><code>X-Fieldora-Signature</code>: HMAC SHA256 of the raw JSON body using your endpoint secret.</p>
    <p><code>X-Fieldora-Event</code>: The event name, such as <code>booking.created</code>.</p>
</section>

<section class="card stack" style="margin-top:20px;">
    <h3>Verify signature</h3>
    <p>Compute HMAC SHA256 of the raw request body using the endpoint secret. Compare it to <code>X-Fieldora-Signature</code>.</p>
    <pre style="white-space:pre-wrap;overflow:auto;"><code><?= e("\$expected = hash_hmac('sha256', \$rawBody, \$endpointSecret);\n\$valid = hash_equals(\$expected, \$_SERVER['HTTP_X_FIELDORA_SIGNATURE'] ?? '');") ?></code></pre>
</section>

<section class="card stack" style="margin-top:20px;">
    <h3>Testing</h3>
    <p>Create an endpoint, use "Send test event", and point the URL at webhook.site, Zapier, n8n, or Make to inspect the incoming request.</p>
    <p class="muted">Fieldora posts clean JSON with a short timeout and retries failed deliveries in the background, so webhook failures do not block bookings, invoices, or payments.</p>
</section>
<?php fieldora_layout_end();
