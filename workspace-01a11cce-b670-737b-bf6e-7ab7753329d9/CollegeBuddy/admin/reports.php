<?php
/*
 * admin/reports.php - review reports sent by students.
 * Actions per report: Resolve, Dismiss, Reopen, or Remove the reported item (which also resolves its pending reports).
 * One JOIN query finds the title of the reported listing, resource or request.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/admin-auth.php';
require_admin();

$status = read_text('status', 'Pending');
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;
if (!in_array($status, ['Pending', 'Resolved', 'Dismissed', 'All'], true)) {
    $status = 'Pending';
}
$returnQuery = ['status' => $status];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $reportId = (int) ($_POST['id'] ?? 0);
    $action = read_text('action', '', 'post');
    $report = $reportId > 0 ? db_select_one('SELECT id, item_type, item_id FROM reports WHERE id = ?', 'i', [$reportId]) : null;

    if ($report === null) {
        set_flash('danger', 'That report no longer exists.');
    } elseif ($action === 'resolve' || $action === 'dismiss' || $action === 'reopen') {
        $newStatus = ['resolve' => 'Resolved', 'dismiss' => 'Dismissed', 'reopen' => 'Pending'][$action];
        db_exec('UPDATE reports SET status = ? WHERE id = ?', 'si', [$newStatus, $reportId]);
        set_flash('success', 'Report marked as ' . strtolower($newStatus) . '.');
    } elseif ($action === 'remove_item') {
        // Remove the reported content, then close every pending report about it
        $itemType = $report['item_type'];
        $itemId = (int) $report['item_id'];
        if ($itemType === 'listing') {
            $removed = delete_listing($itemId);
        } elseif ($itemType === 'resource') {
            $removed = delete_resource($itemId);
        } else {
            $removed = delete_request($itemId);
        }
        db_exec("UPDATE reports SET status = 'Resolved' WHERE item_type = ? AND item_id = ? AND status = 'Pending'", 'si', [$itemType, $itemId]);
        set_flash('success', $removed ? 'The reported ' . $itemType . ' was removed and the report was resolved.'
                                      : 'The item was already removed. The report was marked as resolved.');
    }
    $back = read_text('return', '', 'post');
    redirect('admin/reports.php' . ($back !== '' ? '?' . $back : ''));
}

// Filter with a placeholder (prepared statement), the same way as the other pages
$where = $status !== 'All' ? 'WHERE rp.status = ?' : '';
$tp = $status !== 'All' ? 's' : '';
$params = $status !== 'All' ? [$status] : [];
$total = db_count("SELECT COUNT(*) AS total FROM reports rp $where", $tp, $params);
$pages = max(1, (int) ceil($total / $perPage));
$page = min($page, $pages);

$rows = db_select(
    "SELECT rp.*, u.full_name AS reporter_name, u.email AS reporter_email,
            CASE rp.item_type
                WHEN 'listing' THEN l.title
                WHEN 'resource' THEN res.title
                WHEN 'request' THEN rq.title
            END AS item_title
     FROM reports rp
     JOIN users u ON u.id = rp.user_id
     LEFT JOIN listings l ON rp.item_type = 'listing' AND l.id = rp.item_id
     LEFT JOIN resources res ON rp.item_type = 'resource' AND res.id = rp.item_id
     LEFT JOIN requests rq ON rp.item_type = 'request' AND rq.id = rp.item_id
     $where
     ORDER BY rp.created_at DESC, rp.id DESC
     LIMIT ? OFFSET ?",
    $tp . 'ii',
    array_merge($params, [$perPage, ($page - 1) * $perPage])
);

$returnString = http_build_query(array_merge($returnQuery, ['page' => $page > 1 ? $page : null]));
$itemLinks = ['listing' => 'product.php?id=', 'resource' => 'resource.php?id=', 'request' => 'request.php?id='];

$pageTitle = 'Reports';
$activeNav = 'admin';
$adminSection = 'reports';
$breadcrumbs = [['Admin', 'admin/dashboard.php'], ['Reports', '']];
require __DIR__ . '/../includes/header.php';
render_page_head('Community safety', 'Reports', plural($total, 'report') . ' in this view. Review each one, then resolve, dismiss or remove the item.');
?>

<section class="container py-4 pb-5">
    <ul class="nav nav-pills cb-tabs mb-4">
        <?php foreach (['Pending' => 'Pending', 'Resolved' => 'Resolved', 'Dismissed' => 'Dismissed', 'All' => 'All'] as $key => $label): ?>
            <li class="nav-item"><a class="nav-link<?= $status === $key ? ' active' : '' ?>" href="<?= href('admin/reports.php?status=' . $key) ?>"><?= e($label) ?></a></li>
        <?php endforeach; ?>
    </ul>

    <?php if (!$rows): ?>
        <?php render_empty('bi-shield-check', 'No ' . ($status === 'All' ? '' : strtolower($status) . ' ') . 'reports.', 'When students report something, it appears here for review.'); ?>
    <?php else: ?>
        <div class="d-grid gap-3">
            <?php foreach ($rows as $r):
                $itemGone = $r['item_title'] === null;
                ?>
                <article class="card cb-card cb-report">
                    <div class="card-body p-4">
                        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
                            <div class="d-flex flex-wrap gap-2 align-items-center">
                                <span class="badge bg-dark-subtle text-dark-emphasis text-uppercase"><?= e($r['item_type']) ?> #<?= (int) $r['item_id'] ?></span>
                                <span class="badge bg-warning-subtle text-warning-emphasis"><?= e($r['reason']) ?></span>
                                <?= status_badge($r['status']) ?>
                            </div>
                            <span class="small text-muted"><?= e(format_date($r['created_at'], 'd M Y, h:i A')) ?></span>
                        </div>

                        <h2 class="h6 mb-1">
                            <?php if ($itemGone): ?>
                                <span class="text-muted">This item has already been removed.</span>
                            <?php else: ?>
                                <a href="<?= href($itemLinks[$r['item_type']] . (int) $r['item_id']) ?>"><?= e($r['item_title']) ?></a>
                            <?php endif; ?>
                        </h2>
                        <p class="small text-muted mb-2">Reported by <?= e($r['reporter_name']) ?> (<?= e($r['reporter_email']) ?>)</p>
                        <?php if (!empty($r['description'])): ?>
                            <p class="mb-3 cb-pre bg-light rounded-3 p-3 small"><?= e($r['description']) ?></p>
                        <?php endif; ?>

                        <div class="cb-row-actions d-flex flex-wrap gap-2">
                            <?php if ($r['status'] !== 'Resolved'): ?>
                                <form method="post" action="<?= href('admin/reports.php') ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="resolve">
                                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                    <input type="hidden" name="return" value="<?= e($returnString) ?>">
                                    <button class="btn btn-sm btn-success" type="submit"><i class="bi bi-check2"></i> Resolve</button>
                                </form>
                            <?php endif; ?>
                            <?php if ($r['status'] !== 'Dismissed'): ?>
                                <form method="post" action="<?= href('admin/reports.php') ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="dismiss">
                                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                    <input type="hidden" name="return" value="<?= e($returnString) ?>">
                                    <button class="btn btn-sm btn-outline-secondary" type="submit"><i class="bi bi-x-circle"></i> Dismiss</button>
                                </form>
                            <?php endif; ?>
                            <?php if ($r['status'] !== 'Pending'): ?>
                                <form method="post" action="<?= href('admin/reports.php') ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="reopen">
                                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                    <input type="hidden" name="return" value="<?= e($returnString) ?>">
                                    <button class="btn btn-sm btn-outline-primary" type="submit"><i class="bi bi-arrow-counterclockwise"></i> Reopen</button>
                                </form>
                            <?php endif; ?>
                            <?php if (!$itemGone): ?>
                                <form method="post" action="<?= href('admin/reports.php') ?>" data-confirm="Remove the reported item permanently? Its pending reports will be marked resolved.">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="remove_item">
                                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                    <input type="hidden" name="return" value="<?= e($returnString) ?>">
                                    <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i> Remove item</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
        <?php render_pagination($page, $pages, 'admin/reports.php', $returnQuery); ?>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
