$(document).ready(function () {

    const table = $('#orders-table').DataTable();
    const ordersListTable = $('#orders-list-table').length ? $('#orders-list-table').DataTable() : null;
    let currentOrderId = null;
    let zoneTomSelect = null;
    let storeTomSelect = null;

    const updateOrderCount = () => {
        if (table.page.info() !== undefined) {
            const totalRecords = table.page.info().recordsTotal;
            $('.order-count').html("(" + totalRecords + (totalRecords > 1 ? ' Order Items' : ' Order Item') + ")");
        }
    };

    const reloadActiveTable = () => {
        const activeIsOrders = $('#orders-pane').hasClass('active');
        if (activeIsOrders && ordersListTable) {
            ordersListTable.ajax.reload(null, false);
        } else {
            table.ajax.reload(updateOrderCount, false);
        }
    };

    // Pre-select order type filter from URL ?type= param
    const urlType = new URLSearchParams(window.location.search).get('type');
    if (urlType && $('#orderTypeFilter').length) {
        $('#orderTypeFilter').val(urlType);
    }

    // ── Zone TomSelect (server-side search) ──
    const zoneConfig = window.__ORDER_ZONE_CONFIG__ || {};
    const zoneSelectEl = document.getElementById('zoneFilter');

    if (zoneSelectEl && window.TomSelect && zoneConfig.searchUrl) {
        zoneTomSelect = new TomSelect(zoneSelectEl, {
            copyClassesToDropdown: false,
            dropdownParent: 'body',
            valueField: 'value',
            labelField: 'text',
            searchField: 'text',
            placeholder: zoneSelectEl.getAttribute('placeholder') || '',
            preload: true,
            allowEmptyOption: true,
            load: function (query, callback) {
                var url = new URL(zoneConfig.searchUrl, window.location.origin);
                if (query) url.searchParams.set('q', query);
                axios.get(url.toString())
                    .then(function (r) { callback(Array.isArray(r.data) ? r.data : []); })
                    .catch(function () { callback(); });
            },
            onChange: function () {
                reloadActiveTable();
            },
        });

        // Pre-select zone from URL ?zone_id= param
        if (zoneConfig.initialZoneId) {
            var findUrl = new URL(zoneConfig.searchUrl, window.location.origin);
            findUrl.searchParams.set('find_id', zoneConfig.initialZoneId);
            axios.get(findUrl.toString()).then(function (r) {
                if (Array.isArray(r.data) && r.data.length > 0) {
                    zoneTomSelect.addOption(r.data[0]);
                    zoneTomSelect.setValue(r.data[0].value, true);
                }
            });
        }
    }

    // ── Store TomSelect (server-side search, seller panel) ──
    const storeConfig = window.__ORDER_STORE_CONFIG__ || {};
    const storeSelectEl = document.getElementById('storeFilter');

    if (storeSelectEl && window.TomSelect && storeConfig.searchUrl) {
        storeTomSelect = new TomSelect(storeSelectEl, {
            copyClassesToDropdown: false,
            dropdownParent: 'body',
            valueField: 'value',
            labelField: 'text',
            searchField: 'text',
            placeholder: storeSelectEl.getAttribute('placeholder') || '',
            preload: true,
            allowEmptyOption: true,
            load: function (query, callback) {
                var url = new URL(storeConfig.searchUrl, window.location.origin);
                if (query) url.searchParams.set('q', query);
                axios.get(url.toString())
                    .then(function (r) { callback(Array.isArray(r.data) ? r.data : []); })
                    .catch(function () { callback(); });
            },
            onChange: function () {
                reloadActiveTable();
            },
        });

        if (storeConfig.initialStoreId) {
            var stFindUrl = new URL(storeConfig.searchUrl, window.location.origin);
            stFindUrl.searchParams.set('find_id', storeConfig.initialStoreId);
            axios.get(stFindUrl.toString()).then(function (r) {
                if (Array.isArray(r.data) && r.data.length > 0) {
                    storeTomSelect.addOption(r.data[0]);
                    storeTomSelect.setValue(r.data[0].value, true);
                }
            });
        }
    }

    // Reload whichever tab is active when filters change
    $('#rangeFilter, #statusFilter, #paymentFilter, #orderTypeFilter').on('change', reloadActiveTable);
    $('#refresh').on('click', reloadActiveTable);

    // Tab switch — recalc DataTables column widths so the table renders cleanly
    // (server-side tables in initially-hidden tab-panes lose their column sizing).
    $('#orders-tab a[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
        const target = $(e.target).attr('href');
        if (target === '#orders-pane' && ordersListTable) {
            ordersListTable.columns.adjust().draw(false);
        } else if (target === '#order-items-pane') {
            table.columns.adjust().draw(false);
        }
    });

    setTimeout(function () {
        updateOrderCount();
    }, 1000)

    // Add filter params to BOTH datatable AJAX requests so a single set of
    // filter <select>s applies to whichever tab is currently visible.
    $('#orders-table, #orders-list-table').on('preXhr.dt', function (e, settings, data) {
        data.range = $('#rangeFilter').val();
        data.status = $('#statusFilter').val();
        data.payment_type = $('#paymentFilter').val();
        data.order_type = $('#orderTypeFilter').val();
        data.delivery_zone_id = zoneTomSelect ? zoneTomSelect.getValue() : '';
        data.store_id = storeTomSelect ? storeTomSelect.getValue() : '';
    });

    // ===== Orders tab: expand to show items =====
    (function initOrdersExpand() {
        if (!ordersListTable) return;
        const meta = document.getElementById('order-items-meta');
        if (!meta) return;

        let labels = {};
        try { labels = JSON.parse(meta.dataset.itemsLabels || '{}'); } catch (e) { labels = {}; }
        const urlTemplate = meta.dataset.itemsUrl || '';

        const setChevron = ($btn, open) => {
            const $icon = $btn.find('i');
            $icon.removeClass('ti-chevron-right ti-chevron-down')
                .addClass(open ? 'ti-chevron-down' : 'ti-chevron-right');
        };

        const wrap = (inner) => `<div class="p-3 border-top">${inner}</div>`;
        const escapeHtml = (val) => {
            if (val === null || val === undefined) return '';
            return String(val)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        };

        $('#orders-list-table tbody').on('click', '.expand-order-items', function (e) {
            e.stopPropagation();
            const $btn = $(this);
            const $tr = $btn.closest('tr');
            const row = ordersListTable.row($tr);
            if (!row || !row.node()) return;

            if (row.child.isShown()) {
                row.child.hide();
                $tr.removeClass('shown');
                setChevron($btn, false);
                return;
            }

            const orderId = $btn.data('orderId');
            if (!orderId || !urlTemplate) return;

            row.child(wrap(`<div class="d-flex align-items-center justify-content-center py-4 text-secondary"><div class="spinner-border spinner-border-sm me-2" role="status"></div>${escapeHtml(labels.loading || 'Loading...')}</div>`)).show();
            $tr.addClass('shown');
            setChevron($btn, true);

            const url = urlTemplate.replace('__ID__', encodeURIComponent(orderId));
            axios.get(url)
                .then((response) => {
                    const html = response?.data?.data?.html;
                    if (html) {
                        row.child(html).show();
                    } else {
                        row.child(wrap(`<div class="text-secondary">${escapeHtml(labels.error || 'Failed to load items.')}</div>`)).show();
                    }
                    $tr.addClass('shown');
                    if (typeof refreshFsLightbox !== 'undefined') refreshFsLightbox();
                })
                .catch(() => {
                    row.child(wrap(`<div class="text-danger">${escapeHtml(labels.error || 'Failed to load items.')}</div>`)).show();
                    $tr.addClass('shown');
                });
        });
    })();

    // Capture order ID when accept/reject/preparing buttons are clicked
    $('#acceptModel, #rejectModel, #preparingModel').on('show.bs.modal', function (event) {
        const button = $(event.relatedTarget);
        currentOrderId = button.data('id');
    });

    // Helper to get orders endpoint prefix (seller/orders vs seller/scheduled-orders)
    function getOrderActionPrefix() {
        return window.location.pathname.includes('scheduled-orders') ? '/seller/scheduled-orders' : '/seller/orders';
    }

    // Handle accept order action
    $('#confirmAccept').on('click', function () {
        if (currentOrderId) {
            axios.post(getOrderActionPrefix() + '/' + currentOrderId + '/accept')
                .then(function (response) {
                    // Handle success
                    table.ajax.reload(updateOrderCount, false);
                    if (ordersListTable) ordersListTable.ajax.reload(null, false);
                    let data = response.data;
                    if (data.success === false) {
                        return Toast.fire({
                            icon: "error",
                            title: data.message
                        });
                    }
                    return Toast.fire({
                        icon: "success",
                        title: data.message
                    });
                })
                .catch(function (error) {
                    // Handle error
                    console.error('Error accepting order:', error);
                });
        }
    });

    // Handle reject order action
    $('#confirmReject').on('click', function () {
        if (currentOrderId) {
            axios.post(getOrderActionPrefix() + '/' + currentOrderId + '/reject')
                .then(function (response) {
                    // Handle success
                    table.ajax.reload(updateOrderCount, false);
                    if (ordersListTable) ordersListTable.ajax.reload(null, false);
                    let data = response.data;
                    if (data.success === false) {
                        return Toast.fire({
                            icon: "error",
                            title: data.message
                        });
                    }
                    return Toast.fire({
                        icon: "success",
                        title: data.message
                    });
                })
                .catch(function (error) {
                    // Handle error
                    console.error('Error rejecting order:', error);
                });
        }
    });

    // Handle preparing order action
    $('#confirmPreparing').on('click', function () {
        if (currentOrderId) {
            axios.post(getOrderActionPrefix() + '/' + currentOrderId + '/preparing')
                .then(function (response) {
                    // Handle success
                    table.ajax.reload(updateOrderCount, false);
                    if (ordersListTable) ordersListTable.ajax.reload(null, false);
                    let data = response.data;
                    if (data.success === false) {
                        return Toast.fire({
                            icon: "error",
                            title: data.message
                        });
                    }
                    return Toast.fire({
                        icon: "success",
                        title: data.message
                    });
                })
                .catch(function (error) {
                    // Handle error
                    console.error('Error marking order as preparing:', error);
                });
        }
    });
});

// Legacy seller bulk-update handler removed — the seller panel now uses the
// shared bulk-update modal wired in seller-orders.js (which posts a single
// aggregated request instead of N per-item axios calls). The block below is
// kept as a no-op fallback so other pages that still reference these ids
// don't error out, but it never executes the old form-submit path.
$(document).ready(function () {
    return;
    $('#update-status-form').on('submit', function (e) {
        e.preventDefault();

        const selectedItems = $('.item-checkbox:checked');
        if (selectedItems.length === 0) {
            $('#status-update-results').html(
                '<div class="alert alert-danger">' +
                '<h4 class="alert-heading">Error</h4>' +
                '<p>Please select at least one item to update.</p>' +
                '</div>'
            );
            return;
        }

        // Clear previous results
        $('#status-update-results').empty();

        const status = $('#item-status').val();
        let successCount = 0;
        let errorCount = 0;
        let totalRequests = selectedItems.length;
        let completedRequests = 0;

        // Disable the submit button during processing
        $('#update-items-status').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Processing...');

        // Create a progress alert
        $('#status-update-results').append(
            '<div class="alert alert-info" id="progress-alert">' +
            '<h4 class="alert-heading">Processing...</h4>' +
            '<p>Updating status for selected items. Please wait.</p>' +
            '<div class="progress mt-2">' +
            '<div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>' +
            '</div>' +
            '</div>'
        );

        selectedItems.each(function () {
            const itemId = $(this).val();
            const itemRow = $(this).closest('tr');
            const productName = itemRow.find('td:eq(1)').text(); // Product name is in the second column
            const variantName = itemRow.find('td:eq(2)').text(); // Variant name is in the third column

            // Process each selected item
            axios.post(getOrderActionPrefix() + '/' + itemId + '/' + status)
                .then(function (response) {
                    // Handle success
                    let data = response.data;
                    completedRequests++;

                    // Update progress bar
                    const progressPercentage = (completedRequests / totalRequests) * 100;
                    $('#progress-alert .progress-bar').css('width', progressPercentage + '%').attr('aria-valuenow', progressPercentage);

                    // Add item-specific result
                    const alertClass = data.success ? 'alert-success' : 'alert-danger';
                    const alertIcon = data.success ? 'check-circle' : 'x-circle';
                    const alertTitle = data.success ? 'Success' : 'Error';

                    $('#status-update-results').append(
                        '<div class="alert ' + alertClass + ' mt-2">' +
                        '<div class="d-flex">' +
                        '<div>' +
                        '<svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-' + alertIcon + ' me-2" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">' +
                        '<path stroke="none" d="M0 0h24v24H0z" fill="none"></path>' +
                        (data.success ?
                            '<path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0"></path><path d="M9 12l2 2l4 -4"></path>' :
                            '<path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0"></path><path d="M10 10l4 4m0 -4l-4 4"></path>') +
                        '</svg>' +
                        '</div>' +
                        '<div>' +
                        '<h4 class="alert-title">' + alertTitle + ': ' + productName + ' - ' + variantName + '</h4>' +
                        '<p>' + data.message + '</p>' +
                        '</div>' +
                        '</div>' +
                        '</div>'
                    );

                    data.success ? successCount++ : errorCount++;

                    // Check if all requests are completed
                    if (completedRequests === totalRequests) {
                        finishProcessing(successCount, errorCount, totalRequests);
                    }
                })
                .catch(function (error) {
                    // Handle error
                    completedRequests++;
                    errorCount++;

                    // Update progress bar
                    const progressPercentage = (completedRequests / totalRequests) * 100;
                    $('#progress-alert .progress-bar').css('width', progressPercentage + '%').attr('aria-valuenow', progressPercentage);
                    // Add error message
                    const errorMessage = error.response && error.response.data && error.response.data.message
                        ? error.response.data.message
                        : 'An error occurred while updating the item status.';

                    $('#status-update-results').append(
                        '<div class="alert alert-danger mt-2">' +
                        '<div class="d-flex">' +
                        '<div>' +
                        '<svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-x-circle me-2" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">' +
                        '<path stroke="none" d="M0 0h24v24H0z" fill="none"></path>' +
                        '<path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0"></path>' +
                        '<path d="M10 10l4 4m0 -4l-4 4"></path>' +
                        '</svg>' +
                        '</div>' +
                        '<div>' +
                        '<h4 class="alert-title">Error</h4>' +
                        '<p>' + errorMessage + '</p>' +
                        '</div>' +
                        '</div>' +
                        '</div>'
                    );

                    // Check if all requests are completed
                    if (completedRequests === totalRequests) {
                        finishProcessing(successCount, errorCount, totalRequests);
                    }
                });
        });
    });
});

function finishProcessing(successCount, errorCount, totalRequests) {
    // Re-enable the submit button
    $('#update-items-status').prop('disabled', false).html('Update Status');

    // Remove the progress alert
    $('#progress-alert').remove();

    // Add a summary alert at the top of the results
    let summaryAlertClass, summaryAlertIcon, summaryAlertTitle, summaryMessage;

    if (successCount === totalRequests) {
        summaryAlertClass = 'alert-success';
        summaryAlertIcon = 'check-circle';
        summaryAlertTitle = 'Success';
        summaryMessage = 'All selected items have been updated successfully.';

        // Reload the page after a delay
        setTimeout(function () {
            window.location.reload();
        }, 3000);
    } else if (successCount > 0) {
        summaryAlertClass = 'alert-warning';
        summaryAlertIcon = 'alert-triangle';
        summaryAlertTitle = 'Partial Success';
        summaryMessage = successCount + ' out of ' + totalRequests + ' items updated successfully.';

        // Reload the page after a delay
        setTimeout(function () {
            window.location.reload();
        }, 3000);
    } else {
        summaryAlertClass = 'alert-danger';
        summaryAlertIcon = 'x-circle';
        summaryAlertTitle = 'Error';
        summaryMessage = 'Failed to update any items. Please try again.';
    }

    // Prepend the summary alert to the results container
    $('#status-update-results').prepend(
        '<div class="alert ' + summaryAlertClass + '">' +
        '<div class="d-flex">' +
        '<div>' +
        '<svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-' + summaryAlertIcon + ' me-2" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">' +
        '<path stroke="none" d="M0 0h24v24H0z" fill="none"></path>' +
        (summaryAlertIcon === 'check-circle' ?
            '<path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0"></path><path d="M9 12l2 2l4 -4"></path>' :
            (summaryAlertIcon === 'alert-triangle' ?
                '<path d="M12 9v2m0 4v.01"></path><path d="M5 19h14a2 2 0 0 0 1.84 -2.75l-7.1 -12.25a2 2 0 0 0 -3.5 0l-7.1 12.25a2 2 0 0 0 1.75 2.75"></path>' :
                '<path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0"></path><path d="M10 10l4 4m0 -4l-4 4"></path>')) +
        '</svg>' +
        '</div>' +
        '<div>' +
        '<h4 class="alert-title">' + summaryAlertTitle + '</h4>' +
        '<p>' + summaryMessage + '</p>' +
        (successCount > 0 ? '<p class="mb-0"><small>Page will reload in 3 seconds...</small></p>' : '') +
        '</div>' +
        '</div>' +
        '</div>'
    );

    // Scroll to the top of the results
    $('html, body').animate({
        scrollTop: $('#status-update-results').offset().top - 100
    }, 500);
}
