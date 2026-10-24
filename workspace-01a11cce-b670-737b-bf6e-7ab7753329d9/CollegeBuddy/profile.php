<?php
/*
 * profile.php - view and edit name, branch, year and semester.
 * The e-mail address and the role (student/admin) cannot be changed by the student.
 */
require_once __DIR__ . '/includes/auth.php';
require_login('Please login to view your profile.');

$me = current_user();
$myId = (int) $me['id'];
$branches = xml_list('branches');
$years = xml_list('years');
$semesters = xml_list('semesters');
$errors = [];
$values = [
    'full_name' => $me['full_name'],
    'branch' => $me['branch'],
    'year' => (string) $me['year'],
    'semester' => (string) $me['semester'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $values = [
        'full_name' => preg_replace('/\s+/', ' ', old('full_name')),
        'branch' => old('branch'),
        'year' => old('year'),
        'semester' => old('semester'),
    ];

    $nameLen = mb_strlen($values['full_name']);
    if ($nameLen === 0) {
        $errors['full_name'] = 'Full name is required.';
    } elseif ($nameLen < 3 || $nameLen > 100) {
        $errors['full_name'] = 'Full name must be between 3 and 100 characters.';
    } elseif (!preg_match("/^\\p{L}[\\p{L} .'-]*$/u", $values['full_name'])) {
        $errors['full_name'] = 'Name can contain only letters, spaces, dots, apostrophes and hyphens.';
    }

    if (!in_array($values['branch'], array_column($branches, 'value'), true)) {
        $errors['branch'] = 'Please choose a branch.';
    }
    if (!in_array($values['year'], array_map('strval', array_column($years, 'value')), true)) {
        $errors['year'] = 'Please choose a year.';
    }
    $sem = (int) $values['semester'];
    if (!ctype_digit($values['semester']) || $sem < 1 || $sem > 8) {
        $errors['semester'] = 'Please choose a semester.';
    } elseif (!isset($errors['year']) && intdiv($sem + 1, 2) !== (int) $values['year']) {
        $errors['semester'] = 'This semester does not match your year. Semesters 1-2 are Year 1, 3-4 Year 2, 5-6 Year 3 and 7-8 Year 4.';
    }

    if (!$errors) {
        db_exec(
            'UPDATE users SET full_name = ?, branch = ?, year = ?, semester = ? WHERE id = ?',
            'ssiii',
            [$values['full_name'], $values['branch'], (int) $values['year'], $sem, $myId]
        );
        set_flash('success', 'Your profile has been updated.');
        redirect('profile.php');
    }
}

$stats = db_select_one(
    'SELECT (SELECT COUNT(*) FROM listings WHERE user_id = ?) AS listings,
            (SELECT COUNT(*) FROM resources WHERE user_id = ?) AS resources,
            (SELECT COUNT(*) FROM requests WHERE user_id = ?) AS requests',
    'iii',
    [$myId, $myId, $myId]
);

$pageTitle = 'My Profile';
$activeNav = 'dashboard';
$breadcrumbs = [['Dashboard', 'dashboard.php'], ['Profile', '']];
require __DIR__ . '/includes/header.php';
render_page_head('Your account', 'My Profile', 'Keep your branch, year and semester up to date so the right notes and requests reach you.');
?>

<section class="container py-4 pb-5">
    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card cb-card h-100">
                <div class="card-body p-4 text-center">
                    <span class="cb-avatar cb-avatar-xl mx-auto mb-3"><?= e(initials($me['full_name'])) ?></span>
                    <h2 class="h5 mb-1"><?= e($me['full_name']) ?></h2>
                    <div class="text-muted small mb-3"><?= e($me['email']) ?></div>
                    <span class="badge <?= is_admin() ? 'bg-dark' : 'cb-badge-soft' ?> mb-4"><?= is_admin() ? 'Administrator' : 'Student' ?></span>
                    <ul class="list-group list-group-flush text-start cb-profile-stats">
                        <li class="list-group-item d-flex justify-content-between px-0"><span>Listings</span><strong><?= (int) $stats['listings'] ?></strong></li>
                        <li class="list-group-item d-flex justify-content-between px-0"><span>Resources shared</span><strong><?= (int) $stats['resources'] ?></strong></li>
                        <li class="list-group-item d-flex justify-content-between px-0"><span>Requests posted</span><strong><?= (int) $stats['requests'] ?></strong></li>
                        <li class="list-group-item d-flex justify-content-between px-0"><span>Member since</span><strong><?= e(format_date($me['created_at'], 'M Y')) ?></strong></li>
                    </ul>
                    <p class="small text-muted mt-3 mb-0"><i class="bi bi-lock"></i> Your e-mail address and account role are managed by College Buddy.</p>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card cb-card">
                <div class="card-body p-4 p-md-5">
                    <h2 class="h5 mb-4">Edit profile</h2>
                    <form method="post" action="" novalidate data-validate>
                        <?= csrf_field() ?>
                        <div class="row g-3">
                            <div class="col-12 cb-field">
                                <label class="form-label fw-semibold" for="full_name">Full name *</label>
                                <input type="text" class="form-control<?= invalid_class($errors, 'full_name') ?>" id="full_name" name="full_name" maxlength="100"
                                       value="<?= e($values['full_name']) ?>" data-required data-min-length="3" data-max-length="100" data-name data-label="Full name">
                                <?= field_error($errors, 'full_name') ?>
                            </div>
                            <div class="col-md-4 cb-field">
                                <label class="form-label fw-semibold" for="branch">Branch *</label>
                                <select class="form-select<?= invalid_class($errors, 'branch') ?>" id="branch" name="branch" data-required data-label="Branch">
                                    <?php foreach ($branches as $b): ?>
                                        <option value="<?= e($b['value']) ?>"<?= $values['branch'] === $b['value'] ? ' selected' : '' ?>><?= e($b['value']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?= field_error($errors, 'branch') ?>
                            </div>
                            <div class="col-md-4 cb-field">
                                <label class="form-label fw-semibold" for="year">Year *</label>
                                <select class="form-select<?= invalid_class($errors, 'year') ?>" id="year" name="year" data-required data-label="Year">
                                    <?php foreach ($years as $y): ?>
                                        <option value="<?= e($y['value']) ?>"<?= $values['year'] === (string) $y['value'] ? ' selected' : '' ?>><?= e($y['label']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?= field_error($errors, 'year') ?>
                            </div>
                            <div class="col-md-4 cb-field">
                                <label class="form-label fw-semibold" for="semester">Semester *</label>
                                <select class="form-select<?= invalid_class($errors, 'semester') ?>" id="semester" name="semester" data-required data-label="Semester" data-semester-for="year">
                                    <?php foreach ($semesters as $s): ?>
                                        <option value="<?= e($s['value']) ?>"<?= $values['semester'] === (string) $s['value'] ? ' selected' : '' ?>><?= e($s['label']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?= field_error($errors, 'semester') ?>
                            </div>
                        </div>
                        <div class="d-flex flex-column flex-sm-row gap-2 justify-content-end mt-4">
                            <a class="btn btn-outline-secondary" href="<?= href('dashboard.php') ?>">Cancel</a>
                            <button class="btn btn-primary px-4" type="submit"><i class="bi bi-check2"></i> Save changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
