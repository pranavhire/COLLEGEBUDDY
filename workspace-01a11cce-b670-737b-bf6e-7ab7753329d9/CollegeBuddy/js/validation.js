/*
 * js/validation.js - client-side form checks in plain JavaScript (no libraries).
 *
 * A form with the attribute data-validate is checked before it is sent. Each field gets its rules from data-* attributes:
 *   data-label="Full name"        name used in messages
 *   data-required                 must not be empty
 *   data-email                    e-mail format
 *   data-min-length="3"           minimum characters (after trimming)
 *   data-max-length="100"         maximum characters
 *   data-name                     letters, spaces, dots, apostrophes and hyphens only
 *   data-password                 at least one letter and one number (value is NOT trimmed)
 *   data-match="password"         must equal the field whose name or id is "password"
 *   data-number                   a plain number (decimals allowed)
 *   data-min-value / data-max-value
 *   data-lte="max_price"          must not be greater than the field named max_price
 *   data-accept="pdf,doc"         allowed file extensions
 *   data-max-mb="10"              maximum file size in MB
 *   data-semester-for="year"      semester must belong to the year chosen in the field named year
 *
 * The server (PHP) checks everything again, because a browser check can always be bypassed.
 */
(function () {
    'use strict';

    var EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    var NAME_PATTERN = /^\p{L}[\p{L} .'-]*$/u;
    var NUMBER_PATTERN = /^\d+(\.\d+)?$/;

    function labelOf(field) {
        return field.getAttribute('data-label') || field.name || 'This field';
    }

    function intAttr(field, name) {
        var v = field.getAttribute(name);
        return v === null || v === '' ? null : parseInt(v, 10);
    }

    /* The message box that follows a field. It is created if the page did not include one. */
    function feedbackFor(field) {
        var holder = field.closest('.cb-password') || field;
        var container = holder.parentElement;
        var box = container ? container.querySelector(':scope > .invalid-feedback') : null;
        if (!box) {
            box = document.createElement('div');
            box.className = 'invalid-feedback';
            holder.insertAdjacentElement('afterend', box);
        }
        return box;
    }

    /* Shows (message) or clears ('') the error for one field. Returns true when the field is valid. */
    function setError(field, message) {
        var box = feedbackFor(field);
        if (message) {
            field.classList.add('is-invalid');
            field.setAttribute('aria-invalid', 'true');
            box.textContent = message;
            box.classList.add('d-block');
            return false;
        }
        field.classList.remove('is-invalid');
        field.removeAttribute('aria-invalid');
        box.textContent = '';
        box.classList.remove('d-block');
        return true;
    }

    function findField(form, key) {
        if (!form || !key) {
            return null;
        }
        return form.elements.namedItem(key) || document.getElementById(key);
    }

    /* Checks one field against its rules. */
    function validateField(field) {
        var form = field.form;
        var label = labelOf(field);

        if (field.disabled || field.type === 'hidden' || field.type === 'submit' || field.type === 'button') {
            return true;
        }

        // Files
        if (field.type === 'file') {
            var file = field.files && field.files[0];
            if (!file) {
                return field.hasAttribute('data-required') ? setError(field, label + ' is required.') : setError(field, '');
            }
            var accept = (field.getAttribute('data-accept') || '').toLowerCase().split(',').map(function (s) { return s.trim(); }).filter(Boolean);
            var ext = file.name.indexOf('.') > -1 ? file.name.split('.').pop().toLowerCase() : '';
            if (accept.length && accept.indexOf(ext) === -1) {
                return setError(field, 'Only ' + accept.join(', ').toUpperCase() + ' files are allowed.');
            }
            var maxMb = parseFloat(field.getAttribute('data-max-mb'));
            if (maxMb > 0 && file.size > maxMb * 1024 * 1024) {
                return setError(field, label + ' must be ' + maxMb + ' MB or smaller.');
            }
            return setError(field, '');
        }

        var raw = field.value;                 // passwords keep their spaces, as on the server
        var value = raw.trim();

        // Required
        if (field.hasAttribute('data-required')) {
            var empty = field.hasAttribute('data-password') ? raw === '' : value === '';
            if (empty) {
                return setError(field, label + ' is required.');
            }
        } else if (field.hasAttribute('data-password') ? raw === '' : value === '') {
            return setError(field, '');          // optional and empty: nothing else to check
        }

        // E-mail
        if (field.hasAttribute('data-email')) {
            if (!EMAIL_PATTERN.test(value) || value.length > 150) {
                return setError(field, 'Please enter a valid email address.');
            }
        }

        // Length (password uses the raw value, other fields the trimmed value)
        var text = field.hasAttribute('data-password') ? raw : value;
        var min = intAttr(field, 'data-min-length');
        var max = intAttr(field, 'data-max-length');
        if (min !== null && text.length < min) {
            return setError(field, label + ' must be at least ' + min + ' characters.');
        }
        if (max !== null && text.length > max) {
            return setError(field, label + ' cannot be more than ' + max + ' characters.');
        }

        // Name
        if (field.hasAttribute('data-name') && !NAME_PATTERN.test(value)) {
            return setError(field, 'Name can contain only letters, spaces, dots, apostrophes and hyphens.');
        }

        // Password letters and numbers
        if (field.hasAttribute('data-password') && (!/[A-Za-z]/.test(raw) || !/\d/.test(raw))) {
            return setError(field, 'Password must include at least one letter and one number.');
        }

        // Must match another field (confirm password)
        if (field.hasAttribute('data-match')) {
            var other = findField(form, field.getAttribute('data-match'));
            if (other && other.value !== raw) {
                return setError(field, field.getAttribute('data-match-label') || label + ' does not match.');
            }
        }

        // Numbers and ranges
        if (field.hasAttribute('data-number') || field.type === 'number') {
            if (!NUMBER_PATTERN.test(value)) {
                return setError(field, label + ' must be a number.');
            }
            var n = parseFloat(value);
            var minValue = parseFloat(field.getAttribute('data-min-value'));
            var maxValue = parseFloat(field.getAttribute('data-max-value'));
            if (!isNaN(minValue) && n < minValue) {
                return setError(field, label + ' cannot be less than ' + minValue + '.');
            }
            if (!isNaN(maxValue) && n > maxValue) {
                return setError(field, label + ' cannot be more than ' + maxValue + '.');
            }
            var lte = findField(form, field.getAttribute('data-lte'));
            if (lte && lte.value.trim() !== '' && n > parseFloat(lte.value)) {
                return setError(field, field.getAttribute('data-lte-label') || label + ' cannot be more than ' + labelOf(lte).toLowerCase() + '.');
            }
        }

        // Semester must belong to the chosen year (1-2 = year 1, 3-4 = year 2, ...)
        if (field.hasAttribute('data-semester-for')) {
            var yearField = findField(form, field.getAttribute('data-semester-for'));
            if (yearField && yearField.value !== '' && value !== '') {
                if (Math.ceil(parseInt(value, 10) / 2) !== parseInt(yearField.value, 10)) {
                    return setError(field, 'This semester does not belong to the selected year. Semesters 1-2 are Year 1, 3-4 Year 2, 5-6 Year 3 and 7-8 Year 4.');
                }
            }
        }

        return setError(field, '');
    }

    /* Checks every field in a form. Focuses the first invalid field. */
    function validateForm(form) {
        var first = null;
        var valid = true;
        form.querySelectorAll('input, select, textarea').forEach(function (field) {
            if (!validateField(field)) {
                valid = false;
                if (!first) {
                    first = field;
                }
            }
        });
        if (first) {
            first.focus();
        }
        return valid;
    }

    /* Password strength meter (for fields with data-strength="#elementId") */
    function updateStrength(input, root) {
        var v = input.value;
        var bar = root.querySelector('.cb-strength-bar span');
        var hint = root.querySelector('small');
        var score = 0;
        if (v.length >= 8) { score++; }
        if (v.length >= 12) { score++; }
        if (/[a-z]/.test(v) && /[A-Z]/.test(v)) { score++; }
        if (/\d/.test(v)) { score++; }
        if (/[^A-Za-z0-9]/.test(v)) { score++; }

        var level = 0;
        var word = '';
        if (v.length > 0) {
            level = score <= 1 ? 1 : score === 2 ? 2 : score === 3 ? 3 : 4;
            word = ['', 'Weak', 'Fair', 'Good', 'Strong'][level];
        }
        root.setAttribute('data-level', String(level));
        if (bar) {
            bar.style.width = (level * 25) + '%';
        }
        if (hint) {
            hint.textContent = (word ? 'Strength: ' + word + '. ' : '') + 'At least 8 characters, with a letter and a number.';
        }
    }

    function init() {
        document.querySelectorAll('form[data-validate]').forEach(function (form) {
            form.addEventListener('submit', function (event) {
                if (!validateForm(form)) {
                    event.preventDefault();
                }
            });

            form.querySelectorAll('input, select, textarea').forEach(function (field) {
                // Check a field after the user has changed it (not on first visit, so server messages stay visible)
                field.addEventListener('input', function () {
                    field.dataset.dirty = '1';
                    if (field.classList.contains('is-invalid')) {
                        validateField(field);
                    }
                });
                field.addEventListener('blur', function () {
                    if (field.dataset.dirty === '1') {
                        validateField(field);
                    }
                });
                field.addEventListener('change', function () {
                    field.dataset.dirty = '1';
                    validateField(field);
                    // If the year changed, re-check the semester too
                    var dependent = form.querySelector('[data-semester-for="' + field.name + '"]');
                    if (dependent && dependent.value !== '') {
                        dependent.dataset.dirty = '1';
                        validateField(dependent);
                    }
                });
            });
        });

        document.querySelectorAll('[data-strength]').forEach(function (input) {
            var root = document.querySelector(input.getAttribute('data-strength'));
            if (!root) {
                return;
            }
            input.addEventListener('input', function () { updateStrength(input, root); });
            updateStrength(input, root);
        });
    }

    // Exposed for testing in the browser console
    window.CBValidation = { validateField: validateField, validateForm: validateForm };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
