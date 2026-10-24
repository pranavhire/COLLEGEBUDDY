<?php
/*
 * product.php - full details of one marketplace listing.
 * Anyone can read it. Logged-in students can contact the seller and report it.
 * The seller (owner) can edit, mark as sold or delete it.
 */
require_once __DIR__ . '/includes/auth.php';

$id = (int) ($_GET['id'] ?? 0);
$listing = $id > 0 ? db_select_one(
    'SELECT l.*, u.full_name AS seller_name, u.branch AS seller_branch, u.year AS seller_year, u.created_at AS seller_joined
     FROM listings l JOIN users u ON u.id = l.user_id
     WHERE l.id = ?',
    'i',
    [$id]
) : null;

if ($listing === null) {
    show_error_page(404, 'Listing not found', 'This item may have been removed, or the link is incorrect.');
}

$me = current_user();
$isOwner = $me !== null && (int) $me['id'] === (int) $listing['user_id'];

// Actions submitted from this page (contact seller, mark sold, delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = read_text('action', '', 'post');

    if ($action === 'contact') {
        require_login('Please login to contact the seller.');
        $body = trim(read_text('message', '', 'post'));
        $len = mb_strlen($body);
        if ($isOwner) {
            set_flash('warning', 'This is your own listing, so you cannot message yourself.');
        } elseif ($listing['status'] === 'Sold') {
            set_flash('warning', 'This item has already been sold.');
        } elseif ($len < 5 || $len > 1000) {
            set_flash('danger', 'Your message must be between 5 and 1000 characters.');
        } else {
            send_message((int) $me['id'], (int) $listing['user_id'], 'listing', $id, $body);
            set_flash('success', 'Your message was sent to the seller. Replies appear in Messages.');
        }
        redirect('product.php?id=' . $id . '#contact-seller');
    }

    if ($isOwner && $action === 'toggle_sold') {
        toggle_listing_sold($id, (int) $me['id']);
        set_flash('success', $listing['status'] === 'Sold' ? 'Your item is marked as available again.' : 'Great! Your item is marked as sold.');
        redirect('product.php?id=' . $id);
    }

    if ($isOwner && $action === 'delete') {
        delete_listing($id, (int) $me['id']);
        set_flash('success', 'Your listing was deleted.');
        redirect('my-listings.php');
    }

    redirect('product.php?id=' . $id);
}

$similar = db_select(
    "SELECT id, title, price, image FROM listings WHERE category = ? AND id <> ? AND status = 'Available' ORDER BY created_at DESC LIMIT 3",
    'si',
    [$listing['category'], $id]
);

$pageTitle = $listing['title'];
$activeNav = 'marketplace';
$breadcrumbs = [['Marketplace', 'marketplace.php'], [$listing['title'], '']];
$pageScripts = [];
require __DIR__ . '/includes/header.php';
?>

<section class="container pb-5">
    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card cb-card cb-detail-media overflow-hidden">
                <img src="<?= e(listing_image_url($listing['image'])) ?>" alt="Photo of <?= e($listing['title']) ?>" class="w-100">
            </div>

            <div class="card cb-card mt-4">
                <div class="card-body p-4">
                    <h2 class="h5 mb-3">Description</h2>
                    <p class="mb-0 cb-pre"><?= e($listing['description']) ?></p>
                </div>
            </div>

            <div class="card cb-card mt-4">
                <div class="card-body p-4">
                    <h2 class="h5 mb-3">Item details</h2>
                    <dl class="row mb-0 cb-dl">
                        <dt class="col-sm-4">Category</dt><dd class="col-sm-8"><?= e($listing['category']) ?></dd>
                        <dt class="col-sm-4">Condition</dt><dd class="col-sm-8"><?= e($listing['item_condition']) ?></dd>
                        <dt class="col-sm-4">Pickup location</dt><dd class="col-sm-8"><?= e($listing['location']) ?></dd>
                        <dt class="col-sm-4">Posted on</dt><dd class="col-sm-8"><?= e(format_date($listing['created_at'], 'd M Y, h:i A')) ?></dd>
                        <dt class="col-sm-4">Status</dt><dd class="col-sm-8"><?= status_badge($listing['status']) ?></dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card cb-card cb-sticky">
                <div class="card-body p-4">
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <span class="badge cb-badge-soft"><?= e($listing['category']) ?></span>
                        <?= condition_badge($listing['item_condition']) ?>
                        <?= status_badge($listing['status']) ?>
                    </div>
                    <h1 class="h3 mb-2"><?= e($listing['title']) ?></h1>
                    <div class="cb-price cb-price-lg mb-1"><?= format_price($listing['price']) ?></div>
                    <p class="small text-muted mb-4">Pay the seller directly in person. College Buddy does not handle payments.</p>

                    <div class="cb-seller d-flex align-items-center gap-3 p-3 rounded-3 mb-4">
                        <span class="cb-avatar cb-avatar-lg"><?= e(initials($listing['seller_name'])) ?></span>
                        <div class="min-w-0">
                            <div class="fw-semibold"><?= e($listing['seller_name']) ?></div>
                            <div class="small text-muted"><?= e($listing['seller_branch']) ?> &middot; Year <?= (int) $listing['seller_year'] ?></div>
                            <div class="small text-muted">Member since <?= e(format_date($listing['seller_joined'], 'M Y')) ?></div>
                        </div>
                    </div>

                    <?php if ($isOwner): ?>
                        <h2 class="h6 text-uppercase text-muted mb-3">Manage your listing</h2>
                        <div class="d-grid gap-2">
                            <a class="btn btn-outline-primary" href="<?= href('sell.php?edit=' . $id) ?>"><i class="bi bi-pencil"></i> Edit listing</a>
                            <form method="post" action="<?= href('product.php?id=' . $id) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="toggle_sold">
                                <button class="btn btn-outline-secondary w-100" type="submit">
                                    <i class="bi bi-check2-circle"></i> <?= $listing['status'] === 'Sold' ? 'Mark as Available' : 'Mark as Sold' ?>
                                </button>
                            </form>
                            <form method="post" action="<?= href('product.php?id=' . $id) ?>" data-confirm="Delete this listing permanently? This cannot be undone.">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <button class="btn btn-outline-danger w-100" type="submit"><i class="bi bi-trash"></i> Delete listing</button>
                            </form>
                        </div>
                    <?php elseif ($listing['status'] === 'Sold'): ?>
                        <div class="alert alert-secondary mb-0"><i class="bi bi-bag-check"></i> This item has been sold.</div>
                    <?php elseif ($me): ?>
                        <div id="contact-seller">
                            <h2 class="h6 text-uppercase text-muted mb-3">Contact seller</h2>
                            <form method="post" action="<?= href('product.php?id=' . $id . '#contact-seller') ?>" novalidate data-validate>
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="contact">
                                <div class="cb-field">
                                    <label class="form-label" for="message">Your message</label>
                                    <textarea class="form-control" id="message" name="message" rows="4" maxlength="1000"
                                              placeholder="Hi, is this still available? Can I collect it today?"
                                              data-required data-min-length="5" data-max-length="1000" data-label="Message" data-counter="1000"></textarea>
                                    <div class="invalid-feedback"></div>
                                </div>
                                <button class="btn btn-primary w-100 mt-3" type="submit"><i class="bi bi-send"></i> Send message</button>
                            </form>
                            <p class="small text-muted mt-2 mb-0">Replies appear in your <a href="<?= href('messages.php') ?>">Messages</a> inbox.</p>
                        </div>
                    <?php else: ?>
                        <div id="contact-seller" class="cb-login-cta text-center p-3 rounded-3">
                            <p class="mb-3">Login to contact the seller. Your e-mail address is never shown.</p>
                            <a class="btn btn-primary w-100" href="<?= href('login.php?next=' . urlencode('product.php?id=' . $id)) ?>"><i class="bi bi-box-arrow-in-right"></i> Login to contact seller</a>
                        </div>
                    <?php endif; ?>

                    <?php if (!$isOwner): ?>
                        <a class="btn btn-link text-danger w-100 mt-3" href="<?= href('report.php?type=listing&id=' . $id) ?>"><i class="bi bi-flag"></i> Report this listing</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php if ($similar): ?>
        <h2 class="h5 mt-5 mb-3">More in <?= e($listing['category']) ?></h2>
        <div class="row g-3">
            <?php foreach ($similar as $s): ?>
                <div class="col-sm-4">
                    <a class="card cb-card cb-card-hover cb-mini h-100 text-decoration-none" href="<?= href('product.php?id=' . (int) $s['id']) ?>">
                        <img src="<?= e(listing_image_url($s['image'])) ?>" alt="" class="cb-mini-img" loading="lazy">
                        <div class="card-body">
                            <div class="fw-semibold text-body cb-clamp-1"><?= e($s['title']) ?></div>
                            <div class="cb-price"><?= format_price($s['price']) ?></div>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
