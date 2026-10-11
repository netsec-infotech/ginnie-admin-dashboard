document.addEventListener("DOMContentLoaded", function () {
    // Initialize category product weightage pie chart
    if (window.ApexCharts && document.getElementById("category-chart") && dashboardData.categoryProductWeightage) {
        const categoryData = dashboardData.categoryProductWeightage;

        // Only render the chart if we have data
        if (categoryData.series.length > 0) {
            new ApexCharts(document.getElementById("category-chart"), {
                chart: {
                    type: "donut",
                    fontFamily: "inherit",
                    height: 240,
                    sparkline: {
                        enabled: true,
                    },
                    animations: {
                        enabled: false,
                    },
                },
                series: categoryData.series,
                labels: categoryData.labels,
                tooltip: {
                    theme: "dark",
                    y: {
                        formatter: function (value) {
                            return value + " products";
                        }
                    }
                },
                grid: {
                    strokeDashArray: 4,
                },
                colors: [
                    "color-mix(in srgb, transparent, var(--tblr-primary) 100%)",
                    "color-mix(in srgb, transparent, var(--tblr-primary) 80%)",
                    "color-mix(in srgb, transparent, var(--tblr-primary) 60%)",
                    "color-mix(in srgb, transparent, var(--tblr-primary) 40%)",
                    "color-mix(in srgb, transparent, var(--tblr-green) 80%)",
                    "color-mix(in srgb, transparent, var(--tblr-green) 60%)",
                    "color-mix(in srgb, transparent, var(--tblr-yellow) 80%)",
                    "color-mix(in srgb, transparent, var(--tblr-yellow) 60%)",
                    "color-mix(in srgb, transparent, var(--tblr-red) 60%)",
                    "color-mix(in srgb, transparent, var(--tblr-gray-300) 100%)",
                ],
                legend: {
                    show: true,
                    position: "bottom",
                    offsetY: 6,
                    markers: {
                        width: 10,
                        height: 10,
                        radius: 100,
                    },
                    itemMargin: {
                        horizontal: 8,
                        vertical: 8,
                    },
                },
                tooltip: {
                    fillSeriesColor: false,
                },
            }).render();
        } else {
            // If no data, display a message
            document.getElementById("category-chart").innerHTML = '<div class="text-center p-3">No categories with products found</div>';
        }
    }
    // Initialize new users chart
    if (window.ApexCharts && document.getElementById("chart-new-users")) {
        const newUsersData = dashboardData.newUserRegistrationsData;

        // Extract data for chart
        const dates = newUsersData.daily.map(item => item.date);
        const counts = newUsersData.daily.map(item => item.count);

        new ApexCharts(document.getElementById("chart-new-users"), {
            chart: {
                type: "line",
                fontFamily: "inherit",
                height: 60,
                sparkline: {
                    enabled: true,
                },
                animations: {
                    enabled: false,
                },
            },
            fill: {
                opacity: 1,
            },
            stroke: {
                width: 2,
                lineCap: "round",
                curve: "stepline",
            },
            series: [{
                name: "New Users",
                data: counts
            }],
            tooltip: {
                theme: "dark"
            },
            grid: {
                strokeDashArray: 4,
            },
            xaxis: {
                labels: {
                    padding: 0,
                },
                tooltip: {
                    enabled: false
                },
                type: 'datetime',
                categories: dates,
            },
            yaxis: {
                labels: {
                    padding: 4
                },
            },
            labels: dates,
            colors: ["color-mix(in srgb, transparent, var(--tblr-orange) 100%)"],
            legend: {
                show: false,
            },
        }).render();
    }

    // Handle dynamic data loading for new user registrations
    document.querySelectorAll('.dropdown-menu a[data-period]').forEach(item => {
        item.addEventListener('click', function (e) {
            e.preventDefault();

            // Get the parent dropdown
            const dropdown = this.closest('.dropdown');
            if (!dropdown) return;

            // Get the toggle element
            const toggle = dropdown.querySelector('.dropdown-toggle');
            if (!toggle) return;

            // Update active state
            dropdown.querySelectorAll('.dropdown-item').forEach(el => {
                el.classList.remove('active');
            });
            this.classList.add('active');

            // Update toggle text
            toggle.textContent = this.textContent;

            // Get the period and section type
            const period = this.getAttribute('data-period');
            let type = '';

            if (toggle.classList.contains('new-users-period')) {
                type = 'new_users';
            } else if (toggle.classList.contains('sales-period')) {
                type = 'sales';
            } else if (toggle.classList.contains('revenue-period')) {
                type = 'revenue';
            } else if (toggle.classList.contains('commission-period')) {
                type = 'commissions';
            } else if (toggle.classList.contains('top-sellers-period')) {
                type = 'top_sellers';
            } else if (toggle.classList.contains('top-products-period')) {
                type = 'top_products';
            } else if (toggle.classList.contains('top-delivery-boys-period')) {
                type = 'top_delivery_boys';
            } else if (toggle.classList.contains('top-stores-period')) {
                type = 'top_stores';
            } else if (toggle.classList.contains('top-zones-period')) {
                type = 'top_zones';
            } else if (toggle.classList.contains('order-funnel-period')) {
                type = 'order_funnel';
            } else if (toggle.classList.contains('customer-insights-period')) {
                type = 'customer_insights';
            } else if (toggle.classList.contains('zone-health-period')) {
                type = 'zone_health';
            } else if (toggle.classList.contains('revenue-vs-orders-period')) {
                type = 'revenue_vs_orders';
            } else if (toggle.classList.contains('seller-settlements-period')) {
                type = 'seller_settlements';
            } else if (toggle.classList.contains('db-settlements-period')) {
                type = 'db_settlements';
            } else if (toggle.classList.contains('withdrawals-period')) {
                type = 'withdrawals';
            } else if (toggle.classList.contains('cash-collection-period')) {
                type = 'cash_collection';
            } else if (toggle.classList.contains('ad-campaigns-period')) {
                type = 'ad_campaigns';
            } else if (toggle.classList.contains('subscriptions-period')) {
                type = 'subscriptions';
            }

            // Update the toggle's data-period attribute
            toggle.setAttribute('data-period', period);

            // Make the AJAX request
            fetchDashboardData(type, period);
        });
    });


    // Zone Selector
    const zoneSelector = document.getElementById('zone-selector');
    if (zoneSelector) {
        zoneSelector.addEventListener('change', function () {
            window.currentZoneId = this.value || null;
            updateZonePills(this.value);
            updateZoneIndicator(this.value);
            reloadAllDashboardData();
        });
    }

    // Zone pills
    document.querySelectorAll('.zone-pill').forEach(pill => {
        pill.addEventListener('click', function () {
            const zoneId = this.dataset.zoneId;
            const selector = document.getElementById('zone-selector');

            // Toggle: if already selected, deselect
            if (window.currentZoneId == zoneId) {
                window.currentZoneId = null;
                if (selector) selector.value = '';
            } else {
                window.currentZoneId = zoneId;
                if (selector) selector.value = zoneId;
            }
            updateZonePills(window.currentZoneId);
            updateZoneIndicator(window.currentZoneId);
            reloadAllDashboardData();
        });
    });

    function updateZonePills(activeZoneId) {
        document.querySelectorAll('.zone-pill').forEach(p => {
            if (p.dataset.zoneId == activeZoneId) {
                p.classList.remove('btn-outline-primary');
                p.classList.add('btn-primary');
            } else {
                p.classList.remove('btn-primary');
                p.classList.add('btn-outline-primary');
            }
        });
    }

    function updateZoneIndicator(zoneId) {
        const indicator = document.getElementById('zone-indicator');
        if (!indicator) return;
        indicator.innerHTML = zoneId
            ? '<span class="badge bg-primary-lt">Filtered by zone</span>'
            : '<span class="badge bg-secondary-lt">Showing all zones</span>';
    }

    // Top Stores rank-by dropdown
    document.querySelectorAll('.top-stores-rank .dropdown-item, [data-rank]').forEach(item => {
        if (!item.closest('.dropdown')?.querySelector('.top-stores-rank')) return;
        item.addEventListener('click', function (e) {
            e.preventDefault();
            const rank = this.dataset.rank;
            const dropdown = this.closest('.dropdown');
            dropdown.querySelectorAll('.dropdown-item').forEach(el => el.classList.remove('active'));
            this.classList.add('active');
            dropdown.querySelector('.dropdown-toggle').textContent = this.textContent;
            const period = document.querySelector('.top-stores-period')?.dataset?.period || 30;
            fetchDashboardData('top_stores', period, { rank_by: rank });
        });
    });

    // Categories Filters
    $('#categories-filter').parent().find('.dropdown-item').on('click', function (e) {
        e.preventDefault();
        const filter = $(this).data('filter');
        const filterText = $(this).text();

        $('#categories-filter').text(filterText);
        $('#categories-filter').parent().find('.dropdown-item').removeClass('active');
        $(this).addClass('active');

        const sortBy = $('#categories-sort').parent().find('.dropdown-item.active').data('sort') || 'name';
        loadCategories(sortBy, filter);
    });

    $('#categories-sort').parent().find('.dropdown-item').on('click', function (e) {
        e.preventDefault();
        const sort = $(this).data('sort');
        const sortText = $(this).text();

        $('#categories-sort').text(sortText);
        $('#categories-sort').parent().find('.dropdown-item').removeClass('active');
        $(this).addClass('active');

        const filterBy = $('#categories-filter').parent().find('.dropdown-item.active').data('filter') || 'all';
        loadCategories(sort, filterBy);
    });

    // Initialize Commission Chart
    if (document.getElementById('commission-chart')) {

        window.commissionChart = new ApexCharts(document.getElementById('commission-chart'), {
            chart: {
                type: "area",
                fontFamily: 'inherit',
                height: 240,
                parentHeightOffset: 0,
                toolbar: {
                    show: false,
                },
                animations: {
                    enabled: false
                },
                stacked: true,
            },
            plotOptions: {
                bar: {
                    columnWidth: '50%',
                }
            },
            dataLabels: {
                enabled: false,
            },
            fill: {
                opacity: .16,
                type: 'solid'
            },
            stroke: {
                width: 2,
                lineCap: "round",
                curve: "smooth",
            },
            series: [{
                name: "Commission",
                data: commissionData.map(item => ({
                    x: item.date,
                    y: item.commission
                }))
            }],
            tooltip: {
                theme: 'dark'
            },
            grid: {
                padding: {
                    top: -20,
                    right: 0,
                    left: -4,
                    bottom: -4
                },
                strokeDashArray: 4,
            },
            xaxis: {
                labels: {
                    padding: 0,
                },
                tooltip: {
                    enabled: false
                },
                axisBorder: {
                    show: false,
                },
                type: 'datetime',
            },
            yaxis: {
                labels: {
                    padding: 4
                },
            },
            colors: ["#2fb344"],
            legend: {
                show: false,
            },
        });
        window.commissionChart.render();
    }
});

document.addEventListener("DOMContentLoaded", function () {
    // Initialize charts when data is available
    if (typeof initializeRevenueChart === 'function') {
        initializeRevenueChart(dashboardData.monthlyRevenueData);
    }
    if (typeof initializeDailyPurchaseChart === 'function') {
        initializeDailyPurchaseChart(dashboardData.dailyPurchaseHistory);
    }
    if (typeof initializeRevenueBackgroundChart === 'function') {
        initializeRevenueBackgroundChart(dashboardData.revenueDataBg);
    }

    // Initialize Revenue vs Orders dual-axis chart
    if (window.ApexCharts && document.getElementById('chart-revenue-vs-orders') && typeof revenueVsOrdersData !== 'undefined') {
        const rvoDaily = revenueVsOrdersData.daily || [];
        window.revenueVsOrdersChart = new ApexCharts(document.getElementById('chart-revenue-vs-orders'), {
            chart: {
                type: 'line',
                fontFamily: 'inherit',
                height: 240,
                parentHeightOffset: 0,
                toolbar: { show: false },
                animations: { enabled: false },
            },
            series: [
                {
                    name: 'Orders',
                    type: 'column',
                    data: rvoDaily.map(d => ({ x: d.date, y: d.orders }))
                },
                {
                    name: 'Revenue',
                    type: 'line',
                    data: rvoDaily.map(d => ({ x: d.date, y: d.revenue }))
                }
            ],
            stroke: { width: [0, 3], curve: 'smooth' },
            plotOptions: { bar: { columnWidth: '60%', borderRadius: 2 } },
            fill: { opacity: [0.85, 1] },
            xaxis: { type: 'datetime', labels: { padding: 0 }, tooltip: { enabled: false } },
            yaxis: [
                {
                    title: { text: 'Orders',
                             offsetX: -12,   // 👈 move title away from numbers (increase if needed)
                             offsetY: 0,
                             style: { fontSize: '15px'},
                     },
                    labels: { padding: 4 },
                },
                {
                    opposite: true,
                    title: { text: 'Revenue', 
                             offsetX: 12,   // 👈 move title away from numbers (increase if needed)
                             offsetY: 0,
                             style: { fontSize: '15px' },
                     },
                    labels: {
                        padding: 4,
                        formatter: (val) => (revenueVsOrdersData.currency_symbol || '$') + (val >= 1000 ? (val/1000).toFixed(1) + 'k' : val)
                    }
                }
            ],
            colors: ['color-mix(in srgb, transparent, var(--tblr-primary) 70%)', '#2fb344'],
            tooltip: { theme: 'dark' },
            grid: { padding: { top: -20, right: 0, left: -4, bottom: -4 }, strokeDashArray: 4 },
            legend: { show: true, position: 'top' },
        });
        window.revenueVsOrdersChart.render();
    }
});

function loadCategories(sortBy = 'name', filterBy = 'all') {
    const type = 'categories';
    axios.get(`/admin/dashboard/data?type=${type}&sort_by=${sortBy}&filter_by=${filterBy}`)
        .then(function (response) {
            updateCategoriesGrid(response.data);
        })
        .catch(function (error) {
            console.error('Error loading categories:', error);
        });
}

// Update Functions
function updateTopSellersList(data) {
    let html = '';
    if (data.length === 0) {
        // Show not found SVG full width
        html = `
        <div class="text-center w-100 py-5">
            <img src="${base_url}/assets/theme/img/not-found.svg" alt="No data found" class="w-100" style="max-width: 400px; height: auto; margin: 0 auto; display: block;">
        </div>
    `;
    } else {
        data.forEach((seller, index) => {
            const initials = seller.name ? seller.name.substring(0, 2).toUpperCase() : "NA";

            const avatar = seller.avatar
                ? `<span class="avatar avatar-sm" style="background-image: url('${seller.avatar}');"></span>`
                : `<span class="avatar avatar-sm bg-primary text-white">${initials}</span>`;

            html += `
            <div class="list-group-item d-flex align-items-center">
                <span class="badge bg-teal-lt me-3">${index + 1}</span>
                <div class="me-3">${avatar}</div>
                <div class="flex-fill">
                    <div class="font-weight-medium">${seller.name}</div>
                    <div class="text-secondary">${seller.total_orders} Orders</div>
                </div>
                <div class="text-end">
                    <div class="font-weight-medium">${seller.total_revenue}</div>
                </div>
            </div>
        `;
        });
    }
    $('#top-sellers-list').html(html);
}

function updateTopProductsList(data) {
    let html = '';

    if (!data || data.length === 0) {
        html = `
            <div class="text-center w-100 py-5">
                <img src="${base_url}/assets/theme/img/not-found.svg" alt="No products found"
                     class="w-100" style="max-width: 400px; height: auto; margin: 0 auto; display: block;">
            </div>
        `;
    } else {
        data.forEach((product, index) => {
            const initial = product.name
                ? product.name.substring(0, 1).toUpperCase()
                : 'P';

            const avatar = product.image
                ? `<span class="avatar avatar-sm" style="background-image: url('${product.image}');"></span>`
                : `<span class="avatar avatar-sm bg-primary text-white">${initial}</span>`;

            const productName = product.name?.length > 25
                ? product.name.substring(0, 25) + '...'
                : product.name;

            html += `
                <div class="list-group-item d-flex align-items-center">
                    <span class="badge bg-primary-lt me-3">${index + 1}</span>
                    <div class="me-3">${avatar}</div>
                    <div class="flex-fill">
                        <div class="font-weight-medium">
                            <a href="${base_url}/admin/products/${product.id}" class="text-decoration-none text-body">
                                ${productName}
                            </a>
                        </div>
                        <div class="text-secondary">${product.category}</div>
                        <div class="text-secondary">${product.total_quantity} sold</div>
                    </div>
                    <div class="text-end">
                        <div class="font-weight-medium">${product.total_revenue}</div>
                    </div>
                </div>
            `;
        });
    }

    document.getElementById('top-products-list').innerHTML = html;
}


function updateTopDeliveryBoysList(data) {
    let html = '';

    if (!data || data.length === 0) {
        html = `
            <div class="text-center w-100 py-5">
                <img src="${base_url}/assets/theme/img/not-found.svg" alt="No delivery boys found"
                     class="w-100" style="max-width: 400px; height: auto; margin: 0 auto; display: block;">
            </div>
        `;
    } else {
        data.forEach((deliveryBoy, index) => {
            const initials = deliveryBoy.name
                ? deliveryBoy.name.substring(0, 2).toUpperCase()
                : 'DB';

            const avatar = deliveryBoy.avatar
                ? `<span class="avatar avatar-sm" style="background-image: url('${deliveryBoy.avatar}');"></span>`
                : `<span class="avatar avatar-sm bg-warning text-white">${initials}</span>`;

            html += `
                <div class="list-group-item d-flex align-items-center">
                    <span class="badge bg-warning-lt me-3">${index + 1}</span>
                    <div class="me-3">${avatar}</div>
                    <div class="flex-fill">
                        <div class="font-weight-medium">${deliveryBoy.name}</div>
                        <div class="text-secondary">${deliveryBoy.total_deliveries} deliveries</div>
                    </div>
                    <div class="text-end">
                        <div class="font-weight-medium">${deliveryBoy.total_revenue}</div>
                    </div>
                </div>
            `;
        });
    }

    document.getElementById('top-delivery-boys-list').innerHTML = html;
}


function updateCommissionsData(data) {
    $('#commission-total').text(data.total_commission);
    $('#commission-orders').text(data.total_orders);
    $('#commission-avg').text(data.avg_commission);

    // Update commission chart if exists
    if (window.commissionChart) {
        const chartData = data.daily_data.map(item => ({
            x: item.date,
            y: item.commission
        }));

        window.commissionChart.updateSeries([{
            name: 'Commission',
            data: chartData
        }]);
    }
}

function updateCategoriesGrid(data) {
    let html = '';
    data.forEach((category) => {
        const image = category.image ?
            `<img src="${category.image}" alt="${category.title}" class="avatar avatar-lg object-contain mb-2">` :
            `<div class="avatar avatar-lg mb-2 object-contain avatar-placeholder">${category.title?.substr(0, 2)}</div>`;

        const totalSold = category.total_sold ?
            `<div class="text-success">${category.total_sold} sold</div>` : '';

        html += `
                        <div class="col-sm-6 col-md-4 col-lg-3 mb-3">
                            <div class="card card-sm">
                                <div class="card-body text-center">
                                    ${image}
                                    <h4 class="card-title">${category.title}</h4>
                                    <div class="text-secondary">${category.products_count} Products</div>
                                    ${totalSold}
                                </div>
                            </div>
                        </div>
                    `;
    });
    $('#categories-grid').html(html);
}

// Function to fetch dashboard data via AJAX
function fetchDashboardData(type, days, extraParams = {}) {
    const params = new URLSearchParams({ type, days, ...extraParams });
    if (window.currentZoneId) {
        params.set('zone_id', window.currentZoneId);
    }

    axios.get(`/admin/dashboard/data?${params.toString()}`)
        .then(function (response) {
            const data = response.data;

            switch (type) {
                case 'new_users': updateNewUsersData(data); break;
                case 'sales': updateSalesData(data); break;
                case 'revenue': updateRevenueData(data); break;
                case 'commissions': updateCommissionsData(data); break;
                case 'top_sellers': updateTopSellersList(data); break;
                case 'top_products': updateTopProductsList(data); break;
                case 'top_delivery_boys': updateTopDeliveryBoysList(data); break;
                case 'top_stores': updateTopStoresList(data); break;
                case 'top_zones': updateTopZonesList(data); break;
                case 'order_funnel': updateOrderFunnel(data); break;
                case 'alerts': updateAlerts(data); break;
                case 'customer_insights': updateCustomerInsights(data); break;
                case 'zone_health': updateZoneHealth(data); break;
                case 'insights': updateInsightsData(data); break;
                case 'revenue_vs_orders': updateRevenueVsOrdersData(data); break;
                case 'seller_settlements': updateSellerSettlements(data); break;
                case 'db_settlements': updateDbSettlements(data); break;
                case 'withdrawals': updateWithdrawals(data); break;
                case 'cash_collection': updateCashCollection(data); break;
                case 'ad_campaigns': updateAdCampaigns(data); break;
                case 'subscriptions': updateSubscriptions(data); break;
            }
        })
        .catch(function (error) {
            console.error('Error fetching dashboard data:', error);
        });
}

// Reload all dashboard sections with current zone
function reloadAllDashboardData() {
    const defaultDays = 30;
    fetchDashboardData('sales', defaultDays);
    fetchDashboardData('revenue', defaultDays);
    fetchDashboardData('new_users', defaultDays);
    fetchDashboardData('commissions', defaultDays);
    fetchDashboardData('top_sellers', defaultDays);
    fetchDashboardData('top_products', defaultDays);
    fetchDashboardData('top_delivery_boys', defaultDays);
    fetchDashboardData('top_stores', defaultDays);
    fetchDashboardData('top_zones', defaultDays);
    fetchDashboardData('order_funnel', defaultDays);
    fetchDashboardData('alerts', defaultDays);
    fetchDashboardData('customer_insights', defaultDays);
    fetchDashboardData('zone_health', defaultDays);
    fetchDashboardData('insights', defaultDays);
    fetchDashboardData('revenue_vs_orders', defaultDays);
    // Financial widgets default to "today" (1 day)
    fetchDashboardData('seller_settlements', 1);
    fetchDashboardData('db_settlements', 1);
    fetchDashboardData('withdrawals', 1);
    fetchDashboardData('cash_collection', 1);
    fetchDashboardData('ad_campaigns', 1);
    fetchDashboardData('subscriptions', 1);
}

// Update Top Stores list
function updateTopStoresList(data) {
    const container = document.getElementById('top-stores-container');
    if (!container) return;

    if (!data || data.length === 0) {
        container.innerHTML = `<div class="text-center py-4 text-muted"><img src="${base_url}/assets/images/not-found.svg" class="mb-2" width="60" alt=""><p class="mb-0">No data found</p></div>`;
        return;
    }

    let html = '<div class="list-group list-group-flush">';
    data.forEach((store) => {
        const img = store.image
            ? `<span class="avatar avatar-sm me-3 rounded" style="background-image: url(${store.image})"></span>`
            : `<span class="avatar avatar-sm me-3 bg-primary-lt rounded">${store.name?.substring(0, 2).toUpperCase()}</span>`;
        html += `
            <div class="list-group-item d-flex align-items-center">
                ${img}
                <div class="flex-fill">
                    <div class="fw-medium">${store.name}</div>
                    <div class="text-muted small">${store.total_orders} orders</div>
                </div>
                <div class="text-end"><div class="fw-bold">${store.total_revenue}</div></div>
            </div>`;
    });
    html += '</div>';
    container.innerHTML = html;
}

// Update Top Zones list
function updateTopZonesList(data) {
    const container = document.getElementById('top-zones-container');
    if (!container) return;

    if (!data || data.length === 0) {
        container.innerHTML = `<div class="text-center py-4 text-muted"><img src="${base_url}/assets/images/not-found.svg" class="mb-2" width="60" alt=""><p class="mb-0">No data found</p></div>`;
        return;
    }

    let html = '<div class="list-group list-group-flush">';
    data.forEach((zone) => {
        const rateClass = zone.delivery_rate >= 80 ? 'success' : 'warning';
        html += `
            <div class="list-group-item d-flex align-items-center">
                <span class="avatar avatar-sm me-3 bg-primary-lt rounded">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M12 2a10 10 0 0 1 10 10c0 5.523-4.477 10-10 10S2 17.523 2 12 6.477 2 12 2"/></svg>
                </span>
                <div class="flex-fill">
                    <div class="fw-medium">${zone.name}</div>
                    <div class="text-muted small">${zone.total_orders} orders · ${zone.active_delivery_boys} DBs</div>
                </div>
                <div class="text-end">
                    <div class="fw-bold">${zone.total_revenue}</div>
                    <div class="small text-${rateClass}">${zone.delivery_rate}% delivered</div>
                </div>
            </div>`;
    });
    html += '</div>';
    container.innerHTML = html;
}

// Update Order Funnel
function updateOrderFunnel(data) {
    const container = document.getElementById('order-funnel-container');
    if (!container) return;

    const colors = ['bg-primary', 'bg-info', 'bg-azure', 'bg-success', 'bg-danger'];
    const maxCount = data.funnel[0]?.count || 1;

    let html = '';
    data.funnel.forEach((stage, i) => {
        const width = Math.max(20, (stage.count / maxCount) * 100);
        html += `
            <div class="d-flex align-items-center mb-2">
                <div class="flex-fill">
                    <div class="${colors[i]} rounded px-3 py-2 text-white d-flex justify-content-between align-items-center" style="width: ${width}%; transition: width 0.5s ease;">
                        <span class="fw-medium small">${stage.stage}</span>
                        <span class="fw-bold">${stage.count}</span>
                    </div>
                </div>
            </div>`;
    });
    html += `
        <div class="row mt-3 text-center">
            <div class="col-6">
                <div class="text-muted small">Conversion Rate</div>
                <div class="h3 text-success">${data.conversion_rate}%</div>
            </div>
            <div class="col-6">
                <div class="text-muted small">Cancellation Rate</div>
                <div class="h3 text-danger">${data.cancellation_rate}%</div>
            </div>
        </div>`;
    container.innerHTML = html;
}

// Update Alerts
function updateAlerts(data) {
    const container = document.getElementById('alerts-container');
    const countBadge = document.getElementById('alerts-count');
    if (!container) return;

    if (countBadge) countBadge.textContent = data.total;

    if (!data.alerts || data.alerts.length === 0) {
        container.innerHTML = `<div class="text-center py-4 text-muted"><svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" class="mb-2 text-success"><path d="M5 12l5 5l10 -10"/></svg><p class="mb-0">No alerts</p></div>`;
        return;
    }

    let html = '<div class="list-group list-group-flush">';
    data.alerts.forEach((alert) => {
        const badgeClass = alert.severity === 'critical' ? 'danger' : (alert.severity === 'warning' ? 'warning' : 'info');
        html += `
            <div class="list-group-item d-flex align-items-center">
                <span class="badge bg-${badgeClass} badge-empty me-3"></span>
                <div class="flex-fill"><div class="small">${alert.message}</div></div>
            </div>`;
    });
    html += '</div>';
    container.innerHTML = html;
}

// Update Customer Insights
function updateCustomerInsights(data) {
    const el = (id) => document.getElementById(id);
    if (el('ci-repeat-rate')) el('ci-repeat-rate').textContent = data.repeat_rate + '%';
    if (el('ci-avg-basket')) el('ci-avg-basket').textContent = data.avg_basket_size;
    if (el('ci-churn-risk')) el('ci-churn-risk').textContent = data.churn_risk;
    if (el('ci-new')) el('ci-new').textContent = data.new_customers;
    if (el('ci-returning')) el('ci-returning').textContent = data.returning_customers;
    if (el('ci-total')) el('ci-total').textContent = data.total_customers;
}

// Update Zone Health table
function updateZoneHealth(data) {
    const container = document.getElementById('zone-health-container');
    if (!container) return;

    let html = `<div class="table-responsive"><table class="table table-vcenter card-table table-hover"><thead><tr>
        <th>Zone</th><th class="text-center">Orders</th><th class="text-center">Delivered</th>
        <th class="text-center">Delivery Rate</th><th class="text-end">Revenue</th>
        <th class="text-center">Growth</th><th class="text-center">Delivery Boys</th><th class="text-center">Stores</th>
    </tr></thead><tbody>`;

    data.zones.forEach((zone) => {
        const rowClass = zone.delivery_rate < 70 ? 'bg-danger-lt' : '';
        const rateClass = zone.delivery_rate >= 80 ? 'success' : (zone.delivery_rate >= 60 ? 'warning' : 'danger');
        const growthClass = zone.revenue_growth >= 0 ? 'success' : 'danger';
        const growthSign = zone.revenue_growth >= 0 ? '+' : '';
        const dbClass = zone.active_delivery_boys === 0 ? 'text-danger fw-bold' : '';

        html += `<tr class="${rowClass}">
            <td><div class="fw-medium">${zone.name}</div></td>
            <td class="text-center">${zone.total_orders}</td>
            <td class="text-center">${zone.delivered_orders}</td>
            <td class="text-center"><span class="badge bg-${rateClass}-lt">${zone.delivery_rate}%</span></td>
            <td class="text-end fw-medium">${zone.revenue}</td>
            <td class="text-center"><span class="text-${growthClass}">${growthSign}${zone.revenue_growth}%</span></td>
            <td class="text-center"><span class="${dbClass}">${zone.active_delivery_boys}/${zone.total_delivery_boys}</span></td>
            <td class="text-center">${zone.store_count}</td>
        </tr>`;
    });
    html += '</tbody></table></div>';
    container.innerHTML = html;
}

// Update admin insights (key metric cards)
function updateInsightsData(data) {
    const el = (id) => document.getElementById(id);
    if (el('insight-total-sellers')) el('insight-total-sellers').textContent = data.total_sellers;
    if (el('insight-total-stores')) el('insight-total-stores').textContent = data.total_stores;
    if (el('insight-total-orders')) el('insight-total-orders').textContent = data.total_orders;
    if (el('insight-delivered-orders')) el('insight-delivered-orders').textContent = data.total_delivered_orders;
    if (el('insight-active-dbs')) el('insight-active-dbs').textContent = data.total_active_delivery_boys;
    if (el('insight-total-dbs')) el('insight-total-dbs').textContent = data.total_delivery_boys;
    if (el('insight-total-products')) el('insight-total-products').textContent = data.total_products;
    if (el('insight-total-sales')) el('insight-total-sales').textContent = data.total_product_sales;
}

// Update sales conversion rate data
function updateSalesData(data) {
    const rateEl = document.getElementById('sales-rate');
    if (rateEl) rateEl.textContent = data.rate + '%';

    const trendEl = document.getElementById('sales-trend');
    if (trendEl) {
        trendEl.textContent = Math.abs(data.percentage_change) + '%';
        trendEl.className = `text-${data.is_increase ? 'green' : 'red'} d-inline-flex align-items-center lh-1`;
    }

    const detailsEl = document.getElementById('sales-details');
    if (detailsEl) detailsEl.textContent = `${data.delivered_orders} delivered out of total orders ${data.total_orders}`;

    const progressEl = document.getElementById('sales-progress');
    if (progressEl) progressEl.style.width = data.rate + '%';

    // Update trend paths
    const path1 = document.getElementById('sales-trend-path-1');
    const path2 = document.getElementById('sales-trend-path-2');
    if (path1 && path2) {
        path1.setAttribute('d', data.is_increase ? 'M3 17l6 -6l4 4l8 -8' : 'M3 7l6 6l4 -4l8 8');
        path2.setAttribute('d', data.is_increase ? 'M14 7l7 0l0 7' : 'M21 7l0 7l-7 0');
    }
}

// Update revenue data
function updateRevenueData(data) {
    const totalEl = document.getElementById('revenue-total');
    if (totalEl) totalEl.textContent = data.formatted_total;

    const daysEl = document.getElementById('revenue-days');
    if (daysEl) {
        const dayCount = data.daily ? data.daily.length : 0;
        daysEl.innerHTML = `${dayCount} days <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon ms-1 icon-2"><path d="M4 7a2 2 0 0 1 2 -2h12a2 2 0 0 1 2 2v12a2 2 0 0 1 -2 2h-12a2 2 0 0 1 -2 -2v-12z"/><path d="M16 3v4"/><path d="M8 3v4"/><path d="M4 11h16"/><path d="M11 15h1"/><path d="M12 15v3"/></svg>`;
    }
}

// Update Revenue vs Orders chart
function updateRevenueVsOrdersData(data) {
    const el = (id) => document.getElementById(id);
    if (el('rvo-total-orders')) el('rvo-total-orders').textContent = data.total_orders;
    if (el('rvo-total-revenue')) el('rvo-total-revenue').textContent = data.formatted_total_revenue;
    if (el('rvo-aov')) el('rvo-aov').textContent = data.formatted_aov;

    if (window.revenueVsOrdersChart && data.daily) {
        window.revenueVsOrdersChart.updateSeries([
            {
                name: 'Orders',
                type: 'column',
                data: data.daily.map(d => ({ x: d.date, y: d.orders }))
            },
            {
                name: 'Revenue',
                type: 'line',
                data: data.daily.map(d => ({ x: d.date, y: d.revenue }))
            }
        ]);
    }
}

// Function to update new users data
function updateNewUsersData(data) {
    // Update count
    const newUsersCountElement = document.getElementById('new-users-count');
    if (newUsersCountElement) {
        newUsersCountElement.textContent = data.count;
    }

    // Update trend
    const trendElement = document.getElementById('new-users-trend');
    if (trendElement) {
        trendElement.textContent = Math.abs(data.percentage_change) + '%';
        trendElement.className = `text-${data.is_increase ? 'green' : 'red'} d-inline-flex align-items-center lh-1`;

        // Update trend icon
        const trendIcon = trendElement.querySelector('svg');
        if (trendIcon) {
            const path1 = trendIcon.querySelector('path:nth-child(1)');
            const path2 = trendIcon.querySelector('path:nth-child(2)');

            if (path1 && path2) {
                if (data.is_increase) {
                    path1.setAttribute('d', 'M3 17l6 -6l4 4l8 -8');
                    path2.setAttribute('d', 'M14 7l7 0l0 7');
                } else {
                    path1.setAttribute('d', 'M3 7l6 6l4 -4l8 8');
                    path2.setAttribute('d', 'M21 7l0 7l-7 0');
                }
            }
        }
    }

    // Update chart
    if (window.ApexCharts) {
        const chartElement = document.getElementById("chart-new-users");
        if (chartElement) {
            // Extract data for chart
            const dates = data.daily.map(item => item.date);
            const counts = data.daily.map(item => item.count);

            // Destroy existing chart
            while (chartElement.firstChild) {
                chartElement.removeChild(chartElement.firstChild);
            }

            // Create new chart
            new ApexCharts(chartElement, {
                chart: {
                    type: "line",
                    fontFamily: "inherit",
                    height: 40,
                    sparkline: {
                        enabled: true,
                    },
                    animations: {
                        enabled: false,
                    },
                },
                fill: {
                    opacity: 1,
                },
                stroke: {
                    width: 2,
                    lineCap: "round",
                    curve: "smooth",
                },
                series: [{
                    name: "New Users",
                    data: counts
                }],
                tooltip: {
                    theme: "dark"
                },
                grid: {
                    strokeDashArray: 4,
                },
                xaxis: {
                    labels: {
                        padding: 0,
                    },
                    tooltip: {
                        enabled: false
                    },
                    type: 'datetime',
                    categories: dates,
                },
                yaxis: {
                    labels: {
                        padding: 4
                    },
                },
                labels: dates,
                colors: ["#206bc4"],
                legend: {
                    show: false,
                },
            }).render();
        }
    }
}

// Update Seller Settlements
function updateSellerSettlements(data) {
    const el = (id) => document.getElementById(id);
    if (el('ss-pending-amount')) el('ss-pending-amount').textContent = data.pending_amount;
    if (el('ss-pending-count')) el('ss-pending-count').textContent = data.pending_count;
    if (el('ss-settled-amount')) el('ss-settled-amount').textContent = data.settled_amount;
    if (el('ss-settled-count')) el('ss-settled-count').textContent = data.settled_count;
    if (el('ss-outstanding')) el('ss-outstanding').textContent = data.total_outstanding;
}

// Update Delivery Boy Settlements
function updateDbSettlements(data) {
    const el = (id) => document.getElementById(id);
    if (el('dbs-pending-amount')) el('dbs-pending-amount').textContent = data.pending_amount;
    if (el('dbs-pending-count')) el('dbs-pending-count').textContent = data.pending_count;
    if (el('dbs-paid-amount')) el('dbs-paid-amount').textContent = data.paid_amount;
    if (el('dbs-paid-count')) el('dbs-paid-count').textContent = data.paid_count;
    if (el('dbs-unpaid')) el('dbs-unpaid').textContent = data.total_unpaid;
}

// Update Withdrawals
function updateWithdrawals(data) {
    const el = (id) => document.getElementById(id);
    if (el('wd-total-pending')) el('wd-total-pending').textContent = data.total_pending;
    if (el('wd-approved-amount')) el('wd-approved-amount').textContent = data.approved_amount;
    if (el('wd-total-rejected')) el('wd-total-rejected').textContent = data.total_rejected;
    if (el('wd-seller-pending')) el('wd-seller-pending').textContent = data.seller_pending;
    if (el('wd-db-pending')) el('wd-db-pending').textContent = data.db_pending;
    if (el('wd-oldest-days')) el('wd-oldest-days').textContent = data.oldest_pending_days;
}

// Update Cash Collection
function updateCashCollection(data) {
    const el = (id) => document.getElementById(id);
    if (el('cc-collected')) el('cc-collected').textContent = data.total_collected;
    if (el('cc-submitted')) el('cc-submitted').textContent = data.total_submitted;
    if (el('cc-unsubmitted')) el('cc-unsubmitted').textContent = data.unsubmitted;
    if (el('cc-top-name')) el('cc-top-name').textContent = data.top_collector_name || '-';
    if (el('cc-top-amount')) el('cc-top-amount').textContent = data.top_collector_amount || '-';
}

// Update Ad Campaigns
function updateAdCampaigns(data) {
    const el = (id) => document.getElementById(id);
    if (el('ad-active')) el('ad-active').textContent = data.active_campaigns;
    if (el('ad-spend')) el('ad-spend').textContent = data.total_spend;
    if (el('ad-clicks')) el('ad-clicks').textContent = data.total_clicks;

    const topContainer = document.getElementById('ad-top-campaigns');
    if (topContainer && data.top_campaigns) {
        if (data.top_campaigns.length === 0) {
            topContainer.innerHTML = '<div class="text-muted small text-center py-2">No campaign data</div>';
        } else {
            let html = '';
            data.top_campaigns.forEach(c => {
                html += `<div class="list-group-item px-0 py-1 d-flex align-items-center">
                    <div class="flex-fill small">${c.name.substring(0, 20)}</div>
                    <span class="badge bg-primary-lt me-1">${c.clicks} clicks</span>
                    <span class="text-muted small">${c.spent}</span>
                </div>`;
            });
            topContainer.innerHTML = html;
        }
    }
}

// Update Subscriptions
function updateSubscriptions(data) {
    const el = (id) => document.getElementById(id);
    if (el('sub-active')) el('sub-active').textContent = data.active_count;
    if (el('sub-revenue')) el('sub-revenue').textContent = data.revenue;
    if (el('sub-popular')) el('sub-popular').textContent = data.popular_plan;
    if (el('sub-expiring')) {
        if (data.expiring_soon > 0) {
            el('sub-expiring').className = 'badge bg-warning-lt';
            el('sub-expiring').textContent = data.expiring_soon + ' expiring soon';
        } else {
            el('sub-expiring').className = 'badge bg-success-lt';
            el('sub-expiring').textContent = 'None expiring';
        }
    }
}
