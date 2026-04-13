<?php
http_response_code(410);
header('Content-Type: application/json');
echo json_encode([
    'received' => false,
    'error' => 'Legacy Stripe webhook removed. Use /api/stripe-connect-webhook.php.',
]);
