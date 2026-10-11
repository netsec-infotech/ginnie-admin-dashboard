/* global axios, Toast, TomSelect */
'use strict';

document.addEventListener('DOMContentLoaded', function () {
    let currentReturnId = null;

    // ── Manual rider assignment ──────────────────────────────────────
    (function initAssignRider() {
        const modalEl = document.getElementById('assignRiderModal');
        if (!modalEl) return;

        const assignUrl = modalEl.dataset.assignUrl;
        const searchUrl = modalEl.dataset.searchUrl;
        const zoneId = modalEl.dataset.zoneId || '';
        const currentRiderId = modalEl.dataset.currentRiderId || '';
        const currentRiderName = modalEl.dataset.currentRiderName || '';

        const selectEl = modalEl.querySelector('#assignRiderDeliveryBoyId');
        const allZonesEl = modalEl.querySelector('#assignRiderAllZones');
        const reasonEl = modalEl.querySelector('#assignRiderReason');
        const form = modalEl.querySelector('#assignRiderForm');
        const submitBtn = modalEl.querySelector('#assignRiderSubmit');
        if (!selectEl || !form || !window.TomSelect) return;

        const getTomSelect = () => {
            if (selectEl.tomselect) return selectEl.tomselect;
            return new TomSelect(selectEl, {
                valueField: 'value',
                labelField: 'text',
                searchField: 'text',
                maxItems: 1,
                placeholder: selectEl.getAttribute('placeholder') || 'Search rider…',
                dropdownParent: 'body',
                load: function (query, callback) {
                    if (!searchUrl) return callback();
                    const url = new URL(searchUrl, window.location.origin);
                    if (query) url.searchParams.set('search', query);
                    url.searchParams.set('available', '1');
                    // Scope to the store's zone unless "all zones" is checked.
                    if (zoneId && !(allZonesEl && allZonesEl.checked)) {
                        url.searchParams.set('delivery_zone_id', zoneId);
                    }
                    fetch(url.toString(), { headers: { Accept: 'application/json' } })
                        .then((r) => r.json())
                        .then((rows) => callback(Array.isArray(rows) ? rows : []))
                        .catch(() => callback());
                },
            });
        };

        modalEl.addEventListener('show.bs.modal', function () {
            const ts = getTomSelect();
            ts.clearOptions();
            ts.clear();
            if (currentRiderId) {
                ts.addOption({ value: currentRiderId, text: currentRiderName });
                ts.addItem(currentRiderId, true); // silent preselect
            }
            if (reasonEl) reasonEl.value = '';
        });

        // Re-run search when the zone scope toggles.
        if (allZonesEl) {
            allZonesEl.addEventListener('change', function () {
                const ts = selectEl.tomselect;
                if (ts) {
                    ts.clearOptions();
                    ts.load(ts.lastValue || '');
                }
            });
        }

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            if (!assignUrl) return;
            const raw = (selectEl.value || '').trim();
            submitBtn && (submitBtn.disabled = true);
            axios.post(assignUrl, {
                delivery_boy_id: raw === '' ? null : parseInt(raw, 10),
                reason: (reasonEl?.value || '').trim(),
            }).then(function (response) {
                const data = response.data;
                Toast.fire({ icon: data.success === false ? 'error' : 'success', title: data.message });
                if (data.success !== false) {
                    setTimeout(function () { window.location.reload(); }, 1000);
                }
            }).catch(function (error) {
                const msg = error?.response?.data?.message || 'Error';
                Toast.fire({ icon: 'error', title: msg });
            }).finally(function () {
                submitBtn && (submitBtn.disabled = false);
            });
        });
    })();

    const acceptModalEl = document.getElementById('adminAcceptModel');
    const rejectModalEl = document.getElementById('adminRejectModel');

    [acceptModalEl, rejectModalEl].forEach(function (modalEl) {
        if (!modalEl) {
            return;
        }
        modalEl.addEventListener('show.bs.modal', function (event) {
            currentReturnId = event.relatedTarget?.dataset?.id || null;
        });
        modalEl.addEventListener('hidden.bs.modal', function () {
            modalEl.querySelectorAll('textarea').forEach(function (field) {
                field.value = '';
            });
        });
    });

    function submitDecision(action, comment) {
        if (!currentReturnId) {
            return;
        }
        axios.post('/admin/order-returns/' + currentReturnId + '/decision', {
            action: action,
            admin_comment: comment,
        }).then(function (response) {
            const data = response.data;
            Toast.fire({
                icon: data.success === false ? 'error' : 'success',
                title: data.message,
            });
            if (data.success !== false) {
                setTimeout(function () { window.location.reload(); }, 1000);
            }
        }).catch(function (error) {
            const msg = error?.response?.data?.message || 'Error';
            Toast.fire({ icon: 'error', title: msg });
        });
    }

    const confirmAccept = document.getElementById('adminConfirmAccept');
    if (confirmAccept) {
        confirmAccept.addEventListener('click', function () {
            submitDecision('approve', document.getElementById('admin_comment_accept')?.value || '');
        });
    }

    const confirmReject = document.getElementById('adminConfirmReject');
    if (confirmReject) {
        confirmReject.addEventListener('click', function () {
            submitDecision('reject', document.getElementById('admin_comment_reject')?.value || '');
        });
    }
});
