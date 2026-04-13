<?php
http_response_code(410);
header('Content-Type: application/json');
echo json_encode([
    'success' => false,
    'error' => 'Legacy booking endpoint removed. Use /api/fieldora-booking.php.',
]);
