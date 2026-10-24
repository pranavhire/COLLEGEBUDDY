<?php
/*
 * login.php - student and admin login.
 * Checks: empty fields, valid e-mail format, the account exists, and the password matches
 * (password_verify compares the typed password with the stored hash).
 * On success the user id is saved in the PHP session.
 */
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    redirect(is_admin() ? 'admin/dashboard.php' : 'dashboard.php');
}

$errors = [];
$next = safe_next(read_text('next', '', 'request'));
$lockedUntil = (int) ($_SESSION['login_locked_until'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = strtolower(old('email'));
    $password = read_text('password', '', 'post');

    // 1. Empty fields and e-mail format
    if ($email === '') {
        $errors['email'] = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address.';
    }
    if ($password === '') {
        $errors['password'] = 'Password is required.';
    }

    if (!$errors) {
        // 2. Simple brute-force protection: 5 wrong passwords lock login for 5 minutes
        if (time() < $lockedUntil) {
            $errors['form'] = 'Too many failed attempts. Please wait a few minutes and try again.';
        } else {
            $user = db_select_one('SELECT * FROM users WHERE email = ?', 's', [$email]);
            if ($user === null) {
                $errors['email'] = 'No account found with this email address.';
            } elseif (!password_verify($password, $user['password'])) {
                $errors['password'] = 'Incorrect password. Please try again.';
                $_SESSION['login_failures'] = ((int) ($_SESSION['login_failures'] ?? 0)) + 1;
                if ($_SESSION['login_failures'] >= 5) {
                    $_SESSION['login_locked_until'] = time() + 300;
                    $_SESSION['login_failures'] = 0;
                }
            } else {
                unset($_SESSION['login_failures'], $_SESSION['login_locked_until']);
                login_user((int) $user['id']);
                set_flash('success', 'Welcome back, ' . explode(' ', $user['full_name'])[0] . '!');
                if ($next !== '') {
                    redirect($next);
                }
                redirect($user['role'] === 'admin' ? 'admin/dashboard.php' : 'dashboard.php');
            }
        }
    }
}

$pageTitle = 'Login';
$activeNav = '';
$breadcrumbs = [];
require __DIR__ . '/includes/header.php';
?>

<section class="cb-auth">
    <div class="container">
        <div class="row g-4 align-items-center justify-content-center">
            <div class="col-lg-5 d-none d-lg-block">
                <div class="cb-auth-panel">
                    <img src="<?= href('images/logo.svg') ?>" alt="" width="56" height="56" class="mb-4">
                    <h2 class="h3">Welcome back to College Buddy</h2>
                    <p class="mb-4">Sign in to sell your used items, share notes, post requests and track your attendance and grades.</p>
                    <ul class="cb-check-list">
                        <li><i class="bi bi-check2-circle"></i> Student-to-student marketplace</li>
                        <li><i class="bi bi-check2-circle"></i> Free study resources for your semester</li>
                        <li><i class="bi bi-check2-circle"></i> Calculators for attendance and CGPA</li>
                    </ul>
                </div>
            </div>
            <div class="col-md-8 col-lg-5">
                <div class="card cb-card cb-auth-card">
                    <div class="card-body p-4 p-md-5">
                        <h1 class="h3 mb-1">Login</h1>
                        <p class="text-muted mb-4">Use your college email and password.</p>

                        <?php if (!empty($errors['form'])): ?>
                            <div class="alert alert-danger d-flex gap-2" role="alert"><i class="bi bi-exclamation-octagon-fill"></i> <span><?= e($errors['form']) ?></span></div>
                        <?php endif; ?>

                        <form method="post" action="" id="loginForm" novalidate data-validate>
                            <?= csrf_field() ?>
                            <input type="hidden" name="next" value="<?= e($next) ?>">

                            <div class="cb-field mb-3">
                                <label class="form-label fw-semibold" for="email">Email address</label>
                                <input type="email" class="form-control<?= invalid_class($errors, 'email') ?>" id="email" name="email"
                                       value="<?= e(old('email')) ?>" placeholder="name@college.edu" autocomplete="username"
                                       data-required data-email data-label="Email" required autofocus>
                                <?= field_error($errors, 'email') ?>
                            </div>

                            <div class="cb-field mb-4">
                                <label class="form-label fw-semibold" for="password">Password</label>
                                <div class="cb-password">
                                    <input type="password" class="form-control<?= invalid_class($errors, 'password') ?>" id="password" name="password"
                                           autocomplete="current-password" data-required data-label="Password" required>
                                    <button type="button" class="cb-toggle-pass" data-toggle-password="#password" aria-label="Show password">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                                <?= field_error($errors, 'password') ?>
                            </div>

                            <button class="btn btn-primary btn-lg w-100" type="submit"><i class="bi bi-box-arrow-in-right"></i> Login</button>
                        </form>

                        <p class="text-center mt-4 mb-0">New to College Buddy? <a href="<?= href('register.php') ?>" class="fw-semibold">Create an account</a></p>
                    </div>
                </div>

                <div class="cb-demo-box mt-3">
                    <p class="fw-semibold mb-2"><i class="bi bi-info-circle"></i> Demo accounts for testing</p>
                    <div class="d-flex flex-wrap gap-2">
                        <button type="button" class="btn btn-sm btn-outline-primary" data-fill-form="#loginForm" data-fill-email="student@collegebuddy.com" data-fill-password="Student@123">
                            Fill student login
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-primary" data-fill-form="#loginForm" data-fill-email="admin@collegebuddy.com" data-fill-password="Admin@123">
                            Fill admin login
                        </button>
                    </div>
                    <p class="small text-muted mt-2 mb-0">Student: student@collegebuddy.com / Student@123 &middot; Admin: admin@collegebuddy.com / Admin@123</p>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
