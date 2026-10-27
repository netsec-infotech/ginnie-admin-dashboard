'use strict';

(function () {
    const config = window.adDashboardConfig || {};
    const dataUrl = config.dataUrl;
    const currency = config.currencySymbol || '$';
    let currentDays = 7;
    let charts = {};

    function init() {
        renderCharts(config.initialData);
        bindPeriodSelector();
    }

    function bindPeriodSelector() {
        document.querySelectorAll('#periodSelector .period-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                document.querySelectorAll('#periodSelector .period-btn').forEach(function (b) {
                    b.classList.remove('active');
                });
                this.classList.add('active');
                currentDays = parseInt(this.dataset.days);
                fetchData(currentDays);
            });
        });
    }

    function fetchData(days) {
        axios.get(dataUrl, {
            params: { days: days },
            headers: { 'X-CSRF-TOKEN': csrfToken }
        }).then(function (response) {
            if (response.data.success) {
                renderCharts(response.data.data);
            }
        }).catch(function (error) {
            console.error('Dashboard data fetch failed', error);
        });
    }

    function renderCharts(data) {
        updateStatCards(data.stat_cards);
        renderSpendTrend(data.daily_series);
        renderStatusDonut(data.status_breakdown);
        renderClicksImpressions(data.daily_series);
        renderTopCampaigns(data.top_campaigns);
    }

    function updateStatCards(stats) {
        var el;
        el = document.getElementById('statSpent');
        if (el) el.textContent = stats.formatted_spent;
        el = document.getElementById('statClicks');
        if (el) el.textContent = Number(stats.total_clicks).toLocaleString();
        el = document.getElementById('statImpressions');
        if (el) el.textContent = Number(stats.total_impressions).toLocaleString();
        el = document.getElementById('statCtr');
        if (el) el.textContent = stats.ctr + '%';
    }

    function renderSpendTrend(series) {
        var dates = series.map(function (d) { return d.date; });
        var spent = series.map(function (d) { return d.spent; });

        var options = {
            chart: { type: 'area', height: 280, toolbar: { show: false }, fontFamily: 'inherit' },
            series: [{ name: 'Spend', data: spent }],
            xaxis: { categories: dates, type: 'datetime', labels: { datetimeUTC: false } },
            yaxis: {
                labels: {
                    formatter: function (val) { return currency + val.toFixed(2); }
                }
            },
            colors: ['#206bc4'],
            fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.45, opacityTo: 0.05, stops: [50, 100] } },
            stroke: { width: 2, curve: 'smooth' },
            dataLabels: { enabled: false },
            tooltip: {
                y: { formatter: function (val) { return currency + val.toFixed(2); } }
            },
            grid: { strokeDashArray: 4, padding: { left: 0, right: 0 } },
        };

        if (charts.spend) charts.spend.destroy();
        charts.spend = new ApexCharts(document.querySelector('#spendTrendChart'), options);
        charts.spend.render();
    }

    function renderStatusDonut(breakdown) {
        if (!breakdown.series.length) {
            document.querySelector('#statusDonutChart').innerHTML = '<div class="text-center text-muted py-5">No campaigns yet</div>';
            return;
        }

        var options = {
            chart: { type: 'donut', height: 280, fontFamily: 'inherit' },
            series: breakdown.series,
            labels: breakdown.labels,
            colors: breakdown.colors,
            legend: { position: 'bottom', fontSize: '12px' },
            plotOptions: {
                pie: {
                    donut: { size: '60%', labels: { show: true, total: { show: true, label: 'Total' } } }
                }
            },
            dataLabels: { enabled: false },
        };

        if (charts.donut) charts.donut.destroy();
        charts.donut = new ApexCharts(document.querySelector('#statusDonutChart'), options);
        charts.donut.render();
    }

    function renderClicksImpressions(series) {
        var dates = series.map(function (d) { return d.date; });
        var clicks = series.map(function (d) { return d.clicks; });
        var impressions = series.map(function (d) { return d.impressions; });

        var options = {
            chart: { type: 'line', height: 280, toolbar: { show: false }, fontFamily: 'inherit' },
            series: [
                { name: 'Clicks', data: clicks },
                { name: 'Impressions', data: impressions },
            ],
            xaxis: { categories: dates, type: 'datetime', labels: { datetimeUTC: false } },
            yaxis: { labels: { formatter: function (val) { return Math.round(val); } } },
            colors: ['#2fb344', '#4299e1'],
            stroke: { width: 2, curve: 'smooth' },
            dataLabels: { enabled: false },
            legend: { position: 'top' },
            grid: { strokeDashArray: 4 },
        };

        if (charts.clicksImpr) charts.clicksImpr.destroy();
        charts.clicksImpr = new ApexCharts(document.querySelector('#clicksImpressionsChart'), options);
        charts.clicksImpr.render();
    }

    function renderTopCampaigns(campaigns) {
        var tbody = document.getElementById('topCampaignsBody');
        if (!tbody) return;

        if (!campaigns.length) {
            tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-4">No data available</td></tr>';
            return;
        }

        tbody.innerHTML = campaigns.map(function (c) {
            return '<tr>' +
                '<td>' + escapeHtml(c.product_title) + '</td>' +
                '<td>' + escapeHtml(c.seller_name) + '</td>' +
                '<td><span class="badge bg-' + c.status_class + '-lt">' + escapeHtml(c.status) + '</span></td>' +
                '<td>' + c.formatted_budget + '</td>' +
                '<td>' + c.formatted_spent + '</td>' +
                '<td>' + Number(c.clicks).toLocaleString() + '</td>' +
                '<td>' + Number(c.impressions).toLocaleString() + '</td>' +
                '<td>' + c.ctr + '%</td>' +
                '<td><div class="progress progress-sm"><div class="progress-bar" style="width:' + c.progress + '%"></div></div><small class="text-muted">' + c.progress + '%</small></td>' +
                '</tr>';
        }).join('');
    }

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    document.addEventListener('DOMContentLoaded', init);
})();
