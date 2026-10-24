<?php
/*
 * admin/resources.php - moderate study resources.
 * Actions: View, Download (to check the file), Remove (deletes the database row AND the file).
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/admin-auth.php';
require_admin();

$types = xml_list('resourceTypes');
$branches = array_column(xml_list('branches'), 'value');
$q = trim(read_text('q', ''));
$type = read_text('type', '');
$branch = read_text('branch', '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;

if (!in_array($type, $types, true)) {
    $type = '';
}
if (!in_array($branch, $branches, true)) {
    $branch = '';
}
$returnQuery = array_filter(['q' => $q, 'type' => $type, 'branch' => $branch], fn($v) => $v !== '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = (int) ($_POST['id'] ?? 0);
    if (($_POST['action'] ?? '') === 'remove' && delete_resource($id)) {
        set_flash('success', 'Resource and its file were removed.');
    } else {
        set_flash('danger', 'That resource could not be removed. It may have been removed already.');
    }
    $back = read_text('return', '', 'post');
    redirect('admin/resources.php' . ($back !== '' ? '?' . $back : ''));
}

$where = [];
$tp = '';
$params = [];
if ($q !== '') {
    $like = '%' . addcslashes($q, '%_\\') . '%';
    $where[] = '(r.title LIKE ? OR r.subject LIKE ? OR u.full_name LIKE ?)';
    $tp .= 'sss';
    array_push($params, $like, $like, $like);
}
if ($type !== '') {
    $where[] = 'r.resource_type = ?';
    $tp .= 's';
    $params[] = $type;
}
if ($branch !== '') {
    $where[] = 'r.branch = ?';
    $tp .= 's';
    $params[] = $branch;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total = db_count("SELECT COUNT(*) AS total FROM resources r JOIN users u ON u.id = r.user_id $whereSql", $tp, $params);
$pages = max(1, (int) ceil($total / $perPage));
$page = min($page, $pages);
$rows = db_select(
    "SELECT r.*, u.full_name AS uploader_name
     FROM resources r JOIN users u ON u.id = r.user_id
     $whereSql
     ORDER BY r.created_at DESC, r.id DESC
     LIMIT ? OFFSET ?",
    $tp . 'ii',
    array_merge($params, [$perPage, ($page - 1) * $perPage])
);

$returnString = http_build_query(array_merge($returnQuery, ['page' => $page > 1 ? $page : null]));
$pageTitle = 'Manage Resources';
$activeNav = 'admin';
$adminSection = 'resources';
$breadcrumbs = [['Admin', 'admin/dashboard.php'], ['Resources', '']];
require __DIR__ . '/../includes/header.php';
render_page_head('Study Hub moderation', 'Resources', plural($total, 'resource') . ' found. Remove files that are wrong, copyrighted or inappropriate.');
?>

<section class="container py-4 pb-5">
    <form method="get" action="<?= href('admin/resources.php') ?>" class="card cb-card cb-filter-card mb-4">
        <div class="card-body p-3">
            <div class="row g-2 align-items-end">
                <div class="col-12 col-md-4">
                    <label class="form-label small fw-semibold" for="q">Search title, subject or uploader</label>
                    <input type="search" class="form-control" id="q" name="q" value="<?= e($q) ?>">
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label small fw-semibold" for="type">Type</label>
                    <select class="form-select" id="type" name="type">
                        <option value="">All types</option>
                        <?php foreach ($types as $t): ?><option value="<?= e($t) ?>"<?= $type === $t ? ' selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small fw-semibold" for="branch">Branch</label>
                    <select class="form-select" id="branch" name="branch">
                        <option value="">All</option>
                        <?php foreach ($branches as $b): ?><option value="<?= e($b) ?>"<?= $branch === $b ? ' selected' : '' ?>><?= e($b) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-3 d-flex gap-2">
                    <button class="btn btn-primary flex-grow-1" type="submit">Filter</button>
                    <a class="btn btn-outline-secondary" href="<?= href('admin/resources.php') ?>">Clear</a>
                </div>
            </div>
        </div>
    </form>

    <?php if (!$rows): ?>
        <?php render_empty('bi-journal-x', 'No resources match these filters.', 'Try a different type or branch.'); ?>
    <?php else: ?>
        <div class="card cb-card">
            <div class="table-responsive">
                <table class="table cb-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">Resource</th>
                            <th scope="col" class="d-none d-md-table-cell">Uploader</th>
                            <th scope="col" class="d-none d-lg-table-cell">Branch &middot; Y &middot; S</th>
                            <th scope="col" class="d-none d-sm-table-cell">File</th>
                            <th scope="col" class="d-none d-sm-table-cell">Downloads</th>
                            <th scope="col" class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?= e($r['title']) ?></div>
                                    <div class="small text-muted"><?= e($r['subject']) ?> &middot; <?= e($r['resource_type']) ?></div>
                                </td>
                                <td class="d-none d-md-table-cell"><?= e($r['uploader_name']) ?></td>
                                <td class="d-none d-lg-table-cell"><?= e($r['branch']) ?> &middot; Y<?= (int) $r['year'] ?> &middot; S<?= (int) $r['semester'] ?></td>
                                <td class="d-none d-sm-table-cell small text-uppercase"><?= e($r['file_ext']) ?> &middot; <?= e(format_bytes((int) $r['file_size'])) ?></td>
                                <td class="d-none d-sm-table-cell"><?= number_format((int) $r['download_count']) ?></td>
                                <td class="text-end">
                                    <div class="cb-row-actions d-inline-flex flex-wrap justify-content-end gap-1">
                                        <a class="btn btn-sm btn-outline-primary" href="<?= href('resource.php?id=' . (int) $r['id']) ?>" title="View"><i class="bi bi-eye"></i><span class="d-none d-xl-inline"> View</span></a>
                                        <a class="btn btn-sm btn-outline-secondary" href="<?= href('download.php?id=' . (int) $r['id'] . '&mode=view') ?>" target="_blank" rel="noopener" title="Open file"><i class="bi bi-box-arrow-up-right"></i></a>
                                        <form method="post" action="<?= href('admin/resources.php') ?>" class="d-inline" data-confirm="Remove this resource and delete its file permanently?">
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
        <?php render_pagination($page, $pages, 'admin/resources.php', $returnQuery); ?>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
