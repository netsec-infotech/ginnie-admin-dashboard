'use strict';

document.addEventListener('DOMContentLoaded', function () {
    const timezoneEl = document.getElementById('select-timezone');
    if (timezoneEl && window.TomSelect && !timezoneEl.tomselect) {
        new TomSelect(timezoneEl, {
            copyClassesToDropdown: false,
            dropdownParent: 'body',
            controlInput: '<input>',
            maxOptions: null,
            placeholder: timezoneEl.getAttribute('placeholder') || '',
        });
    }
});
