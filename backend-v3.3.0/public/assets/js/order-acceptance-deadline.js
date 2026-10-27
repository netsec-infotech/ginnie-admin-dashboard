'use strict';

document.addEventListener('DOMContentLoaded', function () {
    const reloadThrottleMs = 30000;
    let lastReloadAt = 0;
    let reloadTimer = null;

    const formatRemaining = function (seconds) {
        const minutes = Math.floor(seconds / 60);
        const remainingSeconds = seconds % 60;

        if (minutes >= 60) {
            const hours = Math.floor(minutes / 60);
            const tailMinutes = minutes % 60;
            return tailMinutes > 0 ? `${hours}h ${tailMinutes}m` : `${hours}h`;
        }

        return `${minutes}m ${String(remainingSeconds).padStart(2, '0')}s`;
    };

    const reloadOrderDatatables = function () {
        const now = Date.now();
        if (now - lastReloadAt < reloadThrottleMs) {
            return;
        }

        lastReloadAt = now;

        if (window.DatatableUtils && typeof window.DatatableUtils.refreshDatatable === 'function') {
            ['orders-list-table', 'orders-table'].forEach(function (tableId) {
                const table = document.getElementById(tableId);
                if (table) {
                    window.DatatableUtils.refreshDatatable(tableId);
                }
            });
        } else if (window.jQuery && window.jQuery.fn && window.jQuery.fn.DataTable) {
            ['#orders-list-table', '#orders-table'].forEach(function (selector) {
                if (window.jQuery.fn.DataTable.isDataTable(selector)) {
                    window.jQuery(selector).DataTable().ajax.reload(null, false);
                }
            });
        }
    };

    const scheduleReloadAfterExpiry = function () {
        if (reloadTimer) {
            return;
        }

        // Give the backend scheduler/accept guard a brief moment to commit the
        // rejected status, then redraw the visible rows without a full page load.
        reloadTimer = window.setTimeout(function () {
            reloadTimer = null;
            reloadOrderDatatables();
        }, 2500);
    };

    const updateBadge = function (badge) {
        const deadline = new Date(badge.dataset.acceptanceDeadline);
        const seconds = Math.max(0, Math.floor((deadline.getTime() - Date.now()) / 1000));
        const wasExpired = badge.dataset.acceptanceExpired === 'true';
        const label = badge.querySelector('.order-acceptance-deadline__label');
        const time = badge.querySelector('.order-acceptance-deadline__time');
        const isExpired = seconds <= 0;

        badge.dataset.acceptanceExpired = isExpired ? 'true' : 'false';
        badge.classList.toggle('is-expired', isExpired);
        badge.classList.toggle('is-urgent', seconds > 0 && seconds <= 180);

        if (label) {
            label.textContent = isExpired ? badge.dataset.expiredLabel : badge.dataset.activeLabel;
        }

        if (time) {
            time.textContent = isExpired ? badge.dataset.expiredText : formatRemaining(seconds);
        }

        if (isExpired && !wasExpired) {
            scheduleReloadAfterExpiry();
        }
    };

    const updateAllBadges = function () {
        Array.from(document.querySelectorAll('[data-acceptance-deadline]')).forEach(updateBadge);
    };

    updateAllBadges();
    window.setInterval(function () {
        updateAllBadges();
    }, 1000);

    if (window.jQuery) {
        window.jQuery(document).on('draw.dt', updateAllBadges);
    }
});
