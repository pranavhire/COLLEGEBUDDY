<?php
/*
 * sell.php - create a new marketplace listing, or edit your own listing (sell.php?edit=ID).
 * Saves the listing with a prepared INSERT or UPDATE. The photo is optional and is checked
 * for type, size and real image content before it is saved.
 */
require_once __DIR__ . '/includes/auth.php';
require_login('Please login to sell an item.');

$me = current_user();
$categories = xml_list('listingCategories');
$conditions = xml_list('conditions');

// Editing: load the listing, but only if it belongs to the logged-in student
$editId = (int) ($_GET['edit'] ?? 0);
$listing = null;
if ($editId > 0) {
    $listing = db_select_one('SELECT * FROM listings WHERE id = ? AND user_id = ?', 'ii', [$editId, (int) $me['id']]);
    if ($listing === null) {
        set_flash('danger', 'You can only edit your own listings.');
        redirect('my-listings.php');
    }
}

$errors = [];
$values = [
    'title' => $listing['title'] ?? '',
    'category' => $listing['category'] ?? '',
    'description' => $listing['description'] ?? '',
    'price' => $listing ? rtrim(rtrim(number_format((float) $listing['price'], 2, '.', ''), '0'), '.') : '',
    'item_condition' => $listing['item_condition'] ?? 'Good',
    'location' => $listing['location'] ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $values = [
        'title' => old('title'),
        'category' => old('category'),
        'description' => trim(read_text('description', '', 'post')),
        'price' => old('price'),
        'item_condition' => old('item_condition'),
        'location' => old('location'),
    ];

    $titleLen = mb_strlen($values['title']);
    if ($titleLen < 3 || $titleLen > 150) {
        $errors['title'] = 'Item name must be between 3 and 150 characters.';
    }
    if (!in_array($values['category'], $categories, true)) {
        $errors['category'] = 'Please choose a category.';
    }
    $descLen = mb_strlen($values['description']);
    if ($descLen < 10 || $descLen > 1000) {
        $errors['description'] = 'Description must be between 10 and 1000 characters.';
    }
    if (!preg_match('/^\d{1,6}(\.\d{1,2})?$/', $values['price'])) {
        $errors['price'] = 'Enter a valid price in rupees, for example 450 or 99.50. Enter 0 for a free item.';
    } elseif ((float) $values['price'] > 100000) {
        $errors['price'] = 'The price cannot be more than Rs. 1,00,000.';
    }
    if (!in_array($values['item_condition'], $conditions, true)) {
        $errors['item_condition'] = 'Please choose the condition of the item.';
    }
    $locLen = mb_strlen($values['location']);
    if ($locLen < 2 || $locLen > 120) {
        $errors['location'] = 'Enter where the buyer can collect the item (2 to 120 characters).';
    }

    // Optional photo: only checked and saved when all the text fields are valid
    $newImage = null;
    if (!$errors) {
        $upload = handle_upload($_FILES['image'] ?? ['error' => UPLOAD_ERR_NO_FILE], image_upload_types(), IMAGE_LIMIT_BYTES, listing_dir(), 'item_', false);
        if (!$upload['ok']) {
            $errors['image'] = $upload['error'];
        } elseif (empty($upload['skipped'])) {
            $newImage = 'uploads/listings/' . $upload['stored'];
        }
    }

    if (!$errors) {
        $price = (float) $values['price'];
        if ($listing) {
            db_exec(
                'UPDATE listings SET title = ?, category = ?, description = ?, price = ?, item_condition = ?, location = ?, image = ?
                 WHERE id = ? AND user_id = ?',
                'sssdsssii',
                [$values['title'], $values['category'], $values['description'], $price, $values['item_condition'],
                 $values['location'], $newImage ?? $listing['image'], (int) $listing['id'], (int) $me['id']]
            );
            if ($newImage !== null) {
                delete_listing_image($listing['image']); // remove the old photo after the new one is saved
            }
            $savedId = (int) $listing['id'];
            set_flash('success', 'Your listing was updated.');
        } else {
            $savedId = db_insert(
                "INSERT INTO listings (user_id, title, category, description, price, item_condition, location, image, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Available')",
                'isssdsss',
                [(int) $me['id'], $values['title'], $values['category'], $values['description'], $price,
                 $values['item_condition'], $values['location'], $newImage]
            );
            set_flash('success', 'Your item is now live on the marketplace!');
        }
        redirect('product.php?id=' . $savedId);
    }
}

$photoPreview = $newImage ?? ($listing['image'] ?? null);
$pageTitle = $listing ? 'Edit listing' : 'Sell an item';
$activeNav = 'marketplace';
$breadcrumbs = [['Marketplace', 'marketplace.php'], [$listing ? 'Edit listing' : 'Sell an item', '']];
$pageScripts = [];
require __DIR__ . '/includes/header.php';
?>

<section class="container pb-5">
    <div class="row justify-content-center">
        <div class="col-lg-9">
            <div class="card cb-card">
                <div class="card-body p-4 p-md-5">
                    <h1 class="h3 mb-1"><?= $listing ? 'Edit your listing' : 'Sell an academic item' ?></h1>
                    <p class="text-muted mb-4">Describe the item honestly. Students will contact you to arrange a meeting. There are no online payments on College Buddy.</p>

                    <form method="post" action="" enctype="multipart/form-data" novalidate data-validate>
                        <?= csrf_field() ?>
                        <div class="row g-3">
                            <div class="col-md-8 cb-field">
                                <label class="form-label fw-semibold" for="title">Item name *</label>
                                <input type="text" class="form-control<?= invalid_class($errors, 'title') ?>" id="title" name="title" maxlength="150"
                                       value="<?= e($values['title']) ?>" placeholder="e.g. Engineering Mathematics Textbook"
                                       data-required data-min-length="3" data-max-length="150" data-label="Item name">
                                <?= field_error($errors, 'title') ?>
                            </div>

                            <div class="col-md-4 cb-field">
                                <label class="form-label fw-semibold" for="price">Price (&#8377;) *</label>
                                <input type="number" class="form-control<?= invalid_class($errors, 'price') ?>" id="price" name="price" min="0" step="0.01"
                                       value="<?= e($values['price']) ?>" placeholder="0 for free" data-required data-number data-min-value="0" data-max-value="100000" data-label="Price">
                                <?= field_error($errors, 'price') ?>
                            </div>

                            <div class="col-md-6 cb-field">
                                <label class="form-label fw-semibold" for="category">Category *</label>
                                <select class="form-select<?= invalid_class($errors, 'category') ?>" id="category" name="category" data-required data-label="Category">
                                    <option value="">Choose a category</option>
                                    <?php foreach ($categories as $c): ?>
                                        <option value="<?= e($c) ?>"<?= $values['category'] === $c ? ' selected' : '' ?>><?= e($c) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?= field_error($errors, 'category') ?>
                            </div>

                            <div class="col-md-6 cb-field">
                                <label class="form-label fw-semibold" for="item_condition">Condition *</label>
                                <select class="form-select<?= invalid_class($errors, 'item_condition') ?>" id="item_condition" name="item_condition" data-required data-label="Condition">
                                    <?php foreach ($conditions as $c): ?>
                                        <option value="<?= e($c) ?>"<?= $values['item_condition'] === $c ? ' selected' : '' ?>><?= e($c) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?= field_error($errors, 'item_condition') ?>
                            </div>

                            <div class="col-12 cb-field">
                                <label class="form-label fw-semibold" for="location">Pickup location *</label>
                                <input type="text" class="form-control<?= invalid_class($errors, 'location') ?>" id="location" name="location" maxlength="120"
                                       list="locationList" value="<?= e($values['location']) ?>" placeholder="e.g. Boys Hostel Gate, Library"
                                       data-required data-min-length="2" data-max-length="120" data-label="Pickup location">
                                <datalist id="locationList">
                                    <option value="Main Gate"></option>
                                    <option value="Central Library"></option>
                                    <option value="Boys Hostel Gate"></option>
                                    <option value="Girls Hostel Reception"></option>
                                    <option value="Engineering Block A"></option>
                                    <option value="Engineering Block B"></option>
                                    <option value="Canteen"></option>
                                </datalist>
                                <?= field_error($errors, 'location') ?>
                            </div>

                            <div class="col-12 cb-field">
                                <label class="form-label fw-semibold" for="description">Description *</label>
                                <textarea class="form-control<?= invalid_class($errors, 'description') ?>" id="description" name="description" rows="5" maxlength="1000"
                                          placeholder="Edition, condition details, what is included, reason for selling..."
                                          data-required data-min-length="10" data-max-length="1000" data-label="Description" data-counter="1000"><?= e($values['description']) ?></textarea>
                                <?= field_error($errors, 'description') ?>
                            </div>

                            <div class="col-12 cb-field">
                                <label class="form-label fw-semibold" for="image">Photo (optional)</label>
                                <div class="cb-upload-box d-flex flex-column flex-md-row gap-3 align-items-md-center">
                                    <img id="imagePreview" class="cb-upload-preview" alt="Photo preview"
                                         src="<?= e(listing_image_url($photoPreview)) ?>">
                                    <div class="flex-grow-1">
                                        <input type="file" class="form-control<?= invalid_class($errors, 'image') ?>" id="image" name="image"
                                               accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                                               data-accept="jpg,jpeg,png,webp" data-max-mb="3" data-label="Photo" data-preview="#imagePreview">
                                        <div class="form-text">JPG, PNG or WEBP, up to 3 MB. A clear photo helps your item sell faster.</div>
                                        <?= field_error($errors, 'image') ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex flex-column flex-sm-row gap-2 justify-content-end mt-4">
                            <a class="btn btn-outline-secondary" href="<?= $listing ? href('product.php?id=' . (int) $listing['id']) : href('my-listings.php') ?>">Cancel</a>
                            <button class="btn btn-cb-accent px-4" type="submit"><i class="bi bi-check2-circle"></i> <?= $listing ? 'Save changes' : 'Publish listing' ?></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
