/* global $, axios, Toast, TomSelect, base_url */
'use strict';

$(document).ready(function () {
    const table = $('#returns-table').DataTable();
    const baseUrl = table.ajax.url();
    let currentReturnId = null;

    // ── Seller picker (Tom Select on the existing /admin/sellers/search endpoint) ──
    let sellerTomSelect = null;
    const sellerSelectEl = document.getElementById('returns-seller-filter');
    if (sellerSelectEl && window.TomSelect) {
        sellerTomSelect = new TomSelect(sellerSelectEl, {
            valueField: 'value',
            labelField: 'text',
            searchField: 'text',
            maxItems: 1,
            placeholder: sellerSelectEl.getAttribute('placeholder') || 'Search seller',
            dropdownParent: 'body',
            wrapperClass: 'ts-wrapper form-select form-select-sm single has-options',
            controlClass: 'ts-control',
            load: function (query, callback) {
                if (!query.length) return callback();
                fetch(`${base_url}/admin/sellers/search?search=${encodeURIComponent(query)}`)
                    .then((r) => r.json())
                    .then((json) => callback(json))
                    .catch(() => callback());
            },
            onChange: function () { debouncedApply(); },
        });
    }

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
        // Seller picker is handled by TomSelect's own onChange — skip generic binding to avoid double-firing.
        if (el.id === 'returns-seller-filter') return;
        const evt = el.tagName === 'SELECT' ? 'change' : 'input';
        el.addEventListener(evt, debouncedApply);
    });

    const resetBtn = document.getElementById('returns-filters-reset');
    if (resetBtn) {
        resetBtn.addEventListener('click', function () {
            document.querySelectorAll('#returns-filters [data-filter]').forEach(function (el) {
                if (el.id === 'returns-seller-filter') return;
                el.value = '';
            });
            if (sellerTomSelect) {
                sellerTomSelect.clear();
                sellerTomSelect.clearOptions();
            }
            applyFilters();
        });
    }

    // ── Decision modal wiring ────────────────────────────────────────
    $('#adminAcceptModel, #adminRejectModel').on('show.bs.modal', function (event) {
        const button = $(event.relatedTarget);
        currentReturnId = button.data('id');
    }).on('hidden.bs.modal', function () {
        $('#admin_comment_accept, #admin_comment_reject').val('');
    });

    $('#adminConfirmAccept, #adminConfirmReject').on('click', function () {
        if (!currentReturnId) {
            return;
        }
        const action = this.id === 'adminConfirmAccept' ? 'approve' : 'reject';
        const note = action === 'approve'
            ? $('#admin_comment_accept').val()
            : $('#admin_comment_reject').val();

        axios.post('/admin/order-returns/' + currentReturnId + '/decision', {
            action: action,
            admin_comment: note,
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
