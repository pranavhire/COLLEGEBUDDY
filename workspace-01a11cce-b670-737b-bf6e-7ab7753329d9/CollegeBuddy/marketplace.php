<?php
/*
 * marketplace.php - used academic items for sale between students (no online payments).
 * Filters (search, category, condition, price range, status), sorting and pagination.
 * Every filter value is checked against an allowed list and sent to MySQL with placeholders.
 */
require_once __DIR__ . '/includes/auth.php';

$pageTitle = 'Marketplace';
$activeNav = 'marketplace';
$breadcrumbs = [['Marketplace', '']];
$me = current_user();

$categories = xml_list('listingCategories');
$conditions = xml_list('conditions');

// 1. Read the filters from the URL (?category=...&sort=...)
$q = trim(read_text('q', ''));
$category = read_text('category', '');
$condition = read_text('condition', '');
$status = read_text('status', 'Available');
$sort = read_text('sort', 'newest');
$minPrice = trim(read_text('min_price', ''));
$maxPrice = trim(read_text('max_price', ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 9;

// 2. Accept only expected values
if (!in_array($category, $categories, true)) {
    $category = '';
}
if (!in_array($condition, $conditions, true)) {
    $condition = '';
}
if (!in_array($status, ['Available', 'Sold', 'All'], true)) {
    $status = 'Available';
}
if (!in_array($sort, ['newest', 'price_asc', 'price_desc'], true)) {
    $sort = 'newest';
}
$minPrice = (is_numeric($minPrice) && (float) $minPrice >= 0) ? $minPrice : '';
$maxPrice = (is_numeric($maxPrice) && (float) $maxPrice >= 0) ? $maxPrice : '';

// 3. Build the WHERE clause. Each condition adds one placeholder (?) and one type letter.
$where = [];
$types = '';
$params = [];
if ($q !== '') {
    $like = '%' . addcslashes($q, '%_\\') . '%';
    $where[] = '(l.title LIKE ? OR l.description LIKE ?)';
    $types .= 'ss';
    array_push($params, $like, $like);
}
if ($category !== '') {
    $where[] = 'l.category = ?';
    $types .= 's';
    $params[] = $category;
}
if ($condition !== '') {
    $where[] = 'l.item_condition = ?';
    $types .= 's';
    $params[] = $condition;
}
if ($status !== 'All') {
    $where[] = 'l.status = ?';
    $types .= 's';
    $params[] = $status;
}
if ($minPrice !== '') {
    $where[] = 'l.price >= ?';
    $types .= 'd';
    $params[] = (float) $minPrice;
}
if ($maxPrice !== '') {
    $where[] = 'l.price <= ?';
    $types .= 'd';
    $params[] = (float) $maxPrice;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$orderSql = ['newest' => 'l.created_at DESC, l.id DESC', 'price_asc' => 'l.price ASC, l.id DESC', 'price_desc' => 'l.price DESC, l.id DESC'][$sort];

// 4. Count the results for pagination, then fetch only the current page
$total = db_count("SELECT COUNT(*) AS total FROM listings l $whereSql", $types, $params);
$pages = max(1, (int) ceil($total / $perPage));
$page = min($page, $pages);
$offset = ($page - 1) * $perPage;

$items = db_select(
    "SELECT l.*, u.full_name AS seller_name, u.branch AS seller_branch
     FROM listings l
     JOIN users u ON u.id = l.user_id
     $whereSql
     ORDER BY $orderSql
     LIMIT ? OFFSET ?",
    $types . 'ii',
    array_merge($params, [$perPage, $offset])
);

// Filters that are not at their default value are kept in the pagination links
$query = array_filter([
    'q' => $q, 'category' => $category, 'condition' => $condition, 'status' => $status,
    'sort' => $sort === 'newest' ? '' : $sort, 'min_price' => $minPrice, 'max_price' => $maxPrice,
], fn($v) => $v !== '');

require __DIR__ . '/includes/header.php';
render_page_head(
    'Student marketplace',
    'Used academic items',
    'Buy and sell books, instruments and accessories with students from your campus. Meet in person and pay directly.',
    '<a class="btn btn-cb-accent" href="' . href('sell.php') . '"><i class="bi bi-plus-lg"></i> Sell an item</a>'
);
?>

<section class="container py-4">
    <form method="get" action="" class="card cb-card cb-filter-card mb-4" id="marketFilters" novalidate data-validate data-autosubmit>
        <div class="card-body p-3 p-md-4">
            <div class="d-flex justify-content-between align-items-center d-lg-none mb-3">
                <strong><i class="bi bi-funnel"></i> Filter items</strong>
                <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#marketFilterBody" aria-expanded="false" aria-controls="marketFilterBody">Show filters</button>
            </div>
            <div class="collapse d-lg-block" id="marketFilterBody">
                <div class="row g-3">
                    <div class="col-12 col-lg-4">
                        <label class="form-label small fw-semibold" for="q">Search</label>
                        <input type="search" class="form-control" id="q" name="q" value="<?= e($q) ?>" placeholder="Search by title or description" maxlength="100">
                    </div>
                    <div class="col-6 col-lg-3">
                        <label class="form-label small fw-semibold" for="category">Category</label>
                        <select class="form-select" id="category" name="category">
                            <option value="">All categories</option>
                            <?php foreach ($categories as $c): ?>
                                <option value="<?= e($c) ?>"<?= $category === $c ? ' selected' : '' ?>><?= e($c) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6 col-lg-3">
                        <label class="form-label small fw-semibold" for="condition">Condition</label>
                        <select class="form-select" id="condition" name="condition">
                            <option value="">Any condition</option>
                            <?php foreach ($conditions as $c): ?>
                                <option value="<?= e($c) ?>"<?= $condition === $c ? ' selected' : '' ?>><?= e($c) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6 col-lg-2">
                        <label class="form-label small fw-semibold" for="sort">Sort by</label>
                        <select class="form-select" id="sort" name="sort">
                            <option value="newest"<?= $sort === 'newest' ? ' selected' : '' ?>>Newest</option>
                            <option value="price_asc"<?= $sort === 'price_asc' ? ' selected' : '' ?>>Price: Low to High</option>
                            <option value="price_desc"<?= $sort === 'price_desc' ? ' selected' : '' ?>>Price: High to Low</option>
                        </select>
                    </div>
                    <div class="col-6 col-lg-2">
                        <label class="form-label small fw-semibold" for="min_price">Min price (&#8377;)</label>
                        <input type="number" class="form-control" id="min_price" name="min_price" min="0" step="1" value="<?= e($minPrice) ?>"
                               data-label="Minimum price" data-number data-min-value="0" data-lte="max_price" data-lte-label="Minimum price cannot be more than maximum price.">
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="col-6 col-lg-2">
                        <label class="form-label small fw-semibold" for="max_price">Max price (&#8377;)</label>
                        <input type="number" class="form-control" id="max_price" name="max_price" min="0" step="1" value="<?= e($maxPrice) ?>"
                               data-label="Maximum price" data-number data-min-value="0">
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="col-6 col-lg-2">
                        <label class="form-label small fw-semibold" for="status">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="Available"<?= $status === 'Available' ? ' selected' : '' ?>>Available</option>
                            <option value="Sold"<?= $status === 'Sold' ? ' selected' : '' ?>>Sold</option>
                            <option value="All"<?= $status === 'All' ? ' selected' : '' ?>>All</option>
                        </select>
                    </div>
                    <div class="col-12 col-lg-6 d-flex gap-2 align-items-end justify-content-lg-end">
                        <button class="btn btn-primary px-4" type="submit"><i class="bi bi-search"></i> Apply filters</button>
                        <a class="btn btn-outline-secondary" href="<?= href('marketplace.php') ?>">Clear</a>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <?php if ($total > 0): ?>
        <p class="small text-muted mb-3">
            Showing <?= $offset + 1 ?>&ndash;<?= min($offset + $perPage, $total) ?> of <?= plural($total, 'item') ?>
        </p>
    <?php endif; ?>

    <?php if (!$items): ?>
        <?php render_empty('bi-bag-x', 'No listings found.', 'Try changing or clearing the filters, or be the first to sell something in this category.',
            '<a class="btn btn-outline-primary" href="' . href('marketplace.php') . '">Clear filters</a>'
            . '<a class="btn btn-primary" href="' . href('sell.php') . '">Sell an item</a>'); ?>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($items as $item):
                $isMine = $me !== null && (int) $me['id'] === (int) $item['user_id'];
                $detailUrl = 'product.php?id=' . (int) $item['id'];
                ?>
                <div class="col-sm-6 col-xl-4">
                    <article class="card cb-card cb-card-hover cb-listing h-100">
                        <a class="cb-listing-media" href="<?= href($detailUrl) ?>" tabindex="-1" aria-hidden="true">
                            <img src="<?= e(listing_image_url($item['image'])) ?>" alt="" loading="lazy">
                            <?php if ($item['status'] === 'Sold'): ?><span class="cb-sold-ribbon">Sold</span><?php endif; ?>
                        </a>
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex flex-wrap gap-1 mb-2">
                                <span class="badge cb-badge-soft"><?= e($item['category']) ?></span>
                                <?= condition_badge($item['item_condition']) ?>
                                <?= status_badge($item['status']) ?>
                            </div>
                            <h2 class="h6 cb-title mb-1"><a href="<?= href($detailUrl) ?>"><?= e($item['title']) ?></a></h2>
                            <div class="cb-price mb-2"><?= format_price($item['price']) ?></div>
                            <p class="small text-muted cb-clamp mb-3"><?= e($item['description']) ?></p>
                            <ul class="cb-meta list-unstyled small text-muted mb-3">
                                <li><i class="bi bi-person"></i> <?= e($item['seller_name']) ?> &middot; <?= e($item['seller_branch']) ?></li>
                                <li><i class="bi bi-geo-alt"></i> <?= e($item['location']) ?></li>
                                <li><i class="bi bi-clock"></i> Posted <?= e(time_ago($item['created_at'])) ?></li>
                            </ul>
                            <a class="btn btn-primary btn-sm mt-auto" href="<?= href($detailUrl) ?>">View Details</a>
                            <div class="d-flex justify-content-between align-items-center mt-2">
                                <?php if ($isMine): ?>
                                    <span class="small text-muted"><i class="bi bi-person-check"></i> Your listing</span>
                                <?php else: ?>
                                    <a class="btn btn-outline-primary btn-sm" href="<?= href($detailUrl . '#contact-seller') ?>"><i class="bi bi-chat-left-text"></i> Contact Seller</a>
                                    <a class="btn btn-link btn-sm text-danger px-1" href="<?= href('report.php?type=listing&id=' . (int) $item['id']) ?>"><i class="bi bi-flag"></i> Report</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                </div>
            <?php endforeach; ?>
        </div>

        <?php render_pagination($page, $pages, 'marketplace.php', $query); ?>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
