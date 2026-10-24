<?php
/*
 * admin/users.php - list, search, view and delete student accounts.
 * Deleting a user also removes their listings, resources, requests, messages and uploaded files.
 * Admin accounts cannot be deleted from this page, and an admin cannot delete themselves.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/admin-auth.php';
require_admin();

$me = current_user();
$myId = (int) $me['id'];
$q = trim(read_text('q', ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;
$viewId = (int) ($_GET['view'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = (int) ($_POST['id'] ?? 0);
    $target = $id > 0 ? db_select_one('SELECT id, full_name, role FROM users WHERE id = ?', 'i', [$id]) : null;

    if (($_POST['action'] ?? '') === 'delete') {
        if ($target === null) {
            set_flash('danger', 'That user no longer exists.');
        } elseif ((int) $target['id'] === $myId) {
            set_flash('danger', 'You cannot delete your own admin account.');
        } elseif ($target['role'] === 'admin') {
            set_flash('danger', 'Admin accounts cannot be deleted from this panel.');
        } elseif (delete_user($id)) {
            set_flash('success', 'Deleted ' . $target['full_name'] . "'s account, with their listings, resources and files.");
        } else {
            set_flash('danger', 'We could not delete that account. Please try again.');
        }
    }
    redirect('admin/users.php' . ($q !== '' ? '?q=' . urlencode($q) : ''));
}

$where = '';
$tp = '';
$params = [];
if ($q !== '') {
    $like = '%' . addcslashes($q, '%_\\') . '%';
    $where = 'WHERE (full_name LIKE ? OR email LIKE ? OR branch LIKE ?)';
    $tp = 'sss';
    $params = [$like, $like, $like];
}
$total = db_count("SELECT COUNT(*) AS total FROM users $where", $tp, $params);
$pages = max(1, (int) ceil($total / $perPage));
$page = min($page, $pages);
$users = db_select(
    "SELECT id, full_name, email, branch, year, semester, role, created_at FROM users $where
     ORDER BY created_at DESC, id DESC LIMIT ? OFFSET ?",
    $tp . 'ii',
    array_merge($params, [$perPage, ($page - 1) * $perPage])
);

// Detail panel for one user (?view=ID)
$detail = null;
if ($viewId > 0) {
    $detail = db_select_one(
        'SELECT u.*,
                (SELECT COUNT(*) FROM listings WHERE user_id = u.id) AS listings,
                (SELECT COUNT(*) FROM resources WHERE user_id = u.id) AS resources,
                (SELECT COUNT(*) FROM requests WHERE user_id = u.id) AS requests,
                (SELECT COUNT(*) FROM reports WHERE user_id = u.id) AS reports_sent
         FROM users u WHERE u.id = ?',
        'i',
        [$viewId]
    );
    if ($detail !== null) {
        $detailListings = db_select('SELECT id, title, status FROM listings WHERE user_id = ? ORDER BY created_at DESC LIMIT 5', 'i', [$viewId]);
        $detailResources = db_select('SELECT id, title, resource_type FROM resources WHERE user_id = ? ORDER BY created_at DESC LIMIT 5', 'i', [$viewId]);
        $detailRequests = db_select('SELECT id, title, status FROM requests WHERE user_id = ? ORDER BY created_at DESC LIMIT 5', 'i', [$viewId]);
    }
}

$pageTitle = 'Manage Users';
$activeNav = 'admin';
$adminSection = 'users';
$breadcrumbs = [['Admin', 'admin/dashboard.php'], ['Users', '']];
require __DIR__ . '/../includes/header.php';
render_page_head('Student accounts', 'Users', plural($total, 'account') . ' found. Search by name, e-mail or branch.');
?>

<section class="container py-4 pb-5">
    <?php if ($detail !== null): ?>
        <div class="card cb-card cb-detail-panel mb-4" id="userDetail">
            <div class="card-body p-4">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
                    <div class="d-flex align-items-center gap-3">
                        <span class="cb-avatar cb-avatar-lg"><?= e(initials($detail['full_name'])) ?></span>
                        <div>
                            <h2 class="h5 mb-0"><?= e($detail['full_name']) ?></h2>
                            <div class="small text-muted"><?= e($detail['email']) ?></div>
                        </div>
                    </div>
                    <a class="btn btn-sm btn-outline-secondary" href="<?= href('admin/users.php' . ($q !== '' ? '?q=' . urlencode($q) : '')) ?>"><i class="bi bi-x-lg"></i> Close</a>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <dl class="row mb-0 cb-dl small">
                            <dt class="col-5">Branch</dt><dd class="col-7"><?= e($detail['branch']) ?></dd>
                            <dt class="col-5">Year / Semester</dt><dd class="col-7">Year <?= (int) $detail['year'] ?>, Semester <?= (int) $detail['semester'] ?></dd>
                            <dt class="col-5">Role</dt><dd class="col-7"><?= e(ucfirst($detail['role'])) ?></dd>
                            <dt class="col-5">Joined</dt><dd class="col-7"><?= e(format_date($detail['created_at'], 'd M Y')) ?></dd>
                            <dt class="col-5">Activity</dt><dd class="col-7"><?= (int) $detail['listings'] ?> listings, <?= (int) $detail['resources'] ?> resources, <?= (int) $detail['requests'] ?> requests, <?= (int) $detail['reports_sent'] ?> reports sent</dd>
                        </dl>
                    </div>
                    <div class="col-md-6">
                        <h3 class="h6">Latest listings</h3>
                        <ul class="small mb-3">
                            <?php foreach ($detailListings as $l): ?>
                                <li><a href="<?= href('product.php?id=' . (int) $l['id']) ?>"><?= e($l['title']) ?></a> <span class="text-muted">(<?= e($l['status']) ?>)</span></li>
                            <?php endforeach; ?>
                            <?php if (!$detailListings): ?><li class="text-muted">None</li><?php endif; ?>
                        </ul>
                        <h3 class="h6">Latest resources</h3>
                        <ul class="small mb-3">
                            <?php foreach ($detailResources as $r): ?>
                                <li><a href="<?= href('resource.php?id=' . (int) $r['id']) ?>"><?= e($r['title']) ?></a> <span class="text-muted">(<?= e($r['resource_type']) ?>)</span></li>
                            <?php endforeach; ?>
                            <?php if (!$detailResources): ?><li class="text-muted">None</li><?php endif; ?>
                        </ul>
                        <h3 class="h6">Latest requests</h3>
                        <ul class="small mb-0">
                            <?php foreach ($detailRequests as $r): ?>
                                <li><a href="<?= href('request.php?id=' . (int) $r['id']) ?>"><?= e($r['title']) ?></a> <span class="text-muted">(<?= e($r['status']) ?>)</span></li>
                            <?php endforeach; ?>
                            <?php if (!$detailRequests): ?><li class="text-muted">None</li><?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    <?php elseif ($viewId > 0): ?>
        <div class="alert alert-warning">That user could not be found.</div>
    <?php endif; ?>

    <form method="get" action="<?= href('admin/users.php') ?>" class="card cb-card cb-filter-card mb-4">
        <div class="card-body p-3 d-flex flex-column flex-sm-row gap-2">
            <input type="search" class="form-control" name="q" value="<?= e($q) ?>" placeholder="Search by name, e-mail or branch" aria-label="Search users">
            <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i> Search</button>
            <a class="btn btn-outline-secondary" href="<?= href('admin/users.php') ?>">Clear</a>
        </div>
    </form>

    <?php if (!$users): ?>
        <?php render_empty('bi-people', 'No users match your search.', 'Try a different name, e-mail or branch.'); ?>
    <?php else: ?>
        <div class="card cb-card">
            <div class="table-responsive">
                <table class="table cb-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">Name &amp; e-mail</th>
                            <th scope="col" class="d-none d-md-table-cell">Branch</th>
                            <th scope="col" class="d-none d-lg-table-cell">Year / Sem</th>
                            <th scope="col">Role</th>
                            <th scope="col" class="d-none d-lg-table-cell">Joined</th>
                            <th scope="col" class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u):
                            $protected = $u['role'] === 'admin' || (int) $u['id'] === $myId;
                            ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?= e($u['full_name']) ?></div>
                                    <div class="small text-muted text-break"><?= e($u['email']) ?></div>
                                </td>
                                <td class="d-none d-md-table-cell"><?= e($u['branch']) ?></td>
                                <td class="d-none d-lg-table-cell">Y<?= (int) $u['year'] ?> / S<?= (int) $u['semester'] ?></td>
                                <td><span class="badge <?= $u['role'] === 'admin' ? 'bg-dark' : 'cb-badge-soft' ?>"><?= e(ucfirst($u['role'])) ?></span></td>
                                <td class="d-none d-lg-table-cell small text-muted"><?= e(format_date($u['created_at'])) ?></td>
                                <td class="text-end">
                                    <div class="cb-row-actions d-inline-flex gap-1">
                                        <a class="btn btn-sm btn-outline-primary" href="<?= href('admin/users.php?view=' . (int) $u['id'] . ($q !== '' ? '&q=' . urlencode($q) : '')) ?>"><i class="bi bi-eye"></i> View</a>
                                        <?php if ($protected): ?>
                                            <button class="btn btn-sm btn-outline-secondary" type="button" disabled title="Admin accounts are protected"><i class="bi bi-shield-lock"></i></button>
                                        <?php else: ?>
                                            <form method="post" action="<?= href('admin/users.php') ?>" class="d-inline" data-confirm="Delete this account? Their listings, resources, requests and files will be removed too.">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                                                <input type="hidden" name="q" value="<?= e($q) ?>">
                                                <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i> Delete</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php render_pagination($page, $pages, 'admin/users.php', array_filter(['q' => $q], fn($v) => $v !== '')); ?>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
