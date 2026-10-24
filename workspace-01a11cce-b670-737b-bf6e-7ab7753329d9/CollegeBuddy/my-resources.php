<?php
/*
 * my-resources.php - study resources the logged-in student has uploaded.
 * Actions: View (resource.php) and Delete (removes the database row AND the file).
 */
require_once __DIR__ . '/includes/auth.php';
require_login('Please login to manage your uploaded resources.');

$me = current_user();
$myId = (int) $me['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = (int) ($_POST['id'] ?? 0);
    if (($_POST['action'] ?? '') === 'delete' && delete_resource($id, $myId)) {
        set_flash('success', 'Resource and its file were deleted.');
    } else {
        set_flash('danger', 'We could not delete that resource. It may have been removed already.');
    }
    redirect('my-resources.php');
}

$resources = db_select(
    'SELECT * FROM resources WHERE user_id = ? ORDER BY created_at DESC, id DESC',
    'i',
    [$myId]
);
$totalDownloads = array_sum(array_column($resources, 'download_count'));

$pageTitle = 'My Resources';
$activeNav = 'study';
$breadcrumbs = [['Dashboard', 'dashboard.php'], ['My Resources', '']];
require __DIR__ . '/includes/header.php';
render_page_head('Notes you have shared', 'My Resources', 'Study resources you uploaded to the Study Hub. You can view them or remove them.',
    '<a class="btn btn-cb-accent" href="' . href('upload-resource.php') . '"><i class="bi bi-cloud-arrow-up"></i> Upload a resource</a>');
?>

<section class="container py-4 pb-5">
    <div class="row g-3 mb-4">
        <div class="col-6">
            <div class="card cb-card cb-stat-card h-100"><div class="card-body"><div class="small text-muted">Resources uploaded</div><div class="cb-stat-value"><?= count($resources) ?></div></div></div>
        </div>
        <div class="col-6">
            <div class="card cb-card cb-stat-card h-100"><div class="card-body"><div class="small text-muted">Total downloads</div><div class="cb-stat-value"><?= number_format((int) $totalDownloads) ?></div></div></div>
        </div>
    </div>

    <?php if (!$resources): ?>
        <?php render_empty('bi-cloud-arrow-up', 'You have not shared any resources yet.', 'Upload your notes or a previous year paper. Your juniors will thank you.',
            '<a class="btn btn-primary" href="' . href('upload-resource.php') . '">Upload a resource</a>'); ?>
    <?php else: ?>
        <div class="card cb-card">
            <div class="table-responsive">
                <table class="table cb-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">Resource</th>
                            <th scope="col" class="d-none d-md-table-cell">Subject</th>
                            <th scope="col" class="d-none d-lg-table-cell">Branch &middot; Year &middot; Sem</th>
                            <th scope="col" class="d-none d-sm-table-cell">Downloads</th>
                            <th scope="col" class="d-none d-xl-table-cell">Uploaded</th>
                            <th scope="col" class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($resources as $r): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        <span class="cb-resource-icon"><i class="bi <?= e(resource_icon($r['resource_type'])) ?>"></i></span>
                                        <div class="min-w-0">
                                            <a class="fw-semibold text-body" href="<?= href('resource.php?id=' . (int) $r['id']) ?>"><?= e($r['title']) ?></a>
                                            <div class="small text-muted"><?= e($r['resource_type']) ?> &middot; <span class="text-uppercase"><?= e($r['file_ext']) ?></span> &middot; <?= e(format_bytes((int) $r['file_size'])) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="d-none d-md-table-cell"><?= e($r['subject']) ?></td>
                                <td class="d-none d-lg-table-cell"><?= e($r['branch']) ?> &middot; Y<?= (int) $r['year'] ?> &middot; S<?= (int) $r['semester'] ?></td>
                                <td class="d-none d-sm-table-cell"><?= number_format((int) $r['download_count']) ?></td>
                                <td class="d-none d-xl-table-cell small text-muted"><?= e(format_date($r['created_at'])) ?></td>
                                <td class="text-end">
                                    <div class="cb-row-actions d-inline-flex flex-wrap justify-content-end gap-1">
                                        <a class="btn btn-sm btn-outline-primary" href="<?= href('resource.php?id=' . (int) $r['id']) ?>"><i class="bi bi-eye"></i><span class="d-none d-xl-inline"> View</span></a>
                                        <form method="post" action="<?= href('my-resources.php') ?>" class="d-inline" data-confirm="Delete this resource and its file? Students will no longer be able to download it.">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                            <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i><span class="d-none d-xl-inline"> Delete</span></button>
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
