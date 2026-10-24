<?php
/*
 * admin/listings.php - moderate marketplace listings.
 * Actions: View, Mark as sold / available, Remove (permanent delete together with the photo).
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/admin-auth.php';
require_admin();

$categories = xml_list('listingCategories');
$q = trim(read_text('q', ''));
$status = read_text('status', 'All');
$category = read_text('category', '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;

if (!in_array($status, ['All', 'Available', 'Sold'], true)) {
    $status = 'All';
}
if (!in_array($category, $categories, true)) {
    $category = '';
}

// Keeps the current filters after an action
$returnQuery = array_filter(['q' => $q, 'status' => $status !== 'All' ? $status : '', 'category' => $category, 'page' => $page > 1 ? $page : ''], fn($v) => $v !== '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = (int) ($_POST['id'] ?? 0);
    $action = read_text('action', '', 'post');
    if ($action === 'toggle' && toggle_listing_sold($id)) {
        set_flash('success', 'Listing status updated.');
    } elseif ($action === 'remove' && delete_listing($id)) {
        set_flash('success', 'Listing removed from the marketplace.');
    } else {
        set_flash('danger', 'That listing could not be updated. It may have been removed already.');
    }
    $back = read_text('return', '', 'post');
    redirect('admin/listings.php' . ($back !== '' ? '?' . $back : ''));
}

$where = [];
$tp = '';
$params = [];
if ($q !== '') {
    $like = '%' . addcslashes($q, '%_\\') . '%';
    $where[] = '(l.title LIKE ? OR u.full_name LIKE ?)';
    $tp .= 'ss';
    array_push($params, $like, $like);
}
if ($status !== 'All') {
    $where[] = 'l.status = ?';
    $tp .= 's';
    $params[] = $status;
}
if ($category !== '') {
    $where[] = 'l.category = ?';
    $tp .= 's';
    $params[] = $category;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total = db_count("SELECT COUNT(*) AS total FROM listings l JOIN users u ON u.id = l.user_id $whereSql", $tp, $params);
$pages = max(1, (int) ceil($total / $perPage));
$page = min($page, $pages);
$rows = db_select(
    "SELECT l.*, u.full_name AS seller_name
     FROM listings l JOIN users u ON u.id = l.user_id
     $whereSql
     ORDER BY l.created_at DESC, l.id DESC
     LIMIT ? OFFSET ?",
    $tp . 'ii',
    array_merge($params, [$perPage, ($page - 1) * $perPage])
);

$returnString = http_build_query(array_merge($returnQuery, ['page' => $page > 1 ? $page : null]) ?: []);
$pageTitle = 'Manage Listings';
$activeNav = 'admin';
$adminSection = 'listings';
$breadcrumbs = [['Admin', 'admin/dashboard.php'], ['Listings', '']];
require __DIR__ . '/../includes/header.php';
render_page_head('Marketplace moderation', 'Listings', plural($total, 'listing') . ' found. Remove listings that break the community rules.');
?>

<section class="container py-4 pb-5">
    <form method="get" action="<?= href('admin/listings.php') ?>" class="card cb-card cb-filter-card mb-4">
        <div class="card-body p-3">
            <div class="row g-2 align-items-end">
                <div class="col-12 col-md-4">
                    <label class="form-label small fw-semibold" for="q">Search title or seller</label>
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
                        <option value="Available"<?= $status === 'Available' ? ' selected' : '' ?>>Available</option>
                        <option value="Sold"<?= $status === 'Sold' ? ' selected' : '' ?>>Sold</option>
                    </select>
                </div>
                <div class="col-12 col-md-3 d-flex gap-2">
                    <button class="btn btn-primary flex-grow-1" type="submit">Filter</button>
                    <a class="btn btn-outline-secondary" href="<?= href('admin/listings.php') ?>">Clear</a>
                </div>
            </div>
        </div>
    </form>

    <?php if (!$rows): ?>
        <?php render_empty('bi-bag-x', 'No listings match these filters.', 'Try a different category or status.'); ?>
    <?php else: ?>
        <div class="card cb-card">
            <div class="table-responsive">
                <table class="table cb-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">Listing</th>
                            <th scope="col" class="d-none d-md-table-cell">Seller</th>
                            <th scope="col">Price</th>
                            <th scope="col" class="d-none d-lg-table-cell">Condition</th>
                            <th scope="col">Status</th>
                            <th scope="col" class="d-none d-xl-table-cell">Posted</th>
                            <th scope="col" class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $l): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        <img class="cb-thumb" src="<?= e(listing_image_url($l['image'])) ?>" alt="" width="46" height="46" loading="lazy">
                                        <div class="min-w-0">
                                            <a class="fw-semibold text-body" href="<?= href('product.php?id=' . (int) $l['id']) ?>"><?= e($l['title']) ?></a>
                                            <div class="small text-muted"><?= e($l['category']) ?> &middot; #<?= (int) $l['id'] ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="d-none d-md-table-cell"><?= e($l['seller_name']) ?></td>
                                <td><?= format_price($l['price']) ?></td>
                                <td class="d-none d-lg-table-cell"><?= condition_badge($l['item_condition']) ?></td>
                                <td><?= status_badge($l['status']) ?></td>
                                <td class="d-none d-xl-table-cell small text-muted"><?= e(format_date($l['created_at'])) ?></td>
                                <td class="text-end">
                                    <div class="cb-row-actions d-inline-flex flex-wrap justify-content-end gap-1">
                                        <a class="btn btn-sm btn-outline-primary" href="<?= href('product.php?id=' . (int) $l['id']) ?>"><i class="bi bi-eye"></i><span class="d-none d-xl-inline"> View</span></a>
                                        <form method="post" action="<?= href('admin/listings.php') ?>" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="toggle">
                                            <input type="hidden" name="id" value="<?= (int) $l['id'] ?>">
                                            <input type="hidden" name="return" value="<?= e($returnString) ?>">
                                            <button class="btn btn-sm btn-outline-secondary" type="submit"><i class="bi bi-check2-circle"></i><span class="d-none d-xl-inline"> <?= $l['status'] === 'Sold' ? 'Available' : 'Sold' ?></span></button>
                                        </form>
                                        <form method="post" action="<?= href('admin/listings.php') ?>" class="d-inline" data-confirm="Remove this listing and its photo permanently?">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="remove">
                                            <input type="hidden" name="id" value="<?= (int) $l['id'] ?>">
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
        <?php render_pagination($page, $pages, 'admin/listings.php', $returnQuery); ?>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
