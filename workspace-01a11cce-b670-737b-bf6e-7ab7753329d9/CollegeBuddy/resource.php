<?php
/*
 * resource.php - details of one study resource. Login is required to open it.
 * Buttons: "Download" (download.php) and "View in browser" for PDF, text and image files.
 */
require_once __DIR__ . '/includes/auth.php';
require_login('Please login to view and download study resources.');

$me = current_user();
$id = (int) ($_GET['id'] ?? 0);
$res = $id > 0 ? db_select_one(
    'SELECT r.*, u.full_name AS uploader_name, u.branch AS uploader_branch, u.year AS uploader_year
     FROM resources r JOIN users u ON u.id = r.user_id
     WHERE r.id = ?',
    'i',
    [$id]
) : null;

if ($res === null) {
    show_error_page(404, 'Resource not found', 'This study resource may have been removed, or the link is incorrect.');
}

$isOwner = (int) $res['user_id'] === (int) $me['id'];
$canView = in_array($res['file_ext'], ['pdf', 'txt', 'jpg', 'jpeg', 'png'], true);

$related = db_select(
    'SELECT id, title, resource_type, file_ext FROM resources WHERE subject = ? AND id <> ? ORDER BY created_at DESC LIMIT 3',
    'si',
    [$res['subject'], $id]
);

$pageTitle = $res['title'];
$activeNav = 'study';
$breadcrumbs = [['Study Hub', 'study.php'], [$res['title'], '']];
require __DIR__ . '/includes/header.php';
?>

<section class="container py-4 pb-5">
    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card cb-card">
                <div class="card-body p-4 p-md-5">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <span class="cb-resource-icon cb-resource-icon-lg"><i class="bi <?= e(resource_icon($res['resource_type'])) ?>"></i></span>
                        <div>
                            <span class="badge cb-badge-soft"><?= e($res['resource_type']) ?></span>
                            <h1 class="h3 mt-1 mb-0"><?= e($res['title']) ?></h1>
                        </div>
                    </div>
                    <p class="text-muted"><?= $res['description'] !== null && $res['description'] !== '' ? e($res['description']) : 'The uploader did not add a description for this file.' ?></p>

                    <dl class="row mb-0 cb-dl mt-4">
                        <dt class="col-sm-4">Subject</dt><dd class="col-sm-8"><?= e($res['subject']) ?></dd>
                        <dt class="col-sm-4">Branch</dt><dd class="col-sm-8"><?= e($res['branch']) ?></dd>
                        <dt class="col-sm-4">Year and semester</dt><dd class="col-sm-8">Year <?= (int) $res['year'] ?>, Semester <?= (int) $res['semester'] ?></dd>
                        <dt class="col-sm-4">Uploaded by</dt><dd class="col-sm-8"><?= e($res['uploader_name']) ?> (<?= e($res['uploader_branch']) ?>, Year <?= (int) $res['uploader_year'] ?>)</dd>
                        <dt class="col-sm-4">Uploaded on</dt><dd class="col-sm-8"><?= e(format_date($res['created_at'], 'd M Y, h:i A')) ?></dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card cb-card cb-sticky">
                <div class="card-body p-4">
                    <h2 class="h5 mb-3">File</h2>
                    <div class="cb-file-box d-flex align-items-center gap-3 p-3 rounded-3 mb-3">
                        <span class="cb-file-ext text-uppercase"><?= e($res['file_ext']) ?></span>
                        <div class="min-w-0">
                            <div class="fw-semibold text-break"><?= e($res['file_name']) ?></div>
                            <div class="small text-muted"><?= e(format_bytes((int) $res['file_size'])) ?> &middot; <?= (int) $res['download_count'] ?> downloads</div>
                        </div>
                    </div>

                    <div class="d-grid gap-2">
                        <a class="btn btn-primary btn-lg" href="<?= href('download.php?id=' . $id) ?>"><i class="bi bi-download"></i> Download</a>
                        <?php if ($canView): ?>
                            <a class="btn btn-outline-primary" href="<?= href('download.php?id=' . $id . '&mode=view') ?>" target="_blank" rel="noopener"><i class="bi bi-eye"></i> View in browser</a>
                        <?php endif; ?>
                    </div>

                    <p class="small text-muted mt-3 mb-3"><i class="bi bi-info-circle"></i> Study resources are free. Do not sell or resell them.</p>

                    <?php if ($isOwner): ?>
                        <a class="btn btn-outline-secondary w-100 mb-2" href="<?= href('my-resources.php') ?>"><i class="bi bi-folder2-open"></i> Manage in My Resources</a>
                    <?php endif; ?>
                    <a class="btn btn-link text-danger w-100 px-0" href="<?= href('report.php?type=resource&id=' . $id) ?>"><i class="bi bi-flag"></i> Report this resource</a>
                </div>
            </div>
        </div>
    </div>

    <?php if ($related): ?>
        <h2 class="h5 mt-5 mb-3">More resources on <?= e($res['subject']) ?></h2>
        <div class="row g-3">
            <?php foreach ($related as $r): ?>
                <div class="col-md-4">
                    <a class="card cb-card cb-card-hover h-100 text-decoration-none" href="<?= href('resource.php?id=' . (int) $r['id']) ?>">
                        <div class="card-body">
                            <span class="badge cb-badge-soft mb-2"><?= e($r['resource_type']) ?></span>
                            <div class="fw-semibold text-body"><?= e($r['title']) ?></div>
                            <div class="small text-muted text-uppercase"><?= e($r['file_ext']) ?></div>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
