<?php
/*
 * logout.php - destroys the PHP session and sends the user back to the home page.
 */
require_once __DIR__ . '/includes/auth.php';

logout_user();   // empties $_SESSION and deletes the session cookie
session_start(); // start a fresh, empty session only to carry the goodbye message
set_flash('success', 'You have been logged out. See you soon!');
redirect('index.php');
