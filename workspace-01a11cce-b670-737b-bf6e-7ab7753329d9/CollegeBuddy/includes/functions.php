<?php
/*
 * includes/functions.php
 * Shared helper functions used by every page:
 * URLs, escaping, flash messages, CSRF protection, XML data, uploads,
 * formatting, pagination and small HTML building blocks.
 */

const UPLOAD_LIMIT_BYTES = 10485760; // 10 MB  - study resources
const IMAGE_LIMIT_BYTES = 3145728;   // 3 MB   - listing photos

/* ============ Paths and URLs ============ */

/** Physical folder of the project, e.g. C:/xampp/htdocs/CollegeBuddy */
function project_root(): string
{
    return dirname(__DIR__);
}

function listing_dir(): string
{
    return project_root() . '/uploads/listings';
}

function resource_dir(): string
{
    return project_root() . '/uploads/resources';
}

/**
 * URL prefix of the project: "/CollegeBuddy" on XAMPP, or "" when the project is the web root.
 * It is calculated from the real folder location, so the site works in any folder name.
 */
function base_url(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }
    $base = '';
    $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
    $project = realpath(project_root());
    if ($docRoot !== false && $project !== false) {
        $docRoot = rtrim(str_replace('\\', '/', $docRoot), '/');
        $project = str_replace('\\', '/', $project);
        if (stripos($project, $docRoot) === 0) {
            $base = rtrim(substr($project, strlen($docRoot)), '/');
        }
    }
    return $base;
}

/** Full URL path to a page or file, e.g. url('marketplace.php') => /CollegeBuddy/marketplace.php */
function url(string $path = ''): string
{
    return base_url() . '/' . ltrim($path, '/');
}

/** Same as url() but already HTML-escaped, ready for href="..." */
function href(string $path = ''): string
{
    return e(url($path));
}

/** Sends the browser to another page of the project and stops the script. */
function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

/** Path of the current page relative to the project, e.g. "product.php?id=3" (used for login redirects). */
function current_relative_url(): string
{
    $path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    $base = base_url();
    if ($base !== '' && strpos($path, $base . '/') === 0) {
        $path = substr($path, strlen($base) + 1);
    } else {
        $path = ltrim($path, '/');
    }
    $query = $_SERVER['QUERY_STRING'] ?? '';
    return $path . ($query !== '' ? '?' . $query : '');
}

/** Only allows safe, internal page names for the ?next= redirect after login (prevents open redirects). */
function safe_next(string $next): string
{
    return preg_match('~^(admin/)?[a-z0-9_-]+\.php(\?[a-z0-9_=&%.+-]*)?(#[a-z0-9_-]*)?$~i', $next) ? $next : '';
}

/* ============ Input and output ============ */

/** Escapes text for HTML output (protects against XSS). */
function e($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Reads one text value sent by the browser, with a default.
 * $from is 'get' (the URL, ?name=...), 'post' (a form) or 'request' (form first, then URL).
 * Someone can send a list instead of text (for example ?q[]=x). Lists are ignored and the
 * default is used, so pages never print "Array to string conversion" warnings.
 */
function read_text(string $key, string $default = '', string $from = 'get'): string
{
    $value = match ($from) {
        'post' => $_POST[$key] ?? null,
        'request' => $_POST[$key] ?? $_GET[$key] ?? null,
        default => $_GET[$key] ?? null,
    };
    return is_scalar($value) ? (string) $value : $default;
}

/** Returns a previously submitted (POST) value so the form keeps what the user typed. */
function old(string $key, string $default = ''): string
{
    return isset($_POST[$key]) && is_string($_POST[$key]) ? trim($_POST[$key]) : $default;
}

/** Bootstrap error message for one field (empty string when there is no error). */
function field_error(array $errors, string $key): string
{
    return isset($errors[$key]) ? '<div class="invalid-feedback d-block">' . e($errors[$key]) . '</div>' : '';
}

/** Adds Bootstrap's red border class to a field that has an error. */
function invalid_class(array $errors, string $key): string
{
    return isset($errors[$key]) ? ' is-invalid' : '';
}

/* ============ Flash messages (shown once, after a redirect) ============ */

function set_flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function get_flashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

/* ============ CSRF protection (hidden token in every form) ============ */

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/** Stops POST requests that do not carry the correct token. Call at the top of every POST handler. */
function verify_csrf(): void
{
    if (empty($_POST) && empty($_FILES) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        show_error_page(413, 'Upload too large', 'The data you sent is bigger than the server allows. Please choose a smaller file and try again.');
    }
    $sent = $_POST['csrf_token'] ?? '';
    $expected = $_SESSION['csrf_token'] ?? '';
    if ($expected === '' || !is_string($sent) || !hash_equals($expected, $sent)) {
        show_error_page(403, 'Form expired', 'For your security this form could not be verified. Please refresh the page and try again.');
    }
}

/* ============ Friendly error pages ============ */

/** Shows a friendly message page (never raw technical errors) and stops the script. */
function show_error_page(int $code, string $title, string $message): void
{
    http_response_code($code);
    $pageTitle = $title;
    $activeNav = '';
    $breadcrumbs = [];
    require __DIR__ . '/header.php';
    echo '<section class="container py-5"><div class="cb-empty cb-empty-lg">'
        . '<div class="cb-empty-icon cb-empty-icon-warn"><i class="bi bi-exclamation-circle"></i></div>'
        . '<h1 class="h3 mb-2">' . e($title) . '</h1>'
        . '<p class="text-muted mb-4">' . e($message) . '</p>'
        . '<div class="d-flex flex-wrap gap-2 justify-content-center">'
        . '<a class="btn btn-primary" href="' . href('index.php') . '"><i class="bi bi-house"></i> Go to Home</a>'
        . '<button type="button" class="btn btn-outline-primary" data-back><i class="bi bi-arrow-left"></i> Go back</button>'
        . '</div></div></section>';
    require __DIR__ . '/footer.php';
    exit;
}

/** Any unexpected error shows a generic page; the real error is written to the Apache error log. */
function install_error_handler(): void
{
    set_exception_handler(function (Throwable $ex): void {
        error_log('[College Buddy] ' . $ex->getMessage() . ' in ' . basename($ex->getFile()) . ':' . $ex->getLine());
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        http_response_code(500);
        $detail = APP_DEBUG ? '<pre class="small text-start">' . e($ex->getMessage()) . '</pre>' : '';
        echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>Something went wrong | College Buddy</title><link rel="stylesheet" href="' . e(url('css/vendor/bootstrap.min.css')) . '"></head>'
            . '<body class="bg-light"><div class="container py-5 text-center">'
            . '<h1 class="h3">Oops! Something went wrong.</h1>'
            . '<p class="text-muted">We could not complete your request right now. Please try again in a moment.</p>'
            . $detail
            . '<a class="btn btn-primary" href="' . href('index.php') . '">Go to Home</a>'
            . '</div></body></html>';
    });
}

/* ============ XML reference data (xml/college_buddy_data.xml) ============ */

/** Loads the static lists (branches, years, categories...) from the XML file. Loaded once per request. */
function xml_data(): array
{
    static $data = null;
    if ($data !== null) {
        return $data;
    }
    $data = [
        'branches' => [], 'years' => [], 'semesters' => [], 'subjects' => [], 'resourceTypes' => [],
        'listingCategories' => [], 'requestCategories' => [], 'conditions' => [], 'reportReasons' => [], 'grades' => [],
    ];
    $xml = @simplexml_load_file(project_root() . '/xml/college_buddy_data.xml');
    if ($xml === false) {
        error_log('[College Buddy] Could not read xml/college_buddy_data.xml');
        return $data;
    }
    foreach ($xml->branches->branch as $b) {
        $data['branches'][] = ['value' => (string) $b['value'], 'label' => (string) $b['label']];
    }
    foreach ($xml->years->year as $y) {
        $data['years'][] = ['value' => (int) $y['value'], 'label' => (string) $y['label']];
    }
    foreach ($xml->semesters->semester as $s) {
        $data['semesters'][] = ['value' => (int) $s['value'], 'label' => (string) $s['label']];
    }
    foreach ($xml->subjects->subject as $s) {
        $data['subjects'][] = ['name' => (string) $s, 'branch' => (string) $s['branch'], 'semester' => (int) $s['semester']];
    }
    foreach ($xml->resourceTypes->type as $t) {
        $data['resourceTypes'][] = (string) $t;
    }
    foreach ($xml->listingCategories->category as $c) {
        $data['listingCategories'][] = (string) $c;
    }
    foreach ($xml->requestCategories->category as $c) {
        $data['requestCategories'][] = (string) $c;
    }
    foreach ($xml->conditions->condition as $c) {
        $data['conditions'][] = (string) $c;
    }
    foreach ($xml->reportReasons->reason as $r) {
        $data['reportReasons'][] = (string) $r;
    }
    foreach ($xml->gradeScale->grade as $g) {
        $data['grades'][] = ['symbol' => (string) $g['symbol'], 'points' => (string) $g['points']];
    }
    return $data;
}

/** Returns one plain list from the XML, e.g. xml_list('resourceTypes'). */
function xml_list(string $key): array
{
    return xml_data()[$key] ?? [];
}

/* ============ File uploads ============ */

/** Allowed study-resource file types: extension => list of real MIME types (checked from file content). */
function resource_upload_types(): array
{
    return [
        'pdf' => ['application/pdf'],
        'doc' => ['application/msword', 'application/vnd.ms-office', 'application/x-ole-storage', 'application/CDFV2'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        'ppt' => ['application/vnd.ms-powerpoint', 'application/vnd.ms-office', 'application/x-ole-storage', 'application/CDFV2'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip'],
        'txt' => ['text/plain'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
    ];
}

/** Allowed listing photo types. */
function image_upload_types(): array
{
    return [
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'webp' => ['image/webp'],
    ];
}

/** Reads the real file type from the file's content (not just its name). */
function detect_mime(string $path): string
{
    if (class_exists('finfo')) {
        return (new finfo(FILEINFO_MIME_TYPE))->file($path) ?: 'application/octet-stream';
    }
    return function_exists('mime_content_type') ? (mime_content_type($path) ?: 'application/octet-stream') : 'application/octet-stream';
}

/**
 * Validates and saves one uploaded file.
 * Checks: upload error code, size limit, extension whitelist, real MIME type, and (for images) getimagesize().
 * The file is saved under a random name, so the user's file name can never run or overwrite anything.
 * Returns ['ok' => true, 'stored' => ..., 'original' => ..., 'size' => ..., 'ext' => ...] or ['ok' => false, 'error' => ...].
 */
function handle_upload(array $file, array $allowed, int $maxBytes, string $dir, string $prefix, bool $required = true): array
{
    $code = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($code === UPLOAD_ERR_NO_FILE) {
        return $required ? ['ok' => false, 'error' => 'Please choose a file to upload.'] : ['ok' => true, 'skipped' => true];
    }
    if ($code === UPLOAD_ERR_INI_SIZE || $code === UPLOAD_ERR_FORM_SIZE) {
        return ['ok' => false, 'error' => 'This file is too large. The maximum size is ' . format_bytes($maxBytes) . '.'];
    }
    if ($code !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        return ['ok' => false, 'error' => 'The upload failed. Please try again.'];
    }
    $size = (int) $file['size'];
    if ($size <= 0) {
        return ['ok' => false, 'error' => 'The selected file is empty.'];
    }
    if ($size > $maxBytes) {
        return ['ok' => false, 'error' => 'This file is too large. The maximum size is ' . format_bytes($maxBytes) . '.'];
    }

    $original = substr(preg_replace('/[^A-Za-z0-9._ -]/', '_', basename((string) $file['name'])), 0, 150);
    $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    if ($ext === '' || !isset($allowed[$ext])) {
        return ['ok' => false, 'error' => 'Invalid file type. Allowed types: ' . implode(', ', array_keys($allowed)) . '.'];
    }

    $mime = detect_mime($file['tmp_name']);
    if (!in_array($mime, $allowed[$ext], true)) {
        return ['ok' => false, 'error' => 'This file does not look like a valid .' . $ext . ' file. Please check it and try again.'];
    }
    if (strpos($mime, 'image/') === 0 && @getimagesize($file['tmp_name']) === false) {
        return ['ok' => false, 'error' => 'The image could not be read. Please upload a different photo.'];
    }

    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $stored = $prefix . bin2hex(random_bytes(16)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $stored)) {
        return ['ok' => false, 'error' => 'The file could not be saved on the server. Please try again.'];
    }
    return ['ok' => true, 'stored' => $stored, 'original' => $original, 'size' => $size, 'ext' => $ext, 'mime' => $mime];
}

/** Served Content-Type for a stored file extension (used by download.php). */
function mime_for_extension(string $ext): string
{
    $map = [
        'pdf' => 'application/pdf', 'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'ppt' => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'txt' => 'text/plain; charset=UTF-8', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
        'png' => 'image/png', 'webp' => 'image/webp',
    ];
    return $map[$ext] ?? 'application/octet-stream';
}

/** Deletes a listing photo (only files inside uploads/listings are ever removed). */
function delete_listing_image(?string $relativePath): void
{
    if ($relativePath && strpos($relativePath, 'uploads/listings/') === 0 && strpos($relativePath, '..') === false) {
        $file = project_root() . '/' . $relativePath;
        if (is_file($file)) {
            @unlink($file);
        }
    }
}

/** Deletes a stored study-resource file from uploads/resources. */
function delete_resource_file(string $storedName): void
{
    $file = resource_dir() . '/' . basename($storedName);
    if (is_file($file)) {
        @unlink($file);
    }
}

/* ============ Formatting ============ */

/** Indian rupee price, e.g. ₹450 or ₹1,250.50 */
function format_price($amount): string
{
    $amount = (float) $amount;
    return '₹' . (floor($amount) == $amount ? number_format($amount, 0) : number_format($amount, 2));
}

function format_bytes(int $bytes): string
{
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 1) . ' MB';
    }
    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 0) . ' KB';
    }
    return $bytes . ' B';
}

function format_date(string $datetime, string $format = 'd M Y'): string
{
    return date($format, strtotime($datetime));
}

function plural(int $n, string $word): string
{
    return $n . ' ' . $word . ($n === 1 ? '' : 's');
}

/** "5 minutes ago", "3 days ago", or a date for older items. */
function time_ago(string $datetime): string
{
    $diff = time() - strtotime($datetime);
    if ($diff < 60) {
        return 'Just now';
    }
    if ($diff < 3600) {
        return plural((int) ($diff / 60), 'minute') . ' ago';
    }
    if ($diff < 86400) {
        return plural((int) ($diff / 3600), 'hour') . ' ago';
    }
    if ($diff < 604800) {
        return plural((int) ($diff / 86400), 'day') . ' ago';
    }
    return format_date($datetime);
}

/** Two-letter avatar text, e.g. "Aarav Sharma" => "AS" */
function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $out = '';
    foreach (array_slice($parts, 0, 2) as $part) {
        $out .= mb_strtoupper(mb_substr($part, 0, 1));
    }
    return $out !== '' ? $out : 'CB';
}

/** Bootstrap badge for listing/request/report status values. */
function status_badge(string $status): string
{
    $colors = [
        'Available' => 'success', 'Open' => 'success', 'Resolved' => 'success',
        'Pending' => 'warning', 'Sold' => 'secondary', 'Closed' => 'secondary', 'Dismissed' => 'secondary',
    ];
    $c = $colors[$status] ?? 'secondary';
    return '<span class="badge rounded-pill bg-' . $c . '-subtle text-' . $c . '-emphasis border border-' . $c . '-subtle">' . e($status) . '</span>';
}

/** Bootstrap badge for the item condition (New, Like New, Good, Used). */
function condition_badge(string $condition): string
{
    $colors = ['New' => 'primary', 'Like New' => 'info', 'Good' => 'success', 'Used' => 'warning'];
    $c = $colors[$condition] ?? 'secondary';
    return '<span class="badge bg-' . $c . '-subtle text-' . $c . '-emphasis">' . e($condition) . '</span>';
}

/** Bootstrap icon class for a study-resource type. */
function resource_icon(string $type): string
{
    $icons = [
        'Notes' => 'bi-journal-text', 'Previous Year Paper' => 'bi-file-earmark-ruled', 'Question Bank' => 'bi-patch-question',
        'Practical' => 'bi-flask', 'Assignment' => 'bi-pencil-square', 'Study Material' => 'bi-book', 'Other' => 'bi-folder2-open',
    ];
    return $icons[$type] ?? 'bi-file-earmark';
}

/** Image URL for a listing (sample image or uploaded photo, with a placeholder fallback). */
function listing_image_url(?string $path): string
{
    return ($path !== null && $path !== '') ? url($path) : url('images/samples/placeholder.svg');
}

/* ============ Page building blocks ============ */

function render_page_head(string $eyebrow, string $title, string $subtitle = '', string $actions = ''): void
{
    echo '<section class="cb-page-head"><div class="container"><div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">'
        . '<div><p class="cb-eyebrow mb-1">' . e($eyebrow) . '</p><h1 class="h3 mb-1">' . e($title) . '</h1>'
        . ($subtitle !== '' ? '<p class="text-muted mb-0">' . e($subtitle) . '</p>' : '') . '</div>'
        . ($actions !== '' ? '<div class="d-flex flex-wrap gap-2">' . $actions . '</div>' : '')
        . '</div></div></section>';
}

/** Friendly empty-state box, e.g. "No listings found." */
function render_empty(string $icon, string $title, string $text, string $actions = ''): void
{
    echo '<div class="cb-empty"><div class="cb-empty-icon"><i class="bi ' . e($icon) . '"></i></div>'
        . '<h2 class="h5 mb-1">' . e($title) . '</h2><p class="text-muted mb-3">' . e($text) . '</p>'
        . ($actions !== '' ? '<div class="d-flex flex-wrap gap-2 justify-content-center">' . $actions . '</div>' : '')
        . '</div>';
}

/** Bootstrap pagination. $query keeps the current filters in every page link. */
function render_pagination(int $page, int $pages, string $path, array $query): void
{
    if ($pages <= 1) {
        return;
    }
    $link = function (int $p) use ($path, $query): string {
        $query['page'] = $p;
        return e(url($path) . '?' . http_build_query($query));
    };
    echo '<nav aria-label="Pages" class="mt-4"><ul class="pagination justify-content-center flex-wrap cb-pagination">';
    echo '<li class="page-item' . ($page <= 1 ? ' disabled' : '') . '"><a class="page-link" href="' . $link(max(1, $page - 1)) . '">&laquo; Prev</a></li>';
    for ($i = 1; $i <= $pages; $i++) {
        if ($i === 1 || $i === $pages || abs($i - $page) <= 1) {
            echo '<li class="page-item' . ($i === $page ? ' active' : '') . '"><a class="page-link" href="' . $link($i) . '">' . $i . '</a></li>';
        } elseif (abs($i - $page) === 2) {
            echo '<li class="page-item disabled"><span class="page-link">&hellip;</span></li>';
        }
    }
    echo '<li class="page-item' . ($page >= $pages ? ' disabled' : '') . '"><a class="page-link" href="' . $link(min($pages, $page + 1)) . '">Next &raquo;</a></li>';
    echo '</ul></nav>';
}

/* ============ Shared database actions (used by owners AND admins) ============ */

/** Deletes a listing. If $ownerId is given, only the owner's listing is deleted. */
function delete_listing(int $id, ?int $ownerId = null): bool
{
    $sql = 'SELECT image FROM listings WHERE id = ?' . ($ownerId !== null ? ' AND user_id = ?' : '');
    $row = $ownerId !== null ? db_select_one($sql, 'ii', [$id, $ownerId]) : db_select_one($sql, 'i', [$id]);
    if ($row === null) {
        return false;
    }
    db_exec('DELETE FROM listings WHERE id = ?', 'i', [$id]);
    delete_listing_image($row['image']);
    return true;
}

/** Switches a listing between Available and Sold. */
function toggle_listing_sold(int $id, ?int $ownerId = null): bool
{
    $sql = "UPDATE listings SET status = CASE WHEN status = 'Sold' THEN 'Available' ELSE 'Sold' END WHERE id = ?";
    return $ownerId !== null
        ? db_exec($sql . ' AND user_id = ?', 'ii', [$id, $ownerId]) > 0
        : db_exec($sql, 'i', [$id]) > 0;
}

/** Deletes a study resource and its file. */
function delete_resource(int $id, ?int $ownerId = null): bool
{
    $sql = 'SELECT stored_name FROM resources WHERE id = ?' . ($ownerId !== null ? ' AND user_id = ?' : '');
    $row = $ownerId !== null ? db_select_one($sql, 'ii', [$id, $ownerId]) : db_select_one($sql, 'i', [$id]);
    if ($row === null) {
        return false;
    }
    db_exec('DELETE FROM resources WHERE id = ?', 'i', [$id]);
    delete_resource_file($row['stored_name']);
    return true;
}

/** Deletes a request from the request board. */
function delete_request(int $id, ?int $ownerId = null): bool
{
    $sql = 'DELETE FROM requests WHERE id = ?' . ($ownerId !== null ? ' AND user_id = ?' : '');
    return $ownerId !== null ? db_exec($sql, 'ii', [$id, $ownerId]) > 0 : db_exec($sql, 'i', [$id]) > 0;
}

/** Switches a request between Open and Closed. */
function toggle_request_status(int $id, ?int $ownerId = null): bool
{
    $sql = "UPDATE requests SET status = CASE WHEN status = 'Open' THEN 'Closed' ELSE 'Open' END WHERE id = ?";
    return $ownerId !== null
        ? db_exec($sql . ' AND user_id = ?', 'ii', [$id, $ownerId]) > 0
        : db_exec($sql, 'i', [$id]) > 0;
}

/** Deletes a user account together with their listings, resources and files (admin action). */
function delete_user(int $id): bool
{
    $resources = db_select('SELECT stored_name FROM resources WHERE user_id = ?', 'i', [$id]);
    $images = db_select('SELECT image FROM listings WHERE user_id = ?', 'i', [$id]);
    $deleted = db_exec('DELETE FROM users WHERE id = ?', 'i', [$id]) > 0; // related rows cascade
    if ($deleted) {
        foreach ($resources as $r) {
            delete_resource_file($r['stored_name']);
        }
        foreach ($images as $i) {
            delete_listing_image($i['image']);
        }
    }
    return $deleted;
}

/** Number of unread messages for the navbar badge. */
function unread_message_count(int $userId): int
{
    return db_count('SELECT COUNT(*) AS c FROM messages WHERE receiver_id = ? AND is_read = 0', 'i', [$userId]);
}

/** Stores a message between two students. context_type is 'listing' or 'request'. */
function send_message(int $senderId, int $receiverId, string $contextType, int $contextId, string $body): int
{
    return db_insert(
        'INSERT INTO messages (sender_id, receiver_id, context_type, context_id, body) VALUES (?, ?, ?, ?, ?)',
        'iisis',
        [$senderId, $receiverId, $contextType, $contextId, $body]
    );
}
