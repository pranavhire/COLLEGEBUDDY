<?php
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));

// Handle /CollegeBuddy prefix seamlessly
if (preg_match('#^/CollegeBuddy(/.*)?$#', $uri, $matches)) {
    $sub = $matches[1] ?? '/';
    if ($sub === '' || $sub === '/') {
        include __DIR__ . '/index.php';
        exit;
    }
    $target = __DIR__ . $sub;
    if (is_file($target)) {
        if (substr($sub, -4) === '.php') {
            include $target;
            exit;
        }
        return false;
    }
}

// If direct file exists, serve it
if ($uri !== '/' && file_exists(__DIR__ . $uri)) {
    return false;
}

// Default to index.php
include __DIR__ . '/index.php';
