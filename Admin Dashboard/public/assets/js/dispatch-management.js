'use strict';

(function () {
    const REFRESH_INTERVAL = 30;
    const container = document.getElementById('dispatch-container');
    if (!container) return;

    const config = {
        statsUrl: container.dataset.statsUrl,
        ridersUrl: container.dataset.ridersUrl,
        unassignedUrl: container.dataset.unassignedUrl,
        pickupUrl: container.dataset.pickupUrl,
        orderShowUrl: container.dataset.orderShowUrl,
        riderSearchUrl: container.dataset.riderSearchUrl,
        reassignUrl: container.dataset.reassignUrl,
        editPermission: container.dataset.editPermission === '1',
        labelStaleAlert: container.dataset.labelStaleAlert || ':count order(s) waiting > 15 min',
        labelNoData: container.dataset.labelNoData || 'No data available',
        labelNoActivity: container.dataset.labelNoActivity || 'No recent activity',
        labelAssignSuccess: container.dataset.labelAssignSuccess || 'Rider assigned',
        labelDeliveries: container.dataset.labelDeliveries || 'Deliveries',
        labelHourlyDeliveries: container.dataset.labelHourlyDeliveries || 'Hourly Delivery Performance',
    };

    let countdown = REFRESH_INTERVAL;
    let countdownTimer = null;
    const tables = {};
    let hourlyChart = null;
    let riderTomSelect = null;

    // ── Filters ──
    const zoneFilter = document.getElementById('dispatch-zone-filter');
    const paymentFilter = document.getElementById('dispatch-filter-payment-type');
    const rangeFilter = document.getElementById('dispatch-filter-range');
    const staleFilter = document.getElementById('dispatch-filter-stale-only');

    const getZoneId = () => zoneFilter ? zoneFilter.value : '';
    const getPaymentType = () => paymentFilter ? paymentFilter.value : '';
    const getRange = () => rangeFilter ? rangeFilter.value : '';
    const getStaleOnly = () => staleFilter ? (staleFilter.checked ? '1' : '') : '';

    if (zoneFilter) {
        zoneFilter.addEventListener('change', function () { refreshAll(); });
    }
    if (paymentFilter) {
        paymentFilter.addEventListener('change', function () { reloadDatatables(); });
    }
    if (rangeFilter) {
        rangeFilter.addEventListener('change', function () { reloadDatatables(); });
    }
    if (staleFilter) {
        staleFilter.addEventListener('change', function () { reloadDatatables(); });
    }

    // ── Auto-refresh countdown ──
    const countdownEl = document.getElementById('dispatch-countdown');

    function startCountdown() {
        stopCountdown();
        countdown = REFRESH_INTERVAL;
        updateCountdownDisplay();

        countdownTimer = setInterval(function () {
            countdown--;
            updateCountdownDisplay();
            if (countdown <= 0) {
                refreshAll();
            }
        }, 1000);
    }

    function stopCountdown() {
        if (countdownTimer) {
            clearInterval(countdownTimer);
            countdownTimer = null;
        }
    }

    function updateCountdownDisplay() {
        if (countdownEl) {
            countdownEl.textContent = countdown + 's';
        }
    }

    // ── Manual refresh button ──
    var refreshBtn = document.getElementById('dispatch-refresh');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', function () { refreshAll(); });
    }

    // ── Refresh stats + datatables ──
    function refreshAll() {
        fetchStats();
        reloadDatatables();
        countdown = REFRESH_INTERVAL;
    }

    function fetchStats() {
        var url = config.statsUrl + '?zone_id=' + encodeURIComponent(getZoneId());

        axios.get(url)
            .then(function (response) {
                if (response.data && response.data.success && response.data.data) {
                    var d = response.data.data;
                    updateStatCards(d.stats);
                    updateZoneAvailability(d.zone_availability);
                    updateActivityFeed(d.recent_activity);
                    updateHourlyChart(d.hourly_deliveries);
                }
            })
            .catch(function (error) {
                console.error('Dispatch stats refresh failed', error);
            });
    }

    function updateStatCards(stats) {
        if (!stats) return;

        var map = {
            'stat-active-riders': stats.active_riders,
            'stat-riders-on-delivery': stats.riders_on_delivery,
            'stat-idle-riders': stats.idle_riders,
            'stat-unassigned-orders': stats.unassigned_orders,
            'stat-ready-for-pickup': stats.ready_for_pickup,
            'stat-ongoing-deliveries': stats.ongoing_deliveries,
            'stat-delivered-today': stats.delivered_today,
            'stat-avg-assignment-time': stats.avg_assignment_time_minutes,
            'stat-drops-today': stats.drops_today,
        };

        for (var id in map) {
            var el = document.getElementById(id);
            if (el) el.textContent = map[id];
        }

        var ridersCount = document.getElementById('tab-riders-count');
        if (ridersCount) ridersCount.textContent = stats.riders_on_delivery;

        var unassignedCount = document.getElementById('tab-unassigned-count');
        if (unassignedCount) unassignedCount.textContent = stats.unassigned_orders;

        var pickupCount = document.getElementById('tab-pickup-count');
        if (pickupCount) pickupCount.textContent = stats.ready_for_pickup;

        updateStaleAlert(stats.stale_unassigned_count);
    }

    function updateStaleAlert(staleCount) {
        var alertRow = document.getElementById('stale-alert-row');

        if (staleCount > 0) {
            if (!alertRow) {
                var html = '<div class="row mb-3" id="stale-alert-row"><div class="col-12">' +
                    '<div class="alert alert-danger alert-dismissible fade show d-flex align-items-center" role="alert">' +
                    '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" ' +
                    'stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" ' +
                    'class="icon icon-tabler icon-tabler-alert-triangle me-2">' +
                    '<path stroke="none" d="M0 0h24v24H0z" fill="none"/>' +
                    '<path d="M12 9v4"/>' +
                    '<path d="M10.363 3.591l-8.106 13.534a1.914 1.914 0 0 0 1.636 2.871h16.214a1.914 1.914 0 0 0 1.636 -2.87l-8.106 -13.536a1.914 1.914 0 0 0 -3.274 0z"/>' +
                    '<path d="M12 16h.01"/>' +
                    '</svg>' +
                    '<span id="stale-alert-text">' + config.labelStaleAlert.replace(':count', staleCount) + '</span>' +
                    '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>' +
                    '</div></div></div>';

                var statsRow = container.querySelector('.row.row-deck');
                if (statsRow) {
                    statsRow.insertAdjacentHTML('beforebegin', html);
                }
            } else {
                var alertText = document.getElementById('stale-alert-text');
                if (alertText) {
                    alertText.textContent = config.labelStaleAlert.replace(':count', staleCount);
                }
            }
        }
    }

    function updateZoneAvailability(zones) {
        var tableContainer = document.getElementById('zone-availability-table');
        if (!tableContainer || !zones) return;

        var tbody = tableContainer.querySelector('tbody');
        if (!tbody) return;

        if (zones.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">' + escapeHtml(config.labelNoData) + '</td></tr>';
            return;
        }

        var html = '';
        zones.forEach(function (za) {
            var rowClass = (za.idle === 0 && za.active_riders > 0) ? ' class="bg-danger-lt"' : '';
            var idleClass = za.idle === 0 ? 'text-danger fw-bold' : 'text-success';
            html += '<tr' + rowClass + '>' +
                '<td>' + escapeHtml(za.zone_name) + '</td>' +
                '<td class="text-center">' + za.total_riders + '</td>' +
                '<td class="text-center">' + za.active_riders + '</td>' +
                '<td class="text-center">' + za.on_delivery + '</td>' +
                '<td class="text-center"><span class="' + idleClass + '">' + za.idle + '</span></td>' +
                '</tr>';
        });

        tbody.innerHTML = html;
    }

    function updateActivityFeed(activities) {
        var feedContainer = document.getElementById('activity-feed');
        if (!feedContainer || !activities) return;

        var listGroup = feedContainer.querySelector('.list-group');
        if (!listGroup) return;

        if (activities.length === 0) {
            listGroup.innerHTML = '<div class="list-group-item text-center text-muted py-4">' + escapeHtml(config.labelNoActivity) + '</div>';
            return;
        }

        var html = '';
        activities.forEach(function (a) {
            var orderUrl = config.orderShowUrl.replace('__ID__', a.order_id);
            html += '<a href="' + orderUrl + '" class="list-group-item list-group-item-action">' +
                '<div class="d-flex justify-content-between align-items-start">' +
                '<div class="small">' + escapeHtml(a.message) + '</div>' +
                '<span class="text-muted small text-nowrap ms-2">' + escapeHtml(a.time_ago) + '</span>' +
                '</div></a>';
        });

        listGroup.innerHTML = html;
    }

    // ── Hourly Delivery Chart (ApexCharts) ──
    function initHourlyChart() {
        var chartEl = document.getElementById('hourly-deliveries-chart');
        if (!chartEl || !window.ApexCharts) return;

        var initialData = window.__DISPATCH_HOURLY__ || [];
        var categories = initialData.map(function (h) { return h.hour; });
        var seriesData = initialData.map(function (h) { return h.count; });

        hourlyChart = new ApexCharts(chartEl, {
            chart: {
                type: 'bar',
                fontFamily: 'inherit',
                height: 200,
                toolbar: { show: false },
                animations: { enabled: false },
            },
            series: [{ name: config.labelDeliveries, data: seriesData }],
            xaxis: {
                categories: categories,
                labels: { style: { fontSize: '11px' } },
            },
            yaxis: {
                labels: { style: { fontSize: '11px' } },
                min: 0,
                forceNiceScale: true,
            },
            plotOptions: {
                bar: {
                    columnWidth: '60%',
                    borderRadius: 2,
                },
            },
            colors: ['color-mix(in srgb, transparent, var(--tblr-primary) 80%)'],
            dataLabels: { enabled: false },
            grid: { strokeDashArray: 4 },
            tooltip: {
                theme: 'dark',
                y: { formatter: function (val) { return val + ' ' + config.labelDeliveries.toLowerCase(); } },
            },
        });
        hourlyChart.render();
    }

    function updateHourlyChart(hourlyData) {
        if (!hourlyChart || !hourlyData) return;

        var categories = hourlyData.map(function (h) { return h.hour; });
        var seriesData = hourlyData.map(function (h) { return h.count; });

        hourlyChart.updateOptions({
            xaxis: { categories: categories },
        });
        hourlyChart.updateSeries([{ name: config.labelDeliveries, data: seriesData }]);
    }

    // ── Datatables ──
    function initDatatables() {
        // Wait for datatable.custom.js to initialize each table, then destroy
        // and re-init with an ajax.data function that appends our filter params.
        document.querySelectorAll('[data-datatable]').forEach(function (tableEl) {
            var tableId = tableEl.id;
            var checkInit = setInterval(function () {
                if ($.fn.DataTable.isDataTable('#' + tableId)) {
                    clearInterval(checkInit);

                    var dt = $('#' + tableId).DataTable();
                    var ajaxUrl = dt.ajax.url();

                    // Destroy and re-init with filter params via ajax.data
                    dt.destroy();

                    tables[tableId] = $('#' + tableId).DataTable({
                        processing: true,
                        serverSide: true,
                        ajax: {
                            url: ajaxUrl,
                            data: function (d) {
                                d.zone_id = getZoneId();
                                d.payment_type = getPaymentType();
                                d.range = getRange();
                                d.stale_only = getStaleOnly();
                            },
                        },
                        columns: JSON.parse(tableEl.getAttribute('data-columns')),
                        order: tableId === 'dispatch-unassigned-table' ? [[8, 'asc']] :
                               tableId === 'dispatch-riders-table' ? [[5, 'desc']] : [[0, 'desc']],
                        pageLength: 10,
                        responsive: true,
                        scrollX: true,
                        pagingType: 'simple_numbers',
                        language: {
                            emptyTable: "<div class='text-center text-secondary p-4'><i class='ti ti-database fs-1'></i><br>No data available.</div>",
                            lengthMenu: "Show _MENU_ per page",
                            info: "_START_-_END_ of _TOTAL_",
                            infoEmpty: "0-0 of 0",
                            infoFiltered: "",
                            paginate: {
                                previous: "<i class='ti ti-arrow-left'></i>",
                                next: "<i class='ti ti-arrow-right'></i>",
                            },
                        },
                        drawCallback: function () {
                            if (typeof bootstrap !== 'undefined') {
                                this.api().table().container().querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
                                    if (!bootstrap.Tooltip.getInstance(el)) {
                                        new bootstrap.Tooltip(el);
                                    }
                                });
                            }
                        },
                    });

                    $('#' + tableId + ' tbody').on('click', 'tr', function (e) {
                        if ($(e.target).closest('.assign-rider-btn').length) return;
                        var rowData = tables[tableId].row(this).data();
                        if (rowData && rowData.order_id) {
                            window.location.href = config.orderShowUrl.replace('__ID__', rowData.order_id);
                        }
                    });

                    $('#' + tableId + ' tbody').css('cursor', 'pointer');
                }
            }, 200);
        });
    }

    function reloadDatatables() {
        for (var id in tables) {
            if (tables[id]) {
                tables[id].ajax.reload(null, false);
            }
        }
    }

    // ── Assign Rider Modal ──
    function initAssignRider() {
        if (!config.editPermission) return;

        var modalEl = document.getElementById('dispatch-assign-rider-modal');
        if (!modalEl || !window.TomSelect) return;

        var selectEl = document.getElementById('dispatch-assign-rider-select');
        var orderIdInput = document.getElementById('dispatch-assign-order-id');
        var zoneIdInput = document.getElementById('dispatch-assign-zone-id');
        var reasonField = document.getElementById('dispatch-assign-reason');
        var submitBtn = document.getElementById('dispatch-assign-submit');
        var spinnerEl = document.getElementById('dispatch-assign-spinner');

        if (!selectEl) return;

        var getTomSelect = function () {
            if (riderTomSelect) return riderTomSelect;
            riderTomSelect = new TomSelect(selectEl, {
                copyClassesToDropdown: false,
                dropdownParent: 'body',
                valueField: 'value',
                labelField: 'text',
                searchField: 'text',
                placeholder: selectEl.getAttribute('placeholder') || '',
                preload: false,
                load: function (query, callback) {
                    if (!config.riderSearchUrl) return callback();
                    var url = new URL(config.riderSearchUrl, window.location.origin);
                    if (query) url.searchParams.set('search', query);
                    url.searchParams.set('available', '1');
                    var zoneVal = zoneIdInput ? zoneIdInput.value : '';
                    if (zoneVal) url.searchParams.set('delivery_zone_id', zoneVal);

                    axios.get(url.toString())
                        .then(function (r) {
                            callback(Array.isArray(r.data) ? r.data : []);
                        })
                        .catch(function () { callback(); });
                },
            });
            return riderTomSelect;
        };

        // Open modal on assign button click (delegated)
        $(document).on('click', '.assign-rider-btn', function () {
            var btn = $(this);
            orderIdInput.value = btn.data('order-id');
            zoneIdInput.value = btn.data('zone-id') || '';

            var ts = getTomSelect();
            ts.clearOptions();
            ts.clear();
            if (reasonField) reasonField.value = '';

            var modal = new bootstrap.Modal(modalEl);
            modal.show();
        });

        // Submit assignment
        if (submitBtn) {
            submitBtn.addEventListener('click', function () {
                var orderId = orderIdInput.value;
                if (!orderId) return;

                var riderId = selectEl.value ? parseInt(selectEl.value, 10) : null;
                var reason = (reasonField ? reasonField.value : '').trim();

                if (!reason || reason.length < 3) {
                    if (window.Toast) {
                        Toast.fire({ icon: 'warning', title: 'Reason is required (min 3 chars)' });
                    }
                    return;
                }

                var url = config.reassignUrl.replace('__ID__', orderId);
                spinnerEl.classList.remove('d-none');
                submitBtn.disabled = true;

                axios.post(url, {
                    delivery_boy_id: riderId,
                    reason: reason,
                }, {
                    headers: { 'X-CSRF-TOKEN': csrfToken }
                })
                .then(function (response) {
                    if (response.data && response.data.success) {
                        if (window.Toast) {
                            Toast.fire({ icon: 'success', title: response.data.message || config.labelAssignSuccess });
                        }
                        $(modalEl).modal('hide');
                        reloadDatatables();
                        fetchStats();
                    } else {
                        if (window.Toast) {
                            Toast.fire({ icon: 'error', title: response.data.message || 'Failed' });
                        }
                    }
                })
                .catch(function (err) {
                    var msg = 'Failed';
                    if (err.response && err.response.data && err.response.data.message) {
                        msg = err.response.data.message;
                    }
                    if (window.Toast) {
                        Toast.fire({ icon: 'error', title: msg });
                    }
                })
                .finally(function () {
                    spinnerEl.classList.add('d-none');
                    submitBtn.disabled = false;
                });
            });
        }
    }

    // ── Helpers ──
    function escapeHtml(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    // ── Init ──
    document.addEventListener('DOMContentLoaded', function () {
        initDatatables();
        initHourlyChart();
        initAssignRider();
        startCountdown();
    });
})();
