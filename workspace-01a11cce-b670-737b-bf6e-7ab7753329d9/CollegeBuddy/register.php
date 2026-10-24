<?php
/*
 * register.php - create a student account.
 * Client side: js/validation.js checks every field before the form is sent.
 * Server side (below): the same rules are checked again, because client checks can be bypassed.
 * The password is stored with password_hash(); the plain text password is never saved.
 */
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    redirect('dashboard.php');
}

$branches = xml_list('branches');
$years = xml_list('years');
$branchValues = array_column($branches, 'value');
$yearValues = array_column($years, 'value');

$errors = [];
$values = ['full_name' => '', 'email' => '', 'branch' => '', 'year' => '', 'semester' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $values = [
        'full_name' => preg_replace('/\s+/', ' ', old('full_name')),
        'email' => strtolower(old('email')),
        'branch' => old('branch'),
        'year' => old('year'),
        'semester' => old('semester'),
    ];
    $password = read_text('password', '', 'post');
    $confirm = read_text('confirm_password', '', 'post');

    // Full name: 3 to 100 letters (Unicode letters allowed), spaces, dots, apostrophes and hyphens
    $nameLen = mb_strlen($values['full_name']);
    if ($nameLen === 0) {
        $errors['full_name'] = 'Full name is required.';
    } elseif ($nameLen < 3 || $nameLen > 100) {
        $errors['full_name'] = 'Full name must be between 3 and 100 characters.';
    } elseif (!preg_match("/^\\p{L}[\\p{L} .'-]*$/u", $values['full_name'])) {
        $errors['full_name'] = 'Name can contain only letters, spaces, dots, apostrophes and hyphens.';
    }

    // E-mail: valid format, unique in the database (checked again on insert below)
    if ($values['email'] === '') {
        $errors['email'] = 'Email is required.';
    } elseif (!filter_var($values['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($values['email']) > 150) {
        $errors['email'] = 'Please enter a valid email address.';
    }

    // Password: 8 to 64 characters, at least one letter and one number
    if ($password === '') {
        $errors['password'] = 'Password is required.';
    } elseif (strlen($password) < 8 || strlen($password) > 64) {
        $errors['password'] = 'Password must be between 8 and 64 characters.';
    } elseif (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        $errors['password'] = 'Password must contain at least one letter and one number.';
    }
    if ($confirm === '') {
        $errors['confirm_password'] = 'Please confirm your password.';
    } elseif ($confirm !== $password) {
        $errors['confirm_password'] = 'Passwords do not match.';
    }

    // Academic details come from the XML file, so only known values are accepted
    if (!in_array($values['branch'], $branchValues, true)) {
        $errors['branch'] = 'Please select your branch.';
    }
    if (!in_array($values['year'], array_map('strval', $yearValues), true)) {
        $errors['year'] = 'Please select your year.';
    }
    $sem = (int) $values['semester'];
    if ($values['semester'] === '' || !ctype_digit($values['semester']) || $sem < 1 || $sem > 8) {
        $errors['semester'] = 'Please select your semester.';
    } elseif (!isset($errors['year']) && intdiv($sem + 1, 2) !== (int) $values['year']) {
        $errors['semester'] = 'This semester does not match your year. Semesters 1-2 are First Year, 3-4 Second Year, 5-6 Third Year and 7-8 Final Year.';
    }

    if (!$errors) {
        try {
            $userId = db_insert(
                'INSERT INTO users (full_name, email, password, branch, year, semester, role) VALUES (?, ?, ?, ?, ?, ?, ?)',
                'ssssiis',
                [
                    $values['full_name'],
                    $values['email'],
                    password_hash($password, PASSWORD_DEFAULT, ['cost' => 10]),
                    $values['branch'],
                    (int) $values['year'],
                    $sem,
                    'student',
                ]
            );
            login_user($userId);
            set_flash('success', 'Welcome to College Buddy, ' . explode(' ', $values['full_name'])[0] . '! Your account is ready.');
            redirect('dashboard.php');
        } catch (mysqli_sql_exception $ex) {
            if ((int) $ex->getCode() === 1062) { // duplicate key = e-mail already exists
                $errors['email'] = 'This email is already registered. Please login instead.';
            } else {
                throw $ex;
            }
        }
    }
}

$pageTitle = 'Register';
$activeNav = '';
$breadcrumbs = [];
require __DIR__ . '/includes/header.php';
?>

<section class="cb-auth">
    <div class="container">
        <div class="row g-4 align-items-start justify-content-center">
            <div class="col-lg-4 d-none d-lg-block">
                <div class="cb-auth-panel cb-auth-panel-sticky">
                    <img src="<?= href('images/logo.svg') ?>" alt="" width="56" height="56" class="mb-4">
                    <h2 class="h3">Join your campus community</h2>
                    <p class="mb-4">Creating an account is free. Your branch, year and semester help us show you the right notes and requests.</p>
                    <ul class="cb-check-list">
                        <li><i class="bi bi-check2-circle"></i> Sell or buy used academic items</li>
                        <li><i class="bi bi-check2-circle"></i> Upload notes and previous year papers</li>
                        <li><i class="bi bi-check2-circle"></i> Post requests and get help from seniors</li>
                    </ul>
                </div>
            </div>
            <div class="col-md-10 col-lg-7">
                <div class="card cb-card cb-auth-card">
                    <div class="card-body p-4 p-md-5">
                        <h1 class="h3 mb-1">Create your account</h1>
                        <p class="text-muted mb-4">Takes less than a minute. Fields marked with * are required.</p>

                        <form method="post" action="" id="registerForm" novalidate data-validate>
                            <?= csrf_field() ?>
                            <div class="row g-3">
                                <div class="col-12 cb-field">
                                    <label class="form-label fw-semibold" for="full_name">Full name *</label>
                                    <input type="text" class="form-control<?= invalid_class($errors, 'full_name') ?>" id="full_name" name="full_name"
                                           value="<?= e($values['full_name']) ?>" maxlength="100" autocomplete="name"
                                           data-required data-label="Full name" data-min-length="3" data-max-length="100" data-name>
                                    <?= field_error($errors, 'full_name') ?>
                                </div>

                                <div class="col-12 cb-field">
                                    <label class="form-label fw-semibold" for="email">College email *</label>
                                    <input type="email" class="form-control<?= invalid_class($errors, 'email') ?>" id="email" name="email"
                                           value="<?= e($values['email']) ?>" maxlength="150" autocomplete="email" placeholder="name@college.edu"
                                           data-required data-email data-label="Email">
                                    <?= field_error($errors, 'email') ?>
                                </div>

                                <div class="col-md-6 cb-field">
                                    <label class="form-label fw-semibold" for="password">Password *</label>
                                    <div class="cb-password">
                                        <input type="password" class="form-control<?= invalid_class($errors, 'password') ?>" id="password" name="password"
                                               maxlength="64" autocomplete="new-password" data-required data-password data-min-length="8"
                                               data-label="Password" data-strength="#passwordStrength">
                                        <button type="button" class="cb-toggle-pass" data-toggle-password="#password" aria-label="Show password"><i class="bi bi-eye"></i></button>
                                    </div>
                                    <div class="cb-strength mt-2" id="passwordStrength" aria-live="polite">
                                        <div class="cb-strength-bar"><span></span></div>
                                        <small class="text-muted">At least 8 characters, with a letter and a number.</small>
                                    </div>
                                    <?= field_error($errors, 'password') ?>
                                </div>

                                <div class="col-md-6 cb-field">
                                    <label class="form-label fw-semibold" for="confirm_password">Confirm password *</label>
                                    <div class="cb-password">
                                        <input type="password" class="form-control<?= invalid_class($errors, 'confirm_password') ?>" id="confirm_password" name="confirm_password"
                                               maxlength="64" autocomplete="new-password" data-required data-match="password" data-match-label="Passwords do not match."
                                               data-label="Confirm password">
                                        <button type="button" class="cb-toggle-pass" data-toggle-password="#confirm_password" aria-label="Show password"><i class="bi bi-eye"></i></button>
                                    </div>
                                    <?= field_error($errors, 'confirm_password') ?>
                                </div>

                                <div class="col-md-4 cb-field">
                                    <label class="form-label fw-semibold" for="branch">Branch *</label>
                                    <select class="form-select<?= invalid_class($errors, 'branch') ?>" id="branch" name="branch" data-required data-label="Branch">
                                        <option value="">Choose branch</option>
                                        <?php foreach ($branches as $b): ?>
                                            <option value="<?= e($b['value']) ?>" title="<?= e($b['label']) ?>"<?= $values['branch'] === $b['value'] ? ' selected' : '' ?>><?= e($b['value']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?= field_error($errors, 'branch') ?>
                                </div>

                                <div class="col-md-4 cb-field">
                                    <label class="form-label fw-semibold" for="year">Year *</label>
                                    <select class="form-select<?= invalid_class($errors, 'year') ?>" id="year" name="year" data-required data-label="Year">
                                        <option value="">Choose year</option>
                                        <?php foreach ($years as $y): ?>
                                            <option value="<?= e($y['value']) ?>"<?= (string) $values['year'] === (string) $y['value'] ? ' selected' : '' ?>><?= e($y['label']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?= field_error($errors, 'year') ?>
                                </div>

                                <div class="col-md-4 cb-field">
                                    <label class="form-label fw-semibold" for="semester">Semester *</label>
                                    <select class="form-select<?= invalid_class($errors, 'semester') ?>" id="semester" name="semester" data-required data-label="Semester" data-semester-for="year">
                                        <option value="">Choose semester</option>
                                        <?php foreach (xml_list('semesters') as $s): ?>
                                            <option value="<?= e($s['value']) ?>"<?= (string) $values['semester'] === (string) $s['value'] ? ' selected' : '' ?>><?= e($s['label']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?= field_error($errors, 'semester') ?>
                                </div>
                            </div>

                            <button class="btn btn-primary btn-lg w-100 mt-4" type="submit"><i class="bi bi-person-check"></i> Create account</button>
                            <p class="text-center mt-3 mb-0">Already registered? <a href="<?= href('login.php') ?>" class="fw-semibold">Login here</a></p>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
