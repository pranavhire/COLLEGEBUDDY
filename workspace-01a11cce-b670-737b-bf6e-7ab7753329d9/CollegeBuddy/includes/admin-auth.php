<?php
/*
 * includes/admin-auth.php
 * Protects every admin page. Only users whose role is 'admin' can pass.
 * Admin pages call require_admin() before they output anything.
 */

require_once __DIR__ . '/auth.php';

function require_admin(): void
{
    if (!is_logged_in()) {
        set_flash('warning', 'Please login with an admin account to continue.');
        redirect('login.php?next=' . urlencode(current_relative_url()));
    }
    if (!is_admin()) {
        set_flash('danger', 'Access denied. Only administrators can open the admin panel.');
        redirect('dashboard.php');
    }
}
