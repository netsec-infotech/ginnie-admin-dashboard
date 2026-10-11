$(document).ready(function () {

    // Auto-load the visibility inspector on the banner edit page (it's only
    // present when editing — create pages don't include it).
    const visibilityInspectorEl = document.querySelector('[data-visibility-inspector]');
    if (visibilityInspectorEl && typeof window.loadVisibilityInspector === 'function') {
        window.loadVisibilityInspector(visibilityInspectorEl);
    }


    // Show/hide fields based on banner type
    function toggleFields() {
        const bannerTypeEl = document.getElementById('bannerType');
        if (!bannerTypeEl)
            return;
        const type = bannerTypeEl.value;

        // Hide all fields
        document.getElementById('productField').style.display = 'none';
        document.getElementById('categoryField').style.display = 'none';
        document.getElementById('brandField').style.display = 'none';
        document.getElementById('customField').style.display = 'none';

        // Show the selected field
        if (type === 'product') {
            document.getElementById('productField').style.display = '';
        } else if (type === 'category') {
            document.getElementById('categoryField').style.display = '';
        } else if (type === 'brand') {
            document.getElementById('brandField').style.display = '';
        } else if (type === 'custom') {
            document.getElementById('customField').style.display = '';
        }
    }

    // Initial toggle
    toggleFields();
    document.getElementById('bannerType')?.addEventListener('change', toggleFields);

    // Initialise TomSelect on the zones multi-select so it renders as searchable chips.
    const zonesSelectEl = document.getElementById('bannerZones');
    if (zonesSelectEl && window.TomSelect && !zonesSelectEl.tomselect) {
        new TomSelect(zonesSelectEl, {
            copyClassesToDropdown: false,
            dropdownParent: 'body',
            placeholder: 'Select zones',
        });
    }

    // "Available in all zones" toggle hides + disables the zones multi-select.
    function toggleZoneFields() {
        const allZonesEl = document.getElementById('bannerAllZones');
        const zonesField = document.getElementById('bannerZonesField');
        const zonesSelect = document.getElementById('bannerZones');
        if (!allZonesEl || !zonesField || !zonesSelect) return;

        const ts = zonesSelect.tomselect;

        if (allZonesEl.checked) {
            zonesField.style.display = 'none';
            zonesSelect.disabled = true;
            if (ts) {
                ts.clear();
                ts.disable();
            } else {
                Array.from(zonesSelect.options).forEach(o => o.selected = false);
            }
        } else {
            zonesField.style.display = '';
            zonesSelect.disabled = false;
            if (ts) ts.enable();
        }
    }

    toggleZoneFields();
    document.getElementById('bannerAllZones')?.addEventListener('change', toggleZoneFields);

    const table = $('#banners-table').DataTable();

    // Reload table when filters change
    $('#typeFilter, #positionFilter, #statusFilter, #scopeTypeFilter, #zoneFilter').on('change', function () {
        table.ajax.reload(null, false);
    });

    // Add filter params to AJAX request
    $('#banners-table').on('preXhr.dt', function (e, settings, data) {
        data.type = $('#typeFilter').val();
        data.position = $('#positionFilter').val();
        data.visibility_status = $('#statusFilter').val();
        data.scope_type = $('#scopeTypeFilter').val();
        data.zone_id = $('#zoneFilter').val();
    });

    document.addEventListener('click', (e) => {
            handleDelete(e, '.delete-banner', `/${panel}/banners/`, 'You are about to delete this Banner.');
        }
    );
});
