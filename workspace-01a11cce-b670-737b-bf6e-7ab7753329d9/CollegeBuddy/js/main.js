/*
 * js/main.js - shared interface behaviour (plain JavaScript).
 *   1. Flash messages close themselves after a few seconds.
 *   2. Show / hide password buttons.
 *   3. "Fill demo account" buttons on the login page.
 *   4. Confirmation dialog before delete actions (forms with data-confirm="...").
 *   5. Filter forms submit as soon as a dropdown changes (data-autosubmit).
 *   6. Character counters for text areas (data-counter="max").
 *   7. Photo preview before upload (data-preview="#imageId").
 *   8. Mobile menu closes after choosing a link.
 */
(function () {
    'use strict';

    var hasBootstrap = typeof window.bootstrap !== 'undefined';

    /* 1. Flash messages */
    document.querySelectorAll('.alert[data-autohide]').forEach(function (alert) {
        var delay = parseInt(alert.getAttribute('data-autohide'), 10) || 6000;
        window.setTimeout(function () {
            if (hasBootstrap && window.bootstrap.Alert) {
                window.bootstrap.Alert.getOrCreateInstance(alert).close();
            } else {
                alert.remove();
            }
        }, delay);
    });

    /* 2. Show / hide password */
    document.querySelectorAll('[data-toggle-password]').forEach(function (button) {
        button.addEventListener('click', function () {
            var input = document.querySelector(button.getAttribute('data-toggle-password'));
            if (!input) {
                return;
            }
            var showing = input.type === 'password';
            input.type = showing ? 'text' : 'password';
            button.setAttribute('aria-label', showing ? 'Hide password' : 'Show password');
            var icon = button.querySelector('i');
            if (icon) {
                icon.className = showing ? 'bi bi-eye-slash' : 'bi bi-eye';
            }
        });
    });

    /* 3. Fill demo account */
    document.querySelectorAll('[data-fill-form]').forEach(function (button) {
        button.addEventListener('click', function () {
            var form = document.querySelector(button.getAttribute('data-fill-form'));
            if (!form) {
                return;
            }
            var email = form.querySelector('[name="email"]');
            var password = form.querySelector('[name="password"]');
            if (email) {
                email.value = button.getAttribute('data-fill-email') || '';
                email.classList.remove('is-invalid');
                email.focus();
            }
            if (password) {
                password.value = button.getAttribute('data-fill-password') || '';
                password.classList.remove('is-invalid');
            }
        });
    });

    /* 4. Confirmation before delete / remove actions */
    var confirmModal = document.getElementById('cbConfirmModal');
    var confirmText = document.getElementById('cbConfirmText');
    var confirmYes = document.getElementById('cbConfirmYes');
    var modal = (hasBootstrap && confirmModal) ? window.bootstrap.Modal.getOrCreateInstance(confirmModal) : null;
    var pendingForm = null;

    document.addEventListener('submit', function (event) {
        var form = event.target;
        var message = form && form.getAttribute ? form.getAttribute('data-confirm') : null;
        if (!message) {
            return;
        }
        if (form === pendingForm) {
            return; // the user has already confirmed this submission
        }
        if (modal && confirmText && confirmYes) {
            event.preventDefault();
            pendingForm = form;
            confirmText.textContent = message;
            modal.show();
        } else if (!window.confirm(message)) {
            event.preventDefault();
        }
    });

    if (confirmYes) {
        confirmYes.addEventListener('click', function () {
            var form = pendingForm;
            pendingForm = null;
            if (modal) {
                modal.hide();
            }
            if (form) {
                form.submit(); // submit() does not fire the submit event again, so there is no loop
            }
        });
    }

    /* 5. Auto-submit filters */
    document.querySelectorAll('form[data-autosubmit]').forEach(function (form) {
        form.querySelectorAll('select').forEach(function (select) {
            select.addEventListener('change', function () {
                if (typeof form.requestSubmit === 'function') {
                    form.requestSubmit();
                } else {
                    form.submit();
                }
            });
        });
    });

    /* 6. Text area counters */
    document.querySelectorAll('textarea[data-counter]').forEach(function (area) {
        var max = parseInt(area.getAttribute('data-counter'), 10) || area.maxLength;
        var counter = document.createElement('div');
        counter.className = 'cb-counter';
        counter.setAttribute('aria-live', 'off');
        area.insertAdjacentElement('afterend', counter);
        var update = function () {
            counter.textContent = area.value.length + ' / ' + max + ' characters';
        };
        area.addEventListener('input', update);
        update();
    });

    /* 7. Photo preview */
    document.querySelectorAll('input[type="file"][data-preview]').forEach(function (input) {
        var preview = document.querySelector(input.getAttribute('data-preview'));
        if (!preview) {
            return;
        }
        var original = preview.getAttribute('src');
        input.addEventListener('change', function () {
            var file = input.files && input.files[0];
            if (file && /^image\//.test(file.type) && window.URL && URL.createObjectURL) {
                preview.src = URL.createObjectURL(file);
            } else {
                preview.src = original;
            }
        });
    });

    /* 7b. "Go back" buttons (used on the friendly error pages) */
    document.querySelectorAll('[data-back]').forEach(function (button) {
        button.addEventListener('click', function () {
            if (window.history.length > 1) {
                window.history.back();
            } else {
                window.location.href = button.getAttribute('data-back-fallback') || 'index.php';
            }
        });
    });

    /* 8. Close the mobile menu after a choice */
    var menu = document.getElementById('cbNavMenu');
    if (menu && hasBootstrap) {
        menu.querySelectorAll('.nav-link, .dropdown-item').forEach(function (link) {
            link.addEventListener('click', function () {
                if (menu.classList.contains('show')) {
                    window.bootstrap.Collapse.getOrCreateInstance(menu).hide();
                }
            });
        });
    }
})();
