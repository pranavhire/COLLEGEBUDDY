<?php
/*
 * admin/requests.php - moderate Request Board posts.
 * Actions: View, Close / Reopen, Remove (permanent delete).
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/admin-auth.php';
require_admin();

$categories = xml_list('requestCategories');
$q = trim(read_text('q', ''));
$status = read_text('status', 'All');
$category = read_text('category', '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;

if (!in_array($status, ['All', 'Open', 'Closed'], true)) {
    $status = 'All';
}
if (!in_array($category, $categories, true)) {
    $category = '';
}
$returnQuery = array_filter(['q' => $q, 'status' => $status !== 'All' ? $status : '', 'category' => $category], fn($v) => $v !== '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = (int) ($_POST['id'] ?? 0);
    $action = read_text('action', '', 'post');
    if ($action === 'toggle' && toggle_request_status($id)) {
        set_flash('success', 'Request status updated.');
    } elseif ($action === 'remove' && delete_request($id)) {
        set_flash('success', 'Request removed.');
    } else {
        set_flash('danger', 'That request could not be updated. It may have been removed already.');
    }
    $back = read_text('return', '', 'post');
    redirect('admin/requests.php' . ($back !== '' ? '?' . $back : ''));
}

$where = [];
$tp = '';
$params = [];
if ($q !== '') {
    $like = '%' . addcslashes($q, '%_\\') . '%';
    $where[] = '(r.title LIKE ? OR r.description LIKE ? OR u.full_name LIKE ?)';
    $tp .= 'sss';
    array_push($params, $like, $like, $like);
}
if ($status !== 'All') {
    $where[] = 'r.status = ?';
    $tp .= 's';
    $params[] = $status;
}
if ($category !== '') {
    $where[] = 'r.category = ?';
    $tp .= 's';
    $params[] = $category;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total = db_count("SELECT COUNT(*) AS total FROM requests r JOIN users u ON u.id = r.user_id $whereSql", $tp, $params);
$pages = max(1, (int) ceil($total / $perPage));
$page = min($page, $pages);
$rows = db_select(
    "SELECT r.*, u.full_name AS requester_name, u.branch AS requester_branch
     FROM requests r JOIN users u ON u.id = r.user_id
     $whereSql
     ORDER BY r.created_at DESC, r.id DESC
     LIMIT ? OFFSET ?",
    $tp . 'ii',
    array_merge($params, [$perPage, ($page - 1) * $perPage])
);

$returnString = http_build_query(array_merge($returnQuery, ['page' => $page > 1 ? $page : null]));
$pageTitle = 'Manage Requests';
$activeNav = 'admin';
$adminSection = 'requests';
$breadcrumbs = [['Admin', 'admin/dashboard.php'], ['Requests', '']];
require __DIR__ . '/../includes/header.php';
render_page_head('Request Board moderation', 'Requests', plural($total, 'request') . ' found. Close or remove posts that are spam or unsafe.');
?>

<section class="container py-4 pb-5">
    <form method="get" action="<?= href('admin/requests.php') ?>" class="card cb-card cb-filter-card mb-4">
        <div class="card-body p-3">
            <div class="row g-2 align-items-end">
                <div class="col-12 col-md-4">
                    <label class="form-label small fw-semibold" for="q">Search title, description or requester</label>
                    <input type="search" class="form-control" id="q" name="q" value="<?= e($q) ?>">
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label small fw-semibold" for="category">Category</label>
                    <select class="form-select" id="category" name="category">
                        <option value="">All categories</option>
                        <?php foreach ($categories as $c): ?><option value="<?= e($c) ?>"<?= $category === $c ? ' selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small fw-semibold" for="status">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="All"<?= $status === 'All' ? ' selected' : '' ?>>All</option>
                        <option value="Open"<?= $status === 'Open' ? ' selected' : '' ?>>Open</option>
                        <option value="Closed"<?= $status === 'Closed' ? ' selected' : '' ?>>Closed</option>
                    </select>
                </div>
                <div class="col-12 col-md-3 d-flex gap-2">
                    <button class="btn btn-primary flex-grow-1" type="submit">Filter</button>
                    <a class="btn btn-outline-secondary" href="<?= href('admin/requests.php') ?>">Clear</a>
                </div>
            </div>
        </div>
    </form>

    <?php if (!$rows): ?>
        <?php render_empty('bi-chat-square', 'No requests match these filters.', 'Try a different status or category.'); ?>
    <?php else: ?>
        <div class="card cb-card">
            <div class="table-responsive">
                <table class="table cb-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">Request</th>
                            <th scope="col" class="d-none d-md-table-cell">Requester</th>
                            <th scope="col" class="d-none d-lg-table-cell">Category</th>
                            <th scope="col">Status</th>
                            <th scope="col" class="d-none d-xl-table-cell">Posted</th>
                            <th scope="col" class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td>
                                    <a class="fw-semibold text-body" href="<?= href('request.php?id=' . (int) $r['id']) ?>"><?= e($r['title']) ?></a>
                                    <div class="small text-muted cb-clamp-1"><?= e($r['description']) ?></div>
                                </td>
                                <td class="d-none d-md-table-cell"><?= e($r['requester_name']) ?> <span class="small text-muted">&middot; <?= e($r['requester_branch']) ?></span></td>
                                <td class="d-none d-lg-table-cell"><span class="badge cb-badge-soft"><?= e($r['category']) ?></span></td>
                                <td><?= status_badge($r['status']) ?></td>
                                <td class="d-none d-xl-table-cell small text-muted"><?= e(format_date($r['created_at'])) ?></td>
                                <td class="text-end">
                                    <div class="cb-row-actions d-inline-flex flex-wrap justify-content-end gap-1">
                                        <a class="btn btn-sm btn-outline-primary" href="<?= href('request.php?id=' . (int) $r['id']) ?>"><i class="bi bi-eye"></i><span class="d-none d-xl-inline"> View</span></a>
                                        <form method="post" action="<?= href('admin/requests.php') ?>" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="toggle">
                                            <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                            <input type="hidden" name="return" value="<?= e($returnString) ?>">
                                            <button class="btn btn-sm btn-outline-secondary" type="submit"><i class="bi bi-<?= $r['status'] === 'Open' ? 'lock' : 'unlock' ?>"></i><span class="d-none d-xl-inline"> <?= $r['status'] === 'Open' ? 'Close' : 'Reopen' ?></span></button>
                                        </form>
                                        <form method="post" action="<?= href('admin/requests.php') ?>" class="d-inline" data-confirm="Remove this request permanently?">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="remove">
                                            <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                            <input type="hidden" name="return" value="<?= e($returnString) ?>">
                                            <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i><span class="d-none d-xl-inline"> Remove</span></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php render_pagination($page, $pages, 'admin/requests.php', $returnQuery); ?>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
