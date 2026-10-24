<?php
/*
 * includes/footer.php
 * Closes <main>, prints the footer, the shared confirmation dialog and the JavaScript files.
 * Pages can add extra scripts by setting $pageScripts = ['attendance.js'];
 */
$pageScripts = $pageScripts ?? [];
?>
</main>

<footer class="cb-footer mt-auto">
    <div class="container py-5">
        <div class="row gy-4">
            <div class="col-lg-4">
                <a class="cb-brand cb-brand-light d-inline-flex align-items-center gap-2 text-decoration-none" href="<?= href('index.php') ?>">
                    <img src="<?= href('images/logo.svg') ?>" alt="" width="34" height="34">
                    <span class="cb-brand-text">COLLEGE<span class="cb-brand-accent">BUDDY</span></span>
                </a>
                <p class="cb-footer-tagline mt-3 mb-1">Your College. Your Community. Your Buddy.</p>
                <p class="small mb-0">A student-to-student platform for used items, free study resources, requests and academic calculators.</p>
            </div>
            <div class="col-6 col-lg-2">
                <h2 class="h6 cb-footer-heading">Quick Links</h2>
                <ul class="list-unstyled cb-footer-links mb-0">
                    <li><a href="<?= href('marketplace.php') ?>">Marketplace</a></li>
                    <li><a href="<?= href('study.php') ?>">Study Hub</a></li>
                    <li><a href="<?= href('requests.php') ?>">Request Board</a></li>
                    <li><a href="<?= href('attendance.php') ?>">Attendance</a></li>
                    <li><a href="<?= href('cgpa.php') ?>">CGPA</a></li>
                </ul>
            </div>
            <div class="col-6 col-lg-3">
                <h2 class="h6 cb-footer-heading">Account</h2>
                <ul class="list-unstyled cb-footer-links mb-0">
                    <?php if (current_user()): ?>
                        <li><a href="<?= href('dashboard.php') ?>">Dashboard</a></li>
                        <li><a href="<?= href('sell.php') ?>">Sell an Item</a></li>
                        <li><a href="<?= href('upload-resource.php') ?>">Upload a Resource</a></li>
                        <li><a href="<?= href('logout.php') ?>">Logout</a></li>
                    <?php else: ?>
                        <li><a href="<?= href('login.php') ?>">Login</a></li>
                        <li><a href="<?= href('register.php') ?>">Create an account</a></li>
                    <?php endif; ?>
                </ul>
            </div>
            <div class="col-lg-3" id="contact">
                <h2 class="h6 cb-footer-heading">Contact</h2>
                <ul class="list-unstyled cb-footer-links mb-0">
                    <li><a href="mailto:support@collegebuddy.com"><i class="bi bi-envelope"></i> support@collegebuddy.com</a></li>
                    <li><a href="<?= href('about.php') ?>">About College Buddy</a></li>
                    <li><span class="small"><i class="bi bi-geo-alt"></i> Pune, Maharashtra, India</span></li>
                </ul>
            </div>
        </div>
        <div class="cb-footer-bottom d-flex flex-column flex-md-row justify-content-between gap-2 mt-5 pt-4 small">
            <span>&copy; <?= date('Y') ?> College Buddy. All rights reserved.</span>
            <span>Built with HTML5, CSS3, Bootstrap, JavaScript, PHP, MySQL and XML.</span>
        </div>
    </div>
</footer>

<!-- Shared confirmation dialog (used by forms with data-confirm="...") -->
<div class="modal fade" id="cbConfirmModal" tabindex="-1" aria-labelledby="cbConfirmTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content cb-modal">
            <div class="modal-body text-center p-4">
                <div class="cb-empty-icon cb-empty-icon-warn mx-auto mb-3"><i class="bi bi-exclamation-triangle"></i></div>
                <h2 class="h5" id="cbConfirmTitle">Are you sure?</h2>
                <p class="text-muted mb-0" id="cbConfirmText">This action cannot be undone.</p>
            </div>
            <div class="modal-footer border-0 justify-content-center pt-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="cbConfirmYes">Yes, continue</button>
            </div>
        </div>
    </div>
</div>

<script src="<?= href('js/vendor/bootstrap.bundle.min.js') ?>"></script>
<script src="<?= href('js/validation.js') ?>"></script>
<script src="<?= href('js/main.js') ?>"></script>
<?php foreach ($pageScripts as $script): ?>
    <script src="<?= href('js/' . $script) ?>"></script>
<?php endforeach; ?>
</body>
</html>
