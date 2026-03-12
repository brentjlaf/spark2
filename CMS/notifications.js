// File: notifications.js
(function (window, document) {
    'use strict';

    var STORAGE_KEY = 'sparkcms.admin.toast';
    var DEFAULT_DURATION = 6000;

    function normalizeMessage(value) {
        if (value === null || value === undefined) {
            return '';
        }
        var container = document.createElement('div');
        container.innerHTML = String(value);
        var text = container.textContent || container.innerText || '';
        return text.trim();
    }

    function ensureContainer() {
        var container = document.getElementById('admin-toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'admin-toast-container';
            container.className = 'admin-toast-container';
            container.setAttribute('aria-live', 'polite');
            container.setAttribute('aria-atomic', 'false');
            document.body.appendChild(container);
        }
        return container;
    }

    function removeToast(toast) {
        if (!toast) {
            return;
        }
        toast.classList.remove('is-visible');
        var timeoutId = toast.getAttribute('data-timeout-id');
        if (timeoutId) {
            window.clearTimeout(Number(timeoutId));
        }
        var handle = function () {
            if (toast && toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        };
        toast.addEventListener('transitionend', handle, { once: true });
        // Fallback in case the browser does not fire transitionend
        window.setTimeout(handle, 400);
    }

    function buildToast(message, options) {
        var settings = options || {};
        var type = settings.type || 'info';
        var role = (type === 'error' || type === 'warning') ? 'alert' : 'status';

        var toast = document.createElement('div');
        toast.className = 'admin-toast admin-toast--' + type;
        toast.setAttribute('role', role);
        toast.setAttribute('tabindex', '-1');

        var text = document.createElement('div');
        text.className = 'admin-toast__message';
        text.textContent = normalizeMessage(message);
        toast.appendChild(text);

        var close = document.createElement('button');
        close.type = 'button';
        close.className = 'admin-toast__close';
        close.setAttribute('aria-label', 'Dismiss notification');
        close.innerHTML = '&times;';
        close.addEventListener('click', function () {
            removeToast(toast);
        });
        toast.appendChild(close);

        return toast;
    }

    function showToast(message, options) {
        var normalizedMessage = normalizeMessage(message);
        if (!normalizedMessage) {
            return null;
        }
        var container = ensureContainer();
        var toast = buildToast(normalizedMessage, options);
        container.appendChild(toast);

        // Allow the element to render before animating
        window.requestAnimationFrame(function () {
            toast.classList.add('is-visible');
            if (typeof toast.focus === 'function') {
                try {
                    toast.focus({ preventScroll: true });
                } catch (error) {
                    toast.focus();
                }
            }
        });

        var duration = DEFAULT_DURATION;
        if (options && typeof options.duration === 'number') {
            duration = options.duration;
        }
        if (duration !== 0) {
            var timeoutId = window.setTimeout(function () {
                removeToast(toast);
            }, Math.max(1000, duration));
            toast.setAttribute('data-timeout-id', String(timeoutId));
        }
        return toast;
    }

    function storeToast(type, message) {
        var normalizedMessage = normalizeMessage(message);
        if (!normalizedMessage) {
            return;
        }
        try {
            var payload = JSON.stringify({ type: type || 'info', message: normalizedMessage });
            window.sessionStorage.setItem(STORAGE_KEY, payload);
        } catch (error) {
            // Ignore storage errors (e.g., disabled cookies)
        }
    }

    function showStoredToast() {
        var raw;
        try {
            raw = window.sessionStorage.getItem(STORAGE_KEY);
            if (!raw) {
                return;
            }
            window.sessionStorage.removeItem(STORAGE_KEY);
        } catch (error) {
            return;
        }
        try {
            var parsed = JSON.parse(raw);
            if (parsed && parsed.message) {
                showToast(parsed.message, { type: parsed.type || 'info' });
            }
        } catch (error) {
            // Ignore JSON parse errors
        }
    }

    var api = {
        showToast: function (message, options) {
            return showToast(message, options || {});
        },
        showSuccessToast: function (message, options) {
            var settings = options || {};
            settings.type = 'success';
            return showToast(message, settings);
        },
        showErrorToast: function (message, options) {
            var settings = options || {};
            settings.type = 'error';
            if (typeof settings.duration !== 'number') {
                settings.duration = 8000;
            }
            return showToast(message, settings);
        },
        showInfoToast: function (message, options) {
            var settings = options || {};
            settings.type = 'info';
            return showToast(message, settings);
        },
        rememberToast: function (type, message) {
            storeToast(type, message);
        }
    };

    window.AdminNotifications = api;

    document.addEventListener('DOMContentLoaded', showStoredToast, { once: true });
})(window, document);
