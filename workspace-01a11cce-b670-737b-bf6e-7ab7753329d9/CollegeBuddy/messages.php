<?php
/*
 * messages.php - private messages between students, always linked to a listing or a request.
 *   messages.php                             -> Inbox / Sent list of conversations
 *   messages.php?type=listing&ctx=3&with=5   -> one conversation with a reply form
 * Students never see each other's e-mail address. Only the seller/requester and the other student can see a thread.
 */
require_once __DIR__ . '/includes/auth.php';
require_login('Please login to read your messages.');

$me = current_user();
$myId = (int) $me['id'];

$type = read_text('type', '', 'request');
$ctx = (int) ($_POST['ctx'] ?? $_GET['ctx'] ?? 0);
$with = (int) ($_POST['with'] ?? $_GET['with'] ?? 0);
$inThread = in_array($type, ['listing', 'request'], true) && $ctx > 0 && $with > 0;

/**
 * Returns the user id of the seller (listing) or requester (request), or null if the item does not exist.
 * Used to make sure a conversation only involves the item owner and one other student.
 */
function message_item_owner(string $type, int $id): ?int
{
    $table = $type === 'listing' ? 'listings' : 'requests';
    $row = db_select_one("SELECT user_id FROM {$table} WHERE id = ?", 'i', [$id]);
    return $row ? (int) $row['user_id'] : null;
}

/** Checks that $myId and $with are the two people allowed in this thread. */
function thread_is_allowed(string $type, int $ctx, int $myId, int $with): bool
{
    $owner = message_item_owner($type, $ctx);
    if ($owner === null || $with === $myId) {
        return false;
    }
    return $owner === $myId || $owner === $with;
}

// Reply (POST) inside a conversation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (!$inThread || !thread_is_allowed($type, $ctx, $myId, $with)) {
        set_flash('danger', 'That conversation is not available.');
        redirect('messages.php');
    }
    $body = trim(read_text('body', '', 'post'));
    $len = mb_strlen($body);
    if ($len < 2 || $len > 1000) {
        set_flash('danger', 'Your message must be between 2 and 1000 characters.');
    } else {
        send_message($myId, $with, $type, $ctx, $body);
        set_flash('success', 'Message sent.');
    }
    redirect('messages.php?type=' . $type . '&ctx=' . $ctx . '&with=' . $with . '#latest');
}

if ($inThread) {
    // ---------- One conversation ----------
    if (!thread_is_allowed($type, $ctx, $myId, $with)) {
        show_error_page(404, 'Conversation not found', 'This conversation does not exist, or you do not have access to it.');
    }
    $partner = db_select_one('SELECT id, full_name, branch FROM users WHERE id = ?', 'i', [$with]);
    if ($partner === null) {
        show_error_page(404, 'Conversation not found', 'This conversation does not exist, or you do not have access to it.');
    }
    $item = $type === 'listing'
        ? db_select_one('SELECT id, title FROM listings WHERE id = ?', 'i', [$ctx])
        : db_select_one('SELECT id, title FROM requests WHERE id = ?', 'i', [$ctx]);
    $itemLink = $type === 'listing' ? 'product.php?id=' . $ctx : 'request.php?id=' . $ctx;

    $messages = db_select(
        "SELECT m.*, u.full_name AS sender_name
         FROM messages m JOIN users u ON u.id = m.sender_id
         WHERE m.context_type = ? AND m.context_id = ?
           AND ((m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?))
         ORDER BY m.created_at ASC, m.id ASC",
        'siiiii',
        [$type, $ctx, $myId, $with, $with, $myId]
    );
    // Mark what the other student sent to me as read (after reading, so "New" tags still show)
    db_exec(
        'UPDATE messages SET is_read = 1 WHERE receiver_id = ? AND sender_id = ? AND context_type = ? AND context_id = ? AND is_read = 0',
        'iisi',
        [$myId, $with, $type, $ctx]
    );

    $pageTitle = 'Conversation with ' . $partner['full_name'];
    $activeNav = '';
    $breadcrumbs = [['Messages', 'messages.php'], [$partner['full_name'], '']];
    require __DIR__ . '/includes/header.php';
    render_page_head('Messages', $partner['full_name'], $item ? 'About: ' . $item['title'] : 'About an item that was removed',
        '<a class="btn btn-outline-secondary" href="' . href('messages.php') . '"><i class="bi bi-arrow-left"></i> All messages</a>'
        . ($item ? '<a class="btn btn-outline-primary" href="' . href($itemLink) . '">View item</a>' : ''));
    ?>

    <section class="container pb-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card cb-card">
                    <div class="card-body p-3 p-md-4">
                        <div class="cb-chat">
                            <?php if (!$messages): ?>
                                <p class="text-muted text-center my-4">No messages yet. Say hello and explain what you need.</p>
                            <?php endif; ?>
                            <?php foreach ($messages as $m):
                                $mine = (int) $m['sender_id'] === $myId;
                                $isNew = !$mine && !(int) $m['is_read'];
                                ?>
                                <div class="cb-bubble-row<?= $mine ? ' is-mine' : '' ?>">
                                    <div class="cb-bubble<?= $mine ? ' cb-bubble-mine' : ' cb-bubble-theirs' ?>">
                                        <div class="cb-bubble-meta">
                                            <?= $mine ? 'You' : e($m['sender_name']) ?> &middot; <?= e(format_date($m['created_at'], 'd M, h:i A')) ?>
                                            <?php if ($isNew): ?><span class="badge bg-warning text-dark ms-1">New</span><?php endif; ?>
                                        </div>
                                        <div class="cb-pre"><?= e($m['body']) ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <span id="latest"></span>
                        </div>

                        <form method="post" action="<?= href('messages.php') ?>" class="mt-4" novalidate data-validate>
                            <?= csrf_field() ?>
                            <input type="hidden" name="type" value="<?= e($type) ?>">
                            <input type="hidden" name="ctx" value="<?= $ctx ?>">
                            <input type="hidden" name="with" value="<?= $with ?>">
                            <div class="cb-field">
                                <label class="form-label fw-semibold" for="body">Reply to <?= e($partner['full_name']) ?></label>
                                <textarea class="form-control" id="body" name="body" rows="3" maxlength="1000"
                                          placeholder="Type your reply. Meet in a public campus place."
                                          data-required data-min-length="2" data-max-length="1000" data-label="Reply" data-counter="1000"></textarea>
                                <div class="invalid-feedback"></div>
                            </div>
                            <div class="d-flex justify-content-end mt-3">
                                <button class="btn btn-primary px-4" type="submit"><i class="bi bi-send"></i> Send</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

// ---------- Inbox / Sent list ----------
$box = read_text('box', 'inbox') === 'sent' ? 'sent' : 'inbox';

// Latest 300 messages involving me, newest first. Then they are grouped into conversations in PHP.
$all = db_select(
    'SELECT m.*, s.full_name AS sender_name, r.full_name AS receiver_name
     FROM messages m
     JOIN users s ON s.id = m.sender_id
     JOIN users r ON r.id = m.receiver_id
     WHERE m.sender_id = ? OR m.receiver_id = ?
     ORDER BY m.created_at DESC, m.id DESC
     LIMIT 300',
    'ii',
    [$myId, $myId]
);

$conversations = [];
foreach ($all as $m) {
    $iAmSender = (int) $m['sender_id'] === $myId;
    $partnerId = $iAmSender ? (int) $m['receiver_id'] : (int) $m['sender_id'];
    $key = $m['context_type'] . ':' . $m['context_id'] . ':' . $partnerId;
    if (!isset($conversations[$key])) {
        $conversations[$key] = [
            'type' => $m['context_type'],
            'ctx' => (int) $m['context_id'],
            'with' => $partnerId,
            'partner' => $iAmSender ? $m['receiver_name'] : $m['sender_name'],
            'last' => $m,          // first row seen = newest message
            'unread' => 0,
            'sent' => false,
            'received' => false,
        ];
    }
    if (!$iAmSender) {
        $conversations[$key]['received'] = true;
        if (!(int) $m['is_read']) {
            $conversations[$key]['unread']++;
        }
    } else {
        $conversations[$key]['sent'] = true;
    }
}

// Look up item titles in one query per table (ids are integers, so the IN list is safe)
$listingIds = array_unique(array_map(fn($c) => $c['ctx'], array_filter($conversations, fn($c) => $c['type'] === 'listing')));
$requestIds = array_unique(array_map(fn($c) => $c['ctx'], array_filter($conversations, fn($c) => $c['type'] === 'request')));
$titles = [];
if ($listingIds) {
    foreach (db_select('SELECT id, title FROM listings WHERE id IN (' . implode(',', array_map('intval', $listingIds)) . ')') as $row) {
        $titles['listing:' . $row['id']] = $row['title'];
    }
}
if ($requestIds) {
    foreach (db_select('SELECT id, title FROM requests WHERE id IN (' . implode(',', array_map('intval', $requestIds)) . ')') as $row) {
        $titles['request:' . $row['id']] = $row['title'];
    }
}

$list = array_filter($conversations, fn($c) => $box === 'sent' ? $c['sent'] : $c['received']);
$totalUnread = array_sum(array_column($conversations, 'unread'));

$pageTitle = 'Messages';
$activeNav = '';
$breadcrumbs = [['Messages', '']];
require __DIR__ . '/includes/header.php';
render_page_head('Your conversations', 'Messages', 'Replies to your listings and requests, and the responses you have sent. Contact details stay private.');
?>

<section class="container pb-5">
    <ul class="nav nav-pills cb-tabs mb-4" role="tablist">
        <li class="nav-item"><a class="nav-link<?= $box === 'inbox' ? ' active' : '' ?>" href="<?= href('messages.php?box=inbox') ?>">Inbox
            <?php if ($totalUnread > 0): ?><span class="badge rounded-pill bg-danger ms-1"><?= $totalUnread ?></span><?php endif; ?></a></li>
        <li class="nav-item"><a class="nav-link<?= $box === 'sent' ? ' active' : '' ?>" href="<?= href('messages.php?box=sent') ?>">Sent</a></li>
    </ul>

    <?php if (!$list): ?>
        <?php render_empty('bi-chat-left-dots', $box === 'sent' ? 'You have not sent any messages yet.' : 'Your inbox is empty.',
            'Contact a seller from a marketplace listing, or respond to a request on the Request Board.',
            '<a class="btn btn-outline-primary" href="' . href('marketplace.php') . '">Browse marketplace</a>'
            . '<a class="btn btn-primary" href="' . href('requests.php') . '">Request Board</a>'); ?>
    <?php else: ?>
        <div class="list-group cb-conv-list">
            <?php foreach ($list as $c):
                $itemTitle = $titles[$c['type'] . ':' . $c['ctx']] ?? null;
                $preview = $c['last'];
                $lastFromMe = (int) $preview['sender_id'] === $myId;
                ?>
                <a class="list-group-item list-group-item-action cb-conv d-flex gap-3 align-items-start<?= $c['unread'] ? ' is-unread' : '' ?>"
                   href="<?= href('messages.php?type=' . $c['type'] . '&ctx=' . $c['ctx'] . '&with=' . $c['with']) ?>">
                    <span class="cb-avatar"><?= e(initials($c['partner'])) ?></span>
                    <span class="flex-grow-1 min-w-0">
                        <span class="d-flex justify-content-between gap-2">
                            <span class="fw-semibold text-truncate"><?= e($c['partner']) ?></span>
                            <span class="small text-muted text-nowrap"><?= e(time_ago($preview['created_at'])) ?></span>
                        </span>
                        <span class="d-block small text-muted text-truncate">
                            <i class="bi <?= $c['type'] === 'listing' ? 'bi-bag' : 'bi-chat-dots' ?>"></i>
                            <?= $itemTitle !== null ? e($itemTitle) : 'Item no longer available' ?>
                        </span>
                        <span class="d-block text-truncate mt-1"><?= $lastFromMe ? '<span class="text-muted">You:</span> ' : '' ?><?= e($preview['body']) ?></span>
                    </span>
                    <?php if ($c['unread']): ?><span class="badge rounded-pill bg-danger align-self-center"><?= $c['unread'] ?> new</span><?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
