<?php
/*
 * index.php - Home page: hero, feature cards, how it works, live statistics and call to action.
 * All numbers and the "latest" previews come from MySQL.
 */
require_once __DIR__ . '/includes/auth.php';

$pageTitle = 'Home';
$activeNav = 'home';
$breadcrumbs = [];

$stats = db_select_one(
    "SELECT (SELECT COUNT(*) FROM users WHERE role = 'student') AS students,
            (SELECT COUNT(*) FROM listings WHERE status = 'Available') AS listings,
            (SELECT COUNT(*) FROM resources) AS resources,
            (SELECT COUNT(*) FROM requests WHERE status = 'Open') AS requests"
);
$latestListing = db_select_one("SELECT id, title, price, item_condition FROM listings WHERE status = 'Available' ORDER BY created_at DESC, id DESC LIMIT 1");
$latestResource = db_select_one('SELECT id, title, subject, resource_type FROM resources ORDER BY created_at DESC, id DESC LIMIT 1');
$latestRequest = db_select_one("SELECT id, title FROM requests WHERE status = 'Open' ORDER BY created_at DESC, id DESC LIMIT 1");
$me = current_user();

$features = [
    ['bi-bag-check', 'Used Academic Items', 'Buy and sell textbooks, drawing instruments, lab coats and more. Student-to-student only, with no online payments.', 'marketplace.php', 'Browse Marketplace'],
    ['bi-journal-bookmark', 'Study Resources', 'Share and download notes, previous year papers, question banks and practical files. Everything here is free.', 'study.php', 'Open Study Hub'],
    ['bi-chat-dots', 'Request Board', 'Need a book, notes or a calculator for tomorrow? Post a request and let your batchmates help.', 'requests.php', 'View Requests'],
    ['bi-calendar2-check', 'Attendance Calculator', 'Find out how many lectures you can skip, or how many you must attend in a row, to reach your required percentage.', 'attendance.php', 'Calculate Attendance'],
    ['bi-calculator', 'CGPA Calculator', 'Calculate your CGPA from semester SGPA values, or use the credit-weighted method for a more accurate result.', 'cgpa.php', 'Calculate CGPA'],
];
$steps = [
    ['bi-person-plus', 'Create an account', 'Sign up with your branch, year and semester in under a minute.'],
    ['bi-search-heart', 'Explore or post something', 'Browse listings and resources, or sell and share your own.'],
    ['bi-people', 'Connect with students', 'Message sellers and reply to requests, all inside College Buddy.'],
    ['bi-check2-circle', 'Get things done', 'Buy books, download notes, and check your attendance and grades.'],
];

require __DIR__ . '/includes/header.php';
?>

<section class="cb-hero">
    <div class="container">
        <div class="row align-items-center gy-5">
            <div class="col-lg-6">
                <span class="cb-hero-tag"><i class="bi bi-stars"></i> Your College. Your Community. Your Buddy.</span>
                <h1 class="cb-hero-title mt-3">Everything You Need for College, in One Place.</h1>
                <p class="cb-hero-sub">Buy, share, request, calculate and connect with your college community.</p>
                <div class="d-flex flex-column flex-sm-row gap-3 mt-4">
                    <a class="btn btn-cb-accent btn-lg" href="<?= href('marketplace.php') ?>"><i class="bi bi-bag"></i> Explore Marketplace</a>
                    <a class="btn btn-outline-light btn-lg" href="<?= href('study.php') ?>"><i class="bi bi-journal-bookmark"></i> Explore Study Hub</a>
                </div>
                <p class="cb-hero-note mt-4 mb-0"><i class="bi bi-shield-check"></i> Student-to-student only. No online payments. Study resources are always free.</p>
            </div>
            <div class="col-lg-6">
                <div class="cb-hero-visual">
                    <a class="cb-hero-mini" href="<?= href('marketplace.php') ?>">
                        <span class="cb-hero-mini-icon"><i class="bi bi-bag"></i></span>
                        <span class="flex-grow-1 min-w-0">
                            <span class="cb-hero-mini-label">Latest on Marketplace</span>
                            <span class="cb-hero-mini-title"><?= $latestListing ? e($latestListing['title']) : 'Be the first to list an item' ?></span>
                        </span>
                        <?php if ($latestListing): ?><span class="cb-hero-mini-price"><?= format_price($latestListing['price']) ?></span><?php endif; ?>
                    </a>
                    <a class="cb-hero-mini cb-hero-mini-2" href="<?= href('study.php') ?>">
                        <span class="cb-hero-mini-icon"><i class="bi bi-journal-bookmark"></i></span>
                        <span class="flex-grow-1 min-w-0">
                            <span class="cb-hero-mini-label">New in Study Hub</span>
                            <span class="cb-hero-mini-title"><?= $latestResource ? e($latestResource['title']) : 'Share your first notes' ?></span>
                        </span>
                        <?php if ($latestResource): ?><span class="cb-hero-mini-price"><?= e($latestResource['resource_type']) ?></span><?php endif; ?>
                    </a>
                    <a class="cb-hero-mini cb-hero-mini-3" href="<?= href('requests.php') ?>">
                        <span class="cb-hero-mini-icon"><i class="bi bi-chat-dots"></i></span>
                        <span class="flex-grow-1 min-w-0">
                            <span class="cb-hero-mini-label">Open request</span>
                            <span class="cb-hero-mini-title"><?= $latestRequest ? e($latestRequest['title']) : 'No open requests right now' ?></span>
                        </span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="cb-section">
    <div class="container">
        <div class="cb-section-head text-center">
            <p class="cb-eyebrow mb-1">What you can do</p>
            <h2 class="h2 mb-2">Five tools built for student life</h2>
            <p class="text-muted mb-0">Use the marketplace, the study hub and the calculators without creating an account. Login is needed to post or contact.</p>
        </div>
        <div class="row g-4 mt-1">
            <?php foreach ($features as $i => [$icon, $title, $text, $path, $button]): ?>
                <div class="<?= $i < 3 ? 'col-md-6 col-lg-4' : 'col-md-6' ?>">
                    <article class="card cb-card cb-card-hover cb-feature h-100">
                        <div class="card-body p-4 d-flex flex-column">
                            <div class="cb-feature-icon mb-3"><i class="bi <?= e($icon) ?>"></i></div>
                            <h3 class="h5 mb-2"><?= e($title) ?></h3>
                            <p class="text-muted mb-4"><?= e($text) ?></p>
                            <a class="btn btn-outline-primary mt-auto align-self-start" href="<?= href($path) ?>"><?= e($button) ?> <i class="bi bi-arrow-right"></i></a>
                        </div>
                    </article>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="cb-section cb-section-alt">
    <div class="container">
        <div class="cb-section-head text-center">
            <p class="cb-eyebrow mb-1">Four simple steps</p>
            <h2 class="h2 mb-0">How College Buddy Works</h2>
        </div>
        <div class="row g-4 mt-1 cb-steps">
            <?php foreach ($steps as $i => [$icon, $title, $text]): ?>
                <div class="col-sm-6 col-lg-3">
                    <div class="cb-step h-100">
                        <div class="cb-step-num"><?= $i + 1 ?></div>
                        <div class="cb-step-icon"><i class="bi <?= e($icon) ?>"></i></div>
                        <h3 class="h6 mb-1"><?= e($title) ?></h3>
                        <p class="small text-muted mb-0"><?= e($text) ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="cb-section">
    <div class="container">
        <div class="cb-stats card cb-card">
            <div class="card-body p-4 p-md-5">
                <div class="row text-center g-4">
                    <?php
                    $statItems = [
                        ['bi-people', (int) $stats['students'], 'Students registered'],
                        ['bi-bag', (int) $stats['listings'], 'Items for sale'],
                        ['bi-journal-bookmark', (int) $stats['resources'], 'Free study resources'],
                        ['bi-chat-dots', (int) $stats['requests'], 'Open requests'],
                    ];
                    foreach ($statItems as [$icon, $value, $label]):
                    ?>
                        <div class="col-6 col-lg-3">
                            <div class="cb-stat-icon mx-auto mb-2"><i class="bi <?= e($icon) ?>"></i></div>
                            <div class="cb-stat-value"><?= number_format($value) ?></div>
                            <div class="cb-stat-label"><?= e($label) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <p class="small text-muted text-center mt-4 mb-0">These numbers are live counts from the College Buddy database.</p>
            </div>
        </div>
    </div>
</section>

<section class="cb-cta">
    <div class="container">
        <div class="row align-items-center gy-4">
            <div class="col-lg-8">
                <h2 class="h2 mb-2">Make College Life Easier.</h2>
                <p class="mb-0">Join your campus community. Sell what you no longer need, share what you have learned, and help your friends get ahead.</p>
            </div>
            <div class="col-lg-4 text-lg-end">
                <?php if ($me): ?>
                    <a class="btn btn-cb-accent btn-lg" href="<?= href('dashboard.php') ?>"><i class="bi bi-speedometer2"></i> Go to my Dashboard</a>
                <?php else: ?>
                    <a class="btn btn-cb-accent btn-lg" href="<?= href('register.php') ?>"><i class="bi bi-person-plus"></i> Join College Buddy</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
