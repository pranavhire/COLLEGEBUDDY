<?php
/*
 * cgpa.php - CGPA calculator (plain JavaScript in js/cgpa.js).
 * Tab 1: simple average of the SGPA values you enter (blank semesters are ignored).
 * Tab 2: credit-weighted CGPA = sum(SGPA x credits) / sum(credits).
 * The grade scale is read from the XML file, for reference only.
 */
require_once __DIR__ . '/includes/auth.php';

$pageTitle = 'CGPA Calculator';
$activeNav = 'cgpa';
$breadcrumbs = [['CGPA Calculator', '']];
$pageScripts = ['cgpa.js'];
$grades = xml_list('grades');

require __DIR__ . '/includes/header.php';
render_page_head(
    'Know where you stand',
    'CGPA Calculator',
    'Enter the SGPA of each semester you have completed. Blank semesters are ignored, so you can use this at any point in your course.'
);
?>

<section class="container py-4 pb-5">
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card cb-card">
                <div class="card-body p-4 p-md-5">
                    <ul class="nav nav-pills cb-tabs mb-4" id="cgpaTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="tab-simple" data-bs-toggle="pill" data-bs-target="#pane-simple" type="button" role="tab" aria-controls="pane-simple" aria-selected="true">Simple average</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="tab-weighted" data-bs-toggle="pill" data-bs-target="#pane-weighted" type="button" role="tab" aria-controls="pane-weighted" aria-selected="false">Credit-weighted</button>
                        </li>
                    </ul>

                    <div class="tab-content">
                        <!-- Tab 1: simple average of SGPA values -->
                        <div class="tab-pane fade show active" id="pane-simple" role="tabpanel" aria-labelledby="tab-simple" tabindex="0">
                            <p class="text-muted">Enter the SGPA for each completed semester (0 to 10, up to two decimal places). CGPA = sum of the entered SGPAs &divide; number of entered SGPAs.</p>
                            <form id="simpleCgpaForm" novalidate>
                                <div class="row g-3">
                                    <?php for ($sem = 1; $sem <= 8; $sem++): ?>
                                        <div class="col-6 col-md-3">
                                            <label class="form-label small fw-semibold" for="sgpa<?= $sem ?>">Semester <?= $sem ?> SGPA</label>
                                            <input type="number" class="form-control sgpa-input" id="sgpa<?= $sem ?>" name="sgpa<?= $sem ?>"
                                                   min="0" max="10" step="0.01" inputmode="decimal" placeholder="0.00 to 10">
                                        </div>
                                    <?php endfor; ?>
                                </div>
                                <div id="simpleErrors" class="cb-form-errors mt-3" aria-live="polite"></div>
                                <div class="d-flex flex-wrap gap-2 mt-4">
                                    <button class="btn btn-primary" type="submit"><i class="bi bi-calculator"></i> Calculate CGPA</button>
                                    <button class="btn btn-outline-secondary" type="button" data-cgpa-clear="simple"><i class="bi bi-eraser"></i> Clear</button>
                                </div>
                            </form>
                            <div id="simpleResult" class="cb-cgpa-result mt-4" aria-live="polite"></div>
                        </div>

                        <!-- Tab 2: credit-weighted CGPA -->
                        <div class="tab-pane fade" id="pane-weighted" role="tabpanel" aria-labelledby="tab-weighted" tabindex="0">
                            <p class="text-muted">For a more accurate CGPA, enter the credits of each semester next to its SGPA. CGPA = sum(SGPA &times; credits) &divide; sum(credits). Leave a whole row empty if that semester is not done.</p>
                            <form id="weightedCgpaForm" novalidate>
                                <div class="table-responsive">
                                    <table class="table cb-table align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th scope="col">Semester</th>
                                                <th scope="col">SGPA (0 to 10)</th>
                                                <th scope="col">Credits (1 to 40)</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php for ($sem = 1; $sem <= 8; $sem++): ?>
                                                <tr>
                                                    <th scope="row" class="fw-normal">Semester <?= $sem ?></th>
                                                    <td><input type="number" class="form-control w-sgpa" id="wsgpa<?= $sem ?>" min="0" max="10" step="0.01" inputmode="decimal" aria-label="Semester <?= $sem ?> SGPA"></td>
                                                    <td><input type="number" class="form-control w-credits" id="wcredits<?= $sem ?>" min="1" max="40" step="1" inputmode="numeric" aria-label="Semester <?= $sem ?> credits"></td>
                                                </tr>
                                            <?php endfor; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div id="weightedErrors" class="cb-form-errors mt-3" aria-live="polite"></div>
                                <div class="d-flex flex-wrap gap-2 mt-4">
                                    <button class="btn btn-primary" type="submit"><i class="bi bi-calculator"></i> Calculate weighted CGPA</button>
                                    <button class="btn btn-outline-secondary" type="button" data-cgpa-clear="weighted"><i class="bi bi-eraser"></i> Clear</button>
                                </div>
                            </form>
                            <div id="weightedResult" class="cb-cgpa-result mt-4" aria-live="polite"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card cb-card cb-sticky">
                <div class="card-body p-4">
                    <h2 class="h6 text-uppercase text-muted mb-3">Grade scale (reference)</h2>
                    <table class="table table-sm cb-table mb-3">
                        <thead><tr><th scope="col">Grade</th><th scope="col" class="text-end">Grade points</th></tr></thead>
                        <tbody>
                            <?php foreach ($grades as $g): ?>
                                <tr><td class="fw-semibold"><?= e($g['symbol']) ?></td><td class="text-end"><?= e($g['points']) ?></td></tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p class="small text-muted mb-3">SGPA and CGPA are normally shown on a 10-point scale. Grade points come from your college's grading rules, so check your own result sheet.</p>
                    <div class="cb-formula">
                        <div class="fw-semibold mb-1">Approximate percentage</div>
                        <p class="small mb-0">Many Indian universities use <strong>percentage = CGPA &times; 9.5</strong> as an approximation. Your university may use a different formula.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
