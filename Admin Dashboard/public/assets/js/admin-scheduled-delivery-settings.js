/**
 * Admin Scheduled Delivery Settings Page JS Handler
 * Handles toggle show/hide logic for maximum booking days ceiling inputs.
 */
document.addEventListener('DOMContentLoaded', function () {
    const toggle = document.getElementById('global-scheduled-delivery-toggle');
    const container = document.getElementById('max-advance-booking-days-container');

    if (toggle && container) {
        const updateVisibility = () => {
            if (toggle.checked) {
                container.style.display = 'block';
            } else {
                container.style.display = 'none';
            }
        };

        toggle.addEventListener('change', updateVisibility);
        updateVisibility(); // run initial check on page load
    }
});
