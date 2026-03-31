<?php
/**
 * Stripe helper – Trash Panda Roll-Offs
 * Uses the Stripe PHP SDK (installed via Composer).
 * SECRET KEY IS NEVER EXPOSED TO FRONTEND.
 */

function stripe_client(): \Stripe\StripeClient
{
    static $client = null;
    if ($client === null) {
        $secret = get_setting('stripe_secret_key', '');
        if (empty($secret)) {
            throw new \RuntimeException('Stripe secret key is not configured.');
        }
        $client = new \Stripe\StripeClient($secret);
    }
    return $client;
}

/**
 * Create a Stripe Checkout Session for a booking.
 */
function stripe_create_checkout(array $booking, string $success_url, string $cancel_url): \Stripe\Checkout\Session
{
    $currency     = get_setting('currency', 'usd');
    $company      = get_setting('company_name', 'Trash Panda Roll-Offs');
    $amount_cents = (int)round((float)$booking['total_amount'] * 100);

    $description = sprintf(
        '%s %s — %s to %s',
        $booking['unit_size'] ?? '',
        ucfirst($booking['unit_type'] ?? 'Dumpster'),
        fmt_date($booking['rental_start']),
        fmt_date($booking['rental_end'])
    );

    $session_params = [
        'payment_method_types' => ['card'],
        'line_items' => [[
            'price_data' => [
                'currency'     => strtolower($currency),
                'product_data' => [
                    'name'        => $company . ' — Dumpster Rental',
                    'description' => $description,
                ],
                'unit_amount'  => $amount_cents,
            ],
            'quantity' => 1,
        ]],
        'mode'        => 'payment',
        'success_url' => $success_url,
        'cancel_url'  => $cancel_url,
        'metadata'    => [
            'booking_id'      => (string)$booking['id'],
            'booking_number'  => $booking['booking_number'],
            'customer_name'   => $booking['customer_name'] ?? '',
            'customer_phone'  => $booking['customer_phone'] ?? '',
            'unit_code'       => $booking['unit_code'] ?? '',
            'rental_start'    => $booking['rental_start'] ?? '',
            'rental_end'      => $booking['rental_end'] ?? '',
        ],
        'payment_intent_data' => [
            'metadata' => [
                'booking_id'     => (string)$booking['id'],
                'booking_number' => $booking['booking_number'] ?? '',
                'customer_name'  => $booking['customer_name'] ?? '',
                'unit_code'      => $booking['unit_code'] ?? '',
            ],
        ],
        'customer_email' => $booking['customer_email'] ?? null,
    ];

    // For $0 amounts, allow checkout completion without requiring a payment method.
    if ($amount_cents === 0) {
        $session_params['payment_method_collection'] = 'if_required';
    }

    return stripe_client()->checkout->sessions->create($session_params);
}

/**
 * Create a Stripe Checkout Session for multiple bookings in a single payment.
 *
 * @param array  $bookings     Array of booking rows from the database
 * @param string $success_url
 * @param string $cancel_url
 */
function stripe_create_multi_checkout(array $bookings, string $success_url, string $cancel_url): \Stripe\Checkout\Session
{
    $currency = get_setting('currency', 'usd');
    $company  = get_setting('company_name', 'Trash Panda Roll-Offs');

    $line_items    = [];
    $total_cents   = 0;
    $booking_ids   = [];
    $booking_nums  = [];

    foreach ($bookings as $booking) {
        $amount_cents = (int)round((float)$booking['total_amount'] * 100);
        $total_cents += $amount_cents;

        $description = sprintf(
            '%s %s — %s to %s',
            $booking['unit_size'] ?? '',
            ucfirst($booking['unit_type'] ?? 'Dumpster'),
            fmt_date($booking['rental_start']),
            fmt_date($booking['rental_end'])
        );

        $line_items[] = [
            'price_data' => [
                'currency'     => strtolower($currency),
                'product_data' => [
                    'name'        => $company . ' — ' . ($booking['unit_code'] ?? 'Dumpster Rental'),
                    'description' => $description,
                ],
                'unit_amount'  => $amount_cents,
            ],
            'quantity' => 1,
        ];

        $booking_ids[]  = (string)$booking['id'];
        $booking_nums[] = $booking['booking_number'];
    }

    $session_params = [
        'payment_method_types' => ['card'],
        'line_items'           => $line_items,
        'mode'                 => 'payment',
        'success_url'          => $success_url,
        'cancel_url'           => $cancel_url,
        'metadata'             => [
            'booking_ids'     => implode(',', $booking_ids),
            'booking_numbers' => implode(',', $booking_nums),
            'customer_name'   => $bookings[0]['customer_name'] ?? '',
            'customer_phone'  => $bookings[0]['customer_phone'] ?? '',
            'rental_start'    => $bookings[0]['rental_start'] ?? '',
            'rental_end'      => $bookings[0]['rental_end'] ?? '',
        ],
        'payment_intent_data'  => [
            'metadata' => [
                'booking_ids'     => implode(',', $booking_ids),
                'booking_numbers' => implode(',', $booking_nums),
                'customer_name'   => $bookings[0]['customer_name'] ?? '',
            ],
        ],
        'customer_email' => $bookings[0]['customer_email'] ?? null,
    ];

    if ($total_cents === 0) {
        $session_params['payment_method_collection'] = 'if_required';
    }

    return stripe_client()->checkout->sessions->create($session_params);
}


function stripe_verify_webhook(string $payload, string $sig_header): \Stripe\Event
{
    $secret = get_setting('stripe_webhook_secret', '');
    return \Stripe\Webhook::constructEvent($payload, $sig_header, $secret);
}
