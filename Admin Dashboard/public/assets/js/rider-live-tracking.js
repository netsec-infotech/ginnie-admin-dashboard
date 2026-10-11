'use strict';

(function () {
    var config = window.__RIDER_TRACKING_CONFIG__;
    if (!config || !config.dataUrl) return;

    var mapEl = document.getElementById('riderTrackingMap');
    if (!mapEl) return;

    // ── Map init ──
    var defaultLat = config.defaultLatitude || 0;
    var defaultLng = config.defaultLongitude || 0;

    var map = L.map('riderTrackingMap', {
        scrollWheelZoom: true,
        zoomControl: true,
    }).setView([defaultLat, defaultLng], 13);

    L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Topo_Map/MapServer/tile/{z}/{y}/{x}', {
        attribution: '&copy; OpenStreetMap contributors',
        maxZoom: 19,
    }).addTo(map);

    var markerBase = (config.assetBase || '/assets/images/map-markers') + '/';
    var riderIconUrl = markerBase + 'rider.png';

    // ── State ──
    var riders = {};        // id → { marker, data, prevLatLng }
    var pollTimer = null;
    var activeRiderId = null;
    var sidebarFilter = '';
    var statusFilter = '';

    // ── Status colours ──
    var statusColors = {
        on_delivery: '#206bc4',
        available: '#2fb344',
        idle: '#f76707',
    };

    // ── Icon helpers ──
    function makeRiderIcon(bearing, status) {
        var borderColor = statusColors[status] || statusColors.idle;
        return L.divIcon({
            className: 'rider-tracking-icon',
            html: '<div style="position:relative;width:40px;height:40px;">' +
                '<img src="' + riderIconUrl + '" style="' +
                    'width:36px;height:auto;display:block;' +
                    'border-radius:50%;border:3px solid ' + borderColor + ';' +
                    'transform:rotate(' + (bearing || 0) + 'deg);' +
                    'transform-origin:center center;' +
                    'transition:transform 0.6s ease;"' +
                ' />' +
                '</div>',
            iconSize: [40, 40],
            iconAnchor: [20, 20],
        });
    }

    // ── Bearing calculation ──
    function calcBearing(from, to) {
        if (!from || !to) return 0;
        var dLng = (to[1] - from[1]) * Math.PI / 180;
        var lat1 = from[0] * Math.PI / 180;
        var lat2 = to[0] * Math.PI / 180;
        var y = Math.sin(dLng) * Math.cos(lat2);
        var x = Math.cos(lat1) * Math.sin(lat2) - Math.sin(lat1) * Math.cos(lat2) * Math.cos(dLng);
        return ((Math.atan2(y, x) * 180 / Math.PI) + 360) % 360;
    }

    // ── Smooth marker animation ──
    function animateMarker(marker, from, to, duration, bearing, status) {
        var start = null;
        function step(ts) {
            if (!start) start = ts;
            var t = Math.min((ts - start) / duration, 1);
            var ease = 1 - Math.pow(1 - t, 3); // ease-out cubic
            var lat = from[0] + (to[0] - from[0]) * ease;
            var lng = from[1] + (to[1] - from[1]) * ease;
            marker.setLatLng([lat, lng]);
            if (t < 1) {
                requestAnimationFrame(step);
            }
        }
        marker.setIcon(makeRiderIcon(bearing, status));
        requestAnimationFrame(step);
    }

    // ── Sidebar rendering ──
    function renderSidebar(riderList) {
        var container = document.getElementById('riderSidebarList');
        var emptyMsg = document.getElementById('riderSidebarEmpty');
        var countEl = document.getElementById('riderMapCount');

        // Filter by search + status
        var filtered = riderList.filter(function (r) {
            var matchName = !sidebarFilter || (r.full_name || '').toLowerCase().indexOf(sidebarFilter.toLowerCase()) !== -1;
            var matchStatus = !statusFilter || r.delivery_status === statusFilter;
            return matchName && matchStatus;
        });

        // Sort: on_delivery first, then available, then idle
        var order = { on_delivery: 0, available: 1, idle: 2 };
        filtered.sort(function (a, b) {
            return (order[a.delivery_status] || 3) - (order[b.delivery_status] || 3);
        });

        if (countEl) {
            countEl.textContent = riderList.length + ' rider' + (riderList.length !== 1 ? 's' : '') + ' in view';
        }

        // Remove old items (keep the empty placeholder)
        var oldItems = container.querySelectorAll('.rider-sidebar-item');
        for (var i = 0; i < oldItems.length; i++) {
            oldItems[i].remove();
        }

        if (filtered.length === 0) {
            if (emptyMsg) emptyMsg.style.display = '';
            return;
        }
        if (emptyMsg) emptyMsg.style.display = 'none';

        filtered.forEach(function (r) {
            var item = document.createElement('a');
            item.className = 'list-group-item list-group-item-action rider-sidebar-item'
                + (activeRiderId === r.id ? ' active' : '');
            item.href = 'javascript:void(0)';
            item.setAttribute('data-rider-id', r.id);
            item.innerHTML =
                '<div class="d-flex align-items-center">' +
                    '<span class="rider-status-dot ' + escapeHtml(r.delivery_status) + ' me-2"></span>' +
                    '<div class="flex-fill">' +
                        '<div class="fw-medium">' + escapeHtml(r.full_name || 'Rider #' + r.id) + '</div>' +
                        '<small class="text-secondary">' + escapeHtml(r.phone || '') + '</small>' +
                    '</div>' +
                    '<a href="' + config.riderDetailUrl.replace(':id', r.id) + '" ' +
                        'class="btn btn-ghost-primary btn-sm" title="View details" onclick="event.stopPropagation();">' +
                        '<svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-external-link" ' +
                            'width="16" height="16" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" ' +
                            'fill="none" stroke-linecap="round" stroke-linejoin="round">' +
                            '<path stroke="none" d="M0 0h24v24H0z" fill="none"/>' +
                            '<path d="M12 6h-6a2 2 0 0 0 -2 2v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2 -2v-6"/>' +
                            '<path d="M11 13l9 -9"/><path d="M15 4h5v5"/>' +
                        '</svg>' +
                    '</a>' +
                '</div>';
            item.addEventListener('click', function () {
                focusRider(r.id);
            });
            container.appendChild(item);
        });
    }

    function focusRider(id) {
        activeRiderId = id;
        var entry = riders[id];
        if (entry && entry.marker) {
            map.setView(entry.marker.getLatLng(), Math.max(map.getZoom(), 14));
            entry.marker.openPopup();
        }
        // Highlight sidebar item
        var items = document.querySelectorAll('.rider-sidebar-item');
        for (var i = 0; i < items.length; i++) {
            items[i].classList.toggle('active', items[i].getAttribute('data-rider-id') == id);
        }
    }

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str || ''));
        return div.innerHTML;
    }

    // ── Data polling ──
    function fetchRiders() {
        var bounds = map.getBounds();
        var ne = bounds.getNorthEast();
        var sw = bounds.getSouthWest();

        axios.get(config.dataUrl, {
            params: {
                ne_lat: ne.lat,
                ne_lng: ne.lng,
                sw_lat: sw.lat,
                sw_lng: sw.lng,
            },
        }).then(function (resp) {
            var data = resp.data;
            if (!data.success) return;

            var incoming = data.data.riders || [];
            var incomingIds = {};

            incoming.forEach(function (r) {
                incomingIds[r.id] = true;
                var existing = riders[r.id];

                if (existing) {
                    // Update position with animation
                    var prevLL = [existing.data.latitude, existing.data.longitude];
                    var newLL = [r.latitude, r.longitude];
                    var bearing = calcBearing(prevLL, newLL);
                    var dist = Math.abs(prevLL[0] - newLL[0]) + Math.abs(prevLL[1] - newLL[1]);
                    if (dist > 0.00001) {
                        animateMarker(existing.marker, prevLL, newLL, 1500, bearing, r.delivery_status);
                    } else {
                        existing.marker.setIcon(makeRiderIcon(0, r.delivery_status));
                    }
                    existing.data = r;
                    existing.marker.setPopupContent(makePopup(r));
                } else {
                    // New rider — add marker
                    var marker = L.marker([r.latitude, r.longitude], {
                        icon: makeRiderIcon(0, r.delivery_status),
                        zIndexOffset: r.delivery_status === 'on_delivery' ? 1000 : 0,
                    }).addTo(map);
                    marker.bindPopup(makePopup(r));
                    marker.on('click', function () { focusRider(r.id); });
                    riders[r.id] = { marker: marker, data: r };
                }
            });

            // Remove riders no longer in bounds
            Object.keys(riders).forEach(function (id) {
                if (!incomingIds[id]) {
                    map.removeLayer(riders[id].marker);
                    delete riders[id];
                }
            });

            // Build list for sidebar
            var riderList = Object.keys(riders).map(function (id) { return riders[id].data; });
            renderSidebar(riderList);

        }).catch(function (err) {
            console.error('Rider tracking poll error', err);
        });
    }

    function makePopup(r) {
        var statusLabel = {
            on_delivery: 'On Delivery',
            available: 'Available',
            idle: 'Idle',
        };
        return '<div style="min-width:160px;">' +
            '<strong>' + escapeHtml(r.full_name || 'Rider #' + r.id) + '</strong><br>' +
            (r.phone ? '<small>' + escapeHtml(r.phone) + '</small><br>' : '') +
            '<span class="badge" style="background:' + (statusColors[r.delivery_status] || '#aaa') + ';color:#fff;">' +
                escapeHtml(statusLabel[r.delivery_status] || r.delivery_status) +
            '</span><br>' +
            '<a href="' + config.riderDetailUrl.replace(':id', r.id) + '" class="small">View details &rarr;</a>' +
            '</div>';
    }

    // ── Poll on map move + interval ──
    function startPolling() {
        fetchRiders();
        clearInterval(pollTimer);
        pollTimer = setInterval(fetchRiders, config.pollInterval || 15000);
    }

    map.on('moveend', function () {
        fetchRiders();
    });

    // ── Sidebar search + filter ──
    var searchInput = document.getElementById('riderSearchInput');
    if (searchInput) {
        searchInput.addEventListener('input', function () {
            sidebarFilter = this.value;
            var riderList = Object.keys(riders).map(function (id) { return riders[id].data; });
            renderSidebar(riderList);
        });
    }

    var statusSelect = document.getElementById('riderStatusFilter');
    if (statusSelect) {
        statusSelect.addEventListener('change', function () {
            statusFilter = this.value;
            var riderList = Object.keys(riders).map(function (id) { return riders[id].data; });
            renderSidebar(riderList);
        });
    }

    // ── Kick off ──
    startPolling();
})();
