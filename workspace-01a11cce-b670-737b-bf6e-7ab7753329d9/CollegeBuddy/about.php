<?php
/*
 * about.php - what College Buddy is, the community rules, the technology used and contact details.
 */
require_once __DIR__ . '/includes/auth.php';

$pageTitle = 'About';
$activeNav = '';
$breadcrumbs = [['About', '']];

$modules = [
    ['bi-bag-check', 'Marketplace', 'Students buy and sell used academic items. Every deal is made in person, with no online payments.', 'marketplace.php'],
    ['bi-journal-bookmark', 'Study Hub', 'Free notes, previous year papers, question banks and practical files, filtered by branch, year and semester.', 'study.php'],
    ['bi-chat-dots', 'Request Board', 'Ask your batchmates for a book, notes or help with a practical. Anyone can respond.', 'requests.php'],
    ['bi-calendar2-check', 'Attendance Calculator', 'Work out how many lectures you can safely skip, or how many you must attend in a row.', 'attendance.php'],
    ['bi-calculator', 'CGPA Calculator', 'Calculate CGPA from SGPA values, with a simple average or a credit-weighted method.', 'cgpa.php'],
];
$rules = [
    'Meet in public campus places. College Buddy does not handle money or online payments.',
    'Describe items honestly, including wear, highlighting and missing parts.',
    'Study resources are free. They must never be sold, traded for money or used to sell notes.',
    'Only share notes, papers and files that you are allowed to share.',
    'Be respectful in messages. Report spam, fake listings or inappropriate content.',
];
$stack = [
    ['HTML5', 'Page structure and forms'],
    ['CSS3', 'Custom theme in css/style.css'],
    ['Bootstrap 5', 'Responsive grid, navbar and components'],
    ['JavaScript', 'Plain JavaScript for validation and calculators'],
    ['PHP', 'Server logic, sessions and security'],
    ['MySQL', 'Users, listings, resources, requests, messages and reports'],
    ['XML', 'Static reference lists such as branches and grade scale'],
    ['XAMPP', 'Local Apache, PHP and MySQL server'],
];

require __DIR__ . '/includes/header.php';
render_page_head(
    'About College Buddy',
    'Your College. Your Community. Your Buddy.',
    'A student-to-student platform that makes everyday college life simpler: used items, free notes, help requests and academic calculators.'
);
?>

<section class="container py-5">
    <div class="row g-4 align-items-center">
        <div class="col-lg-6">
            <h2 class="h3 mb-3">Why College Buddy exists</h2>
            <p class="text-muted">Students often need the same things at the same time: an engineering textbook for one semester, a drawing set for one practical, last year's question paper before an exam. College Buddy keeps these needs inside one trusted campus community, so you do not have to search across scattered chat groups.</p>
            <p class="text-muted mb-0">Everything is built to be simple to use on a phone during a busy day, and simple to explain in a lab viva.</p>
        </div>
        <div class="col-lg-6">
            <div class="card cb-card cb-rules-card">
                <div class="card-body p-4">
                    <h2 class="h5 mb-3"><i class="bi bi-shield-check"></i> Community rules</h2>
                    <ol class="cb-rules mb-0">
                        <?php foreach ($rules as $rule): ?>
                            <li><?= e($rule) ?></li>
                        <?php endforeach; ?>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="cb-section cb-section-alt">
    <div class="container">
        <div class="cb-section-head text-center">
            <p class="cb-eyebrow mb-1">Inside the app</p>
            <h2 class="h3 mb-0">Five modules, one account</h2>
        </div>
        <div class="row g-4 mt-1">
            <?php foreach ($modules as [$icon, $title, $text, $path]): ?>
                <div class="col-md-6 col-lg-4">
                    <a class="card cb-card cb-card-hover cb-module h-100 text-decoration-none" href="<?= href($path) ?>">
                        <div class="card-body p-4">
                            <div class="cb-feature-icon mb-3"><i class="bi <?= e($icon) ?>"></i></div>
                            <h3 class="h6 text-body mb-2"><?= e($title) ?></h3>
                            <p class="small text-muted mb-0"><?= e($text) ?></p>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="container py-5">
    <div class="row g-4">
        <div class="col-lg-7">
            <h2 class="h4 mb-3">How this project is built</h2>
            <p class="text-muted">College Buddy is a Web Technology lab project. It uses only standard web technologies, with no frontend or backend frameworks.</p>
            <div class="row row-cols-2 row-cols-md-4 g-2">
                <?php foreach ($stack as [$name, $use]): ?>
                    <div class="col">
                        <div class="cb-tech h-100">
                            <div class="fw-semibold"><?= e($name) ?></div>
                            <div class="small text-muted"><?= e($use) ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card cb-card h-100">
                <div class="card-body p-4">
                    <h2 class="h4 mb-3">Contact us</h2>
                    <ul class="list-unstyled mb-4 cb-contact-list">
                        <li><i class="bi bi-envelope"></i> <a href="mailto:support@collegebuddy.com">support@collegebuddy.com</a></li>
                        <li><i class="bi bi-geo-alt"></i> Pune, Maharashtra, India</li>
                        <li><i class="bi bi-clock"></i> Replies within 1 to 2 working days</li>
                    </ul>
                    <p class="small text-muted mb-3">To report a listing, resource or request, use the Report button on that item. This sends it straight to the admin review queue.</p>
                    <div class="d-flex flex-wrap gap-2">
                        <a class="btn btn-primary" href="<?= href('marketplace.php') ?>">Browse marketplace</a>
                        <a class="btn btn-outline-primary" href="<?= href('register.php') ?>">Create an account</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
