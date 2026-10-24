<?php
/*
 * requests.php - Request Board: students ask for books, notes, papers or help.
 * Anyone can read the board. Responding and posting need login.
 */
require_once __DIR__ . '/includes/auth.php';

$pageTitle = 'Request Board';
$activeNav = 'requests';
$breadcrumbs = [['Request Board', '']];
$me = current_user();

$categories = xml_list('requestCategories');

$q = trim(read_text('q', ''));
$category = read_text('category', '');
$status = read_text('status', 'Open');
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 9;

if (!in_array($category, $categories, true)) {
    $category = '';
}
if (!in_array($status, ['Open', 'Closed', 'All'], true)) {
    $status = 'Open';
}

$where = [];
$tp = '';
$params = [];
if ($q !== '') {
    $like = '%' . addcslashes($q, '%_\\') . '%';
    $where[] = '(r.title LIKE ? OR r.description LIKE ?)';
    $tp .= 'ss';
    array_push($params, $like, $like);
}
if ($category !== '') {
    $where[] = 'r.category = ?';
    $tp .= 's';
    $params[] = $category;
}
if ($status !== 'All') {
    $where[] = 'r.status = ?';
    $tp .= 's';
    $params[] = $status;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total = db_count("SELECT COUNT(*) AS total FROM requests r $whereSql", $tp, $params);
$pages = max(1, (int) ceil($total / $perPage));
$page = min($page, $pages);
$offset = ($page - 1) * $perPage;

$requests = db_select(
    "SELECT r.*, u.full_name AS requester_name, u.branch AS requester_branch
     FROM requests r JOIN users u ON u.id = r.user_id
     $whereSql
     ORDER BY r.created_at DESC, r.id DESC
     LIMIT ? OFFSET ?",
    $tp . 'ii',
    array_merge($params, [$perPage, $offset])
);

$query = array_filter(['q' => $q, 'category' => $category, 'status' => $status !== 'Open' ? $status : ''], fn($v) => $v !== '');

require __DIR__ . '/includes/header.php';
render_page_head(
    'Ask your batchmates',
    'Request Board',
    'Need a book, notes, a previous year paper or help with a practical? Post a request, or respond to one you can help with.',
    '<a class="btn btn-cb-accent" href="' . href($me ? 'create-request.php' : 'login.php?next=' . urlencode('create-request.php')) . '"><i class="bi bi-plus-lg"></i> Post a request</a>'
);
?>

<section class="container py-4">
    <form method="get" action="" class="card cb-card cb-filter-card mb-4" id="requestFilters" novalidate data-validate data-autosubmit>
        <div class="card-body p-3 p-md-4">
            <div class="row g-3 align-items-end">
                <div class="col-12 col-md-5">
                    <label class="form-label small fw-semibold" for="q">Search</label>
                    <input type="search" class="form-control" id="q" name="q" value="<?= e($q) ?>" maxlength="100" placeholder="Search by title or description">
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label small fw-semibold" for="category">Category</label>
                    <select class="form-select" id="category" name="category">
                        <option value="">All categories</option>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= e($c) ?>"<?= $category === $c ? ' selected' : '' ?>><?= e($c) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small fw-semibold" for="status">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="Open"<?= $status === 'Open' ? ' selected' : '' ?>>Open</option>
                        <option value="Closed"<?= $status === 'Closed' ? ' selected' : '' ?>>Closed</option>
                        <option value="All"<?= $status === 'All' ? ' selected' : '' ?>>All</option>
                    </select>
                </div>
                <div class="col-12 col-md-2 d-flex gap-2">
                    <button class="btn btn-primary flex-grow-1" type="submit">Search</button>
                    <a class="btn btn-outline-secondary" href="<?= href('requests.php') ?>">Clear</a>
                </div>
            </div>
        </div>
    </form>

    <?php if ($total > 0): ?>
        <p class="small text-muted mb-3">Showing <?= $offset + 1 ?>&ndash;<?= min($offset + $perPage, $total) ?> of <?= plural($total, 'request') ?></p>
    <?php endif; ?>

    <?php if (!$requests): ?>
        <?php render_empty('bi-chat-square-dots', 'No requests here yet.', 'Be the first to ask. A short, clear request gets the fastest response.',
            '<a class="btn btn-primary" href="' . href($me ? 'create-request.php' : 'login.php') . '">Post a request</a>'); ?>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($requests as $r):
                $isMine = $me !== null && (int) $me['id'] === (int) $r['user_id'];
                $detailUrl = 'request.php?id=' . (int) $r['id'];
                ?>
                <div class="col-md-6 col-xl-4">
                    <article class="card cb-card cb-card-hover cb-request h-100">
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                <span class="badge cb-badge-soft"><?= e($r['category']) ?></span>
                                <?= status_badge($r['status']) ?>
                            </div>
                            <h2 class="h6 cb-title mb-2"><a href="<?= href($detailUrl) ?>"><?= e($r['title']) ?></a></h2>
                            <p class="small text-muted cb-clamp mb-3"><?= e($r['description']) ?></p>
                            <ul class="cb-meta list-unstyled small text-muted mb-3">
                                <li><i class="bi bi-person"></i> Posted by <?= e($r['requester_name']) ?> &middot; <?= e($r['requester_branch']) ?></li>
                                <li><i class="bi bi-clock"></i> <?= e(time_ago($r['created_at'])) ?></li>
                            </ul>
                            <div class="d-flex gap-2 mt-auto">
                                <a class="btn btn-primary btn-sm flex-grow-1" href="<?= href($detailUrl) ?>">
                                    <?= $isMine ? '<i class="bi bi-gear"></i> Manage' : '<i class="bi bi-reply"></i> View &amp; respond' ?>
                                </a>
                                <?php if (!$isMine): ?>
                                    <a class="btn btn-link btn-sm text-danger px-1" href="<?= href('report.php?type=request&id=' . (int) $r['id']) ?>" aria-label="Report this request"><i class="bi bi-flag"></i></a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                </div>
            <?php endforeach; ?>
        </div>

        <?php render_pagination($page, $pages, 'requests.php', $query); ?>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
