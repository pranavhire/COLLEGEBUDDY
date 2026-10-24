<?php
/*
 * attendance.php - attendance calculator (all calculations run in the browser, in js/attendance.js).
 * The page itself only shows the form, the explanation and the formulas. No result is hard-coded.
 */
require_once __DIR__ . '/includes/auth.php';

$pageTitle = 'Attendance Calculator';
$activeNav = 'attendance';
$breadcrumbs = [['Attendance Calculator', '']];
$pageScripts = ['attendance.js'];

require __DIR__ . '/includes/header.php';
render_page_head(
    'Plan your lectures',
    'Attendance Calculator',
    'Enter the lectures held so far, the lectures you attended and the percentage your college requires. The calculator tells you what to do next.'
);
?>

<section class="container py-4 pb-5">
    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card cb-card cb-calc-card">
                <div class="card-body p-4 p-md-5">
                    <h2 class="h5 mb-4">Your attendance</h2>
                    <form id="attendanceForm" novalidate>
                        <div class="cb-field mb-3">
                            <label class="form-label fw-semibold" for="totalLectures">Total lectures held so far *</label>
                            <input type="number" class="form-control" id="totalLectures" name="total" min="1" max="1000" step="1" inputmode="numeric" placeholder="e.g. 50" required>
                            <div class="invalid-feedback" data-error-for="totalLectures"></div>
                        </div>
                        <div class="cb-field mb-3">
                            <label class="form-label fw-semibold" for="attendedLectures">Lectures attended *</label>
                            <input type="number" class="form-control" id="attendedLectures" name="attended" min="0" max="1000" step="1" inputmode="numeric" placeholder="e.g. 40" required>
                            <div class="invalid-feedback" data-error-for="attendedLectures"></div>
                        </div>
                        <div class="cb-field mb-4">
                            <label class="form-label fw-semibold" for="requiredPercent">Required attendance (%) *</label>
                            <input type="number" class="form-control" id="requiredPercent" name="required" min="0" max="100" step="0.01" inputmode="decimal" placeholder="e.g. 75" required>
                            <div class="form-text">Usually 75%. Check your college rules if it is different.</div>
                            <div class="invalid-feedback" data-error-for="requiredPercent"></div>
                        </div>
                        <div class="d-flex flex-column flex-sm-row gap-2">
                            <button class="btn btn-primary btn-lg flex-grow-1" type="submit"><i class="bi bi-calculator"></i> Calculate</button>
                            <button class="btn btn-outline-secondary btn-lg" type="reset" id="attendanceReset"><i class="bi bi-arrow-counterclockwise"></i> Reset</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card cb-card h-100">
                <div class="card-body p-4 p-md-5">
                    <h2 class="h5 mb-3">Result</h2>
                    <div id="attendanceResult" aria-live="polite">
                        <div class="cb-empty cb-empty-small">
                            <div class="cb-empty-icon"><i class="bi bi-bar-chart-line"></i></div>
                            <p class="text-muted mb-0">Enter your numbers and press <strong>Calculate</strong>. The result and the explanation will appear here.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card cb-card mt-4">
        <div class="card-body p-4 p-md-5">
            <h2 class="h5 mb-3"><i class="bi bi-info-circle"></i> How the answer is calculated</h2>
            <p class="text-muted">Let <strong>T</strong> = lectures held, <strong>A</strong> = lectures attended, <strong>R</strong> = required %, and <strong>P</strong> = current % = A &times; 100 &divide; T.</p>
            <div class="row g-4">
                <div class="col-md-6">
                    <div class="cb-formula h-100">
                        <div class="fw-semibold mb-2">If P is at least R (you can skip)</div>
                        <p class="mb-2">Skipping <em>x</em> more lectures (and missing all of them) gives A &divide; (T + x). You stay at or above R while x is at most:</p>
                        <code>x = floor( 100 &times; A &divide; R &minus; T )</code>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="cb-formula h-100">
                        <div class="fw-semibold mb-2">If P is below R (you must attend)</div>
                        <p class="mb-2">Attending the next <em>y</em> lectures in a row gives (A + y) &divide; (T + y). You reach R when y is at least:</p>
                        <code>y = ceil( (R &times; T &minus; 100 &times; A) &divide; (100 &minus; R) )</code>
                    </div>
                </div>
            </div>
            <ul class="small text-muted mt-4 mb-0">
                <li>If R is 0%, you can skip any number of lectures.</li>
                <li>If R is 100%, attendance can only stay at 100%. If you have already missed a lecture, the requirement can never be met.</li>
                <li>Attended lectures cannot be more than total lectures, and total lectures must be at least 1.</li>
                <li>Results are estimates based on the lectures you enter. Your college's official register is the final record.</li>
            </ul>
        </div>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
