'use strict';

(function () {
    var config = window.__TRACKING_CONFIG__;
    if (!config || !config.url) return;

    var mapEl = document.getElementById('liveTrackingMap');
    if (!mapEl) return;

    var map = L.map('liveTrackingMap', {
        scrollWheelZoom: true,
        zoomControl: true,
    }).setView([0, 0], 13);

    L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Topo_Map/MapServer/tile/{z}/{y}/{x}', {
        attribution: '&copy; OpenStreetMap contributors',
        maxZoom: 19,
    }).addTo(map);

    // Asset-based icons — swap the files in public/assets/images/map-markers/ to customise.
    var markerBase = (config.assetBase || '/assets/images/map-markers') + '/';
    var iconSize = [36, "auto"];
    var iconAnchor = [18, 18];

    // Rider uses a divIcon so we can rotate the image to face the direction of travel
    var riderIconUrl = markerBase + 'rider.png';
    function makeRiderIcon(bearing) {
        return L.divIcon({
            className: 'rider-tracking-icon',
            html: '<img src="' + riderIconUrl + '" style="' +
                'width:36px;height:auto;display:block;' +
                'transform:rotate(' + (bearing || 0) + 'deg);' +
                'transform-origin:center center;' +
                'transition:transform 0.6s ease;"' +
                ' />',
            iconSize: iconSize,
            iconAnchor: iconAnchor,
        });
    }
    // Store icons with route-order badge
    function makeStoreIcon(index, isCollected) {
        var imgSrc = markerBase + (isCollected ? 'store-collected.png' : 'store.png');
        var badgeBg = isCollected ? '#2fb344' : '#f76707';
        var badgeText = isCollected ? '✓' : (index + 1);
        return L.divIcon({
            className: 'store-tracking-icon',
            html: '<div style="position:relative;width:36px;height:36px;">' +
                '<img src="' + imgSrc + '" style="width:36px;height:auto;display:block;" />' +
                '<span style="' +
                    'position:absolute;top:-6px;right:-6px;' +
                    'min-width:18px;height:18px;line-height:18px;' +
                    'border-radius:50%;background:' + badgeBg + ';' +
                    'color:#fff;font-size:11px;font-weight:700;' +
                    'text-align:center;border:2px solid #fff;' +
                    'box-shadow:0 1px 3px rgba(0,0,0,.3);' +
                '">' + badgeText + '</span>' +
                '</div>',
            iconSize: [36, 36],
            iconAnchor: [18, 18],
        });
    }
    var customerIcon = L.icon({
        iconUrl: markerBase + 'customer.png',
        iconSize: iconSize,
        iconAnchor: iconAnchor,
    });

    // Markers & polyline state
    var riderMarker = null;
    var storeMarkers = [];
    var customerMarker = null;
    var routeLines = [];
    var statusBadge = document.getElementById('trackingStatusBadge');
    var pollTimer = null;
    var firstLoad = true;
    var prevRiderLat = null;
    var prevRiderLng = null;
    var currentBearing = 0;
    var animationId = null;

    /**
     * Calculate bearing (0-360) from point A to point B.
     * 0 = north, 90 = east, 180 = south, 270 = west.
     */
    function calcBearing(lat1, lng1, lat2, lng2) {
        var dLng = (lng2 - lng1) * Math.PI / 180;
        var y = Math.sin(dLng) * Math.cos(lat2 * Math.PI / 180);
        var x = Math.cos(lat1 * Math.PI / 180) * Math.sin(lat2 * Math.PI / 180) -
                Math.sin(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) * Math.cos(dLng);
        var brng = Math.atan2(y, x) * 180 / Math.PI;
        return (brng + 360) % 360;
    }

    /**
     * Smoothly animate the rider marker from its current position to a new one.
     */
    function animateRider(marker, fromLat, fromLng, toLat, toLng, duration) {
        if (animationId) cancelAnimationFrame(animationId);
        var start = null;
        duration = duration || 1500;

        function step(timestamp) {
            if (!start) start = timestamp;
            var progress = Math.min((timestamp - start) / duration, 1);
            // Ease-out cubic for natural deceleration
            var ease = 1 - Math.pow(1 - progress, 3);
            var lat = fromLat + (toLat - fromLat) * ease;
            var lng = fromLng + (toLng - fromLng) * ease;
            marker.setLatLng([lat, lng]);
            if (progress < 1) {
                animationId = requestAnimationFrame(step);
            } else {
                animationId = null;
            }
        }
        animationId = requestAnimationFrame(step);
    }

    /**
     * Rotate the rider icon image to face the current bearing.
     */
    function rotateRiderIcon(bearing) {
        if (!riderMarker) return;
        var el = riderMarker.getElement();
        if (!el) return;
        var img = el.querySelector('img');
        if (img) {
            img.style.transform = 'rotate(' + bearing + 'deg)';
        }
    }

    function clearStoreMarkers() {
        for (var i = 0; i < storeMarkers.length; i++) {
            map.removeLayer(storeMarkers[i]);
        }
        storeMarkers = [];
    }

    // OSRM public routing endpoint (free, no API key)
    var osrmBase = 'https://router.project-osrm.org/route/v1/driving/';
    var lastRouteKey = '';

    function clearRouteLines() {
        for (var i = 0; i < routeLines.length; i++) {
            map.removeLayer(routeLines[i]);
        }
        routeLines = [];
    }

    /**
     * Build waypoints array from stores + customer, with a start point from rider.
     * Returns { allCoords: [[lng,lat],...], segments: [{startIdx, endIdx, isCollected},...] }
     */
    function buildWaypoints(riderLoc, stores, customerLoc) {
        var coords = [];
        var segments = [];

        // Start: rider location or first store
        var startLat, startLng;
        if (riderLoc && riderLoc.latitude && riderLoc.longitude) {
            startLat = riderLoc.latitude;
            startLng = riderLoc.longitude;
        } else if (stores.length && stores[0].latitude) {
            startLat = stores[0].latitude;
            startLng = stores[0].longitude;
        } else {
            return null;
        }
        coords.push([startLng, startLat]); // OSRM uses lng,lat

        for (var i = 0; i < stores.length; i++) {
            if (!stores[i].latitude || !stores[i].longitude) continue;
            var idx = coords.length;
            coords.push([stores[i].longitude, stores[i].latitude]);
            segments.push({ startIdx: idx - 1, endIdx: idx, isCollected: stores[i].is_collected });
        }

        if (customerLoc && customerLoc.latitude && customerLoc.longitude) {
            var idx = coords.length;
            coords.push([customerLoc.longitude, customerLoc.latitude]);
            segments.push({ startIdx: idx - 1, endIdx: idx, isCollected: false });
        }

        if (coords.length < 2) return null;
        return { allCoords: coords, segments: segments };
    }

    /**
     * Draw straight-line fallback when OSRM is unavailable.
     */
    function drawStraightFallback(riderLoc, stores, customerLoc) {
        clearRouteLines();
        var points = [];
        if (riderLoc && riderLoc.latitude) points.push([riderLoc.latitude, riderLoc.longitude]);
        for (var i = 0; i < stores.length; i++) {
            if (stores[i].latitude) points.push([stores[i].latitude, stores[i].longitude]);
        }
        if (customerLoc && customerLoc.latitude) points.push([customerLoc.latitude, customerLoc.longitude]);

        // Determine which segments are collected
        var prevPt = null;
        var storeIdx = 0;
        for (var i = 0; i < points.length; i++) {
            if (prevPt) {
                var isDone = false;
                // First segment is rider→first store, check if that store is collected
                // Subsequent segments check the destination store
                if (storeIdx < stores.length) {
                    isDone = stores[storeIdx].is_collected;
                    storeIdx++;
                }
                var line = L.polyline([prevPt, points[i]], isDone
                    ? { color: '#2fb344', weight: 4, opacity: 0.6 }
                    : { color: '#206bc4', weight: 4, opacity: 0.7, dashArray: null }
                ).addTo(map);
                routeLines.push(line);
            }
            prevPt = points[i];
        }
    }

    /**
     * Fetch OSRM road route and draw per-segment polylines.
     * Falls back to straight lines on failure.
     */
    function buildRoute(riderLoc, stores, customerLoc) {
        var wp = buildWaypoints(riderLoc, stores, customerLoc);
        if (!wp) { clearRouteLines(); return; }

        // Build a cache key to avoid refetching when nothing moved
        var routeKey = wp.allCoords.map(function (c) {
            return c[0].toFixed(4) + ',' + c[1].toFixed(4);
        }).join('|');
        if (routeKey === lastRouteKey && routeLines.length > 0) return;
        lastRouteKey = routeKey;

        // OSRM coordinate string: lng,lat;lng,lat;...
        var coordStr = wp.allCoords.map(function (c) { return c[0] + ',' + c[1]; }).join(';');
        var url = osrmBase + coordStr + '?overview=full&geometries=geojson&steps=false';

        axios.get(url, { timeout: 5000 })
            .then(function (response) {
                var data = response.data;
                if (!data || data.code !== 'Ok' || !data.routes || !data.routes[0]) {
                    drawStraightFallback(riderLoc, stores, customerLoc);
                    return;
                }

                clearRouteLines();

                // Split the full geometry by waypoint positions to colour each leg
                var fullCoords = data.routes[0].geometry.coordinates;
                var latLngs = fullCoords.map(function (c) { return [c[1], c[0]]; });

                // Waypoint indices in the full geometry — OSRM returns these
                var waypointIndices = [0]; // start
                if (data.waypoints) {
                    // Approximate: find closest geometry point to each waypoint
                    for (var w = 1; w < data.waypoints.length; w++) {
                        var wLat = data.waypoints[w].location[1];
                        var wLng = data.waypoints[w].location[0];
                        var bestIdx = 0;
                        var bestDist = Infinity;
                        // Search only forward from last index to keep ordering
                        var searchStart = waypointIndices[waypointIndices.length - 1];
                        for (var g = searchStart; g < fullCoords.length; g++) {
                            var d = Math.pow(fullCoords[g][0] - wLng, 2) + Math.pow(fullCoords[g][1] - wLat, 2);
                            if (d < bestDist) { bestDist = d; bestIdx = g; }
                        }
                        waypointIndices.push(bestIdx);
                    }
                }

                // Draw each segment between consecutive waypoints
                for (var i = 0; i < waypointIndices.length - 1 && i < wp.segments.length; i++) {
                    var fromIdx = waypointIndices[i];
                    var toIdx = waypointIndices[i + 1] + 1; // inclusive
                    var segCoords = latLngs.slice(fromIdx, toIdx);
                    var isDone = wp.segments[i].isCollected;

                    if (segCoords.length < 2) continue;

                    var line = L.polyline(segCoords, isDone
                        ? { color: '#2fb344', weight: 4, opacity: 0.7 }
                        : { color: '#0a66d3', weight: 4, opacity: 0.8, dashArray: null }
                    ).addTo(map);
                    routeLines.push(line);
                }
            })
            .catch(function () {
                drawStraightFallback(riderLoc, stores, customerLoc);
            });
    }

    function updateMap(data) {
        // Rider — animate movement + rotate icon to face travel direction
        if (data.rider && data.rider.latitude && data.rider.longitude) {
            var newLat = data.rider.latitude;
            var newLng = data.rider.longitude;

            if (!riderMarker) {
                riderMarker = L.marker([newLat, newLng], {
                    icon: makeRiderIcon(0),
                    zIndexOffset: 1000,
                }).bindTooltip('Rider', { permanent: false, direction: 'top' })
                  .addTo(map);
                prevRiderLat = newLat;
                prevRiderLng = newLng;
            } else {
                // Calculate bearing and animate only if position actually changed
                var moved = prevRiderLat !== null
                    && (Math.abs(newLat - prevRiderLat) > 0.00001
                    ||  Math.abs(newLng - prevRiderLng) > 0.00001);

                if (moved) {
                    currentBearing = calcBearing(prevRiderLat, prevRiderLng, newLat, newLng);
                    rotateRiderIcon(currentBearing);
                    animateRider(riderMarker, prevRiderLat, prevRiderLng, newLat, newLng, 1500);
                }

                prevRiderLat = newLat;
                prevRiderLng = newLng;
            }
        }

        // Stores — numbered by route order
        clearStoreMarkers();
        if (data.stores && data.stores.length) {
            for (var i = 0; i < data.stores.length; i++) {
                var s = data.stores[i];
                if (!s.latitude || !s.longitude) continue;
                var tooltip = '#' + (i + 1) + ' ' + s.name + (s.is_collected ? ' ✓' : '');
                var m = L.marker([s.latitude, s.longitude], {
                    icon: makeStoreIcon(i, s.is_collected),
                    zIndexOffset: 500,
                }).bindTooltip(tooltip, {
                    permanent: false,
                    direction: 'top',
                }).addTo(map);
                storeMarkers.push(m);
            }
        }

        // Customer
        if (data.customer && data.customer.latitude && data.customer.longitude) {
            var custLatLng = [data.customer.latitude, data.customer.longitude];
            if (!customerMarker) {
                customerMarker = L.marker(custLatLng, { icon: customerIcon, zIndexOffset: 500 })
                    .bindTooltip(data.customer.name || 'Customer', { permanent: false, direction: 'top' })
                    .addTo(map);
            } else {
                customerMarker.setLatLng(custLatLng);
            }
        }

        // Route polyline: Rider → Store A → Store B → … → Customer
        buildRoute(data.rider, data.stores || [], data.customer);

        // Fit bounds on first load
        if (firstLoad) {
            var allPoints = [];
            if (data.rider && data.rider.latitude) {
                allPoints.push([data.rider.latitude, data.rider.longitude]);
            }
            if (data.stores) {
                for (var i = 0; i < data.stores.length; i++) {
                    if (data.stores[i].latitude) {
                        allPoints.push([data.stores[i].latitude, data.stores[i].longitude]);
                    }
                }
            }
            if (data.customer && data.customer.latitude) {
                allPoints.push([data.customer.latitude, data.customer.longitude]);
            }
            if (allPoints.length > 1) {
                map.fitBounds(allPoints, { padding: [40, 40] });
            } else if (allPoints.length === 1) {
                map.setView(allPoints[0], 15);
            }
            firstLoad = false;
        }
    }

    function fetchTracking() {
        axios.get(config.url)
            .then(function (response) {
                var res = response.data;
                if (res.success && res.data) {
                    updateMap(res.data);
                    if (statusBadge) {
                        statusBadge.innerHTML = '<span class="badge-dot bg-green me-1"></span>Live';
                    }
                }
            })
            .catch(function () {
                if (statusBadge) {
                    statusBadge.innerHTML = '<span class="badge-dot bg-yellow me-1"></span>Reconnecting…';
                }
            });
    }

    // Initial fetch + start polling
    fetchTracking();
    pollTimer = setInterval(fetchTracking, config.pollInterval || 15000);

    // Invalidate map size after layout settles
    setTimeout(function () { map.invalidateSize(); }, 300);
})();
