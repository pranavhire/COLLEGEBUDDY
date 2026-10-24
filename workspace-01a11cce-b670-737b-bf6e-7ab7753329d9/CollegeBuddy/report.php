<?php
/*
 * report.php - report a listing, study resource or request to the admins (login required).
 * The item type is checked against a fixed list, and the table name comes ONLY from that list,
 * so the value typed in the URL can never change the SQL.
 */
require_once __DIR__ . '/includes/auth.php';
require_login('Please login to report content.');

$me = current_user();

// Whitelist: report type => [table name, link to the item, label]
$targets = [
    'listing' => ['listings', 'product.php?id=', 'listing'],
    'resource' => ['resources', 'resource.php?id=', 'study resource'],
    'request' => ['requests', 'request.php?id=', 'request'],
];

$type = read_text('type', '', 'request');
$id = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);

if (!isset($targets[$type]) || $id <= 0) {
    show_error_page(400, 'Invalid report link', 'Please use the Report button on the item you want to report.');
}
[$table, $itemLink, $label] = $targets[$type];

$item = db_select_one("SELECT * FROM {$table} WHERE id = ?", 'i', [$id]);
if ($item === null) {
    show_error_page(404, 'Item not found', 'The item you want to report no longer exists.');
}
$backUrl = $itemLink . $id;
$isOwner = (int) $item['user_id'] === (int) $me['id'];
$reasons = xml_list('reportReasons');
$errors = [];
$values = ['reason' => '', 'description' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $values['reason'] = old('reason');
    $values['description'] = trim(read_text('description', '', 'post'));

    if ($isOwner) {
        set_flash('warning', 'You cannot report your own ' . $label . '.');
        redirect($backUrl);
    }
    if (!in_array($values['reason'], $reasons, true)) {
        $errors['reason'] = 'Please choose a reason.';
    }
    if (mb_strlen($values['description']) > 500) {
        $errors['description'] = 'Details cannot be more than 500 characters.';
    }

    if (!$errors) {
        // Stop the same student from sending the same pending report again and again
        $already = db_select_one(
            "SELECT id FROM reports WHERE user_id = ? AND item_type = ? AND item_id = ? AND status = 'Pending' LIMIT 1",
            'isi',
            [(int) $me['id'], $type, $id]
        );
        if ($already) {
            set_flash('info', 'You have already reported this ' . $label . '. Our admins will review it soon.');
        } else {
            db_insert(
                'INSERT INTO reports (user_id, item_type, item_id, reason, description) VALUES (?, ?, ?, ?, ?)',
                'isiss',
                [(int) $me['id'], $type, $id, $values['reason'], $values['description'] !== '' ? $values['description'] : null]
            );
            set_flash('success', 'Thank you. Your report was sent to the College Buddy admins for review.');
        }
        redirect($backUrl);
    }
}

$itemTitle = $item['title'];
$pageTitle = 'Report ' . $label;
$activeNav = '';
$breadcrumbs = [['Report', '']];
require __DIR__ . '/includes/header.php';
render_page_head('Keep College Buddy safe', 'Report this ' . $label, 'Reports go to the admin review queue. False reports may lead to account action, so please report only real problems.');
?>

<section class="container pb-5">
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="card cb-card">
                <div class="card-body p-4 p-md-5">
                    <div class="cb-file-box p-3 rounded-3 mb-4">
                        <div class="small text-muted text-uppercase">Reporting</div>
                        <div class="fw-semibold"><?= e($itemTitle) ?></div>
                    </div>

                    <form method="post" action="" novalidate data-validate>
                        <?= csrf_field() ?>
                        <input type="hidden" name="type" value="<?= e($type) ?>">
                        <input type="hidden" name="id" value="<?= $id ?>">

                        <div class="cb-field mb-3">
                            <label class="form-label fw-semibold" for="reason">Reason *</label>
                            <select class="form-select<?= invalid_class($errors, 'reason') ?>" id="reason" name="reason" data-required data-label="Reason">
                                <option value="">Choose a reason</option>
                                <?php foreach ($reasons as $r): ?>
                                    <option value="<?= e($r) ?>"<?= $values['reason'] === $r ? ' selected' : '' ?>><?= e($r) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?= field_error($errors, 'reason') ?>
                        </div>

                        <div class="cb-field mb-4">
                            <label class="form-label fw-semibold" for="description">More details (optional)</label>
                            <textarea class="form-control<?= invalid_class($errors, 'description') ?>" id="description" name="description" rows="4" maxlength="500"
                                      placeholder="Tell the admins what is wrong, so they can check it quickly."
                                      data-max-length="500" data-label="Details" data-counter="500"><?= e($values['description']) ?></textarea>
                            <?= field_error($errors, 'description') ?>
                        </div>

                        <div class="d-flex flex-column flex-sm-row gap-2 justify-content-end">
                            <a class="btn btn-outline-secondary" href="<?= href($backUrl) ?>">Cancel</a>
                            <button class="btn btn-danger px-4" type="submit"><i class="bi bi-flag"></i> Submit report</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
