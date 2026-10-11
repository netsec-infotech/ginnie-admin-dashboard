'use strict';

document.addEventListener('DOMContentLoaded', function () {
    initMap();
    initTabState();
    initTabDatatableAdjust();
});

function initMap() {
    var config = window.__SELLER_DETAIL_CONFIG__;
    if (!config || !config.hasLocation) {
        var mapEl = document.getElementById('seller-map');
        if (mapEl) {
            mapEl.innerHTML = '<div class="d-flex align-items-center justify-content-center h-100 text-muted"><i class="ti ti-map-pin-off me-2"></i>No location data available</div>';
            mapEl.style.background = '#f8f9fa';
        }
        return;
    }

    var lat = config.latitude;
    var lng = config.longitude;

    var map = L.map('seller-map').setView([lat, lng], 15);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
    }).addTo(map);

    L.marker([lat, lng])
        .addTo(map)
        .bindPopup('<strong>' + escapeHtml(config.name) + '</strong><br>Seller location')
        .openPopup();

    document.querySelectorAll('[data-bs-toggle="tab"]').forEach(function (tab) {
        tab.addEventListener('shown.bs.tab', function () {
            map.invalidateSize();
        });
    });
}

function initTabDatatableAdjust() {
    document.querySelectorAll('[data-bs-toggle="tab"]').forEach(function (tab) {
        tab.addEventListener('shown.bs.tab', function () {
            if (typeof $ !== 'undefined' && $.fn.DataTable) {
                $.fn.dataTable.tables({ visible: true, api: true }).columns.adjust().responsive.recalc();
            }
        });
    });
}

function initTabState() {
    var activeHash = window.location.hash;
    if (activeHash) {
        var activeTab = document.querySelector('[data-bs-toggle="tab"][href="' + activeHash + '"]');
        if (activeTab && typeof bootstrap !== 'undefined' && bootstrap.Tab) {
            bootstrap.Tab.getOrCreateInstance(activeTab).show();
        }
    }

    document.querySelectorAll('[data-bs-toggle="tab"]').forEach(function (tab) {
        tab.addEventListener('shown.bs.tab', function (event) {
            var target = event.target.getAttribute('href');
            if (target) {
                history.replaceState(null, '', target);
            }
        });
    });
}

function escapeHtml(text) {
    var div = document.createElement('div');
    div.appendChild(document.createTextNode(text));
    return div.innerHTML;
}
