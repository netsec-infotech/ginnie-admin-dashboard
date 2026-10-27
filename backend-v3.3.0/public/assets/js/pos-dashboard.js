'use strict';

document.addEventListener('DOMContentLoaded', function () {
    if (typeof posDashboardData === 'undefined') return;

    // ── Chart instances (held for destroy-on-reload) ──
    let salesTrendChart = null;
    let paymentDonutChart = null;
    let storeRevenueChart = null;
    let customerDonutChart = null;

    const chartColors = ['#206bc4', '#79a6dc', '#bfe399', '#e9ecf1', '#f59f00', '#d63939', '#2fb344', '#ae3ec9', '#0ca678', '#4263eb'];

    // ── Initialise all charts ──
    initSalesTrendChart(posDashboardData.salesTrend);
    initPaymentDonutChart(posDashboardData.paymentBreakdown);
    initStoreRevenueChart(posDashboardData.storeRevenueSplit);
    initCustomerDonutChart(posDashboardData.customerBreakdown);

    // ── Filter handlers ──
    const storeFilter = document.getElementById('posStoreFilter');
    const rangeFilter = document.getElementById('posRangeFilter');
    const customGroup = document.getElementById('customDateGroup');
    const dateFrom = document.getElementById('posDateFrom');
    const dateTo = document.getElementById('posDateTo');
    const applyBtn = document.getElementById('posApplyCustom');

    rangeFilter.addEventListener('change', function () {
        if (this.value === 'custom') {
            customGroup.style.display = 'flex';
        } else {
            customGroup.style.display = 'none';
            reloadDashboard();
        }
    });

    storeFilter.addEventListener('change', reloadDashboard);

    if (applyBtn) {
        applyBtn.addEventListener('click', function () {
            if (dateFrom.value && dateTo.value) {
                reloadDashboard();
            }
        });
    }

    function reloadDashboard() {
        const params = {
            range: rangeFilter.value,
            store_id: storeFilter.value || '',
        };
        if (rangeFilter.value === 'custom') {
            params.date_from = dateFrom.value;
            params.date_to = dateTo.value;
        }

        axios.get(posDashboardUrl, { params: params })
            .then(function (response) {
                const d = response.data;
                updateKPIs(d);
                updateTopProducts(d.topProducts || []);
                updateDiscounts(d.discountSummary || {});
                updateLowStock(d.lowStock || []);

                // Re-render charts
                destroyAndInit('salesTrend', d.salesTrend);
                destroyAndInit('paymentDonut', d.paymentBreakdown);
                destroyAndInit('storeRevenue', d.storeRevenueSplit);
                destroyAndInit('customerDonut', d.customerBreakdown);
            })
            .catch(function (err) {
                console.error('POS dashboard reload error:', err);
            });
    }

    function destroyAndInit(chartName, data) {
        if (chartName === 'salesTrend') {
            if (salesTrendChart) { salesTrendChart.destroy(); salesTrendChart = null; }
            initSalesTrendChart(data);
        } else if (chartName === 'paymentDonut') {
            if (paymentDonutChart) { paymentDonutChart.destroy(); paymentDonutChart = null; }
            initPaymentDonutChart(data);
        } else if (chartName === 'storeRevenue') {
            if (storeRevenueChart) { storeRevenueChart.destroy(); storeRevenueChart = null; }
            initStoreRevenueChart(data);
        } else if (chartName === 'customerDonut') {
            if (customerDonutChart) { customerDonutChart.destroy(); customerDonutChart = null; }
            initCustomerDonutChart(data);
        }
    }

    // ── KPI card updates ──
    function updateKPIs(d) {
        const s = d.salesSummary || {};
        const r = d.refundSummary || {};
        const p = d.parkedSales || {};
        const c = d.customerBreakdown || {};

        setText('kpiRevenue', s.formatted_revenue_display || s.formatted_revenue || '—');
        setText('kpiEarnings', s.formatted_earnings_display || s.formatted_earnings || '—');
        setText('kpiOrderCount', (s.total_orders || 0) + ' ' + (s.total_orders === 1 ? 'order' : 'orders'));
        setText('kpiAvgValue', s.formatted_avg_value || '—');
        setText('kpiRefundTotal', r.formatted_refund_total_display || r.formatted_refund_total || '—');
        setText('kpiRefundRate', (r.refund_count || 0) + ' refunds (' + (r.refund_rate || 0) + '%)');
        setText('kpiParkedCount', p.count || 0);
        setText('kpiParkedTotal', 'Worth ' + (p.formatted_total || '—'));
        setText('kpiRegistered', c.registered_count || 0);
        setText('kpiRegisteredPct', (c.registered_pct || 0) + '%');
        setText('kpiWalkin', c.walkin_count || 0);
        setText('kpiWalkinPct', (c.walkin_pct || 0) + '%');
        setText('kpiUnique', c.unique_customers || 0);
    }

    function updateTopProducts(products) {
        const tbody = document.querySelector('#topProductsTable tbody');
        if (!tbody) return;
        if (!products.length) {
            tbody.innerHTML = '<tr><td colspan="3" class="text-center text-secondary">No data</td></tr>';
            return;
        }
        tbody.innerHTML = products.map(function (p) {
            return '<tr><td>' + escapeHtml(p.title) + '</td><td class="text-end">' + p.qty_sold + '</td><td class="text-end">' + escapeHtml(p.formatted_revenue) + '</td></tr>';
        }).join('');
    }

    function updateDiscounts(d) {
        setText('kpiPromoOrders', d.promo_orders || 0);
        setText('kpiPromoTotal', '-' + (d.formatted_promo_total || '—'));
        setText('kpiWalletOrders', d.wallet_orders || 0);
        setText('kpiWalletTotal', d.formatted_wallet_total || '—');
        setText('kpiCashierDiscount', '-' + (d.formatted_cashier_discount || '—'));
    }

    function updateLowStock(items) {
        const table = document.getElementById('lowStockTable');
        if (!table) return;
        const tbody = table.querySelector('tbody');
        const card = table.closest('.row.row-cards');
        if (!items.length) {
            if (card) card.style.display = 'none';
            return;
        }
        if (card) card.style.display = '';
        tbody.innerHTML = items.map(function (i) {
            const badgeClass = i.stock <= 3 ? 'bg-danger-lt' : 'bg-warning-lt';
            return '<tr><td>' + escapeHtml(i.product) + '</td><td>' + (escapeHtml(i.variant) || '—') + '</td><td>' + escapeHtml(i.store) + '</td><td class="text-end"><span class="badge ' + badgeClass + '">' + i.stock + '</span></td></tr>';
        }).join('');
    }

    // ── CHARTS ──

    function initSalesTrendChart(trend) {
        const el = document.getElementById('chart-pos-sales-trend');
        if (!el || !window.ApexCharts) return;
        if (!trend || !trend.data || !trend.data.length) {
            el.innerHTML = '<div class="text-center text-secondary py-5">No sales data for this period</div>';
            return;
        }

        const categories = trend.data.map(function (d) {
            if (trend.is_hourly) {
                const h = new Date(d.period).getHours();
                return (h % 12 || 12) + (h < 12 ? ' AM' : ' PM');
            }
            return d.period;
        });

        salesTrendChart = new ApexCharts(el, {
            chart: { height: 290, type: 'area', toolbar: { show: false }, zoom: { enabled: false } },
            series: [
                { name: 'Revenue', data: trend.data.map(function (d) { return d.revenue; }) },
                { name: 'Orders', data: trend.data.map(function (d) { return d.count; }) },
            ],
            xaxis: { categories: categories, labels: { rotate: -45, style: { fontSize: '11px' } } },
            yaxis: [
                { title: { text: 'Revenue' }, labels: { formatter: function (v) { return formatCompact(v); } } },
                { opposite: true, title: { text: 'Orders' }, labels: { formatter: function (v) { return Math.round(v); } } },
            ],
            stroke: { curve: 'smooth', width: [2, 2] },
            fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.1 } },
            colors: ['#206bc4', '#2fb344'],
            tooltip: { shared: true, intersect: false },
            dataLabels: { enabled: false },
            grid: { strokeDashArray: 4 },
        });
        salesTrendChart.render();
    }

    function initPaymentDonutChart(breakdown) {
        const el = document.getElementById('chart-pos-payment-donut');
        if (!el || !window.ApexCharts) return;
        if (!breakdown || !breakdown.length) {
            el.innerHTML = '<div class="text-center text-secondary py-5">No payment data</div>';
            return;
        }

        const labels = breakdown.map(function (b) { return capitalize(b.method); });
        const series = breakdown.map(function (b) { return b.amount; });

        paymentDonutChart = new ApexCharts(el, {
            chart: { height: 290, type: 'donut' },
            series: series,
            labels: labels,
            colors: chartColors.slice(0, labels.length),
            legend: { position: 'bottom', fontSize: '13px' },
            plotOptions: { pie: { donut: { size: '60%', labels: { show: true, total: { show: true, label: 'Total', formatter: function (w) { return formatCompact(w.globals.seriesTotals.reduce(function (a, b) { return a + b; }, 0)); } } } } } },
            dataLabels: { enabled: false },
            tooltip: { y: { formatter: function (val) { return formatCompact(val); } } },
        });
        paymentDonutChart.render();
    }

    function initStoreRevenueChart(data) {
        const el = document.getElementById('chart-pos-store-revenue');
        if (!el || !window.ApexCharts) return;
        if (!data || !data.length) {
            el.innerHTML = '<div class="text-center text-secondary py-5">No store data</div>';
            return;
        }

        storeRevenueChart = new ApexCharts(el, {
            chart: { height: 290, type: 'bar', toolbar: { show: false } },
            series: [{ name: 'Revenue', data: data.map(function (d) { return d.revenue; }) }],
            xaxis: { categories: data.map(function (d) { return d.name; }), labels: { style: { fontSize: '12px' } } },
            plotOptions: { bar: { horizontal: true, borderRadius: 4, barHeight: '60%' } },
            colors: ['#206bc4'],
            dataLabels: {
                enabled: true,
                formatter: function (val, opts) {
                    return data[opts.dataPointIndex].formatted_revenue;
                },
                style: { fontSize: '12px' },
            },
            tooltip: { y: { formatter: function (val, opts) { return data[opts.w.globals.dataPointIndex].formatted_revenue; } } },
            grid: { strokeDashArray: 4 },
        });
        storeRevenueChart.render();
    }

    function initCustomerDonutChart(cust) {
        const el = document.getElementById('chart-pos-customer-donut');
        if (!el || !window.ApexCharts) return;
        if (!cust || cust.total === 0) {
            el.innerHTML = '<div class="text-center text-secondary py-4">No data</div>';
            return;
        }

        customerDonutChart = new ApexCharts(el, {
            chart: { height: 200, type: 'donut' },
            series: [cust.registered_count || 0, cust.walkin_count || 0],
            labels: ['Registered', 'Walk-in'],
            colors: ['#206bc4', '#17a2b8'],
            legend: { show: false },
            plotOptions: { pie: { donut: { size: '65%', labels: { show: true, total: { show: true, label: 'Total', formatter: function () { return cust.total; } } } } } },
            dataLabels: { enabled: false },
        });
        customerDonutChart.render();
    }

    // ── Helpers ──
    function setText(id, text) {
        const el = document.getElementById(id);
        if (el) el.textContent = text;
    }

    function escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    function capitalize(str) {
        if (!str) return '';
        return str.charAt(0).toUpperCase() + str.slice(1).replace(/_/g, ' ');
    }

    function formatCompact(num) {
        if (num >= 1000000) return (num / 1000000).toFixed(1) + 'M';
        if (num >= 1000) return (num / 1000).toFixed(1) + 'K';
        return Number(num).toLocaleString();
    }
});
