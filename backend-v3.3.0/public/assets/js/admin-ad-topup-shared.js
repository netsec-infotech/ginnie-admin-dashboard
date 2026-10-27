'use strict';

window.initAdTopupSellerTomSelect = function (selectId, onChange, initialOption) {
    try {
        const sellerEl = document.getElementById(selectId);
        if (!sellerEl || !window.TomSelect) {
            return null;
        }

        const tomSelect = new TomSelect(sellerEl, {
            copyClassesToDropdown: false,
            dropdownParent: 'body',
            controlInput: '<input>',
            valueField: 'value',
            labelField: 'text',
            searchField: 'text',
            placeholder: (typeof labels !== 'undefined' && labels.seller) ? labels.seller : 'Seller',
            load: function (query, callback) {
                if (!query.length) {
                    return callback();
                }

                fetch(`${base_url}/admin/sellers/search?search=${encodeURIComponent(query)}`)
                    .then(response => response.json())
                    .then(json => callback(json))
                    .catch(() => callback());
            },
            onChange: function (value) {
                if (typeof onChange === 'function') {
                    onChange(value);
                }
            }
        });

        if (initialOption && initialOption.value && initialOption.text) {
            tomSelect.addOption(initialOption);
            tomSelect.setValue(initialOption.value, true);
        }

        return tomSelect;
    } catch (error) {
        console.error(error);
        return null;
    }
};
