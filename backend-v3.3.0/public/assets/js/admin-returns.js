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

    // ── Insight filter cards + URL synchronization ──
    const validStatusGroups = new Set(['approved', 'refund_processed', 'under_review']);
    const getStatusGroupFromUrl = () => {
        const statusGroup = new URL(window.location.href).searchParams.get('status_group');
        return validStatusGroups.has(statusGroup) ? statusGroup : null;
    };
    let activeStatusGroup = getStatusGroupFromUrl();

    const syncActiveInsightCard = () => {
        const exactStatus = ($('#returns-filters [data-filter="status"]').val() || '').trim();

        $('.return-insight-card').each(function () {
            const cardStatusGroup = $(this).attr('data-status-group') || null;
            const isActive = exactStatus === '' && cardStatusGroup === activeStatusGroup;
            const $link = $(this).find('.return-insight-card-link');

            $(this).toggleClass('active', isActive);
            $link.attr('aria-current', isActive ? 'page' : null);
        });
    };

    const updateStatusGroupUrl = (statusGroup, historyMethod = 'pushState') => {
        const url = new URL(window.location.href);

        if (statusGroup) {
            url.searchParams.set('status_group', statusGroup);
        } else {
            url.searchParams.delete('status_group');
        }

        window.history[historyMethod]({ statusGroup }, '', url);
    };

    const applyStatusGroup = (statusGroup, historyMethod = 'pushState') => {
        activeStatusGroup = validStatusGroups.has(statusGroup) ? statusGroup : null;
        $('#returns-filters [data-filter="status"]').val('');
        updateStatusGroupUrl(activeStatusGroup, historyMethod);
        syncActiveInsightCard();
        applyFilters();
    };

    syncActiveInsightCard();

    $(document).on('click', '.return-insight-card-link', function (event) {
        if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) {
            return;
        }

        event.preventDefault();

        const $card = $(this).closest('.return-insight-card');
        const selectedStatusGroup = $card.attr('data-status-group') || null;
        const historyMethod = selectedStatusGroup === activeStatusGroup ? 'replaceState' : 'pushState';

        applyStatusGroup(selectedStatusGroup, historyMethod);
    });

    window.addEventListener('popstate', function () {
        activeStatusGroup = getStatusGroupFromUrl();
        $('#returns-filters [data-filter="status"]').val('');
        syncActiveInsightCard();
        applyFilters();
    });

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

        if (activeStatusGroup && !params.status) {
            params.status_group = activeStatusGroup;
        }

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
        el.addEventListener(evt, function () {
            if (el.dataset.filter === 'status') {
                activeStatusGroup = null;
                updateStatusGroupUrl(null, 'replaceState');
                syncActiveInsightCard();
            }
            debouncedApply();
        });
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
            activeStatusGroup = null;
            updateStatusGroupUrl(null, 'replaceState');
            syncActiveInsightCard();
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
