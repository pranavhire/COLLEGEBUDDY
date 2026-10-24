<?php
/*
 * my-requests.php - requests posted by the logged-in student.
 * Actions: View (request.php), Close / Reopen, Delete.
 */
require_once __DIR__ . '/includes/auth.php';
require_login('Please login to manage your requests.');

$me = current_user();
$myId = (int) $me['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = (int) ($_POST['id'] ?? 0);
    $action = read_text('action', '', 'post');
    if ($action === 'toggle' && toggle_request_status($id, $myId)) {
        set_flash('success', 'Request status updated.');
    } elseif ($action === 'delete' && delete_request($id, $myId)) {
        set_flash('success', 'Request deleted.');
    } else {
        set_flash('danger', 'We could not update that request. It may have been removed already.');
    }
    redirect('my-requests.php');
}

$status = read_text('status', 'All');
if (!in_array($status, ['All', 'Open', 'Closed'], true)) {
    $status = 'All';
}

$requests = db_select(
    "SELECT r.*,
            (SELECT COUNT(*) FROM messages m WHERE m.context_type = 'request' AND m.context_id = r.id AND m.receiver_id = ?) AS responses
     FROM requests r
     WHERE r.user_id = ?" . ($status !== 'All' ? ' AND r.status = ?' : '') . '
     ORDER BY r.created_at DESC, r.id DESC',
    $status !== 'All' ? 'iis' : 'ii',
    $status !== 'All' ? [$myId, $myId, $status] : [$myId, $myId]
);

$pageTitle = 'My Requests';
$activeNav = 'requests';
$breadcrumbs = [['Dashboard', 'dashboard.php'], ['My Requests', '']];
require __DIR__ . '/includes/header.php';
render_page_head('Things you asked for', 'My Requests', 'Track the requests you posted, read responses and close the ones you no longer need.',
    '<a class="btn btn-cb-accent" href="' . href('create-request.php') . '"><i class="bi bi-plus-lg"></i> Post a request</a>');
?>

<section class="container py-4 pb-5">
    <ul class="nav nav-pills cb-tabs mb-4">
        <?php foreach (['All' => 'All', 'Open' => 'Open', 'Closed' => 'Closed'] as $key => $label): ?>
            <li class="nav-item"><a class="nav-link<?= $status === $key ? ' active' : '' ?>" href="<?= href('my-requests.php' . ($key !== 'All' ? '?status=' . $key : '')) ?>"><?= e($label) ?></a></li>
        <?php endforeach; ?>
    </ul>

    <?php if (!$requests): ?>
        <?php render_empty('bi-chat-square-text', 'No requests here yet.', 'Post a request when you need a book, notes or help with a practical.',
            '<a class="btn btn-primary" href="' . href('create-request.php') . '">Post a request</a>'); ?>
    <?php else: ?>
        <div class="card cb-card">
            <div class="table-responsive">
                <table class="table cb-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">Request</th>
                            <th scope="col" class="d-none d-md-table-cell">Category</th>
                            <th scope="col">Status</th>
                            <th scope="col" class="d-none d-sm-table-cell">Responses</th>
                            <th scope="col" class="d-none d-lg-table-cell">Posted</th>
                            <th scope="col" class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requests as $r): ?>
                            <tr>
                                <td>
                                    <a class="fw-semibold text-body" href="<?= href('request.php?id=' . (int) $r['id']) ?>"><?= e($r['title']) ?></a>
                                    <div class="small text-muted cb-clamp-1 d-md-none"><?= e($r['category']) ?></div>
                                </td>
                                <td class="d-none d-md-table-cell"><span class="badge cb-badge-soft"><?= e($r['category']) ?></span></td>
                                <td><?= status_badge($r['status']) ?></td>
                                <td class="d-none d-sm-table-cell"><?= (int) $r['responses'] ?></td>
                                <td class="d-none d-lg-table-cell small text-muted"><?= e(format_date($r['created_at'])) ?></td>
                                <td class="text-end">
                                    <div class="cb-row-actions d-inline-flex flex-wrap justify-content-end gap-1">
                                        <a class="btn btn-sm btn-outline-primary" href="<?= href('request.php?id=' . (int) $r['id']) ?>"><i class="bi bi-eye"></i><span class="d-none d-xl-inline"> View</span></a>
                                        <form method="post" action="<?= href('my-requests.php') ?>" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="toggle">
                                            <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                            <button class="btn btn-sm btn-outline-secondary" type="submit">
                                                <i class="bi bi-<?= $r['status'] === 'Open' ? 'lock' : 'unlock' ?>"></i><span class="d-none d-xl-inline"> <?= $r['status'] === 'Open' ? 'Close' : 'Reopen' ?></span>
                                            </button>
                                        </form>
                                        <form method="post" action="<?= href('my-requests.php') ?>" class="d-inline" data-confirm="Delete this request permanently?">
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
