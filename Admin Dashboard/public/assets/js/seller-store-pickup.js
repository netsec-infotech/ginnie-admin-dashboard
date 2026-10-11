'use strict';

document.addEventListener('DOMContentLoaded', function () {
    const checkbox = document.getElementById('allows-pickup-checkbox');
    const container = document.getElementById('pickup-instructions-container');

    if (checkbox && container) {
        checkbox.addEventListener('change', function () {
            if (this.checked) {
                container.style.display = 'block';
            } else {
                container.style.display = 'none';
            }
        });
    }
});
