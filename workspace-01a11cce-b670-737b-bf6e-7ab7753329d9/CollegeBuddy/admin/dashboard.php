<?php
/*
 * admin/dashboard.php - admin overview: total users, listings, resources, requests and pending reports.
 * Only accounts with role = 'admin' can open this page (require_admin).
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/admin-auth.php';
require_admin();

$totals = db_select_one(
    "SELECT (SELECT COUNT(*) FROM users) AS users,
            (SELECT COUNT(*) FROM listings) AS listings,
            (SELECT COUNT(*) FROM resources) AS resources,
            (SELECT COUNT(*) FROM requests) AS requests,
            (SELECT COUNT(*) FROM reports WHERE status = 'Pending') AS pending_reports"
);

$pendingReports = db_select(
    "SELECT rp.id, rp.item_type, rp.item_id, rp.reason, rp.created_at, u.full_name AS reporter_name
     FROM reports rp JOIN users u ON u.id = rp.user_id
     WHERE rp.status = 'Pending'
     ORDER BY rp.created_at DESC LIMIT 5"
);
$newUsers = db_select(
    'SELECT id, full_name, email, branch, year, semester, role, created_at
     FROM users ORDER BY created_at DESC, id DESC LIMIT 5'
);

$pageTitle = 'Admin Dashboard';
$activeNav = 'admin';
$adminSection = 'dashboard';
$breadcrumbs = [['Admin', 'admin/dashboard.php'], ['Overview', '']];
require __DIR__ . '/../includes/header.php';
render_page_head('Administrator panel', 'Admin Dashboard', 'Live overview of College Buddy. Numbers come straight from the database.');

$cards = [
    ['bi-people', 'Total Users', (int) $totals['users'], 'users.php'],
    ['bi-bag', 'Listings', (int) $totals['listings'], 'listings.php'],
    ['bi-journal-bookmark', 'Resources', (int) $totals['resources'], 'resources.php'],
    ['bi-chat-dots', 'Requests', (int) $totals['requests'], 'requests.php'],
    ['bi-flag', 'Pending Reports', (int) $totals['pending_reports'], 'reports.php?status=Pending'],
];
?>

<section class="container py-4 pb-5">
    <div class="row g-3 g-md-4">
        <?php foreach ($cards as [$icon, $label, $value, $path]): ?>
            <div class="col-6 col-lg">
                <a class="card cb-card cb-stat-card cb-card-hover h-100 text-decoration-none<?= $label === 'Pending Reports' && $value > 0 ? ' cb-stat-alert' : '' ?>" href="<?= href('admin/' . $path) ?>">
                    <div class="card-body">
                        <span class="cb-stat-icon mb-2"><i class="bi <?= e($icon) ?>"></i></span>
                        <div class="small text-muted"><?= e($label) ?></div>
                        <div class="cb-stat-value"><?= number_format($value) ?></div>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="row g-4 mt-1">
        <div class="col-lg-6">
            <div class="card cb-card h-100">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 class="h6 mb-0"><i class="bi bi-flag"></i> Pending reports</h2>
                        <a class="small" href="<?= href('admin/reports.php?status=Pending') ?>">Review all</a>
                    </div>
                    <?php if (!$pendingReports): ?>
                        <p class="text-muted mb-0">No pending reports. Everything is under control.</p>
                    <?php else: ?>
                        <ul class="list-unstyled mb-0 cb-mini-list">
                            <?php foreach ($pendingReports as $r): ?>
                                <li class="d-flex justify-content-between align-items-center gap-2 py-2">
                                    <span class="min-w-0">
                                        <span class="d-block fw-semibold"><?= e($r['reason']) ?></span>
                                        <span class="d-block small text-muted"><?= e(ucfirst($r['item_type'])) ?> #<?= (int) $r['item_id'] ?> &middot; reported by <?= e($r['reporter_name']) ?> &middot; <?= e(time_ago($r['created_at'])) ?></span>
                                    </span>
                                    <a class="btn btn-sm btn-outline-primary" href="<?= href('admin/reports.php?status=Pending') ?>">Review</a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card cb-card h-100">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 class="h6 mb-0"><i class="bi bi-person-plus"></i> Newest members</h2>
                        <a class="small" href="<?= href('admin/users.php') ?>">Manage users</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table cb-table align-middle mb-0">
                            <thead><tr><th scope="col">Name</th><th scope="col">Branch</th><th scope="col">Role</th><th scope="col">Joined</th></tr></thead>
                            <tbody>
                                <?php foreach ($newUsers as $u): ?>
                                    <tr>
                                        <td><div class="fw-semibold"><?= e($u['full_name']) ?></div><div class="small text-muted text-truncate" style="max-width: 220px"><?= e($u['email']) ?></div></td>
                                        <td><?= e($u['branch']) ?></td>
                                        <td><span class="badge <?= $u['role'] === 'admin' ? 'bg-dark' : 'cb-badge-soft' ?>"><?= e(ucfirst($u['role'])) ?></span></td>
                                        <td class="small text-muted"><?= e(format_date($u['created_at'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
