<?php
// Session, CSRF, output escaping, flash messages and display helpers.

function start_secure_session(): void
{
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }
    ini_set('session.gc_maxlifetime', (string) (30 * 86400)); // supports "Remember me"
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

/** Escape any value for safe HTML output (XSS prevention). */
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_valid(?string $token): bool
{
    return isset($_SESSION['csrf_token'])
        && is_string($token)
        && hash_equals($_SESSION['csrf_token'], $token);
}

function flash_set(string $key, string $message): void
{
    $_SESSION['flash'][$key] = $message;
}

function flash_take(string $key): ?string
{
    $message = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $message;
}

/** timothy.j@example.com -> ti*****@example.com */
function mask_email(string $email): string
{
    $at = strrpos($email, '@');
    if ($at === false) {
        return '***';
    }
    $local  = substr($email, 0, $at);
    $domain = substr($email, $at);
    $keep   = min(2, strlen($local));
    return substr($local, 0, $keep) . str_repeat('*', max(3, strlen($local) - $keep)) . $domain;
}

/** Ends the session completely (server data and cookie). */
function destroy_session(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/**
 * Server-side access check for every protected page.
 * Re-reads the account from the database on each request, so a disabled account is
 * locked out immediately and a role change takes effect on the very next page load.
 * Returns the fresh session user: ['id', 'name', 'email', 'role'].
 */
function require_role(array $allowedRoles): array
{
    start_secure_session();
    $sessionUser = $_SESSION['user'] ?? null;
    if (!is_array($sessionUser) || empty($sessionUser['id'])) {
        header('Location: login.php');
        exit;
    }

    try {
        $stmt = db()->prepare('SELECT id, full_name, email, role, status FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([(int) $sessionUser['id']]);
        $row = $stmt->fetch();
    } catch (Throwable $e) {
        error_log('require_role: ' . $e->getMessage());
        http_response_code(503);
        exit('Service temporarily unavailable.');
    }

    if (!$row || $row['status'] !== 'active') {
        destroy_session();
        header('Location: login.php');
        exit;
    }

    $_SESSION['user'] = [
        'id'    => (int) $row['id'],
        'name'  => $row['full_name'],
        'email' => $row['email'],
        'role'  => $row['role'],
    ];

    if (!in_array($row['role'], $allowedRoles, true)) {
        header('Location: dashboard.php');
        exit;
    }
    return $_SESSION['user'];
}
