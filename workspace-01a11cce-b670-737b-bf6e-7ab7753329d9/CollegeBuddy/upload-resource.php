<?php
/*
 * upload-resource.php - share a FREE study resource (notes, papers, practical files).
 * Checks: required fields, XML lists, file extension whitelist, real file type (finfo), size limit (10 MB).
 * The file gets a random name in uploads/resources/. Executable and PHP files are rejected.
 */
require_once __DIR__ . '/includes/auth.php';
require_login('Please login to upload a study resource.');

$me = current_user();
$branches = array_column(xml_list('branches'), 'value');
$years = array_map('strval', array_column(xml_list('years'), 'value'));
$semesters = xml_list('semesters');
$semesterValues = array_map('strval', array_column($semesters, 'value'));
$types = xml_list('resourceTypes');
$subjects = array_values(array_unique(array_column(xml_list('subjects'), 'name')));
sort($subjects);
$allowedExt = array_keys(resource_upload_types());

$errors = [];
$values = ['title' => '', 'description' => '', 'subject' => '', 'branch' => '', 'year' => '', 'semester' => '', 'resource_type' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $values = [
        'title' => old('title'),
        'description' => trim(read_text('description', '', 'post')),
        'subject' => old('subject'),
        'branch' => old('branch'),
        'year' => old('year'),
        'semester' => old('semester'),
        'resource_type' => old('resource_type'),
    ];

    $titleLen = mb_strlen($values['title']);
    if ($titleLen < 3 || $titleLen > 150) {
        $errors['title'] = 'Title must be between 3 and 150 characters.';
    }
    if (mb_strlen($values['description']) > 500) {
        $errors['description'] = 'Description cannot be more than 500 characters.';
    }
    if (!in_array($values['subject'], $subjects, true)) {
        $errors['subject'] = 'Please choose a subject from the list.';
    }
    if (!in_array($values['branch'], $branches, true)) {
        $errors['branch'] = 'Please choose a branch.';
    }
    if (!in_array($values['year'], $years, true)) {
        $errors['year'] = 'Please choose a year.';
    }
    if (!in_array($values['semester'], $semesterValues, true)) {
        $errors['semester'] = 'Please choose a semester.';
    } elseif (!isset($errors['year']) && intdiv((int) $values['semester'] + 1, 2) !== (int) $values['year']) {
        $errors['semester'] = 'This semester does not belong to the year you chose. Semesters 1-2 are Year 1, 3-4 Year 2, 5-6 Year 3 and 7-8 Year 4.';
    }
    if (!in_array($values['resource_type'], $types, true)) {
        $errors['resource_type'] = 'Please choose a resource type.';
    }

    // The file is checked and saved only when the form fields are correct
    $storedName = null;
    $upload = null;
    if (!$errors) {
        $upload = handle_upload($_FILES['file'] ?? ['error' => UPLOAD_ERR_NO_FILE], resource_upload_types(), UPLOAD_LIMIT_BYTES, resource_dir(), 'res_', true);
        if (!$upload['ok']) {
            $errors['file'] = $upload['error'];
        } else {
            $storedName = $upload['stored'];
        }
    }

    if (!$errors && $upload !== null) {
        try {
            $newId = db_insert(
                'INSERT INTO resources (user_id, title, description, subject, branch, year, semester, resource_type,
                                        file_name, stored_name, file_size, file_ext)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                'issssiisssis',
                [
                    (int) $me['id'], $values['title'], $values['description'] !== '' ? $values['description'] : null,
                    $values['subject'], $values['branch'], (int) $values['year'], (int) $values['semester'],
                    $values['resource_type'], $upload['original'], $storedName, (int) $upload['size'], $upload['ext'],
                ]
            );
            set_flash('success', 'Thank you! Your resource is now live in the Study Hub for other students.');
            redirect('resource.php?id=' . $newId);
        } catch (mysqli_sql_exception $ex) {
            delete_resource_file($storedName); // do not keep a file that has no database row
            $errors['form'] = 'We could not save your upload right now. Please try again in a moment.';
        }
    }
}

$pageTitle = 'Upload a resource';
$activeNav = 'study';
$breadcrumbs = [['Study Hub', 'study.php'], ['Upload a resource', '']];
require __DIR__ . '/includes/header.php';
render_page_head('Share with your batch', 'Upload a study resource', 'Share notes, previous year papers or practical files. Resources are free for every student.');
?>

<section class="container pb-5">
    <div class="row justify-content-center">
        <div class="col-lg-9">
            <div class="card cb-card">
                <div class="card-body p-4 p-md-5">
                    <?php if (!empty($errors['form'])): ?>
                        <div class="alert alert-danger d-flex gap-2"><i class="bi bi-exclamation-octagon-fill"></i> <span><?= e($errors['form']) ?></span></div>
                    <?php endif; ?>

                    <form method="post" action="" enctype="multipart/form-data" novalidate data-validate>
                        <?= csrf_field() ?>
                        <div class="row g-3">
                            <div class="col-12 cb-field">
                                <label class="form-label fw-semibold" for="title">Title *</label>
                                <input type="text" class="form-control<?= invalid_class($errors, 'title') ?>" id="title" name="title" maxlength="150"
                                       value="<?= e($values['title']) ?>" placeholder="e.g. Data Structures Notes - Units 1 to 3"
                                       data-required data-min-length="3" data-max-length="150" data-label="Title">
                                <?= field_error($errors, 'title') ?>
                            </div>

                            <div class="col-md-6 cb-field">
                                <label class="form-label fw-semibold" for="subject">Subject *</label>
                                <select class="form-select<?= invalid_class($errors, 'subject') ?>" id="subject" name="subject" data-required data-label="Subject">
                                    <option value="">Choose a subject</option>
                                    <?php foreach ($subjects as $s): ?>
                                        <option value="<?= e($s) ?>"<?= $values['subject'] === $s ? ' selected' : '' ?>><?= e($s) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?= field_error($errors, 'subject') ?>
                            </div>

                            <div class="col-md-6 cb-field">
                                <label class="form-label fw-semibold" for="resource_type">Resource type *</label>
                                <select class="form-select<?= invalid_class($errors, 'resource_type') ?>" id="resource_type" name="resource_type" data-required data-label="Resource type">
                                    <option value="">Choose a type</option>
                                    <?php foreach ($types as $t): ?>
                                        <option value="<?= e($t) ?>"<?= $values['resource_type'] === $t ? ' selected' : '' ?>><?= e($t) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?= field_error($errors, 'resource_type') ?>
                            </div>

                            <div class="col-md-4 cb-field">
                                <label class="form-label fw-semibold" for="branch">Branch *</label>
                                <select class="form-select<?= invalid_class($errors, 'branch') ?>" id="branch" name="branch" data-required data-label="Branch">
                                    <option value="">Choose branch</option>
                                    <?php foreach (xml_list('branches') as $b): ?>
                                        <option value="<?= e($b['value']) ?>"<?= $values['branch'] === $b['value'] ? ' selected' : '' ?>><?= e($b['value']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?= field_error($errors, 'branch') ?>
                            </div>

                            <div class="col-md-4 cb-field">
                                <label class="form-label fw-semibold" for="year">Year *</label>
                                <select class="form-select<?= invalid_class($errors, 'year') ?>" id="year" name="year" data-required data-label="Year">
                                    <option value="">Choose year</option>
                                    <?php foreach (xml_list('years') as $y): ?>
                                        <option value="<?= e($y['value']) ?>"<?= $values['year'] === (string) $y['value'] ? ' selected' : '' ?>><?= e($y['label']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?= field_error($errors, 'year') ?>
                            </div>

                            <div class="col-md-4 cb-field">
                                <label class="form-label fw-semibold" for="semester">Semester *</label>
                                <select class="form-select<?= invalid_class($errors, 'semester') ?>" id="semester" name="semester" data-required data-label="Semester" data-semester-for="year">
                                    <option value="">Choose semester</option>
                                    <?php foreach ($semesters as $s): ?>
                                        <option value="<?= e($s['value']) ?>"<?= $values['semester'] === (string) $s['value'] ? ' selected' : '' ?>><?= e($s['label']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?= field_error($errors, 'semester') ?>
                            </div>

                            <div class="col-12 cb-field">
                                <label class="form-label fw-semibold" for="description">Description (optional)</label>
                                <textarea class="form-control<?= invalid_class($errors, 'description') ?>" id="description" name="description" rows="3" maxlength="500"
                                          placeholder="Units covered, exam year, what is inside the file..."
                                          data-max-length="500" data-label="Description" data-counter="500"><?= e($values['description']) ?></textarea>
                                <?= field_error($errors, 'description') ?>
                            </div>

                            <div class="col-12 cb-field">
                                <label class="form-label fw-semibold" for="file">File *</label>
                                <input type="file" class="form-control<?= invalid_class($errors, 'file') ?>" id="file" name="file"
                                       accept=".pdf,.doc,.docx,.ppt,.pptx,.txt,.jpg,.jpeg,.png"
                                       data-required data-accept="<?= e(implode(',', $allowedExt)) ?>" data-max-mb="10" data-label="File">
                                <div class="form-text">Allowed: PDF, DOC, DOCX, PPT, PPTX, TXT, JPG, JPEG, PNG. Maximum size 10 MB. Executable and script files are not accepted.</div>
                                <?= field_error($errors, 'file') ?>
                            </div>
                        </div>

                        <div class="d-flex flex-column flex-sm-row gap-2 justify-content-end mt-4">
                            <a class="btn btn-outline-secondary" href="<?= href('study.php') ?>">Cancel</a>
                            <button class="btn btn-cb-accent px-4" type="submit"><i class="bi bi-cloud-arrow-up"></i> Upload resource</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
