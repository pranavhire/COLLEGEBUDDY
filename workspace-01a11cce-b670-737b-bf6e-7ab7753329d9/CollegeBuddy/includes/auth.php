<?php
/*
 * includes/auth.php
 * Starts the PHP session and provides the login helpers.
 * Every page includes this file FIRST, so the session and database are always ready.
 */

ob_start(); // buffer the page output so we can still redirect safely

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

install_error_handler();

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');   // only accept session IDs created by this server
    ini_set('session.cookie_httponly', '1');   // JavaScript cannot read the session cookie
    ini_set('session.cookie_samesite', 'Lax');
    session_start();
}

/**
 * Returns the logged-in user's row, or null for guests.
 * The row is read from MySQL on every request, so the role is always up to date
 * (for example, if an admin removes a user, that user is logged out automatically).
 */
function current_user(): ?array
{
    static $cached = false;
    if ($cached !== false) {
        return $cached;
    }
    $id = (int) ($_SESSION['user_id'] ?? 0);
    if ($id <= 0) {
        return $cached = null;
    }
    $row = db_select_one(
        'SELECT id, full_name, email, branch, year, semester, role, created_at FROM users WHERE id = ?',
        'i',
        [$id]
    );
    if ($row === null) {
        unset($_SESSION['user_id']); // the account no longer exists
    }
    return $cached = $row;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function is_admin(): bool
{
    $user = current_user();
    return $user !== null && $user['role'] === 'admin';
}

/** Guests are sent to the login page, then brought back to the page they wanted. */
function require_login(string $message = 'Please login to continue.'): void
{
    if (!is_logged_in()) {
        set_flash('warning', $message);
        redirect('login.php?next=' . urlencode(current_relative_url()));
    }
}

/** Stores the user id in the session after a successful password check. */
function login_user(int $userId): void
{
    session_regenerate_id(true); // prevents "session fixation" attacks
    $_SESSION['user_id'] = $userId;
}

/** Destroys the session completely (used by logout.php). */
function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}
