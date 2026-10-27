'use strict';

// ── Color picker ↔ hex text sync ─────────────────────────────────────────────

function syncColorPicker(pickerId, hexId) {
    const picker = document.getElementById(pickerId);
    const hex    = document.getElementById(hexId);
    if (!picker || !hex) return;

    picker.addEventListener('input', function () {
        hex.value = picker.value;
        updateBadgePreview();
    });
    hex.addEventListener('input', function () {
        const val = hex.value.trim();
        if (/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/.test(val)) {
            picker.value = val;
            updateBadgePreview();
        }
    });
}

function updateBadgePreview() {
    const preview      = document.getElementById('badge-preview');
    const labelInput   = document.getElementById('badge-label');
    const bgPicker     = document.getElementById('badge-bg-color');
    const textPicker   = document.getElementById('badge-text-color');
    const borderPicker = document.getElementById('badge-border-color');
    if (!preview) return;

    preview.textContent            = labelInput ? (labelInput.value || 'Badge') : 'Badge';
    preview.style.backgroundColor  = bgPicker     ? bgPicker.value     : '#3b82f6';
    preview.style.color            = textPicker   ? textPicker.value   : '#ffffff';
    preview.style.border           = (borderPicker && borderPicker.value)
        ? '1px solid ' + borderPicker.value
        : 'none';
}

// ── Modal show (handles both create and edit) ─────────────────────────────────

document.addEventListener('show.bs.modal', function (event) {
    if (event.target.id !== 'badge-modal') return;

    const trigger = event.relatedTarget;
    const badgeId = trigger ? trigger.getAttribute('data-id') : null;

    const form     = document.getElementById('badge-form');
    const titleEl  = document.getElementById('badge-modal-title');
    const submitEl = document.getElementById('badge-submit-label');
    const methodEl = document.getElementById('badge-method');
    const idEl     = document.getElementById('badge-id');
    const labels   = window.badgeLabels || {};

    if (badgeId) {
        // ── Edit mode: populate from data-* attrs (no fetch needed) ──────────
        if (form)     form.action        = base_url + '/' + panel + '/badges/' + badgeId;
        if (titleEl)  titleEl.textContent  = labels.edit  || 'Edit Badge';
        if (submitEl) submitEl.textContent = labels.edit  || 'Edit Badge';
        if (methodEl) methodEl.value = 'POST';
        if (idEl)     idEl.value     = badgeId;

        const setField = function (elId, val) {
            const el = document.getElementById(elId);
            if (el) el.value = val || '';
        };
        setField('badge-name',  trigger.getAttribute('data-name'));
        setField('badge-label', trigger.getAttribute('data-label'));

        const setColor = function (pickerId, hexId, val) {
            const picker = document.getElementById(pickerId);
            const hex    = document.getElementById(hexId);
            const color  = val || '#000000';
            if (picker) picker.value = color;
            if (hex)    hex.value    = color;
        };
        setColor('badge-bg-color',     'badge-bg-color-hex',     trigger.getAttribute('data-bg-color'));
        setColor('badge-text-color',   'badge-text-color-hex',   trigger.getAttribute('data-text-color'));
        setColor('badge-border-color', 'badge-border-color-hex', trigger.getAttribute('data-border-color'));

        updateBadgePreview();
    } else {
        // ── Create mode: reset form ───────────────────────────────────────────
        if (form)     form.action        = base_url + '/' + panel + '/badges';
        if (titleEl)  titleEl.textContent  = labels.create || 'Create Badge';
        if (submitEl) submitEl.textContent = labels.create || 'Create Badge';
        if (methodEl) methodEl.value = 'POST';
        if (idEl)     idEl.value     = '';

        ['badge-name', 'badge-label'].forEach(function (id) {
            const el = document.getElementById(id);
            if (el) el.value = '';
        });

        var defaults = {
            'badge-bg-color':     '#3b82f6',
            'badge-text-color':   '#ffffff',
            'badge-border-color': '#2563eb',
        };
        Object.keys(defaults).forEach(function (id) {
            const val    = defaults[id];
            const picker = document.getElementById(id);
            const hex    = document.getElementById(id + '-hex');
            if (picker) picker.value = val;
            if (hex)    hex.value    = val;
        });

        updateBadgePreview();
    }
});

// ── Color pickers + live preview init ────────────────────────────────────────

document.addEventListener('DOMContentLoaded', function () {
    syncColorPicker('badge-bg-color',     'badge-bg-color-hex');
    syncColorPicker('badge-text-color',   'badge-text-color-hex');
    syncColorPicker('badge-border-color', 'badge-border-color-hex');

    const labelInput = document.getElementById('badge-label');
    if (labelInput) labelInput.addEventListener('input', updateBadgePreview);
});

// ── Delete ────────────────────────────────────────────────────────────────────

document.addEventListener('click', function (event) {
    handleDelete(event, '.delete-badge', '/' + panel + '/badges/', (window.badgeLabels && window.badgeLabels.deleteConfirm) || 'You are about to delete this badge.');
});
