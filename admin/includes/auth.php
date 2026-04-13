<?php
/**
 * Authentication and session helpers.
 */

function session_init(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $isSecure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path' => '/',
        'domain' => '',
        'secure' => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_name(SESSION_NAME);
    session_start();
}

function require_login(): void
{
    if (empty($_SESSION['user_id'])) {
        header('Location: ' . SITE_URL . '/login');
        exit;
    }
}

function current_user(): ?array
{
    static $user = false;

    if ($user === false) {
        if (empty($_SESSION['user_id'])) {
            $user = null;
        } else {
            $row = db_fetch('SELECT * FROM users WHERE id = ? LIMIT 1', [$_SESSION['user_id']]);
            $user = $row ?: null;
        }
    }

    return $user;
}

function has_role(string ...$roles): bool
{
    $user = current_user();
    if ($user === null) {
        return false;
    }
    return in_array($user['role'], $roles, true);
}

function require_role(string ...$roles): void
{
    require_login();

    if (!has_role(...$roles)) {
        http_response_code(403);
        die('You do not have permission to access this page.');
    }
}

function login_user(array $user): void
{
    session_regenerate_id(true);

    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['tenant_id'] = (int) ($user['tenant_id'] ?? 0);

    log_activity('login', 'User logged in', 'user', (int) $user['id'], (int) $user['id']);
    db_execute('UPDATE users SET last_login = NOW() WHERE id = ?', [$user['id']]);
}

function logout_user(): void
{
    $_SESSION = [];
    session_destroy();
    header('Location: ' . SITE_URL . '/login');
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

function csrf_field(): string
{
    return '<input type="hidden" name="' . CSRF_TOKEN_NAME . '" value="' . csrf_token() . '">';
}

function csrf_verify(): bool
{
    $submitted = $_POST[CSRF_TOKEN_NAME] ?? '';
    $stored = $_SESSION[CSRF_TOKEN_NAME] ?? '';

    if ($stored === '' || $submitted === '') {
        return false;
    }

    return hash_equals($stored, $submitted);
}

function csrf_check(): void
{
    if (!csrf_verify()) {
        http_response_code(400);
        die('Invalid security token. Please refresh the page and try again.');
    }
}
