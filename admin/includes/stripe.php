<?php
/**
 * Stripe API cURL Wrapper (no Composer required)
 * Trash Panda Roll-Offs
 *
 * All functions return a decoded associative array from the Stripe API.
 * On error the returned array will contain an 'error' key.
 */

/**
 * Base cURL function for Stripe API requests.
 *
 * @param string $method   HTTP method: GET, POST, DELETE
 * @param string $endpoint Stripe endpoint, e.g. '/v1/payment_intents'
 * @param array  $data     Key/value pairs to send as form-encoded body (POST) or query string (GET)
 * @return array
 */
function stripe_request(string $method, string $endpoint, array $data = []): array
{
    $url = 'https://api.stripe.com' . $endpoint;

    $ch = curl_init();

    $headers = [
        'Authorization: Bearer ' . STRIPE_SECRET_KEY,
        'Content-Type: application/x-www-form-urlencoded',
        'Stripe-Version: 2023-10-16',
    ];

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    $method = strtoupper($method);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    } elseif ($method === 'DELETE') {
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        if (!empty($data)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        }
    } else {
        // GET
        if (!empty($data)) {
            $url .= '?' . http_build_query($data);
        }
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPGET, true);
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        return ['error' => ['message' => 'cURL error: ' . $curlError, 'type' => 'curl_error']];
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        return ['error' => ['message' => 'Invalid JSON response from Stripe', 'type' => 'parse_error']];
    }

    return $decoded;
}

/**
 * Create a PaymentIntent.
 *
 * @param float  $amount    Amount in dollars (will be converted to cents)
 * @param string $currency  e.g. 'usd'
 * @param array  $metadata  Key/value metadata pairs
 * @return array
 */
function stripe_create_payment_intent(float $amount, string $currency = 'usd', array $metadata = []): array
{
    $data = [
        'amount'   => (int)round($amount * 100),
        'currency' => strtolower($currency),
    ];

    foreach ($metadata as $k => $v) {
        $data['metadata[' . $k . ']'] = $v;
    }

    return stripe_request('POST', '/v1/payment_intents', $data);
}

/**
 * Retrieve a PaymentIntent by ID.
 *
 * @param string $pi_id  PaymentIntent ID, e.g. 'pi_...'
 * @return array
 */
function stripe_retrieve_payment_intent(string $pi_id): array
{
    return stripe_request('GET', '/v1/payment_intents/' . urlencode($pi_id));
}

/**
 * Create or retrieve a Stripe Customer.
 *
 * @param string $name
 * @param string $email
 * @return array
 */
function stripe_create_customer(string $name, string $email): array
{
    return stripe_request('POST', '/v1/customers', [
        'name'  => $name,
        'email' => $email,
    ]);
}

/**
 * Refund a charge (full or partial).
 *
 * @param string $charge_id  Stripe charge ID, e.g. 'ch_...'
 * @param float  $amount     Amount in dollars to refund; 0 = full refund
 * @return array
 */
function stripe_refund_charge(string $charge_id, float $amount = 0): array
{
    $data = ['charge' => $charge_id];
    if ($amount > 0) {
        $data['amount'] = (int)round($amount * 100);
    }
    return stripe_request('POST', '/v1/refunds', $data);
}

/**
 * Validate and construct a Stripe webhook event from the raw payload and signature header.
 * Implements Stripe's HMAC-SHA256 signature verification.
 *
 * @param string $payload    Raw request body (file_get_contents('php://input'))
 * @param string $sig_header Value of the 'Stripe-Signature' header
 * @param string $secret     Webhook signing secret (whsec_...)
 * @return array  Decoded event array, or array with 'error' key on failure
 */
function stripe_construct_webhook_event(string $payload, string $sig_header, string $secret): array
{
    if (empty($sig_header)) {
        return ['error' => 'Missing Stripe-Signature header'];
    }

    // Parse the header: t=timestamp,v1=signature,...
    $parts = [];
    foreach (explode(',', $sig_header) as $part) {
        $kv = explode('=', $part, 2);
        if (count($kv) === 2) {
            $parts[$kv[0]][] = $kv[1];
        }
    }

    if (empty($parts['t']) || empty($parts['v1'])) {
        return ['error' => 'Invalid Stripe-Signature header format'];
    }

    $timestamp = (int)$parts['t'][0];

    // Reject events older than 5 minutes
    if (abs(time() - $timestamp) > 300) {
        return ['error' => 'Webhook timestamp too old'];
    }

    $signed_payload = $timestamp . '.' . $payload;
    $expected = hash_hmac('sha256', $signed_payload, $secret);

    $valid = false;
    foreach ($parts['v1'] as $sig) {
        if (hash_equals($expected, $sig)) {
            $valid = true;
            break;
        }
    }

    if (!$valid) {
        return ['error' => 'Webhook signature verification failed'];
    }

    $event = json_decode($payload, true);
    if (!is_array($event)) {
        return ['error' => 'Invalid webhook payload JSON'];
    }

    return $event;
}
