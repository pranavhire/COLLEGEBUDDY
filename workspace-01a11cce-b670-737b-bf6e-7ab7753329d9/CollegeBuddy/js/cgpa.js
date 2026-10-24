/*
 * js/cgpa.js - CGPA calculator (plain JavaScript).
 *
 * Simple average:      CGPA = (SGPA1 + SGPA2 + ...) / number of semesters entered
 * Credit-weighted:     CGPA = (SGPA1 * Credits1 + SGPA2 * Credits2 + ...) / (Credits1 + Credits2 + ...)
 * Approximate percent: CGPA * 9.5
 *
 * Validation: SGPA must be a number from 0 to 10 with at most two decimal places.
 *             Credits must be a whole number from 1 to 40. A weighted row needs both values or neither.
 */
(function () {
    'use strict';

    var SGPA_PATTERN = /^\d{1,2}(\.\d{1,2})?$/;   // e.g. 8, 8.5, 8.75, 10, 10.00
    var CREDIT_PATTERN = /^\d{1,2}$/;

    /** Returns { ok, value } for one SGPA text value. Blank returns { ok: true, value: null }. */
    function parseSgpa(text, label) {
        var t = String(text).trim();
        if (t === '') {
            return { ok: true, value: null };
        }
        if (!SGPA_PATTERN.test(t) || parseFloat(t) > 10) {
            return { ok: false, message: label + ' must be a number from 0 to 10 with at most two decimal places.' };
        }
        return { ok: true, value: parseFloat(t) };
    }

    /** Returns { ok, value } for credits. Blank returns { ok: true, value: null }. */
    function parseCredits(text, label) {
        var t = String(text).trim();
        if (t === '') {
            return { ok: true, value: null };
        }
        if (!CREDIT_PATTERN.test(t) || parseInt(t, 10) < 1 || parseInt(t, 10) > 40) {
            return { ok: false, message: label + ' must be a whole number from 1 to 40.' };
        }
        return { ok: true, value: parseInt(t, 10) };
    }

    function showErrors(box, messages) {
        if (messages.length === 0) {
            box.innerHTML = '';
            return;
        }
        box.innerHTML = '<div class="alert alert-danger mb-0"><ul class="mb-0 ps-3">' +
            messages.map(function (m) { return '<li>' + m + '</li>'; }).join('') +
            '</ul></div>';
    }

    function resultCard(cgpa, lines) {
        var percent = (cgpa * 9.5).toFixed(2);
        return '' +
            '<div class="cb-cgpa-box">' +
                '<div class="small text-muted text-uppercase">Your CGPA</div>' +
                '<div class="cb-result-number">' + cgpa.toFixed(2) + '<span class="fs-5 text-muted"> / 10</span></div>' +
                '<div class="small">Approximate percentage: <strong>' + percent + '%</strong> <span class="text-muted">(CGPA &times; 9.5)</span></div>' +
            '</div>' +
            '<ul class="small text-muted mt-3 mb-0">' +
                lines.map(function (l) { return '<li>' + l + '</li>'; }).join('') +
            '</ul>';
    }

    /* ---------- Tab 1: simple average ---------- */
    function calculateSimple() {
        var errors = [];
        var values = [];
        var semesters = [];
        for (var i = 1; i <= 8; i++) {
            var input = document.getElementById('sgpa' + i);
            var parsed = parseSgpa(input.value, 'Semester ' + i + ' SGPA');
            if (!parsed.ok) {
                errors.push(parsed.message);
            } else if (parsed.value !== null) {
                values.push(parsed.value);
                semesters.push(i);
            }
        }
        showErrors(document.getElementById('simpleErrors'), errors);
        if (errors.length > 0) {
            document.getElementById('simpleResult').innerHTML = '';
            return;
        }
        if (values.length === 0) {
            showErrors(document.getElementById('simpleErrors'), ['Enter the SGPA of at least one semester.']);
            document.getElementById('simpleResult').innerHTML = '';
            return;
        }
        var sum = values.reduce(function (a, b) { return a + b; }, 0);
        var cgpa = sum / values.length;
        document.getElementById('simpleResult').innerHTML = resultCard(cgpa, [
            'Semesters used: ' + semesters.join(', ') + ' (' + values.length + ' of 8).',
            'Sum of SGPA values: ' + sum.toFixed(2) + '. Divided by ' + values.length + ' gives the CGPA.',
            values.length < 8 ? 'Leave a semester blank if it is not completed yet. The CGPA will update as you add results.' : 'All eight semesters are included.'
        ]);
    }

    /* ---------- Tab 2: credit-weighted ---------- */
    function calculateWeighted() {
        var errors = [];
        var totalPoints = 0;
        var totalCredits = 0;
        var rowsUsed = 0;
        for (var i = 1; i <= 8; i++) {
            var sgpaRaw = document.getElementById('wsgpa' + i).value;
            var creditRaw = document.getElementById('wcredits' + i).value;
            var sgpaTextBlank = String(sgpaRaw).trim() === '';
            var creditTextBlank = String(creditRaw).trim() === '';
            if (sgpaTextBlank && creditTextBlank) {
                continue; // semester not entered
            }
            if (sgpaTextBlank || creditTextBlank) {
                errors.push('Semester ' + i + ': enter both the SGPA and the credits, or leave both empty.');
                continue;
            }
            var sgpa = parseSgpa(sgpaRaw, 'Semester ' + i + ' SGPA');
            var credits = parseCredits(creditRaw, 'Semester ' + i + ' credits');
            if (!sgpa.ok) {
                errors.push(sgpa.message);
            }
            if (!credits.ok) {
                errors.push(credits.message);
            }
            if (sgpa.ok && credits.ok) {
                totalPoints += sgpa.value * credits.value;
                totalCredits += credits.value;
                rowsUsed++;
            }
        }
        showErrors(document.getElementById('weightedErrors'), errors);
        if (errors.length > 0) {
            document.getElementById('weightedResult').innerHTML = '';
            return;
        }
        if (rowsUsed === 0) {
            showErrors(document.getElementById('weightedErrors'), ['Enter the SGPA and credits of at least one semester.']);
            document.getElementById('weightedResult').innerHTML = '';
            return;
        }
        var cgpa = totalPoints / totalCredits;
        document.getElementById('weightedResult').innerHTML = resultCard(cgpa, [
            'Semesters used: ' + rowsUsed + '. Total credits counted: ' + totalCredits + '.',
            'Sum of (SGPA &times; credits): ' + totalPoints.toFixed(2) + '.',
            'Credit-weighted CGPA = ' + totalPoints.toFixed(2) + ' &divide; ' + totalCredits + '.'
        ]);
    }

    function clearTab(which) {
        if (which === 'simple') {
            for (var i = 1; i <= 8; i++) {
                document.getElementById('sgpa' + i).value = '';
            }
            showErrors(document.getElementById('simpleErrors'), []);
            document.getElementById('simpleResult').innerHTML = '';
        } else {
            for (var j = 1; j <= 8; j++) {
                document.getElementById('wsgpa' + j).value = '';
                document.getElementById('wcredits' + j).value = '';
            }
            showErrors(document.getElementById('weightedErrors'), []);
            document.getElementById('weightedResult').innerHTML = '';
        }
    }

    function init() {
        var simpleForm = document.getElementById('simpleCgpaForm');
        var weightedForm = document.getElementById('weightedCgpaForm');
        if (!simpleForm || !weightedForm) {
            return;
        }
        simpleForm.addEventListener('submit', function (event) {
            event.preventDefault();
            calculateSimple();
        });
        weightedForm.addEventListener('submit', function (event) {
            event.preventDefault();
            calculateWeighted();
        });
        document.querySelectorAll('[data-cgpa-clear]').forEach(function (button) {
            button.addEventListener('click', function () {
                clearTab(button.getAttribute('data-cgpa-clear'));
            });
        });
    }

    // Exposed for testing in the browser console
    window.CBCgpa = { parseSgpa: parseSgpa, parseCredits: parseCredits };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
