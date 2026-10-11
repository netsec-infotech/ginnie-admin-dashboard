'use strict';

// Delivery zone polygon editor (Google Maps JS API, no deprecated APIs).
// - Vertex markers use AdvancedMarkerElement (pixel-sized DOM dots) instead of
//   geographic-radius Circles, so they look the same at every zoom.
// - Toolbar buttons live in the blade with Tabler classes — this file only
//   wires behaviour to existing DOM nodes (#draw-zone-btn, #undo-vertex-btn,
//   #finish-draw-btn, #cancel-draw-btn, #toggle-edit-btn, #clear-last,
//   #reset-zone) and the readout (#zone-readout, #zone-status, #zone-legend).
// - DrawingManager is not used; it was deprecated in Aug 2025.

let map;
let placeMarker;
let infoWindow;
let polygon = null;
let originalPolygon = null;
let center = { lat: 40.749933, lng: -73.98633 };
let otherZoneOverlays = []; // { polygon, label, centroid, vertices }
let cachedOtherZones = [];  // raw zone payloads {id, name, boundary_json}

// Drawing state
let isDrawing = false;
let drawingPath = [];        // google.maps.LatLng[]
let vertexMarkers = [];      // AdvancedMarkerElement[]
let previewPolyline = null;  // open polyline of placed vertices
let previewPolygon = null;   // semi-transparent closed preview
let ghostPolyline = null;    // segment from last vertex to current cursor
let mapClickListener = null;
let mapMouseMoveListener = null;
let firstVertexSnap = false;
let editMode = true;         // whether finished polygon is editable

let AdvancedMarkerElementCls;

async function initMap() {
    const [{ Map }, markerLib] = await Promise.all([
        google.maps.importLibrary('maps'),
        google.maps.importLibrary('marker'),
        google.maps.importLibrary('places'),
        google.maps.importLibrary('geometry'),
    ]);
    AdvancedMarkerElementCls = markerLib.AdvancedMarkerElement;

    // Center: from hidden inputs in edit mode, else the configured default.
    const centerLatInput = document.getElementById('center-latitude');
    const centerLngInput = document.getElementById('center-longitude');
    if (centerLatInput.value && centerLngInput.value) {
        center = {
            lat: parseFloat(centerLatInput.value),
            lng: parseFloat(centerLngInput.value),
        };
    } else {
        const mapEl = document.getElementById('map');
        const defaultLat = mapEl?.dataset.defaultLat;
        const defaultLng = mapEl?.dataset.defaultLng;
        if (defaultLat && defaultLng) {
            center = { lat: parseFloat(defaultLat), lng: parseFloat(defaultLng) };
        }
    }

    map = new Map(document.getElementById('map'), {
        center,
        zoom: 13,
        mapId: '4504f8b37365c3d0',
        mapTypeControl: false,
    });

    setupPlaceAutocomplete();

    placeMarker = new AdvancedMarkerElementCls({ map });
    infoWindow = new google.maps.InfoWindow({});

    // Restore an existing polygon (edit mode).
    const boundaryJsonInput = document.getElementById('boundary-json');
    if (boundaryJsonInput.value) {
        try {
            const pathArr = JSON.parse(boundaryJsonInput.value);
            if (Array.isArray(pathArr) && pathArr.length > 0) {
                const path = pathArr.map((c) => new google.maps.LatLng(c.lat, c.lng));
                originalPolygon = createZonePolygon(path, true);
                map.fitBounds(getBoundsForPath(path));
                polygon = originalPolygon;
                attachPolygonEditing(polygon);
                updateBoundaryInput(polygon);
                showFinishedControls(true);
            }
        } catch (_e) { /* ignore */ }
    }

    // Render other delivery zones (viewport-aware).
    try {
        await loadOtherDeliveryZones();
        map.addListener('idle', renderOtherZonesInViewport);
    } catch (e) {
        console.warn('Unable to render other delivery zones on form:', e);
    }

    wireToolbar();
    wireKeyboardShortcuts();
}

function setupPlaceAutocomplete() {
    const placeAutocomplete = new google.maps.places.PlaceAutocompleteElement();
    placeAutocomplete.id = 'place-autocomplete-input';
    placeAutocomplete.locationBias = center;
    const card = document.getElementById('place-autocomplete-card');
    card.appendChild(placeAutocomplete);
    map.controls[google.maps.ControlPosition.TOP_LEFT].push(card);

    placeAutocomplete.addEventListener('gmp-select', async ({ placePrediction }) => {
        const place = placePrediction.toPlace();
        await place.fetchFields({ fields: ['displayName', 'formattedAddress', 'location'] });
        if (place.viewport) {
            map.fitBounds(place.viewport);
        } else {
            map.setCenter(place.location);
            map.setZoom(17);
        }
        const content = `<div id="infowindow-content">
            <span id="place-displayname" class="title">${place.displayName}</span><br />
            <span id="place-address">${place.formattedAddress}</span>
        </div>`;
        infoWindow.setContent(content);
        infoWindow.setPosition(place.location);
        infoWindow.open({ map, anchor: placeMarker, shouldFocus: false });
        placeMarker.position = place.location;
    });
}

// --------------------------------------------------------------------------
// Toolbar wiring
// --------------------------------------------------------------------------
function wireToolbar() {
    const drawBtn = document.getElementById('draw-zone-btn');
    const undoBtn = document.getElementById('undo-vertex-btn');
    const finishBtn = document.getElementById('finish-draw-btn');
    const cancelBtn = document.getElementById('cancel-draw-btn');
    const editBtn = document.getElementById('toggle-edit-btn');
    const clearBtn = document.getElementById('clear-last');
    const resetBtn = document.getElementById('reset-zone');

    drawBtn?.addEventListener('click', () => {
        if (isDrawing) stopDrawing(false); else startDrawing();
    });
    undoBtn?.addEventListener('click', undoLastVertex);
    finishBtn?.addEventListener('click', () => stopDrawing(true));
    cancelBtn?.addEventListener('click', () => stopDrawing(false));

    editBtn?.addEventListener('click', () => {
        if (!polygon) return;
        editMode = !editMode;
        polygon.setEditable(editMode);
        polygon.setDraggable(editMode);
        const label = editMode ? editBtn.dataset.doneLabel : editBtn.dataset.editLabel;
        editBtn.querySelector('span').textContent = label;
        editBtn.classList.toggle('btn-outline-primary', !editMode);
        editBtn.classList.toggle('btn-primary', editMode);
    });

    clearBtn?.addEventListener('click', () => {
        if (!polygon && !isDrawing) return;
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'warning',
                title: window.confirmRemovePolygonText || 'Remove?',
                text: clearBtn.dataset.confirm || '',
                showCancelButton: true,
                confirmButtonText: clearBtn.dataset.confirmYes || 'Yes',
                cancelButtonText: clearBtn.dataset.confirmNo || 'Cancel',
                reverseButtons: true,
            }).then((res) => { if (res.isConfirmed) clearPolygon(); });
        } else if (window.confirm('Remove the current polygon?')) {
            clearPolygon();
        }
    });

    resetBtn?.addEventListener('click', () => {
        if (isDrawing) stopDrawing(false);
        if (!originalPolygon) return;
        if (polygon) polygon.setMap(null);
        const origPath = originalPolygon.getPath().getArray().map((ll) => ({
            lat: ll.lat(), lng: ll.lng(),
        }));
        polygon = createZonePolygon(
            origPath.map((c) => new google.maps.LatLng(c.lat, c.lng)),
            true,
        );
        map.fitBounds(getBoundsForPath(polygon.getPath().getArray()));
        attachPolygonEditing(polygon);
        updateBoundaryInput(polygon);
        showFinishedControls(true);
    });
}

function wireKeyboardShortcuts() {
    document.addEventListener('keydown', (e) => {
        if (!isDrawing) return;
        // Ignore when typing in a form field.
        const t = e.target;
        const tag = (t && t.tagName) || '';
        if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || (t && t.isContentEditable)) {
            return;
        }
        if (e.key === 'Escape') {
            e.preventDefault();
            stopDrawing(false);
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (drawingPath.length >= 3) stopDrawing(true);
        } else if (e.key === 'Backspace') {
            e.preventDefault();
            undoLastVertex();
        }
    });
}

// --------------------------------------------------------------------------
// Drawing lifecycle
// --------------------------------------------------------------------------
function startDrawing() {
    // Drop any existing polygon so we start clean.
    if (polygon) {
        polygon.setMap(null);
        polygon = null;
        document.getElementById('boundary-json').value = '';
    }

    isDrawing = true;
    drawingPath = [];
    vertexMarkers = [];
    firstVertexSnap = false;
    map.setOptions({ draggableCursor: 'crosshair' });

    toggleDrawingButtons(true);
    setStatus(window.zoneStatusClickToPlace || 'Click on the map to place vertices.');

    previewPolyline = new google.maps.Polyline({
        path: [],
        strokeColor: '#d63939',
        strokeWeight: 2,
        clickable: false,
        map,
    });
    previewPolygon = new google.maps.Polygon({
        paths: [],
        strokeOpacity: 0,
        fillColor: '#d63939',
        fillOpacity: 0.12,
        clickable: false,
        map,
    });
    ghostPolyline = new google.maps.Polyline({
        path: [],
        strokeColor: '#d63939',
        strokeOpacity: 0.5,
        strokeWeight: 1,
        clickable: false,
        icons: [{
            icon: { path: 'M 0,-1 0,1', strokeOpacity: 1, scale: 3 },
            offset: '0', repeat: '8px',
        }],
        map,
    });

    mapClickListener = map.addListener('click', onDrawingClick);
    mapMouseMoveListener = map.addListener('mousemove', onDrawingMouseMove);
}

function onDrawingClick(e) {
    if (!isDrawing) return;
    const latLng = e.latLng;

    // Auto-close when clicking near the first vertex (and we already have >=3).
    if (drawingPath.length >= 3) {
        const first = drawingPath[0];
        const distMeters = google.maps.geometry.spherical.computeDistanceBetween(first, latLng);
        if (distMeters < 100 || firstVertexSnap) {
            stopDrawing(true);
            return;
        }
    }

    drawingPath.push(latLng);
    const isFirst = drawingPath.length === 1;
    const marker = createVertexMarker(latLng, isFirst);
    vertexMarkers.push(marker);

    previewPolyline.setPath(drawingPath);
    previewPolygon.setPaths(drawingPath);
    updateReadoutFromPath(drawingPath);
}

function onDrawingMouseMove(e) {
    if (!isDrawing || drawingPath.length === 0) return;
    const last = drawingPath[drawingPath.length - 1];
    const cursor = e.latLng;
    ghostPolyline.setPath([last, cursor]);

    // Snap-to-close detection on the first vertex.
    if (drawingPath.length >= 3) {
        const first = drawingPath[0];
        const dist = google.maps.geometry.spherical.computeDistanceBetween(first, cursor);
        const shouldSnap = dist < 100;
        if (shouldSnap !== firstVertexSnap) {
            firstVertexSnap = shouldSnap;
            const firstMarker = vertexMarkers[0];
            if (firstMarker && firstMarker.content) {
                firstMarker.content.classList.toggle('is-snap', shouldSnap);
            }
            setStatus(shouldSnap
                ? (window.zoneStatusClickToClose || 'Click the first point to close the polygon.')
                : (window.zoneStatusClickToPlace || 'Click on the map to place vertices.'));
        }
    }
}

function undoLastVertex() {
    if (!isDrawing || drawingPath.length === 0) return;
    drawingPath.pop();
    const m = vertexMarkers.pop();
    if (m) m.map = null;
    if (previewPolyline) previewPolyline.setPath(drawingPath);
    if (previewPolygon) previewPolygon.setPaths(drawingPath);
    if (ghostPolyline) ghostPolyline.setPath([]);
    updateReadoutFromPath(drawingPath);
}

function stopDrawing(commit) {
    isDrawing = false;
    map.setOptions({ draggableCursor: null });

    if (mapClickListener) {
        google.maps.event.removeListener(mapClickListener);
        mapClickListener = null;
    }
    if (mapMouseMoveListener) {
        google.maps.event.removeListener(mapMouseMoveListener);
        mapMouseMoveListener = null;
    }

    // Clear preview overlays + temporary markers.
    vertexMarkers.forEach((m) => { m.map = null; });
    vertexMarkers = [];
    if (previewPolyline) { previewPolyline.setMap(null); previewPolyline = null; }
    if (previewPolygon)  { previewPolygon.setMap(null);  previewPolygon = null; }
    if (ghostPolyline)   { ghostPolyline.setMap(null);   ghostPolyline = null; }

    if (commit && drawingPath.length >= 3) {
        polygon = createZonePolygon(drawingPath, true);
        attachPolygonEditing(polygon);
        updateBoundaryInput(polygon);
        showFinishedControls(true);
    } else {
        showFinishedControls(!!polygon);
        if (polygon) updateBoundaryInput(polygon);
        else hideReadout();
    }

    toggleDrawingButtons(false);
    setStatus('');
    drawingPath = [];
    firstVertexSnap = false;
}

function clearPolygon() {
    if (isDrawing) stopDrawing(false);
    if (polygon) {
        polygon.setMap(null);
        polygon = null;
    }
    document.getElementById('boundary-json').value = '';
    document.getElementById('radius-km').value = '';
    document.getElementById('center-latitude').value = '';
    document.getElementById('center-longitude').value = '';
    hideReadout();
    showFinishedControls(false);
}

// --------------------------------------------------------------------------
// Polygon + vertex helpers
// --------------------------------------------------------------------------
function createZonePolygon(path, editable) {
    return new google.maps.Polygon({
        paths: path,
        fillColor: '#d63939',
        fillOpacity: 0.2,
        strokeColor: '#d63939',
        strokeWeight: 2,
        editable,
        draggable: editable,
        map,
    });
}

function createVertexMarker(latLng, isFirst) {
    const el = document.createElement('div');
    el.className = 'zone-vertex' + (isFirst ? ' is-first' : '');
    return new AdvancedMarkerElementCls({
        map,
        position: latLng,
        content: el,
    });
}

function attachPolygonEditing(poly) {
    const path = poly.getPath();
    google.maps.event.clearListeners(path, 'set_at');
    google.maps.event.clearListeners(path, 'insert_at');
    google.maps.event.clearListeners(path, 'remove_at');
    path.addListener('set_at', () => updateBoundaryInput(poly));
    path.addListener('insert_at', () => updateBoundaryInput(poly));
    path.addListener('remove_at', () => updateBoundaryInput(poly));
}

function updateBoundaryInput(poly) {
    const path = poly.getPath().getArray().map((ll) => ({ lat: ll.lat(), lng: ll.lng() }));
    document.getElementById('boundary-json').value = JSON.stringify(path);

    const center = getPolygonCentroid(path);
    if (center) {
        document.getElementById('center-latitude').value = center.lat;
        document.getElementById('center-longitude').value = center.lng;
    }
    const radiusKm = getMaxRadiusKm(center, path);
    document.getElementById('radius-km').value = radiusKm.toFixed(3);

    updateReadoutFromPath(poly.getPath().getArray(), { closed: true });
}

// --------------------------------------------------------------------------
// Readout + status
// --------------------------------------------------------------------------
function updateReadoutFromPath(latLngArr, opts = {}) {
    const wrap = document.getElementById('zone-readout');
    if (!wrap) return;
    if (!latLngArr || latLngArr.length === 0) { hideReadout(); return; }

    wrap.classList.remove('d-none');

    const verticesEl  = document.getElementById('readout-vertices');
    const perimeterEl = document.getElementById('readout-perimeter');
    const areaEl      = document.getElementById('readout-area');
    const radiusEl    = document.getElementById('readout-radius');

    const pts = latLngArr.map((p) => (p.lat instanceof Function ? p : new google.maps.LatLng(p.lat, p.lng)));
    const closed = opts.closed === true;
    const pathForLength = closed ? [...pts, pts[0]] : pts;
    const perimeterKm = google.maps.geometry.spherical.computeLength(pathForLength) / 1000;
    const areaKm2 = pts.length >= 3
        ? Math.abs(google.maps.geometry.spherical.computeArea(pts)) / 1_000_000
        : 0;
    const centroid = getPolygonCentroid(pts.map((ll) => ({ lat: ll.lat(), lng: ll.lng() })));
    const radiusKm = centroid
        ? getMaxRadiusKm(centroid, pts.map((ll) => ({ lat: ll.lat(), lng: ll.lng() })))
        : 0;

    verticesEl.textContent  = pts.length;
    perimeterEl.textContent = perimeterKm.toFixed(2);
    areaEl.textContent      = areaKm2.toFixed(2);
    radiusEl.textContent    = radiusKm.toFixed(2);
}

function hideReadout() {
    const wrap = document.getElementById('zone-readout');
    if (wrap) wrap.classList.add('d-none');
}

function setStatus(text) {
    const el = document.getElementById('zone-status');
    if (el) el.textContent = text || '';
}

function toggleDrawingButtons(drawing) {
    const drawBtn = document.getElementById('draw-zone-btn');
    const undoBtn = document.getElementById('undo-vertex-btn');
    const finishBtn = document.getElementById('finish-draw-btn');
    const cancelBtn = document.getElementById('cancel-draw-btn');
    if (drawBtn) drawBtn.classList.toggle('d-none', drawing);
    if (undoBtn) undoBtn.classList.toggle('d-none', !drawing);
    if (finishBtn) finishBtn.classList.toggle('d-none', !drawing);
    if (cancelBtn) cancelBtn.classList.toggle('d-none', !drawing);
}

function showFinishedControls(hasPolygon) {
    const editBtn = document.getElementById('toggle-edit-btn');
    const clearBtn = document.getElementById('clear-last');
    if (editBtn) editBtn.classList.toggle('d-none', !hasPolygon);
    if (clearBtn) clearBtn.classList.toggle('d-none', !hasPolygon);
    if (!hasPolygon) hideReadout();
}

// --------------------------------------------------------------------------
// Other delivery zones (viewport-aware)
// --------------------------------------------------------------------------
async function loadOtherDeliveryZones() {
    const currentZoneIdEl = document.getElementById('current-zone-id');
    const currentZoneId = currentZoneIdEl ? parseInt(currentZoneIdEl.value, 10) : null;

    const response = await fetch('/api/delivery-zone?per_page=500', {
        headers: { Accept: 'application/json' },
    });
    if (!response.ok) return;
    const json = await response.json();
    const items = (json && json.data && Array.isArray(json.data.data))
        ? json.data.data
        : (Array.isArray(json.data) ? json.data : []);

    cachedOtherZones = items
        .filter((z) => !currentZoneId || z.id !== currentZoneId)
        .filter((z) => Array.isArray(z.boundary_json) && z.boundary_json.length >= 3)
        .map((z) => {
            const path = z.boundary_json
                .map((pt) => ({ lat: parseFloat(pt.lat), lng: parseFloat(pt.lng) }))
                .filter((p) => !Number.isNaN(p.lat) && !Number.isNaN(p.lng));
            return { id: z.id, name: z.name, path };
        })
        .filter((z) => z.path.length >= 3);

    // Render the first pass so something shows up before the first idle event.
    renderOtherZonesInViewport();
}

function renderOtherZonesInViewport() {
    if (!cachedOtherZones.length) return;

    const bounds = map.getBounds();
    if (!bounds) return;

    // Clear current overlays.
    otherZoneOverlays.forEach((ov) => {
        ov.polygon.setMap(null);
        if (ov.label) ov.label.map = null;
    });
    otherZoneOverlays = [];

    const legendShown = { visible: false };
    cachedOtherZones.forEach((z) => {
        const inView = z.path.some((p) => bounds.contains(new google.maps.LatLng(p.lat, p.lng)));
        if (!inView) return;

        const poly = new google.maps.Polygon({
            paths: z.path,
            strokeColor: '#0066ff',
            strokeOpacity: 0.8,
            strokeWeight: 2,
            fillColor: '#1a73e8',
            fillOpacity: 0.08,
            clickable: false,
            zIndex: 0,
            map,
        });

        let label = null;
        if (z.name) {
            const centroid = getPolygonCentroid(z.path);
            if (centroid) {
                const labelEl = document.createElement('div');
                labelEl.className = 'zone-label';
                labelEl.textContent = z.name;
                label = new AdvancedMarkerElementCls({
                    map,
                    position: centroid,
                    content: labelEl,
                });
            }
        }
        otherZoneOverlays.push({ polygon: poly, label });
        legendShown.visible = true;
    });

    const legend = document.getElementById('zone-legend');
    if (legend) legend.style.display = legendShown.visible ? 'inline-flex' : 'none';
}

// --------------------------------------------------------------------------
// Math helpers
// --------------------------------------------------------------------------
function getPolygonCentroid(path) {
    if (!path.length) return null;
    let lat = 0, lng = 0;
    path.forEach((p) => { lat += p.lat; lng += p.lng; });
    return { lat: lat / path.length, lng: lng / path.length };
}

function getMaxRadiusKm(center, path) {
    if (!center) return 0;
    let max = 0;
    path.forEach((p) => {
        const d = haversineKm(center, p);
        if (d > max) max = d;
    });
    return max;
}

function haversineKm(a, b) {
    const R = 6371;
    const dLat = toRad(b.lat - a.lat);
    const dLng = toRad(b.lng - a.lng);
    const lat1 = toRad(a.lat);
    const lat2 = toRad(b.lat);
    const x = Math.sin(dLat / 2) ** 2 +
        Math.sin(dLng / 2) ** 2 * Math.cos(lat1) * Math.cos(lat2);
    return R * 2 * Math.atan2(Math.sqrt(x), Math.sqrt(1 - x));
}

function toRad(deg) { return deg * Math.PI / 180; }

function getBoundsForPath(path) {
    const bounds = new google.maps.LatLngBounds();
    path.forEach((ll) => {
        bounds.extend(ll instanceof google.maps.LatLng
            ? ll
            : new google.maps.LatLng(ll.lat, ll.lng));
    });
    return bounds;
}

try { initMap(); } catch (e) { console.error('Error initializing map:', e); }

document.addEventListener('DOMContentLoaded', () => {
    document.addEventListener('click', (event) => {
        handleDelete(event, '.delete-delivery-zone', `/${panel}/delivery-zones/`,
            'You are about to delete this Zone.');
    });
});
