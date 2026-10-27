'use strict';

document.addEventListener('DOMContentLoaded', function () {
    const list = document.getElementById('pos-custom-methods-list');
    const addBtn = document.getElementById('pos-add-custom-method');
    if (!list || !addBtn) return;

    function nextIndex() {
        const rows = list.querySelectorAll('.pos-custom-method-row');
        let max = -1;
        rows.forEach(function (row) {
            const input = row.querySelector('input[name*="[name]"]');
            if (input) {
                const m = input.name.match(/\[(\d+)\]/);
                if (m) max = Math.max(max, parseInt(m[1], 10));
            }
        });
        return max + 1;
    }

    var labels = {
        icon: addBtn.dataset.labelIcon || 'Icon',
        name: addBtn.dataset.labelName || 'Name',
        namePlaceholder: addBtn.dataset.labelNamePlaceholder || '',
        instructions: addBtn.dataset.labelInstructions || 'Instructions',
        instructionsPlaceholder: addBtn.dataset.labelInstructionsPlaceholder || ''
    };

    function buildRow(idx) {
        const div = document.createElement('div');
        div.className = 'card card-body mb-2 pos-custom-method-row';
        div.innerHTML =
            '<div class="row g-2 align-items-start">' +
                '<div class="col-auto">' +
                    '<label class="form-label small">' + labels.icon + '</label>' +
                    '<input type="text" class="form-control text-center" style="width:60px" ' +
                        'name="pos_payment_config[custom_methods][' + idx + '][icon]" value="💳" maxlength="4">' +
                '</div>' +
                '<div class="col">' +
                    '<label class="form-label small">' + labels.name + ' <span class="text-danger">*</span></label>' +
                    '<input type="text" class="form-control" ' +
                        'name="pos_payment_config[custom_methods][' + idx + '][name]" maxlength="50" required placeholder="' + labels.namePlaceholder + '">' +
                '</div>' +
                '<div class="col">' +
                    '<label class="form-label small">' + labels.instructions + '</label>' +
                    '<input type="text" class="form-control" ' +
                        'name="pos_payment_config[custom_methods][' + idx + '][instructions]" maxlength="250" placeholder="' + labels.instructionsPlaceholder + '">' +
                '</div>' +
                '<div class="col-auto d-flex align-items-end gap-2" style="padding-bottom:2px">' +
                    '<label class="form-check form-switch mb-0" title="Enabled">' +
                        '<input type="hidden" name="pos_payment_config[custom_methods][' + idx + '][enabled]" value="0">' +
                        '<input class="form-check-input" type="checkbox" ' +
                            'name="pos_payment_config[custom_methods][' + idx + '][enabled]" value="1" checked>' +
                    '</label>' +
                    '<button type="button" class="btn btn-sm btn-outline-danger pos-remove-custom-method" title="Remove">' +
                        '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" ' +
                            'stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
                            '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>' +
                        '</svg>' +
                    '</button>' +
                '</div>' +
            '</div>';
        return div;
    }

    addBtn.addEventListener('click', function () {
        list.appendChild(buildRow(nextIndex()));
    });

    list.addEventListener('click', function (e) {
        const btn = e.target.closest('.pos-remove-custom-method');
        if (btn) btn.closest('.pos-custom-method-row').remove();
    });

    // Toggle self-pickup sub-settings
    const allowsPickupCheckbox = document.getElementById('allows_pickup_checkbox');
    const pickupSubSettings = document.getElementById('pickup-sub-settings');

    if (allowsPickupCheckbox && pickupSubSettings) {
        allowsPickupCheckbox.addEventListener('change', function () {
            if (this.checked) {
                pickupSubSettings.style.display = 'block';
            } else {
                pickupSubSettings.style.display = 'none';
            }
        });
    }
});
