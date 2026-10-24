<?php
/*
 * my-listings.php - the logged-in student's marketplace listings.
 * Actions: Edit (sell.php), View, Mark as Sold / Available, Delete. Each action is a POST form with a CSRF token.
 */
require_once __DIR__ . '/includes/auth.php';
require_login('Please login to manage your listings.');

$me = current_user();
$myId = (int) $me['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = (int) ($_POST['id'] ?? 0);
    $action = read_text('action', '', 'post');
    if ($action === 'toggle' && toggle_listing_sold($id, $myId)) {
        set_flash('success', 'Listing status updated.');
    } elseif ($action === 'delete' && delete_listing($id, $myId)) {
        set_flash('success', 'Listing deleted.');
    } else {
        set_flash('danger', 'We could not update that listing. It may have been removed already.');
    }
    redirect('my-listings.php');
}

$status = read_text('status', 'All');
if (!in_array($status, ['All', 'Available', 'Sold'], true)) {
    $status = 'All';
}

$counts = db_select_one(
    "SELECT COUNT(*) AS total,
            SUM(status = 'Available') AS available,
            SUM(status = 'Sold') AS sold
     FROM listings WHERE user_id = ?",
    'i',
    [$myId]
);

$listings = db_select(
    'SELECT * FROM listings WHERE user_id = ?' . ($status !== 'All' ? ' AND status = ?' : '') . ' ORDER BY created_at DESC, id DESC',
    $status !== 'All' ? 'is' : 'i',
    $status !== 'All' ? [$myId, $status] : [$myId]
);

$pageTitle = 'My Listings';
$activeNav = 'marketplace';
$breadcrumbs = [['Dashboard', 'dashboard.php'], ['My Listings', '']];
require __DIR__ . '/includes/header.php';
render_page_head('Your marketplace items', 'My Listings', 'Edit, mark as sold or remove the items you are selling.',
    '<a class="btn btn-cb-accent" href="' . href('sell.php') . '"><i class="bi bi-plus-lg"></i> Sell an item</a>');
?>

<section class="container py-4 pb-5">
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-4">
            <a class="card cb-card cb-stat-card h-100 text-decoration-none" href="<?= href('my-listings.php') ?>"><div class="card-body"><div class="small text-muted">All listings</div><div class="cb-stat-value"><?= (int) $counts['total'] ?></div></div></a>
        </div>
        <div class="col-6 col-md-4">
            <a class="card cb-card cb-stat-card h-100 text-decoration-none" href="<?= href('my-listings.php?status=Available') ?>"><div class="card-body"><div class="small text-muted">Available</div><div class="cb-stat-value"><?= (int) $counts['available'] ?></div></div></a>
        </div>
        <div class="col-6 col-md-4">
            <a class="card cb-card cb-stat-card h-100 text-decoration-none" href="<?= href('my-listings.php?status=Sold') ?>"><div class="card-body"><div class="small text-muted">Sold</div><div class="cb-stat-value"><?= (int) $counts['sold'] ?></div></div></a>
        </div>
    </div>

    <ul class="nav nav-pills cb-tabs mb-4">
        <?php foreach (['All' => 'All', 'Available' => 'Available', 'Sold' => 'Sold'] as $key => $label): ?>
            <li class="nav-item"><a class="nav-link<?= $status === $key ? ' active' : '' ?>" href="<?= href('my-listings.php' . ($key !== 'All' ? '?status=' . $key : '')) ?>"><?= e($label) ?></a></li>
        <?php endforeach; ?>
    </ul>

    <?php if (!$listings): ?>
        <?php render_empty('bi-bag-plus', 'No listings here yet.', 'Sell textbooks, instruments or lab items you no longer need.',
            '<a class="btn btn-primary" href="' . href('sell.php') . '">Sell an item</a>'); ?>
    <?php else: ?>
        <div class="card cb-card">
            <div class="table-responsive">
                <table class="table cb-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">Item</th>
                            <th scope="col">Price</th>
                            <th scope="col" class="d-none d-md-table-cell">Condition</th>
                            <th scope="col">Status</th>
                            <th scope="col" class="d-none d-lg-table-cell">Posted</th>
                            <th scope="col" class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($listings as $l): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        <img class="cb-thumb" src="<?= e(listing_image_url($l['image'])) ?>" alt="" width="52" height="52" loading="lazy">
                                        <div class="min-w-0">
                                            <a class="fw-semibold text-body" href="<?= href('product.php?id=' . (int) $l['id']) ?>"><?= e($l['title']) ?></a>
                                            <div class="small text-muted"><?= e($l['category']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="fw-semibold"><?= format_price($l['price']) ?></td>
                                <td class="d-none d-md-table-cell"><?= condition_badge($l['item_condition']) ?></td>
                                <td><?= status_badge($l['status']) ?></td>
                                <td class="d-none d-lg-table-cell small text-muted"><?= e(format_date($l['created_at'])) ?></td>
                                <td class="text-end">
                                    <div class="cb-row-actions d-inline-flex flex-wrap justify-content-end gap-1">
                                        <a class="btn btn-sm btn-outline-primary" href="<?= href('sell.php?edit=' . (int) $l['id']) ?>" title="Edit"><i class="bi bi-pencil"></i><span class="d-none d-xl-inline"> Edit</span></a>
                                        <form method="post" action="<?= href('my-listings.php') ?>" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="toggle">
                                            <input type="hidden" name="id" value="<?= (int) $l['id'] ?>">
                                            <button class="btn btn-sm btn-outline-secondary" type="submit" title="<?= $l['status'] === 'Sold' ? 'Mark as available' : 'Mark as sold' ?>">
                                                <i class="bi bi-check2-circle"></i><span class="d-none d-xl-inline"> <?= $l['status'] === 'Sold' ? 'Available' : 'Sold' ?></span>
                                            </button>
                                        </form>
                                        <form method="post" action="<?= href('my-listings.php') ?>" class="d-inline" data-confirm="Delete this listing permanently? This cannot be undone.">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= (int) $l['id'] ?>">
                                            <button class="btn btn-sm btn-outline-danger" type="submit" title="Delete"><i class="bi bi-trash"></i><span class="d-none d-xl-inline"> Delete</span></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
