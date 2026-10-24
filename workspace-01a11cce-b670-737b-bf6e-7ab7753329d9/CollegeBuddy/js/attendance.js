/*
 * js/attendance.js - Attendance calculator (plain JavaScript, no libraries).
 *
 * Two parts:
 *   1. calculateAttendance(total, attended, required)  - pure maths, no DOM. Easy to test.
 *   2. Form handling - reads the inputs, shows errors, and writes the explanation into the page.
 *
 * Formulas (T = lectures held, A = attended, R = required %, P = current %):
 *   P = A * 100 / T
 *   If P >= R (can skip):   skip x  = floor(100 * A / R - T)     (largest x where A / (T + x) >= R / 100)
 *   If P <  R (must attend): attend y = ceil((R * T - 100 * A) / (100 - R))  (smallest y where (A + y) / (T + y) >= R / 100)
 */
(function () {
    'use strict';

    // Small tolerance so that floating-point rounding does not change a whole-number answer (for example 3.0000000001 becomes 3)
    var EPS = 1e-9;

    /**
     * Pure calculation. Returns an object describing the situation.
     * Assumes the inputs were already validated: total >= 1, 0 <= attended <= total, 0 <= required <= 100.
     */
    function calculateAttendance(total, attended, required) {
        var current = (attended / total) * 100;
        var result = {
            total: total,
            attended: attended,
            missed: total - attended,
            required: required,
            current: current,
            state: 'above',      // 'above' | 'exact' | 'below'
            unlimited: false,    // true when required is 0%
            impossible: false,   // true when required is 100% but some lectures were already missed
            skip: 0,             // how many more lectures can be skipped
            skipAfter: null,     // attendance % if the skip number is used
            attend: 0,           // how many consecutive lectures must be attended
            attendAfter: null    // attendance % after attending that many
        };

        if (current + EPS >= required) {
            // At or above the requirement: work out how many lectures can be skipped
            result.state = Math.abs(current - required) < EPS ? 'exact' : 'above';
            if (required === 0) {
                result.unlimited = true;
            } else {
                result.skip = Math.max(0, Math.floor((100 * attended) / required - total + EPS));
                result.skipAfter = (attended / (total + result.skip)) * 100;
            }
        } else {
            // Below the requirement: work out how many consecutive lectures must be attended
            result.state = 'below';
            if (required >= 100) {
                result.impossible = true;
            } else {
                var needed = Math.ceil(((required * total) - (100 * attended)) / (100 - required) - EPS);
                result.attend = Math.max(1, needed);
                result.attendAfter = ((attended + result.attend) / (total + result.attend)) * 100;
            }
        }
        return result;
    }

    /** Parses a whole number. Returns { ok, value, message }. */
    function parseWhole(raw, min, max, emptyMessage, invalidMessage) {
        var text = String(raw).trim();
        if (text === '') {
            return { ok: false, message: emptyMessage };
        }
        if (!/^\d+$/.test(text)) {
            return { ok: false, message: invalidMessage };
        }
        var value = parseInt(text, 10);
        if (value < min || value > max) {
            return { ok: false, message: invalidMessage };
        }
        return { ok: true, value: value };
    }

    /** Parses the required percentage (0 to 100, up to two decimals). */
    function parsePercent(raw) {
        var text = String(raw).trim();
        if (text === '') {
            return { ok: false, message: 'Enter the required attendance percentage.' };
        }
        if (!/^\d{1,3}(\.\d{1,2})?$/.test(text) || parseFloat(text) > 100) {
            return { ok: false, message: 'Enter a percentage between 0 and 100 (up to two decimal places).' };
        }
        return { ok: true, value: parseFloat(text) };
    }

    function pct(value) {
        return value.toFixed(2) + '%';
    }

    /*
     * Shows the current percentage with enough decimals that the rounded number agrees with the verdict.
     * Example: 2 out of 3 is 66.666...%. Against a 66.67% requirement, "66.67%" would look equal, so we show more digits.
     */
    function pctPrecise(value, required) {
        for (var digits = 2; digits <= 6; digits++) {
            var text = value.toFixed(digits);
            if ((value >= required) === (parseFloat(text) >= required)) {
                return text + '%';
            }
        }
        return value.toFixed(6) + '%';
    }

    function plural(n, word) {
        return n + ' ' + word + (n === 1 ? '' : 's');
    }

    /** Builds the HTML for the result panel. Only numbers from the calculation are used. */
    function renderResult(r) {
        var badge;
        var headline;
        var details = [];

        if (r.impossible) {
            badge = '<span class="badge bg-danger-subtle text-danger-emphasis">Not possible</span>';
            headline = 'A 100% requirement cannot be reached.';
            details.push('You have already missed ' + plural(r.missed, 'lecture') + ', so your attendance can never return to 100%.');
            details.push('Ask your faculty whether a lower requirement or a condonation policy applies.');
        } else if (r.state === 'below') {
            badge = '<span class="badge bg-danger-subtle text-danger-emphasis">Below requirement</span>';
            headline = 'You must attend the next ' + plural(r.attend, 'lecture') + ' in a row.';
            details.push('Attend every one of the next ' + r.attend + ' lectures without missing any. Each lecture you miss adds more lectures to this number.');
            details.push('After attending them, attendance would be (' + r.attended + ' + ' + r.attend + ') / (' + r.total + ' + ' + r.attend + ') = ' + pct(r.attendAfter) + '.');
        } else if (r.unlimited) {
            badge = '<span class="badge bg-success-subtle text-success-emphasis">Above requirement</span>';
            headline = 'You can skip any number of lectures.';
            details.push('Your required attendance is 0%, so no number of skipped lectures can take you below it.');
        } else if (r.skip === 0) {
            badge = r.state === 'exact'
                ? '<span class="badge bg-warning-subtle text-warning-emphasis">Exactly at requirement</span>'
                : '<span class="badge bg-success-subtle text-success-emphasis">Above requirement</span>';
            headline = 'You cannot skip the next lecture.';
            details.push('Skipping even one more lecture would take your attendance below ' + pct(r.required) + '.');
            details.push('Attend the next lectures to build a safety margin.');
        } else {
            badge = r.state === 'exact'
                ? '<span class="badge bg-warning-subtle text-warning-emphasis">Exactly at requirement</span>'
                : '<span class="badge bg-success-subtle text-success-emphasis">Above requirement</span>';
            headline = 'You can skip up to ' + plural(r.skip, 'more lecture') + '.';
            details.push('If you miss the next ' + r.skip + ' lecture' + (r.skip === 1 ? '' : 's') + ', attendance becomes ' + r.attended + ' / (' + r.total + ' + ' + r.skip + ') = ' + pct(r.skipAfter) + ', which is still at or above ' + pct(r.required) + '.');
            details.push('Skipping ' + (r.skip + 1) + ' would take you below ' + pct(r.required) + '.');
        }

        return '' +
            '<div class="cb-result-head d-flex flex-wrap align-items-center gap-2 mb-3">' + badge + '</div>' +
            '<div class="cb-result-current mb-3">' +
                '<div class="small text-muted">Your current attendance</div>' +
                '<div class="cb-result-number">' + pctPrecise(r.current, r.required) + '</div>' +
                '<div class="small text-muted">' + r.attended + ' attended of ' + r.total + ' lectures &middot; required ' + pct(r.required) + '</div>' +
            '</div>' +
            '<div class="cb-result-headline mb-3">' + headline + '</div>' +
            '<ul class="cb-result-list small text-muted">' +
                details.map(function (d) { return '<li>' + d + '</li>'; }).join('') +
            '</ul>';
    }

    function showErrors(form, errors) {
        form.querySelectorAll('[data-error-for]').forEach(function (box) {
            box.textContent = '';
        });
        form.querySelectorAll('.form-control').forEach(function (input) {
            input.classList.remove('is-invalid');
        });
        Object.keys(errors).forEach(function (id) {
            var input = document.getElementById(id);
            var box = form.querySelector('[data-error-for="' + id + '"]');
            if (input) {
                input.classList.add('is-invalid');
            }
            if (box) {
                box.textContent = errors[id];
            }
        });
    }

    function init() {
        var form = document.getElementById('attendanceForm');
        var resultBox = document.getElementById('attendanceResult');
        if (!form || !resultBox) {
            return;
        }
        var resetButton = document.getElementById('attendanceReset');
        var placeholder = resultBox.innerHTML;

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            var totalRaw = document.getElementById('totalLectures').value;
            var attendedRaw = document.getElementById('attendedLectures').value;
            var requiredRaw = document.getElementById('requiredPercent').value;
            var errors = {};

            var total = parseWhole(totalRaw, 1, 1000,
                'Enter the total number of lectures held so far.',
                'Enter a whole number from 1 to 1000.');
            if (!total.ok) {
                errors.totalLectures = total.message;
            }

            var attended = parseWhole(attendedRaw, 0, 1000,
                'Enter the number of lectures you attended (0 if none).',
                'Enter a whole number, 0 or more.');
            if (!attended.ok) {
                errors.attendedLectures = attended.message;
            } else if (total.ok && attended.value > total.value) {
                errors.attendedLectures = 'Attended lectures cannot be more than total lectures (' + total.value + ').';
            }

            var required = parsePercent(requiredRaw);
            if (!required.ok) {
                errors.requiredPercent = required.message;
            }

            if (Object.keys(errors).length > 0) {
                showErrors(form, errors);
                resultBox.innerHTML = '<div class="alert alert-warning mb-0"><i class="bi bi-exclamation-triangle"></i> Please correct the highlighted fields to see your result.</div>';
                return;
            }

            showErrors(form, {});
            var result = calculateAttendance(total.value, attended.value, required.value);
            resultBox.innerHTML = renderResult(result);
        });

        if (resetButton) {
            resetButton.addEventListener('click', function () {
                // The form reset happens after this click handler, so clear the messages on the next tick
                setTimeout(function () {
                    showErrors(form, {});
                    resultBox.innerHTML = placeholder;
                }, 0);
            });
        }
    }

    // Used by the test script and by the page
    window.CBAttendance = { calculate: calculateAttendance };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
