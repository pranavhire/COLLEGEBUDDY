<?php
/*
 * download.php - sends a study resource file to a logged-in student.
 * SECURITY: the file is looked up by its database id. The user never types a file path,
 * so nobody can use this page to read other files on the server.
 * ?mode=view opens PDF, text and image files inside the browser. Otherwise the file is downloaded.
 */
require_once __DIR__ . '/includes/auth.php';
require_login('Please login to download study resources.');

$id = (int) ($_GET['id'] ?? 0);
$res = $id > 0 ? db_select_one('SELECT * FROM resources WHERE id = ?', 'i', [$id]) : null;
if ($res === null) {
    show_error_page(404, 'File not found', 'This study resource does not exist or was removed.');
}

// basename() removes any folder parts from the stored name, so the path always stays inside uploads/resources/
$path = resource_dir() . DIRECTORY_SEPARATOR . basename($res['stored_name']);
if (!is_file($path)) {
    show_error_page(404, 'File not found', 'The file for this resource is missing on the server. Please ask the uploader to upload it again.');
}

$inline = (($_GET['mode'] ?? '') === 'view') && in_array($res['file_ext'], ['pdf', 'txt', 'jpg', 'jpeg', 'png'], true);
if (!$inline) {
    // Count only real downloads
    db_exec('UPDATE resources SET download_count = download_count + 1 WHERE id = ?', 'i', [$id]);
}

// Remove any output buffers so only the file bytes are sent
while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: ' . mime_for_extension($res['file_ext']));
header('Content-Length: ' . filesize($path));
header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . $res['file_name'] . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=0, must-revalidate');
readfile($path);
exit;
