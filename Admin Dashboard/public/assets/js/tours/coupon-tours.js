/**
 * Coupon / Promo Code Guided Tours Module
 */
(function() {
    'use strict';

    if (!window.TourManager) return;

    const getBasePath = () => window.location.origin;

    window.TourManager.registerTours({
        create_coupon: {
            url: getBasePath() + '/admin/promos',
            steps: [
                {
                    element: '#promos-table_wrapper, #promos-table, .card-table',
                    popover: {
                        title: 'List of Promo Coupons',
                        description: 'Here you can view all active and expired discount coupons.',
                        side: 'top',
                        align: 'center'
                    }
                },
                {
                    element: '#add-coupon-btn, a[data-bs-target="#promo-modal"]',
                    popover: {
                        title: 'Add Promo Coupon',
                        description: 'Click Add Promo to open the promo code creation form.',
                        side: 'bottom',
                        align: 'center'
                    },
                    onHighlightStarted: function() {
                        const modalBtn = document.querySelector('#add-coupon-btn, a[data-bs-target="#promo-modal"]');
                        if (modalBtn) modalBtn.click();
                    }
                },
                {
                    element: '#promo-modal .modal-body',
                    popover: {
                        title: 'Promo Details & Discount Type',
                        description: 'Enter promo code (e.g. WELCOME50), set discount type (percentage vs fixed amount), and usage limits.',
                        side: 'center'
                    }
                },
                {
                    element: '#promo-modal button[type="submit"]',
                    popover: {
                        title: 'Save Coupon',
                        description: 'Click Save to activate your promo coupon.',
                        side: 'top'
                    }
                }
            ]
        }
    });
})();
