/* global $, axios, Toast */
'use strict';

$(document).ready(function () {
    const table = $('#returns-table').DataTable();
    const baseUrl = table.ajax.url();
    let currentReturnId = null;

    // ── Filter wiring ────────────────────────────────────────────────
    function collectFilters() {
        const params = {};
        document.querySelectorAll('#returns-filters [data-filter]').forEach(function (el) {
            const key = el.dataset.filter;
            const value = (el.value || '').trim();
            if (value !== '') {
                params[key] = value;
            }
        });
        return params;
    }

    function applyFilters() {
        const params = collectFilters();
        const query = new URLSearchParams(params).toString();
        const url = query ? baseUrl + (baseUrl.includes('?') ? '&' : '?') + query : baseUrl;
        table.ajax.url(url).load(null, false);
    }

    let debounceTimer = null;
    function debouncedApply() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(applyFilters, 250);
    }

    document.querySelectorAll('#returns-filters [data-filter]').forEach(function (el) {
        const evt = el.tagName === 'SELECT' ? 'change' : 'input';
        el.addEventListener(evt, debouncedApply);
    });

    const resetBtn = document.getElementById('returns-filters-reset');
    if (resetBtn) {
        resetBtn.addEventListener('click', function () {
            document.querySelectorAll('#returns-filters [data-filter]').forEach(function (el) {
                el.value = '';
            });
            applyFilters();
        });
    }

    // ── Decision modal wiring ─────────
    $('#acceptModel, #rejectModel, #storeConfirmModel, #storeRejectModel').on('show.bs.modal', function (event) {
        const button = $(event.relatedTarget);
        currentReturnId = button.data('id');
    }).on('hidden.bs.modal', function () {
        $('#seller_comment, #seller_comment_reject, #store_confirm_comment, #store_rejection_reason').val('');
    });

    $('#confirmAccept, #confirmReject').on('click', function () {
        if (!currentReturnId) {
            return;
        }
        const action = this.id === 'confirmAccept' ? 'approve' : 'reject';
        const note = action === 'approve'
            ? $('#seller_comment').val()
            : $('#seller_comment_reject').val();

        axios.post('/seller/order-returns/' + currentReturnId + '/decision', {
            action: action,
            seller_comment: note,
        }).then(function (response) {
            table.ajax.reload();
            const data = response.data;
            Toast.fire({
                icon: data.success === false ? 'error' : 'success',
                title: data.message,
            });
        }).catch(function (error) {
            const msg = error?.response?.data?.message || 'Error';
            Toast.fire({ icon: 'error', title: msg });
        });
    });

    $('#confirmStoreReceiptBtn').on('click', function () {
        if (!currentReturnId) {
            return;
        }
        axios.post('/seller/order-returns/' + currentReturnId + '/store-confirm', {
            action: 'approve',
        }).then(function (response) {
            table.ajax.reload();
            const data = response.data;
            Toast.fire({
                icon: data.success === false ? 'error' : 'success',
                title: data.message,
            });
        }).catch(function (error) {
            const msg = error?.response?.data?.message || 'Error';
            Toast.fire({ icon: 'error', title: msg });
        });
    });

    $('#confirmStoreRejectBtn').on('click', function (e) {
        if (!currentReturnId) {
            return;
        }
        const reason = $('#store_rejection_reason').val().trim();
        if (!reason) {
            e.preventDefault();
            Toast.fire({ icon: 'warning', title: 'Please enter a rejection reason.' });
            $('#store_rejection_reason').focus();
            return;
        }
        const actionOnRejection = $('input[name="action_on_rejection"]:checked').val() || 'no_refund_customer';

        axios.post('/seller/order-returns/' + currentReturnId + '/store-confirm', {
            action: 'reject',
            rejection_reason: reason,
            action_on_rejection: actionOnRejection,
        }).then(function (response) {
            table.ajax.reload();
            const data = response.data;
            Toast.fire({
                icon: data.success === false ? 'error' : 'success',
                title: data.message,
            });
        }).catch(function (error) {
            const msg = error?.response?.data?.message || 'Error';
            Toast.fire({ icon: 'error', title: msg });
        });
    });
});
