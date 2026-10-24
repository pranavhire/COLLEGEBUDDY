<?php
/*
 * request.php - one request in detail.
 * Other students can respond (sends a message to the requester). The requester can close/reopen
 * or delete the request, and can read and reply to every response.
 */
require_once __DIR__ . '/includes/auth.php';

$id = (int) ($_GET['id'] ?? 0);
$req = $id > 0 ? db_select_one(
    'SELECT r.*, u.full_name AS requester_name, u.branch AS requester_branch, u.year AS requester_year
     FROM requests r JOIN users u ON u.id = r.user_id
     WHERE r.id = ?',
    'i',
    [$id]
) : null;

if ($req === null) {
    show_error_page(404, 'Request not found', 'This request may have been deleted, or the link is incorrect.');
}

$me = current_user();
$isOwner = $me !== null && (int) $me['id'] === (int) $req['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = read_text('action', '', 'post');

    if ($action === 'respond') {
        require_login('Please login to respond to this request.');
        $body = trim(read_text('message', '', 'post'));
        $len = mb_strlen($body);
        if ($isOwner) {
            set_flash('warning', 'You cannot respond to your own request.');
        } elseif ($req['status'] !== 'Open') {
            set_flash('warning', 'This request is closed, so responses are no longer accepted.');
        } elseif ($len < 5 || $len > 1000) {
            set_flash('danger', 'Your response must be between 5 and 1000 characters.');
        } else {
            send_message((int) $me['id'], (int) $req['user_id'], 'request', $id, $body);
            set_flash('success', 'Your response was sent to ' . $req['requester_name'] . '. They can reply in Messages.');
        }
        redirect('request.php?id=' . $id . '#respond');
    }

    if ($isOwner && $action === 'toggle') {
        toggle_request_status($id, (int) $me['id']);
        set_flash('success', $req['status'] === 'Open' ? 'Your request is now closed.' : 'Your request is open again.');
        redirect('request.php?id=' . $id);
    }

    if ($isOwner && $action === 'delete') {
        delete_request($id, (int) $me['id']);
        set_flash('success', 'Your request was deleted.');
        redirect('my-requests.php');
    }

    redirect('request.php?id=' . $id);
}

// Responses received by the requester (only the owner sees them)
$responses = [];
$myResponse = null;
if ($isOwner) {
    $responses = db_select(
        "SELECT m.*, u.full_name AS sender_name, u.branch AS sender_branch
         FROM messages m JOIN users u ON u.id = m.sender_id
         WHERE m.context_type = 'request' AND m.context_id = ? AND m.receiver_id = ?
         ORDER BY m.created_at DESC",
        'ii',
        [$id, (int) $me['id']]
    );
} elseif ($me !== null) {
    // Has this student already responded? Then show a link to continue the conversation
    $myResponse = db_select_one(
        "SELECT id FROM messages WHERE context_type = 'request' AND context_id = ? AND sender_id = ? LIMIT 1",
        'ii',
        [$id, (int) $me['id']]
    );
}

$pageTitle = $req['title'];
$activeNav = 'requests';
$breadcrumbs = [['Request Board', 'requests.php'], [$req['title'], '']];
require __DIR__ . '/includes/header.php';
?>

<section class="container py-4 pb-5">
    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card cb-card">
                <div class="card-body p-4 p-md-5">
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <span class="badge cb-badge-soft"><?= e($req['category']) ?></span>
                        <?= status_badge($req['status']) ?>
                    </div>
                    <h1 class="h3 mb-3"><?= e($req['title']) ?></h1>
                    <p class="mb-4 cb-pre"><?= e($req['description']) ?></p>
                    <dl class="row mb-0 cb-dl">
                        <dt class="col-sm-4">Posted by</dt><dd class="col-sm-8"><?= e($req['requester_name']) ?> (<?= e($req['requester_branch']) ?>, Year <?= (int) $req['requester_year'] ?>)</dd>
                        <dt class="col-sm-4">Posted on</dt><dd class="col-sm-8"><?= e(format_date($req['created_at'], 'd M Y, h:i A')) ?> (<?= e(time_ago($req['created_at'])) ?>)</dd>
                    </dl>
                </div>
            </div>

            <?php if ($isOwner): ?>
                <div class="card cb-card mt-4" id="responses">
                    <div class="card-body p-4">
                        <h2 class="h5 mb-3"><i class="bi bi-chat-dots"></i> Responses (<?= count($responses) ?>)</h2>
                        <?php if (!$responses): ?>
                            <p class="text-muted mb-0">No responses yet. Students who can help will appear here.</p>
                        <?php else: ?>
                            <ul class="list-unstyled mb-0 cb-thread-list">
                                <?php foreach ($responses as $m): ?>
                                    <li class="cb-thread-item d-flex gap-3">
                                        <span class="cb-avatar"><?= e(initials($m['sender_name'])) ?></span>
                                        <div class="flex-grow-1 min-w-0">
                                            <div class="d-flex flex-wrap justify-content-between gap-2">
                                                <span class="fw-semibold"><?= e($m['sender_name']) ?> <span class="small text-muted fw-normal">&middot; <?= e($m['sender_branch']) ?></span></span>
                                                <span class="small text-muted"><?= e(time_ago($m['created_at'])) ?></span>
                                            </div>
                                            <p class="mb-2 cb-pre"><?= e($m['body']) ?></p>
                                            <a class="btn btn-sm btn-outline-primary" href="<?= href('messages.php?type=request&ctx=' . $id . '&with=' . (int) $m['sender_id']) ?>"><i class="bi bi-reply"></i> Reply</a>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="col-lg-5">
            <div class="card cb-card cb-sticky" id="respond">
                <div class="card-body p-4">
                    <?php if ($isOwner): ?>
                        <h2 class="h5 mb-3">Manage your request</h2>
                        <p class="small text-muted">Status: <?= status_badge($req['status']) ?></p>
                        <div class="d-grid gap-2">
                            <form method="post" action="<?= href('request.php?id=' . $id) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="toggle">
                                <button class="btn btn-outline-secondary w-100" type="submit">
                                    <i class="bi bi-<?= $req['status'] === 'Open' ? 'lock' : 'unlock' ?>"></i>
                                    <?= $req['status'] === 'Open' ? 'Close request' : 'Reopen request' ?>
                                </button>
                            </form>
                            <form method="post" action="<?= href('request.php?id=' . $id) ?>" data-confirm="Delete this request permanently?">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <button class="btn btn-outline-danger w-100" type="submit"><i class="bi bi-trash"></i> Delete request</button>
                            </form>
                        </div>
                        <a class="btn btn-link px-0 mt-2" href="<?= href('my-requests.php') ?>"><i class="bi bi-arrow-left"></i> Back to My Requests</a>

                    <?php elseif ($req['status'] === 'Closed'): ?>
                        <h2 class="h5 mb-3">This request is closed</h2>
                        <p class="text-muted mb-0">The requester has closed this request, so new responses are not accepted. Browse the <a href="<?= href('requests.php') ?>">Request Board</a> for other requests.</p>

                    <?php elseif ($me === null): ?>
                        <h2 class="h5 mb-2">Can you help?</h2>
                        <p class="text-muted">Login to respond. Your e-mail address is never shown to other students.</p>
                        <a class="btn btn-primary w-100" href="<?= href('login.php?next=' . urlencode('request.php?id=' . $id)) ?>"><i class="bi bi-box-arrow-in-right"></i> Login to respond</a>

                    <?php elseif ($myResponse): ?>
                        <h2 class="h5 mb-2">You have responded</h2>
                        <p class="text-muted">Your response was sent to <?= e($req['requester_name']) ?>. Continue the conversation in your inbox.</p>
                        <a class="btn btn-outline-primary w-100" href="<?= href('messages.php?type=request&ctx=' . $id . '&with=' . (int) $req['user_id']) ?>"><i class="bi bi-chat-left-text"></i> Open conversation</a>

                    <?php else: ?>
                        <h2 class="h5 mb-3">Respond to this request</h2>
                        <form method="post" action="<?= href('request.php?id=' . $id . '#respond') ?>" novalidate data-validate>
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="respond">
                            <div class="cb-field">
                                <label class="form-label" for="message">Your response</label>
                                <textarea class="form-control" id="message" name="message" rows="5" maxlength="1000"
                                          placeholder="Hi, I have this with me. I can share it or meet at the library."
                                          data-required data-min-length="5" data-max-length="1000" data-label="Response" data-counter="1000"></textarea>
                                <div class="invalid-feedback"></div>
                            </div>
                            <button class="btn btn-primary w-100 mt-3" type="submit"><i class="bi bi-send"></i> Send response</button>
                        </form>
                    <?php endif; ?>

                    <?php if (!$isOwner): ?>
                        <a class="btn btn-link text-danger w-100 mt-3" href="<?= href('report.php?type=request&id=' . $id) ?>"><i class="bi bi-flag"></i> Report this request</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
