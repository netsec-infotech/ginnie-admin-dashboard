/**
 * Tax Rate & Tax Group Guided Tours Module
 */
(function() {
    'use strict';

    if (!window.TourManager) return;

    const getBasePath = () => window.location.origin;

    window.TourManager.registerTours({
        create_tax: {
            url: getBasePath() + '/admin/tax-rates',
            steps: [
                {
                    element: '#tax-rates-table_wrapper, #tax-rates-table, .card-table',
                    popover: {
                        title: 'List of Tax Rates',
                        description: 'Here you can view all registered tax rules and percentage rates.',
                        side: 'top',
                        align: 'center'
                    }
                },
                {
                    element: '#add-tax-btn, a[data-bs-target="#tax-rate-modal"]',
                    popover: {
                        title: 'Add Tax Rate',
                        description: 'Click Add Tax Rate to open the creation modal.',
                        side: 'bottom',
                        align: 'center'
                    },
                    onHighlightStarted: function() {
                        const modalBtn = document.querySelector('#add-tax-btn, a[data-bs-target="#tax-rate-modal"]');
                        if (modalBtn) modalBtn.click();
                    }
                },
                {
                    element: '#tax-rate-modal .modal-body',
                    popover: {
                        title: 'Tax Details',
                        description: 'Enter tax title (e.g. GST 18%), percentage rate, and status.',
                        side: 'right',
                        align: 'start'
                    }
                },
                {
                    element: '#tax-rate-modal button[type="submit"]',
                    popover: {
                        title: 'Save Tax Rate',
                        description: 'Click Save to store and apply the tax rule.',
                        side: 'top',
                        align: 'center'
                    }
                },
                {
                    element: '#tax-group-table-wrapper',
                    popover: {
                        title: 'List of Tax Groups',
                        description: 'Here you can view all registered tax groups.',
                        side: 'top',
                        align: 'center'
                    },
                    onHighlightStarted: function() {
                        const taxRateModal = document.getElementById('tax-rate-modal');
                        if (taxRateModal) {
                            const closeBtn = taxRateModal.querySelector('.btn-close, [data-bs-dismiss="modal"]');
                            if (closeBtn) closeBtn.click();
                        }
                    }
                },
                {
                    element: '#tax-group-btn, a[data-bs-target="#tax-class-modal"]',
                    popover: {
                        title: 'Add Tax Group',
                        description: 'Click here to add a tax group.',
                        side: 'bottom',
                        align: 'center'
                    },
                    onHighlightStarted: function() {
                        const modalBtn = document.querySelector('#tax-group-btn, a[data-bs-target="#tax-class-modal"]');
                        if (modalBtn) modalBtn.click();
                    }
                },
                {
                    element: '#tax-class-modal .modal-body',
                    popover: {
                        title: 'Tax Group Details',
                        description: 'Enter tax group title and select which taxes to include.',
                        side: 'right',
                        align: 'start'
                    }
                },
                {
                    element: '#tax-class-modal button[type="submit"]',
                    popover: {
                        title: 'Save Tax Group',
                        description: 'Click Save to store tax group.',
                        side: 'top',
                        align: 'center'
                    }
                }
            ]
        }
    });
})();
