/**
 * Brand Management Guided Tours Module
 */
(function() {
    'use strict';

    if (!window.TourManager) return;

    const getBasePath = () => window.location.origin;

    window.TourManager.registerTours({
        create_brand: {
            url: getBasePath() + '/admin/brands',
            steps: [
                {
                    element: '#brands-table_wrapper, #brands-table, .card-table',
                    popover: {
                        title: 'List of Brands',
                        description: 'Here you can view all registered product manufacturer brands.',
                        side: 'top',
                        align: 'center'
                    }
                },
                {
                    element: '.card-actions .dropdown-toggle, #add-brand-btn',
                    popover: {
                        title: 'Add Brand',
                        description: 'Click on Actions > Add Brand to open the creation modal form.',
                        side: 'bottom',
                        align: 'center'
                    },
                    onHighlightStarted: function() {
                        const modalBtn = document.querySelector('#add-brand-btn, a[data-bs-target="#brand-modal"]');
                        if (modalBtn) modalBtn.click();
                    }
                },
                {
                    element: '#brand-modal .modal-body',
                    popover: {
                        title: 'Brand Form Details',
                        description: 'Fill in the brand name, upload the brand logo, and configure visibility status.',
                        side: 'center'
                    }
                },
                {
                    element: '#brand-modal button[type="submit"]',
                    popover: {
                        title: 'Save Brand',
                        description: 'Click Save to register and publish the new brand into your catalog.',
                        side: 'top'
                    }
                }
            ]
        }
    });
})();
