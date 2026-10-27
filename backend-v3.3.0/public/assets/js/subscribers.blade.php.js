$(document).ready(function () {

    const validStatusGroups = new Set(['active', 'cancelled', 'pending']);
    const getStatusGroupFromUrl = () => {
        const statusGroup = new URL(window.location.href).searchParams.get('status_group');
        return validStatusGroups.has(statusGroup) ? statusGroup : null;
    };
    let activeStatusGroup = getStatusGroupFromUrl();

    // Prefill from URL params if provided
    try {
        const params = new URLSearchParams(window.location.search);
        const pid = params.get('plan_id');
        // const sid = params.get('seller_id');
        const st = params.get('status');
        if (pid && $('#planFilter').length) {
            $('#planFilter').val(pid);
        }
        if (st && $('#statusFilter').length) {
            $('#statusFilter').val(st);
        }
        // For seller, if option exists (server prefilled), TomSelect will pick it
    } catch (e) { console.error(e); }

    const table = $('#subscription-subscribers-table').DataTable();

    const syncActiveInsightCard = () => {
        $('.subscriber-insight-card').each(function () {
            const cardStatusGroup = $(this).attr('data-status-group') || null;
            const isActive = cardStatusGroup === activeStatusGroup;
            const $link = $(this).find('.subscriber-insight-card-link');

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
        $('#statusFilter').val('');
        updateStatusGroupUrl(activeStatusGroup, historyMethod);
        syncActiveInsightCard();
        table.ajax.reload(null, false);
    };

    syncActiveInsightCard();

    $(document).on('click', '.subscriber-insight-card-link', function (event) {
        if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) {
            return;
        }
        event.preventDefault();
        const $card = $(this).closest('.subscriber-insight-card');
        const selectedStatusGroup = $card.attr('data-status-group') || null;
        const historyMethod = selectedStatusGroup === activeStatusGroup ? 'replaceState' : 'pushState';
        applyStatusGroup(selectedStatusGroup, historyMethod);
    });

    window.addEventListener('popstate', function () {
        activeStatusGroup = getStatusGroupFromUrl();
        syncActiveInsightCard();
        table.ajax.reload(null, false);
    });

    // Hook preXhr to append filters
    table.on('preXhr.dt', function (e, settings, data) {
        data.plan_id = $('#planFilter').val();
        data.seller_id = $('#select-seller').val();
        data.status = $('#statusFilter').val();
        if (!data.status && activeStatusGroup) {
            data.status_group = activeStatusGroup;
        }
    });

    // Reload table when any filter changes, no page refresh
    $('#statusFilter').on('change', function () {
        if ($(this).val()) {
            activeStatusGroup = null;
            updateStatusGroupUrl(null, 'replaceState');
            syncActiveInsightCard();
        }
        table.ajax.reload(null, false);
    });

    $('#planFilter, #select-seller').on('change', function () {
        table.ajax.reload(null, false);
    });

    $('#refresh').on('click', function () {
        table.ajax.reload(null, false);
    });
});
