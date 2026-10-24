<?php
/*
 * create-request.php - post a new request on the Request Board (login required).
 */
require_once __DIR__ . '/includes/auth.php';
require_login('Please login to post a request.');

$me = current_user();
$categories = xml_list('requestCategories');
$errors = [];
$values = ['title' => '', 'category' => '', 'description' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $values = [
        'title' => old('title'),
        'category' => old('category'),
        'description' => trim(read_text('description', '', 'post')),
    ];

    $titleLen = mb_strlen($values['title']);
    if ($titleLen < 3 || $titleLen > 150) {
        $errors['title'] = 'Title must be between 3 and 150 characters.';
    }
    if (!in_array($values['category'], $categories, true)) {
        $errors['category'] = 'Please choose a category.';
    }
    $descLen = mb_strlen($values['description']);
    if ($descLen < 10 || $descLen > 1000) {
        $errors['description'] = 'Description must be between 10 and 1000 characters. Mention the subject, edition or semester if you can.';
    }

    if (!$errors) {
        try {
            $newId = db_insert(
                "INSERT INTO requests (user_id, title, description, category, status) VALUES (?, ?, ?, ?, 'Open')",
                'isss',
                [(int) $me['id'], $values['title'], $values['description'], $values['category']]
            );
            set_flash('success', 'Your request is live. Students can now respond to it.');
            redirect('request.php?id=' . $newId);
        } catch (mysqli_sql_exception $ex) {
            $errors['form'] = 'We could not post your request right now. Please try again.';
        }
    }
}

$pageTitle = 'Post a request';
$activeNav = 'requests';
$breadcrumbs = [['Request Board', 'requests.php'], ['Post a request', '']];
require __DIR__ . '/includes/header.php';
render_page_head('Ask your batchmates', 'Post a request', 'Describe what you need clearly. Other students will reply to you through College Buddy messages.');
?>

<section class="container pb-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card cb-card">
                <div class="card-body p-4 p-md-5">
                    <?php if (!empty($errors['form'])): ?>
                        <div class="alert alert-danger d-flex gap-2"><i class="bi bi-exclamation-octagon-fill"></i> <span><?= e($errors['form']) ?></span></div>
                    <?php endif; ?>

                    <form method="post" action="" novalidate data-validate>
                        <?= csrf_field() ?>
                        <div class="cb-field mb-3">
                            <label class="form-label fw-semibold" for="title">Request title *</label>
                            <input type="text" class="form-control<?= invalid_class($errors, 'title') ?>" id="title" name="title" maxlength="150"
                                   value="<?= e($values['title']) ?>" placeholder="e.g. Need DSA textbook"
                                   data-required data-min-length="3" data-max-length="150" data-label="Title">
                            <?= field_error($errors, 'title') ?>
                        </div>

                        <div class="cb-field mb-3">
                            <label class="form-label fw-semibold" for="category">Category *</label>
                            <select class="form-select<?= invalid_class($errors, 'category') ?>" id="category" name="category" data-required data-label="Category">
                                <option value="">Choose a category</option>
                                <?php foreach ($categories as $c): ?>
                                    <option value="<?= e($c) ?>"<?= $values['category'] === $c ? ' selected' : '' ?>><?= e($c) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?= field_error($errors, 'category') ?>
                        </div>

                        <div class="cb-field mb-4">
                            <label class="form-label fw-semibold" for="description">Description *</label>
                            <textarea class="form-control<?= invalid_class($errors, 'description') ?>" id="description" name="description" rows="5" maxlength="1000"
                                      placeholder="What do you need, which subject or semester, how urgent is it, and what can you offer in return?"
                                      data-required data-min-length="10" data-max-length="1000" data-label="Description" data-counter="1000"><?= e($values['description']) ?></textarea>
                            <?= field_error($errors, 'description') ?>
                        </div>

                        <div class="d-flex flex-column flex-sm-row gap-2 justify-content-end">
                            <a class="btn btn-outline-secondary" href="<?= href('requests.php') ?>">Cancel</a>
                            <button class="btn btn-cb-accent px-4" type="submit"><i class="bi bi-send"></i> Post request</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
