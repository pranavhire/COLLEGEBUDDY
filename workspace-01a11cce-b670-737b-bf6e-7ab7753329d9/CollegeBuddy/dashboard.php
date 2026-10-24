<?php
/*
 * dashboard.php - the student's home screen after login.
 * Shows personal counts (from MySQL), quick actions and the latest items on the site.
 */
require_once __DIR__ . '/includes/auth.php';
require_login('Please login to open your dashboard.');

$me = current_user();
$myId = (int) $me['id'];

// Personal counts in one query. Each (SELECT ...) is one number.
$mine = db_select_one(
    'SELECT (SELECT COUNT(*) FROM listings WHERE user_id = ?) AS listings,
            (SELECT COUNT(*) FROM resources WHERE user_id = ?) AS resources,
            (SELECT COUNT(*) FROM requests WHERE user_id = ?) AS requests,
            (SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0) AS unread',
    'iiii',
    [$myId, $myId, $myId, $myId]
);

// Latest items from everyone (site-wide)
$recentListings = db_select(
    "SELECT l.id, l.title, l.price, l.image, l.status, l.created_at, u.full_name AS seller_name
     FROM listings l JOIN users u ON u.id = l.user_id
     WHERE l.status = 'Available' ORDER BY l.created_at DESC, l.id DESC LIMIT 3"
);
$recentResources = db_select(
    'SELECT r.id, r.title, r.subject, r.resource_type, r.created_at, u.full_name AS uploader_name
     FROM resources r JOIN users u ON u.id = r.user_id
     ORDER BY r.created_at DESC, r.id DESC LIMIT 3'
);
$recentRequests = db_select(
    "SELECT r.id, r.title, r.category, r.created_at, u.full_name AS requester_name
     FROM requests r JOIN users u ON u.id = r.user_id
     WHERE r.status = 'Open' ORDER BY r.created_at DESC, r.id DESC LIMIT 3"
);

$firstName = explode(' ', $me['full_name'])[0];
$pageTitle = 'Dashboard';
$activeNav = 'dashboard';
$breadcrumbs = [['Dashboard', '']];
require __DIR__ . '/includes/header.php';
?>

<section class="cb-page-head">
    <div class="container">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <div>
                <p class="cb-eyebrow mb-1">Welcome to College Buddy</p>
                <h1 class="h3 mb-1">Welcome back, <?= e($firstName) ?>!</h1>
                <p class="text-muted mb-0"><?= e($me['branch']) ?> &middot; Year <?= (int) $me['year'] ?> &middot; Semester <?= (int) $me['semester'] ?></p>
            </div>
            <a class="btn btn-outline-primary" href="<?= href('profile.php') ?>"><i class="bi bi-person-gear"></i> Edit profile</a>
        </div>
    </div>
</section>

<section class="container py-4">
    <div class="row g-3 g-md-4">
        <div class="col-6 col-lg-3">
            <a class="card cb-card cb-stat-card cb-card-hover h-100 text-decoration-none" href="<?= href('my-listings.php') ?>">
                <div class="card-body">
                    <span class="cb-stat-icon mb-2"><i class="bi bi-bag"></i></span>
                    <div class="small text-muted">My Listings</div>
                    <div class="cb-stat-value"><?= (int) $mine['listings'] ?></div>
                </div>
            </a>
        </div>
        <div class="col-6 col-lg-3">
            <a class="card cb-card cb-stat-card cb-card-hover h-100 text-decoration-none" href="<?= href('my-resources.php') ?>">
                <div class="card-body">
                    <span class="cb-stat-icon mb-2"><i class="bi bi-journal-bookmark"></i></span>
                    <div class="small text-muted">My Resources</div>
                    <div class="cb-stat-value"><?= (int) $mine['resources'] ?></div>
                </div>
            </a>
        </div>
        <div class="col-6 col-lg-3">
            <a class="card cb-card cb-stat-card cb-card-hover h-100 text-decoration-none" href="<?= href('my-requests.php') ?>">
                <div class="card-body">
                    <span class="cb-stat-icon mb-2"><i class="bi bi-chat-dots"></i></span>
                    <div class="small text-muted">My Requests</div>
                    <div class="cb-stat-value"><?= (int) $mine['requests'] ?></div>
                </div>
            </a>
        </div>
        <div class="col-6 col-lg-3">
            <a class="card cb-card cb-stat-card cb-card-hover h-100 text-decoration-none" href="<?= href('messages.php') ?>">
                <div class="card-body">
                    <span class="cb-stat-icon mb-2"><i class="bi bi-envelope"></i></span>
                    <div class="small text-muted">Unread messages</div>
                    <div class="cb-stat-value"><?= (int) $mine['unread'] ?></div>
                </div>
            </a>
        </div>
    </div>

    <h2 class="h5 mt-5 mb-3">Quick actions</h2>
    <div class="row g-3">
        <?php
        $actions = [
            ['bi-tag', 'Sell an Item', 'Post a textbook or instrument', 'sell.php'],
            ['bi-cloud-arrow-up', 'Upload Resource', 'Share notes for free', 'upload-resource.php'],
            ['bi-megaphone', 'Create Request', 'Ask for something you need', 'create-request.php'],
            ['bi-calendar2-check', 'Calculate Attendance', 'Skip or attend lectures wisely', 'attendance.php'],
            ['bi-calculator', 'Calculate CGPA', 'SGPA average or credit-weighted', 'cgpa.php'],
        ];
        foreach ($actions as [$icon, $title, $text, $path]):
        ?>
            <div class="col-md-6 col-xl">
                <a class="card cb-card cb-card-hover cb-action h-100 text-decoration-none" href="<?= href($path) ?>">
                    <div class="card-body d-flex align-items-center gap-3">
                        <span class="cb-feature-icon"><i class="bi <?= e($icon) ?>"></i></span>
                        <span>
                            <span class="d-block fw-semibold text-body"><?= e($title) ?></span>
                            <span class="d-block small text-muted"><?= e($text) ?></span>
                        </span>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="row g-4 mt-1">
        <div class="col-lg-4">
            <div class="card cb-card h-100">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 class="h6 mb-0"><i class="bi bi-bag"></i> Recent on Marketplace</h2>
                        <a class="small" href="<?= href('marketplace.php') ?>">View all</a>
                    </div>
                    <?php if (!$recentListings): ?>
                        <p class="text-muted small mb-0">No items for sale yet. <a href="<?= href('sell.php') ?>">Sell the first one</a>.</p>
                    <?php endif; ?>
                    <ul class="list-unstyled mb-0 cb-mini-list">
                        <?php foreach ($recentListings as $l): ?>
                            <li>
                                <a class="d-flex gap-3 align-items-center py-2 text-decoration-none text-body" href="<?= href('product.php?id=' . (int) $l['id']) ?>">
                                    <img class="cb-thumb" src="<?= e(listing_image_url($l['image'])) ?>" alt="" width="44" height="44" loading="lazy">
                                    <span class="flex-grow-1 min-w-0">
                                        <span class="d-block fw-semibold cb-clamp-1"><?= e($l['title']) ?></span>
                                        <span class="d-block small text-muted"><?= e($l['seller_name']) ?> &middot; <?= e(time_ago($l['created_at'])) ?></span>
                                    </span>
                                    <span class="cb-price small"><?= format_price($l['price']) ?></span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card cb-card h-100">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 class="h6 mb-0"><i class="bi bi-journal-bookmark"></i> Recent Study Resources</h2>
                        <a class="small" href="<?= href('study.php') ?>">View all</a>
                    </div>
                    <?php if (!$recentResources): ?>
                        <p class="text-muted small mb-0">No resources yet. <a href="<?= href('upload-resource.php') ?>">Upload your notes</a>.</p>
                    <?php endif; ?>
                    <ul class="list-unstyled mb-0 cb-mini-list">
                        <?php foreach ($recentResources as $r): ?>
                            <li>
                                <a class="d-flex gap-3 align-items-center py-2 text-decoration-none text-body" href="<?= href('resource.php?id=' . (int) $r['id']) ?>">
                                    <span class="cb-resource-icon"><i class="bi <?= e(resource_icon($r['resource_type'])) ?>"></i></span>
                                    <span class="flex-grow-1 min-w-0">
                                        <span class="d-block fw-semibold cb-clamp-1"><?= e($r['title']) ?></span>
                                        <span class="d-block small text-muted"><?= e($r['subject']) ?> &middot; <?= e(time_ago($r['created_at'])) ?></span>
                                    </span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card cb-card h-100">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 class="h6 mb-0"><i class="bi bi-chat-dots"></i> Open Requests</h2>
                        <a class="small" href="<?= href('requests.php') ?>">View all</a>
                    </div>
                    <?php if (!$recentRequests): ?>
                        <p class="text-muted small mb-0">No open requests right now. <a href="<?= href('create-request.php') ?>">Post one</a>.</p>
                    <?php endif; ?>
                    <ul class="list-unstyled mb-0 cb-mini-list">
                        <?php foreach ($recentRequests as $r): ?>
                            <li>
                                <a class="d-flex gap-3 align-items-center py-2 text-decoration-none text-body" href="<?= href('request.php?id=' . (int) $r['id']) ?>">
                                    <span class="cb-resource-icon"><i class="bi bi-chat-square-text"></i></span>
                                    <span class="flex-grow-1 min-w-0">
                                        <span class="d-block fw-semibold cb-clamp-1"><?= e($r['title']) ?></span>
                                        <span class="d-block small text-muted"><?= e($r['requester_name']) ?> &middot; <?= e(time_ago($r['created_at'])) ?></span>
                                    </span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
