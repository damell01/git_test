<?php
/**
 * Login Rate Limiting
 * Trash Panda Roll-Offs
 *
 * Uses the rate_limit_locks and login_attempts tables to throttle brute-force attacks.
 */

define('RATE_LIMIT_MAX_ATTEMPTS', 10);
define('RATE_LIMIT_WINDOW_MINUTES', 15);
define('RATE_LIMIT_LOCKOUT_MINUTES', 15);

/**
 * Check whether the given IP is currently rate-limited.
 * If it is, output a lockout message and die().
 *
 * @param string $ip
 */
function check_rate_limit(string $ip): void
{
    $lock = db_fetch(
        'SELECT * FROM rate_limit_locks WHERE ip_address = ? LIMIT 1',
        [$ip]
    );

    if (!$lock) {
        return;
    }

    if ($lock['locked_until'] !== null && strtotime($lock['locked_until']) > time()) {
        $minutes_remaining = (int)ceil((strtotime($lock['locked_until']) - time()) / 60);
        http_response_code(429);
        $app_name = defined('APP_NAME') ? APP_NAME : 'Trash Panda Roll-Offs';
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Too Many Attempts | ' . htmlspecialchars($app_name, ENT_QUOTES, 'UTF-8') . '</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<style>body{background:#0f1117;min-height:100vh;display:flex;align-items:center;justify-content:center;}
.card{background:#1a1d27;border:1px solid #2a2d3e;border-radius:12px;padding:2.5rem;max-width:400px;color:#e5e7eb;text-align:center;}
h1{color:#ef4444;font-size:1.5rem;margin-bottom:1rem;}
p{color:#9ca3af;}</style>
</head><body>
<div class="card">
  <h1>&#128274; Too Many Attempts</h1>
  <p>Too many failed login attempts. Try again in <strong style="color:#f97316;">' . (int)$minutes_remaining . ' minute' . ($minutes_remaining === 1 ? '' : 's') . '</strong>.</p>
  <p style="font-size:.8rem;">If you believe this is an error, please contact your administrator.</p>
</div>
</body></html>';
        exit;
    }
}

/**
 * Record a failed login attempt and update rate_limit_locks.
 *
 * @param string $ip
 * @param string $email
 */
function record_failed_attempt(string $ip, string $email): void
{
    // Insert into login_attempts log
    db_execute(
        'INSERT INTO login_attempts (ip_address, email, attempted_at) VALUES (?, ?, NOW())',
        [$ip, $email]
    );

    // Count recent attempts in the window
    $count_row = db_fetch(
        'SELECT COUNT(*) AS cnt FROM login_attempts
         WHERE ip_address = ? AND attempted_at >= DATE_SUB(NOW(), INTERVAL ? MINUTE)',
        [$ip, RATE_LIMIT_WINDOW_MINUTES]
    );
    $attempts = (int)($count_row['cnt'] ?? 1);

    // Determine locked_until
    $locked_until = null;
    if ($attempts >= RATE_LIMIT_MAX_ATTEMPTS) {
        $locked_until = date('Y-m-d H:i:s', strtotime('+' . RATE_LIMIT_LOCKOUT_MINUTES . ' minutes'));
    }

    // Upsert rate_limit_locks
    db_execute(
        'INSERT INTO rate_limit_locks (ip_address, attempts, locked_until, updated_at)
         VALUES (?, ?, ?, NOW())
         ON DUPLICATE KEY UPDATE
           attempts     = ?,
           locked_until = ?,
           updated_at   = NOW()',
        [$ip, $attempts, $locked_until, $attempts, $locked_until]
    );
}

/**
 * Clear all rate limit data for an IP (called on successful login).
 *
 * @param string $ip
 */
function clear_attempts(string $ip): void
{
    db_execute('DELETE FROM login_attempts WHERE ip_address = ?', [$ip]);
    db_execute('DELETE FROM rate_limit_locks WHERE ip_address = ?', [$ip]);
}

/**
 * Return the number of recent failed attempts for an IP, or 0.
 *
 * @param string $ip
 * @return int
 */
function count_recent_attempts(string $ip): int
{
    $row = db_fetch(
        'SELECT COUNT(*) AS cnt FROM login_attempts
         WHERE ip_address = ? AND attempted_at >= DATE_SUB(NOW(), INTERVAL ? MINUTE)',
        [$ip, RATE_LIMIT_WINDOW_MINUTES]
    );
    return (int)($row['cnt'] ?? 0);
}
